<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Recipe;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\FoodItemPricesSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsdaFoodImportFixture;
use Tests\TestCase;

class ClinicalFoodSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UsdaFoodImportFixture::fake();
    }

    public function test_food_imports_are_repeatable_and_preserve_manual_foods(): void
    {
        $manualFood = FoodItem::factory()->create([
            'name' => 'Ward Dietitian Custom Food',
            'usda_fdc_id' => null,
            'calories' => 88,
        ]);

        $this->seed(FoodItemsSeeder::class);
        $firstCount = FoodItem::query()->count();
        $this->seed(FoodItemsSeeder::class);

        $this->assertTrue(FoodItem::query()->whereKey($manualFood->id)->exists());
        $this->assertSame($firstCount, FoodItem::query()->count());
        $this->assertSame(1, FoodItem::query()->where('name', 'Cooked White Rice')->count());
        $this->assertNotNull(FoodItem::query()->where('name', 'Cooked White Rice')->value('usda_fdc_id'));
        $garlic = FoodItem::query()->where('name', 'Garlic (Raw)')->firstOrFail();
        $this->assertGreaterThan(0, (float) $garlic->calories);
        $this->assertArrayNotHasKey('ready_to_eat', $garlic->getAttributes());
    }

    public function test_price_seeder_sets_canonical_prices_then_recalculates_recipe_costs(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $this->seed(FoodItemPricesSeeder::class);

        $this->assertGreaterThan(0, (float) FoodItem::query()->where('name', 'Cooked White Rice')->value('unit_price'));
        $this->assertDatabaseMissing('recipes', ['name' => 'Plain White Rice Meal']);
        $this->assertGreaterThan(0, (float) Recipe::query()->where('name', 'Adobong Manok')->value('cost'));
    }

    public function test_seeded_recipes_have_explicit_meal_component_and_prepared_portion_contracts(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $recipes = Recipe::query()->get();

        $this->assertNotEmpty($recipes);
        foreach ($recipes as $recipe) {
            $this->assertNotEmpty($recipe->meal_types, $recipe->name.' needs explicit meal types.');
            $this->assertContains($recipe->component_type, ['main_dish', 'staple', 'complete_meal', 'side', 'other']);
            $this->assertGreaterThan(0, (float) $recipe->prepared_portion_amount);
            $this->assertNotSame('', trim((string) $recipe->prepared_portion_unit));
            $this->assertGreaterThan(0, $recipe->servings);
        }
        $this->assertSame(4, Recipe::where('name', 'Adobong Manok')->firstOrFail()->servings);
        $this->assertSame(6, Recipe::where('name', 'Calderetang Baka')->firstOrFail()->servings);
        $meal = Recipe::where('name', 'Lugaw na may Itlog')->firstOrFail();
        $this->assertSame(8, $meal->servings);
        $first = $meal->planSnapshot();
        $this->assertSame(185.0, (float) $first['recipe_ingredients'][0]['quantity']);
        $this->seed(RecipeSeeder::class);
        $second = $meal->fresh()->planSnapshot();
        foreach (['recipe_ingredients', 'recipe_original_ingredients'] as $key) {
            $this->assertSame(array_column($first[$key], 'quantity'), array_column($second[$key], 'quantity'));
            unset($first[$key], $second[$key]);
        }
        $this->assertSame($first, $second);
    }

    public function test_side_rice_is_removed_from_dishes_but_retained_in_true_rice_based_meals(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $sideRiceDishes = [
            'Adobong Manok',
            'Adobong Baboy',
            'Calderetang Manok',
            'Calderetang Baka',
            'Menudo',
            'Nilagang Itlog',
            'Ginisang Monggo',
            'Chopsuey',
            'Paksiw na Bangus',
        ];

        foreach ($sideRiceDishes as $name) {
            $recipe = Recipe::query()->where('name', $name)->first();
            $this->assertNotNull($recipe, $name.' was not seeded.');

            $this->assertFalse(
                $recipe->ingredients()->whereHas('foodItem', fn ($query) => $query->whereIn('name', [
                    'Cooked White Rice',
                    'Cooked Brown Rice',
                ]))->exists(),
                $name.' still embeds side rice.'
            );
        }

        foreach (['Arroz Caldo', 'Champorado', 'Bringhe'] as $name) {
            $recipe = Recipe::query()->where('name', $name)->first();
            $this->assertNotNull($recipe, $name.' was not seeded.');

            $this->assertTrue(
                $recipe->ingredients()->whereHas('foodItem', fn ($query) => $query->whereIn('name', [
                    'White Rice, Uncooked',
                    'Glutinous Rice, Uncooked',
                ]))->exists(),
                $name.' must retain structurally defining rice.'
            );
        }
    }
}
