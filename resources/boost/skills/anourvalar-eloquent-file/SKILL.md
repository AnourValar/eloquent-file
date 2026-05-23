---
name: anourvalar-eloquent-file
description: Load when working with the anourvalar/eloquent-file package, the EloquentFile facade, FileVirtual/FilePhysical models, or Laravel tasks involving file uploads, downloads, deletions, signed/proxy URLs, generated previews, visibility/type handlers, or seeding/replicating uploaded files.
---

# AnourValar Eloquent File

`anourvalar/eloquent-file` is a Laravel database (Eloquent) layer for file management. It splits files into two models: `FilePhysical` (the actual stored bytes, addressed by sha256) and `FileVirtual` (a named, polymorphic link to an entity, e.g. a user's avatar). Visibility (`public`, `private`, `private_encrypt`) and type (`simple`, `image`) handlers control where files live, how they are validated, and how derived files (e.g. image previews) are generated.

## When to use

- Working with the `EloquentFile` facade or `AnourValar\EloquentFile\Services\FileService`.
- Implementing upload, delete, or proxy-download HTTP endpoints for the package's models.
- Adding new entities/names/visibilities/types to `config/eloquent_file.php`.
- Seeding test data with `FileVirtual` / `FilePhysical` records.
- Generating signed or proxied URLs for private files, or invoking `eloquent-file:on-zero` / `eloquent-file:regenerate` artisan commands.

## Facades

### `AnourValar\EloquentFile\Facades\EloquentFileFacade`

Aliased as `EloquentFile`. Resolves to `AnourValar\EloquentFile\Services\FileService`.

Public methods (delegated to `FileService`):

- `prepareFromBuffer(string $binary, ?string $fileName = null, ?string $mimeType = null): \Illuminate\Http\UploadedFile` — wraps a binary string in a temporary `UploadedFile` (uses `tmpfile()`; the resource is closed when the service is destructed or after `upload()` finishes).
- `prepareFromPath(string $fullPath, ?string $fileName = null, ?string $mimeType = null): \Illuminate\Http\UploadedFile` — wraps an existing file on disk in an `UploadedFile`.
- `upload(?\Illuminate\Http\UploadedFile $file, \AnourValar\EloquentFile\FileVirtual &$fileVirtual, ?string $fileValidationKey = null, ?callable $acl = null): void` — validates `$file`, creates/reuses a `FilePhysical` (locking on `visibility|type|sha256` and deduplicating via sha256 when the visibility handler `preventDuplicates()` is true), links it to `$fileVirtual` and persists. Throws `\RuntimeException` if `$fileVirtual` already has a `file_physical_id` or is already persisted. `$acl` receives the populated `$fileVirtual` and may throw `\Illuminate\Auth\Access\AuthorizationException`.
- `collect(array $attributes, $prefix = null): \Illuminate\Database\Eloquent\Collection` — validates `entity`, `entity_id`, `name`, optional `id` and returns matching `FileVirtual` records (with `filePhysical` eager-loaded).
- `replicate(\AnourValar\EloquentFile\FileVirtual $fileVirtual, array $data, $prefix = null): \AnourValar\EloquentFile\FileVirtual` — replicates a `FileVirtual` (resetting `entity`, `entity_id` and computed columns), force-fills `$data`, validates and saves.
- `lock(\AnourValar\EloquentFile\FilePhysical $filePhysical): void` — acquires an atomic lock via `\Atom::lockFilePhysical($visibility, $type, $sha256)`. Requires `visibility`, `type`, and `sha256` to be set; otherwise throws `\RuntimeException`.

```php
use AnourValar\EloquentFile\Facades\EloquentFile;
use App\FileVirtual;

// Upload a programmatically built file
$fileVirtual = (new FileVirtual())->forceFill([
    'entity'    => 'user',
    'entity_id' => $user->id,
    'name'      => 'avatar',
]);

$uploaded = EloquentFile::prepareFromBuffer($pngBinary, 'avatar.png', 'image/png');
EloquentFile::upload($uploaded, $fileVirtual);
```

## Services

### `AnourValar\EloquentFile\Services\FileService`

The class the facade resolves to. Resolve directly via `app(\AnourValar\EloquentFile\Services\FileService::class)` if you prefer not to use the facade. Same public surface as listed above.

## Models

These are the Eloquent models that ship as stubs in `app/` after running `vendor:publish` with the `models` tag. They extend the package's abstract models.

### `App\FileVirtual` extends `\AnourValar\EloquentFile\FileVirtual`

Columns: `id`, `file_physical_id`, `entity`, `entity_id`, `name`, `filename`, `title`, `weight`, `details` (jsonb), `archived_at`, timestamps.

Notable public methods:
- `filePhysical(): BelongsTo` — relation to `FilePhysical`.
- `entitable(): MorphTo` — polymorphic owner via `entity` / `entity_id` (uses `withTrashed()`).
- `getEntityHandler(): EntityInterface` — resolves the handler bound in `config('eloquent_file.file_virtual.entity.{entity}.bind')`.
- `getNameHandler(): NameInterface` — resolves the handler bound for the configured `name`.
- `getEntityPolicyHandler(): PolicyInterface` — resolves the policy bound under `name.policy.bind`.
- `scopeLight(Builder)` — selects only the lightweight columns and publishes `id, name, filename, title, created_at, mime_type, size, url, url_generate`.

Virtual attributes (require `filePhysical` to be eager-loaded for the last four):
- `entity_details`, `name_details`, `name_title` — config lookups.
- `url` — direct URL for `DirectAccessInterface` visibilities, proxy URL otherwise.
- `url_generate` — same idea, keyed by generated variant (e.g. `preview`).
- `mime_type`, `size` — pulled from the related `FilePhysical`.

### `App\FilePhysical` extends `\AnourValar\EloquentFile\FilePhysical`

Columns: `id`, `visibility`, `type`, `disk`, `path`, `path_generate` (jsonb), `sha256`, `size`, `mime_type`, `linked`, timestamps.

Notable public methods:
- `fileVirtuals(): HasMany` — linked virtuals.
- `getVisibilityHandler(): VisibilityInterface` — resolves the visibility handler.
- `getTypeHandler(): TypeInterface` — resolves the type handler.
- Virtual attributes: `visibility_details`, `type_details`, `content` (returns the raw bytes via the visibility handler's `AdapterInterface` if implemented, otherwise `\Storage::disk($disk)->get($path)`).

## Traits

### `AnourValar\EloquentFile\Traits\ControllerTrait`

Mix into your `FileController` to get HTTP-aware helpers:

- `uploadFileFrom(\Illuminate\Http\Request $request, $data = [], $extraData = []): FileVirtual` — extracts `entity`, `entity_id`, `name`, `title`, `details` from the route/input (or from a passed Eloquent model), takes the single `$request->file()` entry, runs ACL through `EntityInterface::canUpload`, and calls `FileService::upload`. `$data` may be an associative array OR an `Illuminate\Database\Eloquent\Model` (its `getMorphClass()` + `getKey()` become `entity`/`entity_id`).
- `downloadFileFrom(string $url, array $data, ?string $validationKey = null): callable` — two-step: downloads the URL synchronously via `downloadProcedure()` (override to swap HTTP client), then returns a closure that uploads inside your transaction.
- `downloadProcedure(string $url): string|false` — protected hook (default: `@file_get_contents`).
- `deleteFileFrom(\Illuminate\Http\Request $request, array $where = []): FileVirtual` — finds the `FileVirtual`, runs ACL via `canDelete`, calls `validateDelete()->delete()`.
- `proxyUserAuthorize(\Illuminate\Http\Request $request, bool $download = false): \Symfony\Component\HttpFoundation\Response` — proxies a download, authorizing via `EntityInterface::canDownload($fileVirtual, $request->user())`. Supports a `?generate=preview` query parameter to serve a generated variant.
- `proxyUrlSigned(\Illuminate\Http\Request $request, bool $download = false): \Symfony\Component\HttpFoundation\Response` — same as above, but requires `$request->hasValidSignature()` (Laravel signed URLs) instead of user authorization.
- `extractFileVirtualFrom(\Illuminate\Http\Request $request, array $where = []): FileVirtual` — resolves the `file_virtual` route/input id and runs `findOrFail`.

Visibility handlers used by proxy routes must implement `ProxyAccessInterface`.

### `AnourValar\EloquentFile\Traits\SeederTrait`

Use inside a seeder/test helper:

- `fakeStorages(): self` — calls `\Storage::fake($disk)` on every disk listed in `config('filesystems.disks')`.
- `createFromList(FileVirtual &$fileVirtual, \Illuminate\Database\Eloquent\Model $entitable, string $path, array $files = [], ?string $mime = null): void` — picks a random file from `$path` (or `$files`), guesses MIME from the extension, sets `entity`/`entity_id` from `$entitable`, fakes name-handler attributes, and uploads via `FileService`. Throws `\LogicException` if the chosen disk is not a faked `FilesystemAdapter` while `app.env === 'testing'`.
- `createFromBuffer(FileVirtual &$fileVirtual, \Illuminate\Database\Eloquent\Model $entitable, string $binary): void` — same idea but from raw bytes.

### `AnourValar\EloquentFile\Traits\ListenerTrait`

- `avatarSchema(FileVirtual $fileVirtual, $generateKeys = 'preview'): ?array` — returns `['generated' => bool, 'url' => string]` for the most recent non-archived `FileVirtual` of `(entity, entity_id, name)`, preferring one of the supplied `$generateKeys` variants. Returns `null` if no row is found.

## Handler interfaces (custom integrations)

Implement and register in `config/eloquent_file.php` under the appropriate `bind` key.

- `AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface` — `canUpload`, `canDownload`, `canDelete`, `lockOnChange`, `validate`, `validateDelete`. Bundled: `UserEntity` (delegates to the `update` ability on the owning model).
- `AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface` — `validate`, `canonizeDetails`, `generateFake`. Bundled: `SimpleName`, `SimpleTitleName`.
- `AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface` — bundled: `UniquePolicy`, `ArchivePolicy`, `ListPolicy` (plus `AbstractPolicy`).
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface` — `validate`, `onZero`, `dispatchOnZero`. Bundled: `SimpleType`, `ImageType`.
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface` — `generate`, `keepOriginal`, `dispatchGenerate`. Implemented by `ImageType` to produce previews.
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface` — `preventDuplicates`, `getDisk`, `getPath`. Bundled: `PublicVisibility`, `PrivateVisibility`, `PrivateEncryptVisibility`.
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface` — `directUrl($disk, $path)`. Implemented by `PublicVisibility` for storage-served URLs.
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface` — `proxyUrl(FileVirtual, ?string $generate = null)`. Required for visibilities served through your controller (private / private_encrypt).
- `AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface` — `putFile`, `getFile`. Implemented by `PrivateEncryptVisibility` to wrap reads/writes (used both during `FileService::upload` and `FilePhysical::content`).

## Events & jobs

- Event `AnourValar\EloquentFile\Events\FileVirtualChanged` — fired by observers when a `FileVirtual` is created/updated/deleted (listeners should not be queued because they may run inside a DB transaction).
- Jobs (queued): `AnourValar\EloquentFile\Jobs\GenerateJob`, `AnourValar\EloquentFile\Jobs\AfterGenerateJob`, `AnourValar\EloquentFile\Jobs\OnZeroJob`.

## Artisan commands

- `php artisan eloquent-file:on-zero --days=N` — runs `dispatchOnZero` on `FilePhysical`s with `linked = false` whose `updated_at < now - N days` (defaults to 1 hour when `--days=0`). Schedule daily:
  ```php
  $schedule->command('eloquent-file:on-zero --days=10')
      ->dailyAt('00:30')->runInBackground()->onOneServer();
  ```
- `php artisan eloquent-file:regenerate --created_before="-1 minute"` — re-dispatches `GenerateJob` for every `FilePhysical` whose type handler implements `GenerateInterface`.

## Usage examples

### Controller (HTTP upload + delete)

```php
use AnourValar\EloquentFile\Traits\ControllerTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    use ControllerTrait;

    public function upload(Request $request): array
    {
        $fileVirtual = DB::transaction(fn () => $this->uploadFileFrom($request));
        return ['file_virtual' => ['id' => $fileVirtual->id]];
    }

    public function delete(Request $request): array
    {
        DB::transaction(fn () => $this->deleteFileFrom($request));
        return ['file_virtual' => ['id' => (int) $request->route('file_virtual')]];
    }

    // Proxy download for private files (signed URL variant)
    public function download(Request $request)
    {
        return $this->proxyUrlSigned($request); // or proxyUserAuthorize($request)
    }
}
```

Routes (see `README.md`):

```php
use Illuminate\Support\Facades\Route;

Route::pattern('file_virtual', '[0-9]{1,18}');
Route::pattern('entity_id', '[0-9]{1,18}');

Route::prefix('/file')->controller(\App\Http\Controllers\Api\FileController::class)
    ->group(function () {
        Route::post('/upload/{entity}/{entity_id}/{name}', 'upload');
        Route::post('/delete/{file_virtual}', 'delete');
    });

Route::any('/file/{file_virtual}/download/{filename}', [\App\Http\Controllers\FileController::class, 'download'])
    ->middleware('throttle:lax')
    ->name('file.download'); // must match config('eloquent_file.file_physical.visibility.*.proxy_route')
```

### Programmatic upload from a path

```php
use AnourValar\EloquentFile\Facades\EloquentFile;
use App\FileVirtual;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($user, $sourcePath) {
    $fileVirtual = (new FileVirtual())->forceFill([
        'entity'    => $user->getMorphClass(),
        'entity_id' => $user->getKey(),
        'name'      => 'avatar',
    ]);

    $upload = EloquentFile::prepareFromPath($sourcePath, 'me.jpg', 'image/jpeg');
    EloquentFile::upload($upload, $fileVirtual);
});
```

### Required entity relation (on the owner model)

```php
public function fileVirtuals(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(\App\FileVirtual::class, 'entity', 'entity')
        ->orderBy('id', 'ASC');
}

// In the entity model's deleting() observer, cascade:
foreach ($model->fileVirtuals()->get() as $item) {
    $item->delete(); // triggers FileVirtualObserver / GC of FilePhysical
}
```

### Seeder

```php
use AnourValar\EloquentFile\Traits\SeederTrait;
use App\FileVirtual;

class UserSeeder extends \Illuminate\Database\Seeder
{
    use SeederTrait;

    public function run(): void
    {
        $this->fakeStorages();

        $user = \App\User::factory()->create();
        $fileVirtual = (new FileVirtual())->forceFill(['name' => 'avatar']);

        $this->createFromList($fileVirtual, $user, database_path('seeders/files/avatars/'));
    }
}
```

## Conventions / gotchas

- Install flow: `composer require anourvalar/eloquent-file` then `php artisan vendor:publish --tag=AnourValar\EloquentFile\Providers\EloquentFileServiceProvider` to publish the config, migrations, language files, and `App\FileVirtual` / `App\FilePhysical` model stubs. The package binds observers in `boot()` against the classes configured in `eloquent_file.models`, so do not skip publishing the model stubs.
- Required configuration in `config/eloquent_file.php`:
  - `models.file_virtual`, `models.file_physical` — your subclasses.
  - `file_physical.visibility.{name}` — each entry needs `bind` (implementing `VisibilityInterface`) and `disks` (array). Private/proxied visibilities also need `proxy_route` and `proxy_route_method`.
  - `file_physical.type.{name}` — needs `bind` (implementing `TypeInterface`), `rules` (Laravel validation), and `rules_validate_mime_by_extension` (bool).
  - `file_virtual.entity.{entity}.bind` — `EntityInterface` implementation.
  - `file_virtual.entity.{entity}.name.{name}` — must define `bind` (`NameInterface`), `title`, `policy.bind` (`PolicyInterface`), `visibility`, and a `types` map keyed by extension (`'*'` matches anything, otherwise lowercase extension).
- `FileVirtual::url` and `url_generate` throw `\LogicException` if `filePhysical` is not eager-loaded — always do `->with('filePhysical')` (or use the `light` scope).
- `EloquentFile::upload()` is destructive on `$fileVirtual`: it expects `file_physical_id === null` and `exists === false`, and mutates the model in place (passed by reference). Wrap calls in a DB transaction — the service registers `\Atom::onRollBack` cleanup so the orphaned `FilePhysical` is removed if the surrounding transaction rolls back.
- Deduplication is per `(visibility, type, sha256)`. Only visibilities whose handler returns `preventDuplicates() === true` reuse existing physical files; otherwise a fresh row is created.
- `EntityInterface::canUpload/canDownload/canDelete` receive a nullable `Authenticatable`. The bundled `UserEntity` returns `false` for guests because `$user?->can(...)` returns `null`.
- Proxy routes: the visibility config's `proxy_route` must match a registered named route (default `file.download`) that calls `proxyUrlSigned` or `proxyUserAuthorize`. If the resolved visibility handler does not implement `ProxyAccessInterface`, the trait throws `AuthorizationException` with the `eloquent-file::auth.download.unsupported` message.
- `ImageType` produces a `preview` variant by default (`config.file_physical.type.image.generate`); use `eloquent-file:regenerate` to rebuild them after changing the config.
- Listeners for `FileVirtualChanged` must not be queued — the event is dispatched inside the DB transaction by the observers.
