<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount('admin@nutriscope.local', 'Elena', 'Villanueva', 'Admin');
        $this->seedAccount('rnd@nutriscope.local', 'Rosa Mae', 'Dela Cruz', 'RND');
        $this->seedAccount('fss@nutriscope.local', 'Maria', 'Santos', 'FSS');
    }

    private function seedAccount(string $email, string $firstName, string $lastName, string $role): void
    {
        $displayName = "{$firstName} {$lastName}";
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $displayName,
                'password' => Hash::make('nutriscope2024!'),
                'role' => $role,
                'is_active' => true,
            ],
        );

        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $displayName,
        ]);
        if ($user->isDirty(['first_name', 'last_name', 'name'])) {
            $user->save();
        }
    }
}
