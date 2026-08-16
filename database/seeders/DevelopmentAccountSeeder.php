<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class DevelopmentAccountSeeder extends Seeder
{
    public function run(): void
    {
        Account::updateOrCreate(
            [
                'username' => 'dev_account',
            ],
            [
                'email' => 'admin@admin.com',
                'password' => '112233',
                'status' => 'active',
            ],
        );
    }
}