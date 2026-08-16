<?php

namespace Database\Seeders;

use App\Models\CharacterClass;
use Illuminate\Database\Seeder;

class CharacterClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            [
                'id' => 'warrior',
                'display_name' => 'Guerrero',
                'is_enabled' => true,
                'sort_order' => 0,
            ],
            [
                'id' => 'mage',
                'display_name' => 'Mago',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'archer',
                'display_name' => 'Arquero',
                'is_enabled' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($classes as $class) {
            CharacterClass::updateOrCreate(
                [
                    'id' => $class['id'],
                ],
                $class
            );
        }
    }
}