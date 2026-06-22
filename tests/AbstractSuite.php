<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Tests\Models\User;
use AnourValar\EloquentFile\Tests\Support\NoLockStrategy;
use AnourValar\EloquentFile\Tests\Support\TestController;
use AnourValar\EloquentFile\Tests\Support\UserPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

abstract class AbstractSuite extends \Orchestra\Testbench\TestCase
{
    /**
     * Init
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();

        $this->fakeStorages();

        Gate::policy(User::class, UserPolicy::class);

        NoLockStrategy::$keys = [];
        TestController::$remoteContent = false;
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app)
    {
        return [
            \AnourValar\EloquentFile\Providers\EloquentFileServiceProvider::class,
            \AnourValar\EloquentValidation\Providers\EloquentValidationServiceProvider::class,
            \AnourValar\LaravelAtom\Providers\LaravelAtomServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app)
    {
        return [
            'Atom' => \AnourValar\LaravelAtom\Facades\AtomFacade::class,
            'EloquentFile' => \AnourValar\EloquentFile\Facades\EloquentFileFacade::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $config = $app['config'];

        // App
        $config->set('app.env', 'testing');
        $config->set('app.debug', true);
        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Database
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Models
        $config->set('eloquent_file.models.file_physical', FilePhysical::class);
        $config->set('eloquent_file.models.file_virtual', FileVirtual::class);

        // Disks
        $config->set('filesystems.disks.s3_public', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/s3_public'),
            'url' => 'http://localhost/storage/s3_public',
            'visibility' => 'public',
            'throw' => true,
        ]);
        $config->set('filesystems.disks.s3_private', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/s3_private'),
            'url' => 'http://localhost/storage/s3_private',
            'visibility' => 'private',
            'throw' => true,
        ]);

        // Atom: replace the Postgres-only advisory lock with a no-op
        $config->set('atom.locks.strategies.test_no_lock', NoLockStrategy::class);
        $config->set('atom.locks.strategy', 'test_no_lock');

        // Auth
        $config->set('auth.providers.users.model', User::class);

        // Extra "binary" type with no extension restriction (handy for buffer uploads)
        $config->set('eloquent_file.file_physical.type.binary', [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType::class,
            'rules' => ['max:10240'],
            'rules_validate_mime_by_extension' => false,
        ]);

        // Morph map
        Relation::enforceMorphMap(['user' => User::class]);

        // Extra file_virtual.entity.user names to cover every handler
        $this->defineFileVirtualNames($config);
    }

    /**
     * @param \Illuminate\Contracts\Config\Repository $config
     * @return void
     */
    protected function defineFileVirtualNames($config): void
    {
        $names = $config->get('eloquent_file.file_virtual.entity.user.name');

        // ListPolicy + SimpleTitleName + private + simple (qty/size limits)
        $names['document'] = [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleTitleName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ListPolicy::class,
                'limit_qty' => 2,
                'limit_size' => 50, // KiB
            ],
            'visibility' => 'private',
            'types' => ['*' => 'simple'],
        ];

        // ListPolicy + SimpleName + public + image (no limits)
        $names['gallery'] = [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ListPolicy::class,
                'limit_qty' => 0,
                'limit_size' => 0,
            ],
            'visibility' => 'public',
            'types' => ['*' => 'image'],
        ];

        // ListPolicy + SimpleName + public + simple
        $names['note'] = [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ListPolicy::class,
                'limit_qty' => 0,
                'limit_size' => 0,
            ],
            'visibility' => 'public',
            'types' => ['*' => 'simple'],
        ];

        // SimpleName + public + binary (no extension required) - used by buffer seeding
        $names['blob'] = [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ListPolicy::class,
                'limit_qty' => 0,
                'limit_size' => 0,
            ],
            'visibility' => 'public',
            'types' => ['*' => 'binary'],
        ];

        // DetailsName (accepts "details") + public + simple
        $names['meta'] = [
            'bind' => \AnourValar\EloquentFile\Tests\Support\DetailsName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ListPolicy::class,
                'limit_qty' => 0,
                'limit_size' => 0,
            ],
            'visibility' => 'public',
            'types' => ['*' => 'binary'],
        ];

        // ArchivePolicy + SimpleName + private_encrypt + simple
        $names['scan'] = [
            'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName::class,
            'title' => 'eloquent_file::file_virtual.entity.user.name.avatar',
            'policy' => [
                'bind' => \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\ArchivePolicy::class,
                'limit_qty' => 0,
                'limit_size' => 0,
            ],
            'visibility' => 'private_encrypt',
            'types' => ['*' => 'simple'],
        ];

        $config->set('eloquent_file.file_virtual.entity.user.name', $names);
    }

    /**
     * @param \Illuminate\Routing\Router $router
     * @return void
     */
    protected function defineRoutes($router)
    {
        $router->pattern('file_virtual', '[0-9]+');

        $router->post('/file/upload/{entity}/{entity_id}/{name}', [TestController::class, 'upload']);
        $router->post('/file/upload', [TestController::class, 'upload']);
        $router->post('/file/upload-url', [TestController::class, 'uploadFromUrl']);
        $router->post('/file/delete/{file_virtual}', [TestController::class, 'delete']);

        $router->get('/file/{file_virtual}/download/{filename}', [TestController::class, 'downloadSigned'])
            ->name('file.download');
        $router->get('/file-auth/{file_virtual}/download/{filename}', [TestController::class, 'downloadAuth'])
            ->name('file.download.auth');
    }

    /**
     * Run the package migration plus the test "users" table.
     *
     * @return void
     */
    protected function migrate(): void
    {
        $migration = require __DIR__.'/../src/database/migrations/2019_08_19_000000_create_eloquent_files.php';
        $migration->up();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    /**
     * Fake every configured disk.
     *
     * @return void
     */
    protected function fakeStorages(): void
    {
        foreach (array_keys(config('filesystems.disks')) as $disk) {
            Storage::fake($disk);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     * @return \AnourValar\EloquentFile\Tests\Models\User
     */
    protected function createUser(array $attributes = []): User
    {
        return User::create(array_replace([
            'name' => 'John Doe',
            'email' => 'john'.mt_rand(1, PHP_INT_MAX).'@example.com',
            'password' => bcrypt('secret'),
        ], $attributes));
    }

    /**
     * @param string $name
     * @return string
     */
    protected function fixturePath(string $name): string
    {
        return __DIR__.'/fixtures/'.$name;
    }

    /**
     * @param string $name
     * @param string|null $clientName
     * @param string|null $mimeType
     * @return \Illuminate\Http\UploadedFile
     */
    protected function fixtureFile(string $name, ?string $clientName = null, ?string $mimeType = null): UploadedFile
    {
        return new UploadedFile(
            $this->fixturePath($name),
            $clientName ?? $name,
            $mimeType,
            null,
            true
        );
    }

    /**
     * Upload a fixture and return the persisted FileVirtual (wrapped in a transaction).
     *
     * @param string $fixture
     * @param array<string, mixed> $attributes
     * @param string|null $clientName
     * @param string|null $mimeType
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function uploadFixture(
        string $fixture,
        array $attributes,
        ?string $clientName = null,
        ?string $mimeType = null
    ): FileVirtual {
        $fileVirtual = (new FileVirtual())->forceFill($attributes);

        \DB::transaction(function () use (&$fileVirtual, $fixture, $clientName, $mimeType) {
            \App::make(\AnourValar\EloquentFile\Services\FileService::class)
                ->upload($this->fixtureFile($fixture, $clientName, $mimeType), $fileVirtual);
        });

        return $fileVirtual;
    }
}
