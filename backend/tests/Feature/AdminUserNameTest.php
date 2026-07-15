<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_resources_keep_the_deprecated_name_field(): void
    {
        $admin = User::factory()->admin()->create();
        $rnd = User::factory()->rnd()->legacyName('Maria Teresa Del Rosario')->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/users/{$rnd->uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Maria Teresa Del Rosario');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Maria Teresa Del Rosario']);
    }

    public function test_admin_unrelated_update_preserves_the_legacy_name(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->rnd()->legacyName('Jose Rizal Mercado')->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->uuid}", ['role' => 'FSS'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Jose Rizal Mercado')
            ->assertJsonPath('data.role', 'FSS');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jose Rizal Mercado',
            'role' => 'FSS',
        ]);
    }
}
