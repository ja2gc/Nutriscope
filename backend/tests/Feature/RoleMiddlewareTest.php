<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-rnd', function () {
            return response()->json(['message' => 'Success']);
        })->middleware('role:RND');
    }

    public function test_unauthenticated_user_is_rejected()
    {
        $response = $this->getJson('/test-rnd');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_with_wrong_role_is_rejected()
    {
        $user = User::forceCreate([
            'name' => 'Test', 'email' => 'test1@example.com', 'password' => Hash::make('pass'),
            'role' => 'FSS',
        ]);

        $response = $this->actingAs($user)->getJson('/test-rnd');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden. Insufficient role.']);
    }

    public function test_deactivated_user_is_rejected()
    {
        $user = User::forceCreate([
            'name' => 'Test', 'email' => 'test2@example.com', 'password' => Hash::make('pass'),
            'role' => 'RND', 'is_active' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/test-rnd');
        $response->assertStatus(403)
            ->assertJson(['message' => 'Account is deactivated.']);
    }

    public function test_authenticated_user_with_correct_role_is_accepted()
    {
        $user = User::forceCreate([
            'name' => 'Test', 'email' => 'test3@example.com', 'password' => Hash::make('pass'),
            'role' => 'RND', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/test-rnd');
        $response->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }
}
