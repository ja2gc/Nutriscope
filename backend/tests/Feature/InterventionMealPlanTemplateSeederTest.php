<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealPlanTemplate;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\Demo\AdminUserSeeder;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsdaFoodImportFixture;
use Tests\TestCase;

class InterventionMealPlanTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UsdaFoodImportFixture::fake();
    }

    public function test_existing_canonical_template_carb_repair_preserves_other_lines_and_identities(): void
    {
        $this->seedMigrationFixtures();
        $template = MealPlanTemplate::with('days.items.recipe')->orderBy('id')->firstOrFail();
        $slot = $template->days->first(fn ($day) => $day->day_of_week === 'Tuesday' && $day->meal_type === 'breakfast');
        $slot->items->first(fn ($item) => $item->recipe->category === 'carb')->delete();
        $lunch = $template->days->first(fn ($day) => $day->day_of_week === 'Tuesday' && $day->meal_type === 'lunch');
        $line = $lunch->items->first(fn ($item) => $item->recipe->category === 'carb');
        $other = Recipe::where('category', 'carb')->where('id', '!=', $line->recipe_id)->firstOrFail();
        $line->update(['recipe_id' => $other->id, 'nutrient_snapshot' => $other->planSnapshot()]);
        $lineId = $line->id;
        $otherLines = DB::table('meal_plan_template_items')->whereNotIn('recipe_id', Recipe::where('category', 'carb')->pluck('id'))->orderBy('id')->get()->toJson();
        $parents = DB::table('meal_plan_templates')->orderBy('id')->get()->toJson();
        $slots = DB::table('meal_plan_template_days')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_16_214009_align_canonical_clinical_template_carbs.php');
        $migration->up();
        $template->refresh()->load('days.items.recipe');
        $carbs = $template->days->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])->flatMap->items->filter(fn ($item) => $item->recipe->category === 'carb');
        $this->assertCount(21, $carbs);
        $this->assertCount(1, $carbs->pluck('recipe_id')->unique());
        $this->assertDatabaseHas('meal_plan_template_items', ['id' => $lineId, 'recipe_id' => $carbs->first()->recipe_id]);
        $this->assertSame($otherLines, DB::table('meal_plan_template_items')->whereNotIn('recipe_id', Recipe::where('category', 'carb')->pluck('id'))->orderBy('id')->get()->toJson());
        $this->assertSame($parents, DB::table('meal_plan_templates')->orderBy('id')->get()->toJson());
        $this->assertSame($slots, DB::table('meal_plan_template_days')->orderBy('id')->get()->toJson());
        $first = DB::table('meal_plan_template_items')->orderBy('id')->get()->toJson();
        $migration->up();
        $this->assertSame($first, DB::table('meal_plan_template_items')->orderBy('id')->get()->toJson());
    }

    public function test_legacy_liver_template_remains_while_the_missing_no_snacks_variant_is_added(): void
    {
        $this->seedMigrationFixtures();
        $legacy = MealPlanTemplate::where('name', 'Demo — Liver Disease — No Snacks')->firstOrFail();
        $legacy->update(['name' => 'Demo — Liver Disease — Soft Frequent Meals']);
        $standard = MealPlanTemplate::with('days.items')->where('name', 'Demo — Liver Disease — Frequent Meals')->firstOrFail();
        foreach ($legacy->days()->whereIn('meal_type', ['am_snack', 'pm_snack'])->get() as $day) {
            $source = $standard->days->first(fn ($slot) => $slot->day_of_week === $day->day_of_week && $slot->meal_type === $day->meal_type)->items->first();
            $day->items()->create($source->only(['food_item_id', 'recipe_id', 'quantity', 'unit', 'nutrient_snapshot', 'line_order']));
        }
        $before = $legacy->getRawOriginal();
        $snackIds = $legacy->days()->whereIn('meal_type', ['am_snack', 'pm_snack'])->pluck('id');
        $snacks = DB::table('meal_plan_template_items')->whereIn('template_day_id', $snackIds)->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_16_214009_align_canonical_clinical_template_carbs.php');
        $migration->up();
        $this->assertSame($before, $legacy->fresh()->getRawOriginal());
        $this->assertSame($snacks, DB::table('meal_plan_template_items')->whereIn('template_day_id', $snackIds)->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('meal_plan_templates', ['name' => 'Demo — Liver Disease — No Snacks']);
        $new = MealPlanTemplate::with('days.items.recipe')->where('name', 'Demo — Liver Disease — No Snacks')->firstOrFail();
        $this->assertSame(0, $new->days->whereIn('meal_type', ['am_snack', 'pm_snack'])->flatMap->items->count());
        $this->assertCount(21, $new->days->flatMap->items->filter(fn ($item) => $item->recipe?->category === 'carb'));
        $this->assertCount(21, $legacy->fresh()->load('days.items.recipe')->days->flatMap->items->filter(fn ($item) => $item->recipe?->category === 'carb'));
        $this->assertSame(3, MealPlanTemplate::count());
        $migration->up();
        $this->assertSame(3, MealPlanTemplate::count());
    }

    public function test_recipe_seeder_uses_menu_taxonomy_and_preserves_existing_recipe_ids(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);

        $rnd = User::query()->where('role', 'RND')->firstOrFail();
        $legacyRice = Recipe::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Plain White Rice Meal',
            'category' => 'carb',
            'servings' => 1,
            'prep_notes' => 'Keep linked recipe identity.',
        ]);
        $manual = Recipe::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'Manual RND recipe',
            'category' => 'meal',
            'servings' => 1,
            'prep_notes' => 'Preserve manual values.',
        ]);

        $this->seed(RecipeSeeder::class);

        $expectedCategories = [
            'Chopsuey' => 'meal',
            'Ginisang Sayote' => 'meal',
            'Cooked White Rice' => 'carb',
            'Boiled Sweet Potato' => 'carb',
            'Cooked Cassava' => 'carb',
            'Boiled Corn' => 'carb',
            'Saging' => 'snack',
            'Papaya' => 'snack',
        ];
        foreach ($expectedCategories as $name => $category) {
            $this->assertDatabaseHas('recipes', ['name' => $name, 'category' => $category]);
        }

        foreach ([
            'Cooked White Rice' => 'Cooked White Rice', 'Cooked Brown Rice' => 'Cooked Brown Rice',
            'Boiled Corn' => 'Boiled Corn', 'Pechay' => 'Bok Choy / Pechay (Cooked)',
            'Carrots' => 'Carrots (Cooked)', 'Kalabasa' => 'Squash / Kalabasa (Cooked)',
            'Green Beans' => 'Green Beans, Boiled', 'Talong' => 'Eggplant / Talong (Cooked)',
            'Kangkong' => 'Water Spinach (Kangkong)', 'Saging' => 'Banana (Raw)', 'Papaya' => 'Papaya (Raw)',
        ] as $name => $foodName) {
            $source = FoodItem::query()->where('name', $foodName)->firstOrFail();
            $menuItem = Recipe::query()->where('rnd_user_id', $rnd->id)->where('name', $name)
                ->with('ingredients')->firstOrFail();

            $this->assertCount(1, $menuItem->ingredients, $name);
            $this->assertSame($source->serving_unit, $menuItem->ingredients->first()->unit, $name);
            $this->assertEquals((float) $source->serving_size * $menuItem->servings, (float) $menuItem->ingredients->first()->quantity, $name);
            $this->assertEquals((float) $source->serving_size, (float) $menuItem->prepared_portion_amount, $name);
            $this->assertSame($source->serving_unit, $menuItem->prepared_portion_unit, $name);
        }

        $this->assertSame([], Recipe::query()->where('rnd_user_id', $rnd->id)
            ->whereNotIn('category', ['meal', 'carb', 'snack'])
            ->pluck('name')->all());

        $seededIds = Recipe::query()->whereIn('name', array_keys($expectedCategories))->orderBy('name')->pluck('id')->all();
        $this->seed(RecipeSeeder::class);

        $this->assertSame($seededIds, Recipe::query()
            ->whereIn('name', array_keys($expectedCategories))
            ->orderBy('name')
            ->pluck('id')
            ->all());
        $this->assertSame($legacyRice->id, Recipe::query()->where('name', $legacyRice->name)->value('id'));
        $this->assertSame($manual->id, Recipe::query()->where('name', $manual->name)->value('id'));
        $this->assertSame('Preserve manual values.', $manual->fresh()->prep_notes);
    }

    public function test_it_seeds_goal_specific_custom_and_maternal_templates_with_separate_carb_items(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);

        $rnd = User::query()->where('role', 'RND')->firstOrFail();
        $manual = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $rnd->id,
            'name' => 'My manual template',
            'goal_type' => 'custom',
        ]);

        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $firstIds = MealPlanTemplate::query()->whereNotNull('seed_key')->orderBy('name')->pluck('id')->all();
        $this->seed(InterventionMealPlanTemplateSeeder::class);
        $secondIds = MealPlanTemplate::query()->whereNotNull('seed_key')->orderBy('name')->pluck('id')->all();

        $this->assertSame($firstIds, $secondIds);
        $this->assertCount(12, $secondIds);
        $this->assertTrue(MealPlanTemplate::query()->whereKey($manual->id)->exists());

        $templates = MealPlanTemplate::query()
            ->whereNotNull('seed_key')
            ->with(['days.items.foodItem', 'days.items.recipe'])
            ->get();

        foreach ($templates as $template) {
            $this->assertCount($template->snacks_hidden ? 21 : 35, $template->days, $template->name);
            $this->assertTrue($template->days->every(
                fn ($day): bool => $day->items->every(fn ($item): bool => $item->nutrient_snapshot !== null
                    && ! empty($item->nutrient_snapshot['recipe_original_ingredients'])
                    && ! empty($item->nutrient_snapshot['recipe_ingredients'])
                    && (float) ($item->nutrient_snapshot['recipe_yield'] ?? 0) > 0
                    && $item->recipe_id !== null
                    && $item->food_item_id === null
                    && $item->unit === 'serving'
                    && in_array($item->recipe?->category, ['meal', 'carb', 'snack'], true))
            ), $template->name);

            $snackItems = $template->days
                ->whereIn('meal_type', ['am_snack', 'pm_snack'])
                ->sum(fn ($day): int => $day->items->count());

            if ($template->snacks_hidden) {
                $this->assertSame(0, $snackItems, $template->name);
            } else {
                $this->assertGreaterThan(0, $snackItems, $template->name);
            }

            $carbIds = [];
            foreach ($template->days->whereIn('meal_type', ['lunch', 'dinner']) as $mainMeal) {
                $carbItems = $mainMeal->items->filter(fn ($item): bool => $item->recipe?->category === 'carb');
                $this->assertCount(1, $carbItems, $template->name.' '.$mainMeal->day_of_week.' '.$mainMeal->meal_type);
                $carbIds[] = $carbItems->sole()->recipe_id;
            }
            $this->assertCount(1, array_unique($carbIds), $template->name.' must use one Carb throughout its main slots.');
        }

        $this->assertTrue($templates->flatMap->days->flatMap->items->contains(
            fn ($item): bool => $item->recipe?->category === 'carb'
                && ! str_contains(strtolower($item->recipe->name), 'rice')
        ), 'At least one template must use a non-rice Carb Menu Item.');

        $this->assertCount(1, $templates->where('goal_type', 'liver_disease'));
        $this->assertFalse($templates->firstWhere('goal_type', 'liver_disease')->snacks_hidden);

        $maternalTemplates = $templates->whereNotNull('maternal_status')->values();
        $this->assertCount(2, $maternalTemplates);
        $this->assertCount(1, $maternalTemplates->where('maternal_status', 'pregnant'));
        $this->assertCount(1, $maternalTemplates->where('maternal_status', 'lactating'));
        $this->assertTrue($maternalTemplates->every(fn ($template) => $template->snacks_hidden));

        foreach ($maternalTemplates as $maternal) {
            $this->assertNull($maternal->goal_type);
            $this->assertStringContainsString('Maternal', $maternal->name);
            $this->assertTrue($maternal->days->whereIn('meal_type', ['lunch', 'dinner'])->every(
                fn ($day): bool => $day->items->contains(fn ($item): bool => $item->recipe?->category === 'meal'
                    && $item->recipe?->component_type === 'side')
            ), $maternal->name.' must retain a Meal Menu Item vegetable at lunch and dinner.');

            foreach ($maternal->days->groupBy('day_of_week') as $dayItems) {
                $items = $dayItems->flatMap->items;
                $requiredMilkServings = $maternal->maternal_status === 'lactating' ? 2 : 1;

                $this->assertGreaterThanOrEqual(
                    $requiredMilkServings,
                    $items->filter(fn ($item): bool => $item->recipe?->name === 'Gatas')->count(),
                    $maternal->name.' '.$dayItems->first()->day_of_week.' needs its maternal milk servings.'
                );
                $this->assertTrue($items->filter(fn ($item): bool => $item->recipe?->name === 'Gatas')->every(
                    fn ($item): bool => $item->unit === 'serving'
                        && $item->quantity == 1
                        && (float) $item->recipe->prepared_portion_amount === 244.0
                ), $maternal->name.' milk quantities must stay on the source measure.');
                $this->assertTrue(
                    $items->contains(fn ($item): bool => in_array($item->recipe?->name, ['Papaya', 'Saging'], true)),
                    $maternal->name.' '.$dayItems->first()->day_of_week.' needs fruit.'
                );
                $this->assertTrue($items->filter(fn ($item): bool => in_array($item->recipe?->name, ['Papaya', 'Saging'], true))->every(
                    fn ($item): bool => $item->unit === 'serving'
                        && $item->quantity == ($item->recipe->name === 'Papaya' ? 90 : 80)
                            / (float) $item->recipe->prepared_portion_amount
                ), $maternal->name.' fruit quantities must stay on the source measure.');
            }

            if ($maternal->snacks_hidden) {
                $this->assertTrue($maternal->days->whereIn('meal_type', ['breakfast', 'lunch', 'dinner'])->flatMap->items
                    ->contains(fn ($item): bool => $item->recipe?->category === 'snack'));
            }
        }

        $customTemplates = $templates->where('goal_type', 'custom');
        $this->assertCount(1, $customTemplates);
        $customMealsMissingVegetables = $customTemplates->flatMap(
            fn ($custom) => $custom->days->whereIn('meal_type', ['lunch', 'dinner'])
                ->filter(fn ($day): bool => ! $day->items->contains(fn ($item): bool => $item->recipe?->category === 'meal'
                    && $item->recipe?->component_type === 'side'))
                ->map(fn ($day): string => $custom->name.' '.$day->day_of_week.'/'.$day->meal_type.' ['
                    .$day->items->map(fn ($item): string => ($item->recipe?->name ?? 'missing recipe').'='.($item->recipe?->category ?? 'missing category'))->implode(', ')
                    .']')
        );
        $this->assertCount(0, $customMealsMissingVegetables, 'Custom templates must use Meal Menu Item vegetables: '.$customMealsMissingVegetables->implode('; '));
    }

    private function seedMigrationFixtures(): void
    {
        $this->seed(AdminUserSeeder::class);
        $owner = User::where('role', 'RND')->firstOrFail();
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        foreach ([
            'Steamed White Rice' => 'carb', 'Sweet Potato / Kamote' => 'carb',
            'Lugaw with Chicken (Soft Diet)' => 'meal', 'Oatmeal with Banana' => 'meal',
            'Arroz Caldo (Chicken Rice Soup)' => 'meal', 'Boiled Chicken Breast' => 'meal',
            'Steamed Tilapia' => 'meal', 'Plain Ginisang Monggo' => 'meal',
            'Tinolang Manok (Chicken Ginger Soup)' => 'meal', 'Crackers and Milk Snack' => 'snack', 'Papaya Snack' => 'snack',
        ] as $name => $category) {
            $recipe = Recipe::factory()->create(['rnd_user_id' => $owner->id, 'name' => $name, 'category' => $category,
                'servings' => 1, 'prepared_portion_amount' => 100, 'prepared_portion_unit' => 'g']);
            $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
            $recipe->recalculateTotals();
        }
        $main = Recipe::where('name', 'Boiled Chicken Breast')->firstOrFail();
        $carb = Recipe::where('name', 'Steamed White Rice')->firstOrFail();
        foreach (['Frequent Meals', 'No Snacks'] as $variant) {
            $template = MealPlanTemplate::create(['rnd_user_id' => $owner->id, 'name' => 'Demo — Liver Disease — '.$variant,
                'goal_type' => 'liver_disease', 'disease_stage' => 'decompensated']);
            foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName) {
                foreach (['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'] as $slot) {
                    $day = $template->days()->create(['day_of_week' => $dayName, 'meal_type' => $slot, 'recipe_id' => $main->id, 'quantity' => 1, 'unit' => 'serving']);
                    if (! str_contains($slot, 'snack')) {
                        foreach ([$main, $carb] as $index => $recipe) {
                            $day->items()->create(['recipe_id' => $recipe->id, 'quantity' => 1, 'unit' => 'serving',
                                'nutrient_snapshot' => $recipe->planSnapshot(), 'line_order' => $index + 1]);
                        }
                    } elseif ($variant === 'Frequent Meals') {
                        $snack = Recipe::where('category', 'snack')->firstOrFail();
                        $day->items()->create(['recipe_id' => $snack->id, 'quantity' => 1, 'unit' => 'serving',
                            'nutrient_snapshot' => $snack->planSnapshot(), 'line_order' => 1]);
                    }
                }
            }
        }
    }
}
