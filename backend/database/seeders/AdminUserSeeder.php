<?php

namespace Database\Seeders;

use App\Actions\Identity\SynchronizePersonName;
use App\Models\User;
use App\Rules\PersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('role', 'Admin')->where('is_active', true)->exists()) {
            return;
        }

        $data = Validator::make(config('seeding.admin', []), [
            'first_name' => ['required', 'string', new PersonName],
            'last_name' => ['required', 'string', new PersonName],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ])->validate();

        $data = app(SynchronizePersonName::class)->forCreate($data);
        User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => 'Admin',
            'is_active' => true,
            'must_change_password' => true,
            'must_set_recovery_email' => true,
        ]);
    }
}
