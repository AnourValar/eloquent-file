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
$schedule
    ->command('eloquent-file:on-zero --days=10')
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->onOneServer();
```
