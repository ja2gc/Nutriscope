<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFeatureTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Route::post('/api/auth/login', [\App\Http\Controllers\Auth\AuthController::class, 'login']);
        \Illuminate\Support\Facades\Route::post('/api/auth/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');
        \Illuminate\Support\Facades\Route::get('/api/auth/me', [\App\Http\Controllers\Auth\AuthController::class, 'me'])->middleware('auth:sanctum');
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::forceCreate([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        User::forceCreate([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_deactivated_user_cannot_login()
    {
        User::forceCreate([
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_fetch_their_profile()
    {
        $user = User::forceCreate([
            'name' => 'Test',
            'email' => 'test4@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)
                 ->assertJsonPath('email', $user->email);
    }

    public function test_user_can_logout()
    {
        $user = User::forceCreate([
            'name' => 'Test',
            'email' => 'test5@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->tokens);
    }
}
