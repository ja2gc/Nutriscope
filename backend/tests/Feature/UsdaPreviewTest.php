<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsdaPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function rndUser(): User
    {
        return User::factory()->create(['role' => 'RND']);
    }

    public function test_preview_returns_nutrient_data_without_saving(): void
    {
        $user = $this->rndUser();

        $this->mock(UsdaService::class, function ($mock) {
            $mock->shouldReceive('fetch')->with(331960)->once()->andReturn([
                'fdc_id' => 331960,
                'name' => 'Chicken, broilers or fryers, breast',
                'calories' => 165.0,
                'protein' => 31.0,
                'carbs' => 0.0,
                'fat' => 3.6,
                'micronutrients' => ['sodium' => 74.0, 'potassium' => 256.0],
            ]);
        });

        $response = $this->actingAs($user)
            ->getJson('/api/rnd/usda/preview/331960');

        $response->assertOk()
            ->assertJsonPath('data.fdc_id', 331960)
            ->assertJsonPath('data.calories', 165)
            ->assertJsonPath('data.micronutrients.sodium', 74);

        $this->assertDatabaseCount('food_items', 0);
    }

    public function test_preview_rejects_non_numeric_fdc_id(): void
    {
        $user = $this->rndUser();

        $response = $this->actingAs($user)
            ->getJson('/api/rnd/usda/preview/abc123');

        $response->assertStatus(404);
    }

    public function test_preview_requires_authentication(): void
    {
        $this->getJson('/api/rnd/usda/preview/331960')->assertUnauthorized();
    }
}
