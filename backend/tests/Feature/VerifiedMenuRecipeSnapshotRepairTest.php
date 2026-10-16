<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VerifiedMenuRecipeSnapshotRepairTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('preparedMeasures')]
    public function test_repair_freezes_verified_batches_preserves_saved_edits_and_is_repeatable(string $unit, float $reference, float $quantity, float $servings): void
    {
        Storage::fake('report_cache');
        Storage::disk('report_cache')->put('original.pdf', 'unchanged fictional archive');
        $rnd = User::factory()->rnd()->create();
        $food = FoodItem::factory()->create(['name' => 'Measured rice', 'serving_size' => 100, 'serving_unit' => 'g', 'calories' => 200]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $rnd->id, 'servings' => 2, 'prepared_portion_amount' => $reference, 'prepared_portion_unit' => $unit]);
        $ingredient = $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 500, 'unit' => 'g']);
        $plan = MealPlan::factory()->create();
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id, 'recipe_id' => $recipe->id, 'food_item_id' => null, 'quantity' => $quantity, 'unit' => $unit,
            'nutrient_snapshot' => ['name' => $recipe->name, 'serving_size' => $reference, 'serving_unit' => $unit, 'calories' => 0,
                'recipe_ingredients' => [['id' => $ingredient->id, 'name' => $food->name, 'quantity' => 400, 'unit' => 'g']]],
        ]);
        $frozen = MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'recipe_id' => $recipe->id, 'food_item_id' => null, 'unit' => 'serving', 'nutrient_snapshot' => $recipe->planSnapshot()]);
        $direct = MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'recipe_id' => null, 'food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $template = MealPlanTemplate::create(['rnd_user_id' => $rnd->id, 'name' => 'Verified template']);
        $slot = $template->days()->create(['day_of_week' => 'Monday', 'meal_type' => 'breakfast', 'quantity' => 1, 'unit' => 'serving']);
        $line = $slot->items()->create(['recipe_id' => $recipe->id, 'quantity' => 1.5, 'unit' => 'serving', 'line_order' => 2, 'nutrient_snapshot' => ['name' => $recipe->name, 'serving_size' => 1, 'serving_unit' => 'serving']]);
        $before = $item->fresh()->getRawOriginal();
        $frozenBefore = $frozen->fresh()->getRawOriginal();
        $directBefore = $direct->fresh()->getRawOriginal();
        $lineBefore = $line->fresh()->getRawOriginal();
        $auditCount = DB::table('activity_log')->count();
        $migration = require database_path('migrations/2026_10_16_111556_repair_verified_menu_recipe_snapshots.php');
        $migration->up();

        $item->refresh();
        $line->refresh();
        $this->assertEquals($servings, (float) $item->quantity);
        $this->assertSame('serving', $item->unit);
        $this->assertSame(2.0, (float) $item->nutrient_snapshot['recipe_yield']);
        $this->assertSame(500.0, (float) $item->nutrient_snapshot['recipe_original_ingredients'][0]['quantity']);
        $this->assertSame(400.0, (float) $item->nutrient_snapshot['recipe_ingredients'][0]['quantity']);
        $this->assertSame(400.0, (float) $item->nutrient_snapshot['calories']);
        $this->assertSame('verified_catalog_repair', $item->nutrient_snapshot['recipe_snapshot_source']);
        $this->assertNotEmpty($line->nutrient_snapshot['recipe_original_ingredients']);
        $this->assertSame(Arr::except($before, ['quantity', 'unit', 'nutrient_snapshot']), Arr::except($item->getRawOriginal(), ['quantity', 'unit', 'nutrient_snapshot']));
        $this->assertSame(Arr::except($lineBefore, ['nutrient_snapshot']), Arr::except($line->getRawOriginal(), ['nutrient_snapshot']));
        $this->assertSame($frozenBefore, $frozen->fresh()->getRawOriginal());
        $this->assertSame($directBefore, $direct->fresh()->getRawOriginal());
        $first = $item->getRawOriginal();
        $firstLine = $line->getRawOriginal();
        $migration->up();
        $this->assertSame($first, $item->fresh()->getRawOriginal());
        $this->assertSame($firstLine, $line->fresh()->getRawOriginal());
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertSame('unchanged fictional archive', Storage::disk('report_cache')->get('original.pdf'));
    }

    public static function preparedMeasures(): array
    {
        return [['g', 125.0, 250.0, 2.0], ['cup', 1.5, 4.0, 2.67]];
    }

    public function test_five_serving_meal_batches_preserve_saved_snapshots_and_per_serving_nutrients(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g', 'calories' => 100, 'water_g' => 70]);
        foreach (['meal', 'carb', 'snack'] as $category) {
            $recipe = Recipe::factory()->create(['category' => $category, 'servings' => 2]);
            $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 250, 'unit' => 'g']);
            $recipe->recalculateTotals();
        }
        $meal = Recipe::where('category', 'meal')->firstOrFail();
        $snapshot = $meal->planSnapshot();
        $day = MealPlanDay::factory()->create(['day_of_week' => 'Monday', 'meal_type' => 'breakfast']);
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'recipe_id' => $meal->id, 'food_item_id' => null, 'quantity' => 1.2, 'unit' => 'serving', 'nutrient_snapshot' => $snapshot]);
        $report = new Report(['type' => 'patient_menu_plan', 'parameters' => ['meal_plan_id' => $day->mealPlan->uuid]]);
        $generator = app(PatientMenuPlanGenerator::class);
        $originalPortions = $generator->data($report)['portion_details'];
        $saved = $item->fresh()->getRawOriginal();
        $others = Recipe::whereIn('category', ['carb', 'snack'])->get()->map->getRawOriginal()->all();
        $migration = require database_path('migrations/2026_10_16_120000_normalize_existing_meal_recipe_batches.php');
        $migration->up();
        $meal->refresh();
        $this->assertSame(5, (int) $meal->servings);
        $this->assertSame(625.0, (float) $meal->ingredients()->first()->quantity);
        $this->assertSame($snapshot['calories'], $meal->planSnapshot()['calories']);
        $this->assertSame($snapshot['water_g'], $meal->planSnapshot()['water_g']);
        $this->assertSame($saved, $item->fresh()->getRawOriginal());
        $this->assertSame($originalPortions, $generator->data($report)['portion_details']);
        $this->assertSame($others, Recipe::whereIn('category', ['carb', 'snack'])->get()->map->getRawOriginal()->all());
        $first = $meal->getRawOriginal();
        $migration->up();
        $this->assertSame($first, $meal->fresh()->getRawOriginal());
    }

    public function test_batch_normalization_stops_before_changing_catalog_when_saved_details_are_missing(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $meal = Recipe::factory()->create(['category' => 'meal', 'servings' => 1]);
        $meal->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        MealPlanItem::factory()->create(['recipe_id' => $meal->id, 'nutrient_snapshot' => ['calories' => 100]]);
        $before = $meal->fresh()->getRawOriginal();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Repair saved recipe snapshots before resizing Meal batches.');
        try {
            (require database_path('migrations/2026_10_16_120000_normalize_existing_meal_recipe_batches.php'))->up();
        } finally {
            $this->assertSame($before, $meal->fresh()->getRawOriginal());
            $this->assertSame(100.0, (float) $meal->ingredients()->first()->quantity);
        }
    }

    public function test_unmatched_reference_stops_the_repair_without_partial_writes(): void
    {
        $food = FoodItem::factory()->create(['serving_size' => 100, 'serving_unit' => 'g']);
        $recipe = Recipe::factory()->create(['servings' => 1, 'prepared_portion_amount' => 125, 'prepared_portion_unit' => 'g']);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 125, 'unit' => 'g']);
        $day = MealPlanDay::factory()->create();
        foreach ([125, 150] as $reference) {
            MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'recipe_id' => $recipe->id, 'food_item_id' => null, 'quantity' => 250, 'unit' => 'g', 'nutrient_snapshot' => ['serving_size' => $reference, 'serving_unit' => 'g']]);
        }
        $before = DB::table('meal_plan_items')->orderBy('id')->get()->toJson();
        $this->expectException(\RuntimeException::class);
        $migration = require database_path('migrations/2026_10_16_111556_repair_verified_menu_recipe_snapshots.php');
        try {
            $migration->up();
        } finally {
            $this->assertSame($before, DB::table('meal_plan_items')->orderBy('id')->get()->toJson());
        }
    }
}
