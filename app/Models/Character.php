<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


#[Fillable([
    'account_id',
    'slot_index',
    'name',
    'class_id',
    'level',
    'experience',
    'reset_count',
])]
class Character extends Model
{
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',

            'slot_index' => 'integer',

            'level' => 'integer',
            'experience' => 'integer',
            'reset_count' => 'integer',
        ];
    }


    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class
        );
    }


    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(
            CharacterClass::class,
            'class_id'
        );
    }


    public function runtimeState(): HasOne
    {
        return $this->hasOne(
            CharacterRuntimeState::class
        );
    }


    public function statAllocation(): HasOne
    {
        return $this->hasOne(
            CharacterStatAllocation::class
        );
    }


    public function skills(): HasMany
    {
        return $this->hasMany(
            CharacterSkill::class
        );
    }
}