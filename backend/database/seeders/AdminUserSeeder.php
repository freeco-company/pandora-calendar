<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Group-wide admin login: admin@freeco.cc / Feling@79041.
 *
 * Same credentials are used in mothership / pandora-meal — operations team
 * memorizes one login per environment.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@freeco.cc'],
            [
                'name' => 'Pandora Admin',
                'display_name' => 'Pandora Admin',
                'password' => Hash::make('Feling@79041'),
                'is_admin' => true,
                'subscription_tier' => 'admin',
            ]
        );
    }
}
