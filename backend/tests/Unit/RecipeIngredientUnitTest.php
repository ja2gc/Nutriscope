<?php

namespace Tests\Unit;

use App\Http\Controllers\FSS\FoodServiceRecipeController;
use Tests\TestCase;

class RecipeIngredientUnitTest extends TestCase
{
    public function test_same_dimension_is_compatible(): void
    {
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('g', 'g'));
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('kg', 'g'));   // mass↔mass
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('cup', 'mL')); // volume↔volume
    }

    public function test_count_vs_mass_is_incompatible(): void
    {
        // "2 eggs" (pc) against a gram base would silently cost 2 as 2 grams.
        $this->assertFalse(FoodServiceRecipeController::unitCompatible('pc', 'g'));
    }
}
