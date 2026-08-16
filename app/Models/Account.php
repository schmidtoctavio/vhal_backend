<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'username',
    'email',
    'password',
    'status',
    'last_login_at',
])]
#[Hidden([
    'password',
])]
class Account extends Authenticatable
{
    use HasApiTokens;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }
}