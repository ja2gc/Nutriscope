<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@nutriscope.local'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('nutriscope2024!'),
                'role' => 'Admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'rnd@nutriscope.local'],
            [
                'name' => 'RND User',
                'password' => Hash::make('nutriscope2024!'),
                'role' => 'RND',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'fss@nutriscope.local'],
            [
                'name' => 'Maria Santos',
                'password' => Hash::make('nutriscope2024!'),
                'role' => 'FSS',
                'is_active' => true,
            ]
        );
    }
}
