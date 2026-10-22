<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Demo workspace. Change the passwords in Settings after the first login. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@postflow.test'], [
            'name' => 'Art Director',
            'password' => Hash::make('password'),
            'role' => Role::Admin,
            'locale' => 'fr',
        ]);

        User::updateOrCreate(['email' => 'cm@postflow.test'], [
            'name' => 'Community Manager',
            'password' => Hash::make('password'),
            'role' => Role::CommunityManager,
            'locale' => 'fr',
        ]);
    }
}
