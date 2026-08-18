<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInstance extends Model
{
    protected $fillable = [
        'account_id',
        'character_id',
        'uid',
        'item_id',
        'container',
        'quantity',
        'grid_x',
        'grid_y',
        'equipment_slot',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'character_id' => 'integer',
            'quantity' => 'integer',
            'grid_x' => 'integer',
            'grid_y' => 'integer',
            'state' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}