<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsdaFoodImportFixture;
use Tests\TestCase;

class ClinicalLibraryRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UsdaFoodImportFixture::fake();
    }

    public function test_incompatible_food_basis_stops_the_refresh_without_partial_updates(): void
    {
        $this->seed([AdminUserSeeder::class, FoodItemsSeeder::class]);
        $milk = FoodItem::where('usda_fdc_id', 170872)->firstOrFail();
        $milk->update(['serving_unit' => 'mL']);
        try {
            $this->seed(RecipeSeeder::class);
            $this->fail('An incompatible nutrient basis must require review.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('incompatible', $exception->getMessage());
        }
        $this->assertDatabaseCount('recipes', 0);
        $this->assertSame('mL', $milk->fresh()->serving_unit);
    }

    public function test_missing_template_recipe_rolls_back_all_reference_template_writes(): void
    {
        $this->seed([AdminUserSeeder::class, FoodItemsSeeder::class, RecipeSeeder::class]);
        $manual = MealPlanTemplate::create(['name' => 'My measured rotation', 'rnd_user_id' => User::where('role', 'RND')->value('id')]);
        Recipe::where('name', 'Pesang Isda')->firstOrFail()->delete();
        try {
            $this->seed(InterventionMealPlanTemplateSeeder::class);
            $this->fail('Missing recipes must stop template seeding.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString("requires measured recipe 'Pesang Isda'", $exception->getMessage());
        }
        $this->assertDatabaseCount('meal_plan_templates', 1);
        $this->assertDatabaseCount('meal_plan_template_days', 0);
        $this->assertDatabaseCount('meal_plan_template_items', 0);
        $this->assertModelExists($manual);
    }

    public function test_library_keeps_usda_nutrient_baselines_including_zero_energy_ingredients(): void
    {
        $this->seed(FoodItemsSeeder::class);
        $water = FoodItem::where('name', 'Water')->firstOrFail();
        $salt = FoodItem::where('name', 'Salt')->firstOrFail();
        $this->assertSame(0.0, (float) $water->calories);
        $this->assertGreaterThan(0, $water->water_g);
        $this->assertSame('mL', $water->serving_unit);
        $this->assertSame(100.0, (float) $water->serving_size);
        $this->assertSame(0.0, (float) $salt->calories);
        $this->assertSame('g', $salt->serving_unit);
        $this->assertEqualsWithDelta(1162.74, 3 / (float) $salt->serving_size * $salt->micronutrients['sodium'], 0.01);
        foreach (['Canola Oil', 'Soy Sauce', 'Vinegar', 'Fish Sauce', 'Low-fat Milk', 'Chicken Egg, Hard-boiled'] as $name) {
            $food = FoodItem::where('name', $name)->firstOrFail();
            $this->assertSame('g', $food->serving_unit);
            $this->assertSame(100.0, (float) $food->serving_size);
        }
    }

    public function test_food_refresh_preserves_manual_ingredient_amounts_and_plan_snapshots(): void
    {
        $milk = FoodItem::factory()->create(['name' => 'Low-fat Milk (1%)', 'usda_fdc_id' => 170872,
            'serving_size' => 100, 'serving_unit' => 'g', 'calories' => 42, 'protein' => 3.37,
            'carbs' => 4.99, 'fat' => 0.97, 'water_g' => 89.92, 'unit_price' => 9]);
        $recipe = Recipe::factory()->create(['servings' => 2]);
        $line = $recipe->ingredients()->create(['food_item_id' => $milk->id, 'quantity' => 244, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $snapshot = $recipe->planSnapshot();
        $item = MealPlanItem::factory()->create(['recipe_id' => $recipe->id, 'nutrient_snapshot' => $snapshot]);
        $saved = $item->fresh()->getRawOriginal('nutrient_snapshot');
        $this->seed(FoodItemsSeeder::class);
        $line->refresh();
        $this->assertSame($milk->id, FoodItem::where('name', 'Low-fat Milk')->firstOrFail()->id);
        $this->assertSame('g', $line->unit);
        $this->assertEqualsWithDelta(244, (float) $line->quantity, 0.000001);
        $this->assertEqualsWithDelta(102.48, (float) $recipe->fresh()->total_calories, 0.01);
        $this->assertSame($saved, $item->fresh()->getRawOriginal('nutrient_snapshot'));
        $this->seed(FoodItemsSeeder::class);
        $this->assertSame($line->unit, $line->fresh()->unit);
        $this->assertSame($line->quantity, $line->fresh()->quantity);
    }

    public function test_refresh_keeps_recipe_identity_and_saved_snapshots_when_renaming(): void
    {
        $this->seed([AdminUserSeeder::class, FoodItemsSeeder::class]);
        $catalog = json_decode(file_get_contents(database_path('seeders/data/clinical-recipes.json')), true, flags: JSON_THROW_ON_ERROR);
        $legacy = Recipe::factory()->create(['name' => 'Paksiw na Bangus with Rice', 'rnd_user_id' => User::where('role', 'RND')->value('id'),
            'prep_notes' => $catalog['references']['Paksiw na Bangus']]);
        $riceRecipe = Recipe::factory()->create(['name' => 'Steamed White Rice', 'rnd_user_id' => $legacy->rnd_user_id,
            'category' => 'carb', 'prep_notes' => $catalog['references']['Steamed White Rice']]);
        $food = FoodItem::where('name', 'Cooked White Rice')->firstOrFail();
        $sameName = FoodItem::factory()->create(['name' => 'Cooked White Rice', 'usda_fdc_id' => 99999991]);
        $legacy->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 150, 'unit' => 'g']);
        $legacy->recalculateTotals();
        $saved = MealPlanItem::factory()->create(['recipe_id' => $legacy->id, 'quantity' => 1.25, 'unit' => 'serving', 'nutrient_snapshot' => $legacy->planSnapshot()]);
        $snapshot = $saved->fresh()->getRawOriginal('nutrient_snapshot');
        $manual = Recipe::factory()->create(['name' => 'Menudo', 'rnd_user_id' => $legacy->rnd_user_id, 'prep_notes' => 'My measured family recipe']);
        $this->seed(RecipeSeeder::class);
        $this->assertSame($legacy->uuid, Recipe::where('name', 'Paksiw na Bangus')->firstOrFail()->uuid);
        $this->assertSame($riceRecipe->uuid, Recipe::where('name', 'Cooked White Rice')->firstOrFail()->uuid);
        $ids = Recipe::orderBy('id')->pluck('uuid')->all();
        $this->seed(RecipeSeeder::class);
        $this->assertSame($ids, Recipe::orderBy('id')->pluck('uuid')->all());
        $this->assertModelExists($manual);
        $this->assertFalse(Recipe::whereNotNull('seed_key')->whereHas('ingredients', fn ($query) => $query->where('food_item_id', $sameName->id))->exists());
        $this->assertSame('My measured family recipe', $manual->fresh()->prep_notes);
        $this->assertSame($snapshot, $saved->fresh()->getRawOriginal('nutrient_snapshot'));
        $this->assertSame(1.25, (float) $saved->fresh()->quantity);
        foreach (Recipe::with('ingredients.foodItem')->whereNotNull('seed_key')->where('rnd_user_id', $legacy->rnd_user_id)->get() as $recipe) {
            $this->assertTrue($recipe->hasValidPortionData(), $recipe->name);
            $this->assertDoesNotMatchRegularExpression('/[()]|Hospital Version|Soft Diet|Simplified/', $recipe->name);
        }
        $paksiw = Recipe::with('ingredients.foodItem')->where('name', 'Paksiw na Bangus')->firstOrFail();
        foreach (['Water', 'Vinegar', 'Salt'] as $name) {
            $this->assertTrue($paksiw->ingredients->contains(fn ($line) => $line->foodItem->name === $name), $name);
        }
        $salt = $paksiw->ingredients->first(fn ($line) => $line->foodItem->name === 'Salt');
        $this->assertSame('g', $salt->unit);
        $this->assertEqualsWithDelta(9, (float) $salt->quantity, 0.000001);
        $milk = Recipe::with('ingredients')->where('name', 'Gatas')->firstOrFail()->ingredients->sole();
        $this->assertSame('g', $milk->unit);
        $this->assertEqualsWithDelta(244, (float) $milk->quantity, 0.000001);
        foreach (Recipe::whereNotNull('seed_key')->with('ingredients.foodItem')->get()->flatMap->ingredients as $ingredient) {
            $this->assertContains($ingredient->unit, ['g', 'mL']);
            $this->assertSame($ingredient->foodItem->serving_unit, $ingredient->unit);
        }
        foreach (['Calderetang Manok', 'Calderetang Baka', 'Menudo', 'Adobong Manok', 'Bringhe', 'Suam na Mais'] as $name) {
            $this->assertDatabaseHas('recipes', ['name' => $name]);
        }
        $this->assertSame('side', Recipe::where('name', 'Pechay')->value('component_type'));
        $this->assertSame('carb', Recipe::where('name', 'Sinangag')->value('category'));
    }

    public function test_one_template_per_profile_with_snacks_only_where_planned_and_repeatable(): void
    {
        $this->seed([AdminUserSeeder::class, FoodItemsSeeder::class, RecipeSeeder::class]);
        $manual = MealPlanTemplate::create(['name' => 'My own menu', 'rnd_user_id' => User::where('role', 'RND')->value('id')]);
        $manualRecipe = Recipe::factory()->create(['name' => 'Adobong Manok', 'rnd_user_id' => $manual->rnd_user_id, 'servings' => 1]);
        $manualRecipe->ingredients()->create(['food_item_id' => FoodItem::where('name', 'Cooked White Rice')->value('id'), 'quantity' => 100, 'unit' => 'g']);
        $manualRecipe->recalculateTotals();
        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $templates = MealPlanTemplate::with('days.items.recipe')->whereKeyNot($manual->id)->get();
        $this->assertCount(12, $templates);
        foreach ($templates as $template) {
            $this->assertStringNotContainsString('Demo', $template->name);
            $this->assertStringNotContainsString('No Snacks', $template->name);
            $withSnacks = in_array($template->name, ['Liver Disease', 'Weight Gain', 'Malnutrition'], true);
            $this->assertSame(! $withSnacks, $template->snacks_hidden, $template->name);
            $snacks = $template->days->whereIn('meal_type', ['am_snack', 'pm_snack'])->flatMap->items;
            $this->assertSame($withSnacks, $snacks->isNotEmpty(), $template->name);
            foreach ($template->days->flatMap->items as $item) {
                $this->assertNotNull($item->recipe);
                $this->assertNotSame($manualRecipe->id, $item->recipe_id);
                $this->assertNotEmpty($item->nutrient_snapshot['recipe_original_ingredients']);
            }
        }
        $before = MealPlanTemplate::orderBy('id')->pluck('uuid')->all();
        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $this->assertSame($before, MealPlanTemplate::orderBy('id')->pluck('uuid')->all());
        $this->assertModelExists($manual);
    }
}
