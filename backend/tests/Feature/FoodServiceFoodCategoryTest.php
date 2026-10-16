<?php

namespace Tests\Feature;

use App\Models\FoodServiceRecipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoodServiceFoodCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_food_categories_are_corrected_without_changing_other_values_and_are_repeatable(): void
    {
        $rnd = User::factory()->rnd()->create();
        foreach (['breakfast', 'lunch', 'dinner', 'snack', 'beverage', null] as $index => $category) {
            FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => $category === null ? 'Cheezwhiz Sandwich' : 'Food '.$index, 'category' => $category, 'servings' => 50, 'cost' => 120]);
        }
        $before = DB::table('food_service_recipes')->orderBy('id')->get()->map(fn ($row) => Arr::except((array) $row, ['category']))->all();
        $migration = require database_path('migrations/2026_10_16_121409_normalize_food_service_food_categories.php');
        $migration->up();
        $this->assertSame(['meal', 'meal', 'meal', 'snack', 'beverage', 'meal'], FoodServiceRecipe::orderBy('id')->pluck('category')->all());
        $this->assertSame($before, DB::table('food_service_recipes')->orderBy('id')->get()->map(fn ($row) => Arr::except((array) $row, ['category']))->all());
        $first = DB::table('food_service_recipes')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertSame($first, DB::table('food_service_recipes')->orderBy('id')->get()->toJson());
    }

    public function test_unclassified_custom_food_stops_the_correction_without_partial_writes(): void
    {
        $rnd = User::factory()->rnd()->create();
        foreach (['breakfast', null] as $index => $category) {
            FoodServiceRecipe::create(['rnd_user_id' => $rnd->id, 'name' => 'Custom '.$index, 'category' => $category, 'servings' => 1]);
        }
        $before = DB::table('food_service_recipes')->orderBy('id')->get()->toJson();
        $this->expectException(\RuntimeException::class);
        try {
            (require database_path('migrations/2026_10_16_121409_normalize_food_service_food_categories.php'))->up();
        } finally {
            $this->assertSame($before, DB::table('food_service_recipes')->orderBy('id')->get()->toJson());
        }
    }
}
