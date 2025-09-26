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
