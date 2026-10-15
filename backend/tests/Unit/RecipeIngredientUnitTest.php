<?php

namespace Tests\Unit;

use App\Http\Controllers\FSS\FoodServiceRecipeController;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Tests\TestCase;

class RecipeIngredientUnitTest extends TestCase
{
    public function test_same_dimension_is_compatible(): void
    {
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('g', 'g'));
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('kg', 'g'));
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('cup', 'mL'));
    }

    public function test_count_vs_mass_is_incompatible(): void
    {
        $this->assertFalse(FoodServiceRecipeController::unitCompatible('pc', 'g'));
        $this->assertFalse(FoodServiceRecipeController::unitCompatible('tbsp', 'kg'));
    }

    public function test_recipe_eligibility_rejects_unverified_cross_family_rows(): void
    {
        $food = new FoodItem(['serving_size' => 100, 'serving_unit' => 'g', 'water_g' => 80]);
        $ingredient = new RecipeIngredient(['quantity' => 1, 'unit' => 'piece']);
        $ingredient->setRelation('foodItem', $food);
        $recipe = new Recipe(['servings' => 1]);
        $recipe->setRelation('ingredients', collect([$ingredient]));

        $this->assertFalse($recipe->hasValidPortionData());
        $this->expectException(\InvalidArgumentException::class);
        $recipe->total_water;
    }

    public function test_water_uses_the_same_mass_conversion_as_other_nutrients(): void
    {
        $food = new FoodItem(['serving_size' => 100, 'serving_unit' => 'g', 'water_g' => 80]);
        $ingredient = new RecipeIngredient(['quantity' => 0.5, 'unit' => 'kg']);
        $ingredient->setRelation('foodItem', $food);
        $recipe = new Recipe(['servings' => 5]);
        $recipe->setRelation('ingredients', collect([$ingredient]));

        $this->assertTrue($recipe->hasValidPortionData());
        $this->assertSame(400.0, $recipe->total_water);
    }
}
