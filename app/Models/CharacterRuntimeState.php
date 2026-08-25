<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable([
    'character_id',
    'map_id',
    'position_x',
    'position_y',
    'position_z',
    'rotation_y',
    'hp',
    'mp',
    'revision',
])]
class CharacterRuntimeState extends Model
{
    protected $primaryKey = 'character_id';

    public $incrementing = false;

    protected $keyType = 'int';


    protected function casts(): array
    {
        return [
            'character_id' => 'integer',

            'position_x' => 'float',
            'position_y' => 'float',
            'position_z' => 'float',
            'rotation_y' => 'float',

            'hp' => 'integer',
            'mp' => 'integer',

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