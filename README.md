# Laravel Eloquent File

## Installation

```bash
composer require anourvalar/eloquent-file
```

```bash
php artisan vendor:publish --tag=AnourValar\EloquentFile\Providers\EloquentFileServiceProvider
```


## Prune command

```php
$schedule->command('eloquent-file:on-zero --days=10')->dailyAt('00:30')->runInBackground()->onOneServer();
```


## Relation

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
 */
public function fileVirtuals(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
	return $this->morphMany(\App\FileVirtual::class, 'entity', 'entity')->orderBy('id', 'ASC');
}
```


## Observer

```php
public function deleting(Model $model)
{
	// FileVirtuals
	foreach ($model->fileVirtuals()->get() as $item) {
		$item->delete();
	}
}
```


## Proxy download

```php
// Url signed
Route::controller(App\Http\Controllers\FileController::class)->group(function () {
    Route::any('/file/{file_virtual}/download/{filename}', 'download')
        ->middleware('throttle:lax')->name('file.download');
    // $this->proxyUrlSigned($request, false);
});

// User authorized
Route::controller(App\Http\Controllers\FileController::class)->group(function () {
    Route::any('/file/{file_virtual}/download/{filename}', 'download')
        ->middleware('auth:sanctum', 'throttle:lax')->name('file.download');
    // $this->proxyUserAuthorize($request, false);
});
```


## Route

```php
\Route::pattern('file_virtual', '[0-9]{1,18}'); // RouteServiceProvider
\Route::pattern('entity_id', '[0-9]{1,18}'); // RouteServiceProvider

Route::prefix('/file')
    ->middleware('auth:sanctum')
    ->controller(App\Http\Controllers\Api\FileController::class)
    ->group(function () {
        Route::post('/upload/{entity}/{entity_id}/{name}', 'upload'); // + middleware
        Route::post('/delete/{file_virtual}', 'delete'); // + middleware
    });
```


## Controller

```php
class FileController extends Controller
{
    use \AnourValar\EloquentFile\Traits\ControllerTrait;

    /**
     * Web-service: Upload a file
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function upload(Request $request): array
    {
        $fileVirtual = \DB::transaction(function () use ($request) {
            return $this->uploadFileFrom($request);
        });

        return ['file_virtual' => ['id' => $fileVirtual->id]];
    }

    /**
     * Web-service: Delete a file
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function delete(Request $request)
    {
        \DB::transaction(function () use ($request) {
            $this->deleteFileFrom($request);
        });

        return ['file_virtual' => ['id' => (int) $request->route('file_virtual')]];
    }
}
```
