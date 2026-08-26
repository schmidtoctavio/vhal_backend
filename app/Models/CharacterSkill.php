<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable([
    'character_id',
    'skill_id',
])]
class CharacterSkill extends Model
{
    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
        ];
    }


    public function character(): BelongsTo
    {
        return $this->belongsTo(
            Character::class
        );
    }
}