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

    public function test_different_device_names_leave_both_tokens_valid()
    {
        $user = User::forceCreate([
            'name' => 'Multi Device',
            'email' => 'multi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $phoneResponse = $this->postJson('/api/auth/login', [
            'email'       => 'multi@example.com',
            'password'    => 'password123',
            'device_name' => 'Phone',
        ]);
        $phoneResponse->assertStatus(200);
        $phoneToken = $phoneResponse->json('token');

        $webResponse = $this->postJson('/api/auth/login', [
            'email'       => 'multi@example.com',
            'password'    => 'password123',
            'device_name' => 'Web',
        ]);
        $webResponse->assertStatus(200);
        $webToken = $webResponse->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeader('Authorization', 'Bearer ' . $phoneToken)
             ->getJson('/api/auth/me')
             ->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer ' . $webToken)
             ->getJson('/api/auth/me')
             ->assertStatus(200);
    }

    public function test_same_device_name_login_revokes_previous_token()
    {
        $user = User::forceCreate([
            'name' => 'Same Device',
            'email' => 'samedevice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $first = $this->postJson('/api/auth/login', [
            'email'       => 'samedevice@example.com',
            'password'    => 'password123',
            'device_name' => 'MyPhone',
        ]);
        $first->assertStatus(200);
        $firstToken = $first->json('token');

        $second = $this->postJson('/api/auth/login', [
            'email'       => 'samedevice@example.com',
            'password'    => 'password123',
            'device_name' => 'MyPhone',
        ]);
        $second->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'MyPhone']);

        // Verify the first token's DB record no longer exists (revoked).
        [$firstTokenId] = explode('|', $firstToken, 2);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => (int) $firstTokenId]);
    }

    public function test_login_without_device_name_uses_default_token_name()
    {
        $user = User::forceCreate([
            'name' => 'No Device',
            'email' => 'nodevice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nodevice@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'nutriscope-token']);
    }
}
