<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'account_id',
    'slot_index',
    'name',
    'class_id',
    'level',
])]
class Character extends Model
{
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'slot_index' => 'integer',
            'level' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(
            CharacterClass::class,
            'class_id'
        );
    }
}