<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Services\UsdaService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FoodItemPricesSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ClinicalFoodSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_seeding_is_offline_repeatable_and_preserves_manual_foods(): void
    {
        $manualFood = FoodItem::factory()->create([
            'name' => 'Ward Dietitian Custom Food',
            'usda_fdc_id' => null,
            'calories' => 88,
        ]);

        $usda = Mockery::mock(UsdaService::class);
        $usda->shouldNotReceive('search');
        $usda->shouldNotReceive('import');
        $this->app->instance(UsdaService::class, $usda);

        $this->seed(FoodItemsSeeder::class);
        $firstCount = FoodItem::query()->count();
        $this->seed(FoodItemsSeeder::class);

        $this->assertTrue(FoodItem::query()->whereKey($manualFood->id)->exists());
        $this->assertSame($firstCount, FoodItem::query()->count());
        $this->assertSame(1, FoodItem::query()->where('name', 'Steamed White Rice')->count());
        $this->assertNotNull(FoodItem::query()->where('name', 'Steamed White Rice')->value('usda_fdc_id'));
        $readyFoods = FoodItem::query()->whereNotNull('usda_fdc_id')->readyToEat()->get();
        $this->assertNotEmpty($readyFoods);
        $this->assertTrue($readyFoods->every(fn (FoodItem $food): bool => $food->category === 'fruit'));
        $this->assertFalse(FoodItem::query()->where('name', 'Garlic (Raw)')->firstOrFail()->isReadyToEat());
    }

    public function test_price_seeder_sets_canonical_prices_then_recalculates_recipe_costs(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $this->seed(FoodItemPricesSeeder::class);

        $this->assertGreaterThan(0, (float) FoodItem::query()->where('name', 'Steamed White Rice')->value('unit_price'));
        $this->assertGreaterThan(0, (float) Recipe::query()->where('name', 'Plain White Rice Meal')->value('cost'));
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
            $this->assertContains($recipe->component_type, ['main_dish', 'staple', 'complete_meal', 'other']);
            $this->assertGreaterThan(0, (float) $recipe->prepared_portion_amount);
            $this->assertNotSame('', trim((string) $recipe->prepared_portion_unit));
            $this->assertGreaterThan(0, $recipe->servings);
        }
    }

    public function test_side_rice_is_removed_from_dishes_but_retained_in_true_rice_based_meals(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $sideRiceDishes = [
            'Boiled Chicken Breast',
            'Chicken Breast with Pechay',
            'Steamed Tilapia',
            'Steamed Milkfish (Bangus)',
            'Drained Sardines',
            'Hard-Boiled Egg',
            'Tokwa with Kangkong',
            'Plain Ginisang Monggo',
            'Ampalaya with Tokwa',
            'Roasted Pork Loin',
            'Chopsuey',
            'Mongo Guisado (Mung Bean Stew)',
            'Paksiw na Bangus',
            'Mackerel Adobo Flakes',
        ];

        foreach ($sideRiceDishes as $name) {
            $recipe = Recipe::query()->where('name', $name)->first();
            $this->assertNotNull($recipe, $name.' was not seeded.');

            $this->assertFalse(
                $recipe->ingredients()->whereHas('foodItem', fn ($query) => $query->whereIn('name', [
                    'Steamed White Rice',
                    'Steamed Brown Rice',
                ]))->exists(),
                $name.' still embeds side rice.'
            );
        }

        foreach (['Arroz Caldo (Chicken Rice Soup)', 'Champorado (Chocolate Rice Porridge)'] as $name) {
            $recipe = Recipe::query()->where('name', $name)->first();
            $this->assertNotNull($recipe, $name.' was not seeded.');

            $this->assertTrue(
                $recipe->ingredients()->whereHas('foodItem', fn ($query) => $query->whereIn('name', [
                    'Steamed White Rice',
                    'Glutinous Rice (Cooked)',
                    'Rice Porridge (Lugaw)',
                ]))->exists(),
                $name.' must retain structurally defining rice.'
            );
        }
    }
}
