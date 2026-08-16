<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Character;
use Illuminate\Database\Seeder;

class DevelopmentCharacterSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::query()
            ->where(
                'username',
                'dev_account'
            )
            ->firstOrFail();


        $characters = [
            [
                'slot_index' => 0,
                'name' => 'Atilio',
                'class_id' => 'warrior',
                'level' => 120,
            ],
            [
                'slot_index' => 1,
                'name' => 'Lyra',
                'class_id' => 'archer',
                'level' => 85,
            ],
            [
                'slot_index' => 2,
                'name' => 'Merlin',
                'class_id' => 'mage',
                'level' => 57,
            ],
        ];


        foreach ($characters as $characterData) {
            Character::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'slot_index' => $characterData['slot_index'],
                ],
                [
                    'name' => $characterData['name'],
                    'class_id' => $characterData['class_id'],
                    'level' => $characterData['level'],
                ],
            );
        }
    }
}