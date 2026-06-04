<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoodItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;
    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_rnd_can_list_food_items_paginated(): void
    {
        FoodItem::factory(5)->create();

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_rnd_can_search_food_items_by_name(): void
    {
        FoodItem::factory()->create(['name' => 'Chicken Breast']);
        FoodItem::factory()->create(['name' => 'Brown Rice']);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?search=Chicken');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Chicken', $data[0]['name']);
    }

    public function test_rnd_can_filter_food_items_by_category(): void
    {
        FoodItem::factory()->create(['category' => 'protein']);
        FoodItem::factory()->create(['category' => 'carbs']);
        FoodItem::factory()->create(['category' => 'protein']);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/food-items?category=protein');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rnd_can_create_food_item_manually(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/food-items', [
                'name'         => 'Grilled Salmon',
                'category'     => 'protein',
                'calories'     => 208,
                'protein'      => 28,
                'carbs'        => 0,
                'fat'          => 10,
                'serving_size' => 100,
                'serving_unit' => 'g',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Grilled Salmon');

        $this->assertDatabaseHas('food_items', ['name' => 'Grilled Salmon']);
    }

    public function test_create_food_item_requires_name_and_calories(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/food-items', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'calories']);
    }

    public function test_rnd_can_view_single_food_item(): void
    {
        $food = FoodItem::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/food-items/{$food->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $food->id);
    }

    public function test_rnd_can_update_food_item(): void
    {
        $food = FoodItem::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->rnd)
            ->putJson("/api/rnd/food-items/{$food->id}", [
                'name'     => 'Updated Name',
                'calories' => 150,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('food_items', ['id' => $food->id, 'name' => 'Updated Name']);
    }

    public function test_rnd_can_delete_food_item(): void
    {
        $food = FoodItem::factory()->create();

        $response = $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/food-items/{$food->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('food_items', ['id' => $food->id]);
    }

    public function test_non_rnd_cannot_access_food_items(): void
    {
        $response = $this->actingAs($this->fss)
            ->getJson('/api/rnd/food-items');

        $response->assertForbidden();
    }
}
