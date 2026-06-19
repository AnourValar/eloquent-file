<?php

namespace AnourValar\EloquentFile\Tests\Support;

use AnourValar\EloquentFile\Tests\Models\User;

/**
 * A user may manage their own files only.
 */
class UserPolicy
{
    /**
     * @param \AnourValar\EloquentFile\Tests\Models\User $authUser
     * @param \AnourValar\EloquentFile\Tests\Models\User $targetUser
     * @return bool
     */
    public function update(User $authUser, User $targetUser): bool
    {
        return $authUser->getKey() === $targetUser->getKey();
    }
}
