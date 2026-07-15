<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->randomElement([
            'Maria Luisa', 'Jose Miguel', 'Ana Marie', 'Carlo Andres', 'Rosa Mae',
        ]);
        $lastName = fake()->randomElement([
            'Dela Cruz', 'De los Santos', 'Del Rosario', 'Reyes', 'Santos Cruz',
        ]);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "{$firstName} {$lastName}",
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['RND', 'FSS', 'Admin']),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Represent an untouched pre-migration account without guessing name parts.
     */
    public function legacyName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'first_name' => null,
            'last_name' => null,
            'name' => $name,
        ]);
    }

    /**
     * Indicate that the user has the RND role.
     */
    public function rnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'RND',
        ]);
    }

    /**
     * Indicate that the user has the FSS role.
     */
    public function fss(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'FSS',
        ]);
    }

    /**
     * Indicate that the user has the Admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Admin',
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
