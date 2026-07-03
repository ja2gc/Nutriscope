<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Clicking a menu cell shows the recipe's ingredient profile scaled to that day's
 * estimated population — same scaling/cost the costing engine uses.
 */
class RecipeProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_scales_ingredients_and_cost_to_population(): void
    {
        $fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
        $rnd = User::factory()->create(['role' => 'RND']);

        // Rice: 5000 g per 50 servings at ₱0.052/g (kg→g via units).
        $fs = FsItem::factory()->create([
            'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 52,
        ]);
        $recipe = FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Steamed Rice', 'servings' => 50]);
        FoodServiceRecipeIngredient::create([
            'food_service_recipe_id' => $recipe->id, 'fs_item_id' => $fs->id, 'quantity' => 5000, 'unit' => 'g',
        ]);

        $res = $this->actingAs($fss)
            ->getJson("/api/fss/food-service-recipes/{$recipe->uuid}/profile?population=100")
            ->assertOk();

        // factor 100/50 = 2 → 10000 g rice → ₱520 total → ₱5.20/head
        $res->assertJsonPath('data.population', 100);
        $this->assertEqualsWithDelta(520.0, (float) $res->json('data.total_cost'), 1e-6);
        $this->assertEqualsWithDelta(5.2, (float) $res->json('data.cost_per_head'), 1e-6);

        $line = collect($res->json('data.ingredient_usage'))->firstWhere('fs_item_id', $fs->id);
        $this->assertEqualsWithDelta(10000.0, $line['quantity'], 1e-6);
        $this->assertEqualsWithDelta(520.0, $line['cost'], 1e-6);
    }
}
