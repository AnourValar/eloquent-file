<?php

namespace AnourValar\EloquentFile\Tests\Support;

use AnourValar\LaravelAtom\Strategies\StrategyInterface;
use Illuminate\Database\Connection;

/**
 * The default "pessimistic_advisory" strategy relies on pg_advisory_xact_lock,
 * which is not available on SQLite. Tests substitute this no-op implementation.
 */
class NoLockStrategy implements StrategyInterface
{
    /**
     * Collected lock keys (handy for assertions).
     *
     * @var array<string>
     */
    public static array $keys = [];

    /**
     * {@inheritDoc}
     * @see \AnourValar\LaravelAtom\Strategies\StrategyInterface::lock()
     */
    public function lock(string $sha1, Connection $connection): void
    {
        static::$keys[] = $sha1;
    }
}
