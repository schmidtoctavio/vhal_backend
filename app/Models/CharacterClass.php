<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'display_name',
    'is_enabled',
    'sort_order',
])]
class CharacterClass extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function characters(): HasMany
    {
        return $this->hasMany(
            Character::class,
            'class_id'
        );
    }
}