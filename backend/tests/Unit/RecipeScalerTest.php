<?php

namespace Tests\Unit;

use App\Models\FoodServiceRecipe;
use App\Support\RecipeScaler;
use Tests\TestCase;

/**
 * Pure-logic test (no DB): scaling a recipe from its baseline yield to a target
 * number of servings. This same primitive drives the menu planner's
 * population scaling, so it must be exact and guarded.
 */
class RecipeScalerTest extends TestCase
{
    public function test_factor_scales_50_serving_base_to_120(): void
    {
        $this->assertEqualsWithDelta(2.4, RecipeScaler::factor(50, 120), 1e-9);
    }

    public function test_factor_is_one_at_baseline(): void
    {
        $this->assertSame(1.0, RecipeScaler::factor(50, 50));
    }

    public function test_zero_base_servings_is_safe(): void
    {
        // A recipe with a nonsensical 0 yield falls back to 1 rather than dividing by zero.
        $this->assertSame(100.0, RecipeScaler::factor(0, 100));
    }

    public function test_zero_target_is_zero(): void
    {
        $this->assertSame(0.0, RecipeScaler::factor(50, 0));
    }

    public function test_scale_multiplies_a_value_by_the_factor(): void
    {
        // 280 g of an ingredient for 50 servings → 672 g for 120 servings
        $this->assertEqualsWithDelta(672.0, RecipeScaler::scale(280, 50, 120), 1e-9);
    }

    public function test_recipe_scaled_cost_uses_the_primitive(): void
    {
        // ₱100 recipe yielding 50 → ₱240 when scaled to 120
        $recipe = new FoodServiceRecipe(['cost' => 100, 'servings' => 50]);
        $this->assertEqualsWithDelta(240.0, $recipe->scaledCost(120), 1e-9);
    }
}
