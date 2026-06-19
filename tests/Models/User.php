<?php

namespace AnourValar\EloquentFile\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test entitable model. Mapped to the "user" morph alias (see AbstractSuite).
 */
class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @var array<string>
     */
    protected $hidden = ['password'];
}
