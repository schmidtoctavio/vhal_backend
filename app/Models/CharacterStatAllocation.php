<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable([
    'character_id',

    'allocated_strength',
    'allocated_agility',
    'allocated_vitality',
    'allocated_energy',

    'bonus_stat_points',

    'revision',
])]
class CharacterStatAllocation extends Model
{
    protected $primaryKey = 'character_id';

    public $incrementing = false;

    protected $keyType = 'int';


    protected function casts(): array
    {
        return [
            'character_id' => 'integer',

            'allocated_strength' => 'integer',
            'allocated_agility' => 'integer',
            'allocated_vitality' => 'integer',
            'allocated_energy' => 'integer',

            'bonus_stat_points' => 'integer',

            'revision' => 'integer',
        ];
    }


    public function character(): BelongsTo
    {
        return $this->belongsTo(
            Character::class
        );
    }
}