<?php

namespace Javaabu\BulkActions\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }
}
