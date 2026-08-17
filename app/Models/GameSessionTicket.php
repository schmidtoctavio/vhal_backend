<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'character_id',
    'token_hash',
    'expires_at',
    'consumed_at',
])]
class GameSessionTicket extends Model
{
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'character_id' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class
        );
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(
            Character::class
        );
    }
}