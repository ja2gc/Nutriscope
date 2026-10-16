<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\MealPlanTemplate;
use App\Models\MealPlanTemplateDay;
use App\Models\MealPlanTemplateItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MealPlanService;
use Database\Seeders\FoodItemsSeeder;
use Database\Seeders\InterventionMealPlanTemplateSeeder;
use Database\Seeders\RecipeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MealPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeInterventionWithNcpRecord(array $interventionAttributes = []): array
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 55,
            'fluid_ml' => 2000,
            ...$interventionAttributes,
        ]);

        return [$ncpRecord, $intervention, $patient];
    }

    private function seedRecipes(int $count = 10): void
    {
        $foods = FoodItem::factory(5)->create([
            'category' => 'fruit',
            'ready_to_eat' => true,
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);
        Recipe::factory($count)->create(['rnd_user_id' => $this->rnd->id])
            ->each(fn (Recipe $recipe) => $recipe->ingredients()->create([
                'food_item_id' => $foods->first()->id,
                'quantity' => 100,
                'unit' => 'g',
            ]));
        foreach ($foods as $food) {
            $snack = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'category' => 'snack', 'servings' => 1]);
            $snack->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
            $snack->recalculateTotals();
        }
    }

    private function measuredTemplate(string $unit = 'serving'): array
    {
        $food = FoodItem::factory()->create(['calories' => 400, 'protein' => 20, 'carbs' => 40, 'fat' => 10, 'allergens' => []]);
        $recipe = Recipe::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'category' => 'snack', 'servings' => 2,
            'prepared_portion_amount' => 1, 'prepared_portion_unit' => 'cup',
        ]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $template = MealPlanTemplate::forceCreate(['rnd_user_id' => $this->rnd->id, 'name' => 'Measured template']);
        $day = MealPlanTemplateDay::forceCreate(['template_id' => $template->id, 'day_of_week' => 'Monday', 'meal_type' => 'breakfast']);
        $line = MealPlanTemplateItem::forceCreate([
            'template_day_id' => $day->id, 'recipe_id' => $recipe->id, 'quantity' => 2, 'unit' => $unit,
            'nutrient_snapshot' => ['name' => 'Frozen measured Snack', 'calories' => 200, 'protein' => 10, 'carbs' => 20, 'fat' => 5, 'serving_size' => 1, 'serving_unit' => 'serving', 'micronutrients' => []],
            'line_order' => 0,
        ]);

        return [$template, $line, $recipe];
    }

    public function test_template_preview_converts_measured_legacy_portions_without_persisting_a_plan(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        [$template] = $this->measuredTemplate('cup');
        $before = $intervention->fresh()->getAttributes();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12', 'preview' => true,
        ])->assertOk()->assertJsonPath('meta.preview', true)->assertJsonCount(35, 'data.days')
            ->assertJsonPath('items.0.quantity', '2.00')->assertJsonPath('items.0.unit', 'serving')
            ->assertJsonPath('items.0.nutrient_snapshot.name', 'Frozen measured Snack');
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
        $this->assertSame($before, $intervention->fresh()->getAttributes());
    }

    public function test_template_load_freezes_and_labels_current_recipe_fallback_for_legacy_snapshots(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        [$template, $source, $recipe] = $this->measuredTemplate();
        $response = $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12',
        ])->assertCreated();
        $response->assertJsonPath('items.0.nutrient_snapshot.recipe_yield', 2)
            ->assertJsonPath('items.0.nutrient_snapshot.recipe_ingredients.0.quantity', 100)
            ->assertJsonPath('items.0.nutrient_snapshot.recipe_snapshot_source', 'current_recipe_fallback')
            ->assertJsonPath('items.0.nutrient_snapshot.calories', 200)
            ->assertJsonPath('items.0.nutrient_snapshot.name', 'Frozen measured Snack');
        $before = MealPlanItem::sole()->nutrient_snapshot;
        $recipe->update(['servings' => 8]);
        $recipe->ingredients()->update(['quantity' => 999]);
        $this->assertSame($before, MealPlanItem::sole()->fresh()->nutrient_snapshot);
        $this->assertArrayNotHasKey('recipe_yield', $source->fresh()->nutrient_snapshot);
    }

    public function test_template_load_rejects_missing_or_incompatible_prepared_portion_bases(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        [$template, , $recipe] = $this->measuredTemplate('cup');
        $recipe->update(['prepared_portion_amount' => null, 'prepared_portion_unit' => null]);
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12', 'preview' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('template');
        $recipe->update(['prepared_portion_amount' => 100, 'prepared_portion_unit' => 'g']);
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12', 'preview' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('template');
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_template_save_replaces_the_existing_week_atomically_without_a_second_plan(): void
    {
        [$ncp, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        [$template, $line, $recipe] = $this->measuredTemplate();
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $patient->id]);
        $oldDay = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        MealPlanItem::factory()->create(['meal_plan_day_id' => $oldDay->id]);
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'meal_plan_id' => $plan->uuid, 'week_start_date' => '2026-10-12',
            'lines' => [['source_template_item_id' => $line->uuid, 'recipe_id' => $recipe->uuid, 'day_of_week' => 'Monday', 'meal_type' => 'breakfast', 'quantity' => 1.2, 'unit' => 'serving']],
        ])->assertOk()->assertJsonPath('data.id', $plan->uuid);
        $this->assertDatabaseCount('meal_plans', 1);
        $this->assertCount(35, $plan->fresh()->days);
        $items = $plan->fresh('days.items')->days->flatMap->items;
        $this->assertCount(1, $items);
        $this->assertSame('1.20', $items->sole()->quantity);
        $sourceSnapshot = $line->fresh()->nutrient_snapshot;
        $this->assertSame($sourceSnapshot, array_intersect_key($items->sole()->nutrient_snapshot, $sourceSnapshot));
        $this->assertSame('current_recipe_fallback', $items->sole()->nutrient_snapshot['recipe_snapshot_source']);
        $this->assertTrue($plan->fresh()->needs_rescaling);
    }

    public function test_template_replacement_rejects_a_historical_intervention_week(): void
    {
        [$ncp, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        [$template] = $this->measuredTemplate();
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $patient->id]);
        Intervention::factory()->create(['ncp_record_id' => $ncp->id, 'created_at' => now()->addMinute()]);
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'meal_plan_id' => $plan->uuid, 'week_start_date' => '2026-10-12',
        ])->assertForbidden();
        $this->assertDatabaseCount('meal_plans', 1);
    }

    public function test_historical_week_rejects_all_plan_and_item_mutations(): void
    {
        [$ncp, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        [, , $recipe] = $this->measuredTemplate();
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $patient->id, 'status' => 'draft', 'needs_rescaling' => true]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'recipe_id' => $recipe->id, 'quantity' => 1.5, 'unit' => 'serving']);
        Intervention::factory()->create(['ncp_record_id' => $ncp->id, 'created_at' => now()->addMinute()]);
        $base = "/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$plan->uuid}";
        $this->actingAs($this->rnd)->patchJson($base, ['status' => 'active'])->assertForbidden();
        $this->postJson($base.'/scale-to-prescription')->assertForbidden();
        $this->postJson($base."/days/{$day->uuid}/items", ['recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving'])->assertForbidden();
        $this->patchJson($base."/days/{$day->uuid}/items/{$item->uuid}", ['quantity' => 2])->assertForbidden();
        $this->deleteJson($base."/days/{$day->uuid}/items/{$item->uuid}")->assertForbidden();
        $this->deleteJson($base)->assertForbidden();
        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertSame('1.50', $item->fresh()->quantity);
        $this->assertDatabaseCount('meal_plans', 1);
        $this->assertDatabaseCount('meal_plan_items', 1);
    }

    public function test_template_preview_scaling_uses_the_existing_algorithm_without_saving_rows(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord([
            'energy_kcal' => 240, 'protein_g' => 12, 'carbs_g' => 24, 'fat_g' => 6,
        ]);
        [$template] = $this->measuredTemplate();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12', 'preview' => true, 'scale' => true,
        ])->assertOk()->assertJsonPath('data.scale_status', 'already_scaled')->assertJsonPath('items.0.quantity', '1.20');
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
        $this->assertSame('240.00', $intervention->fresh()->energy_kcal);
    }

    public function test_template_draft_ingredient_edits_use_measured_units_without_mutating_the_library(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        [$template, $line, $recipe] = $this->measuredTemplate();
        $ingredient = $recipe->ingredients()->firstOrFail();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-10-12', 'preview' => true,
            'lines' => [[
                'source_template_item_id' => $line->uuid, 'recipe_id' => $recipe->uuid,
                'day_of_week' => 'Monday', 'meal_type' => 'breakfast', 'quantity' => 1.2, 'unit' => 'serving',
                'ingredient_overrides' => [['id' => $ingredient->id, 'quantity' => 200]],
            ]],
        ])->assertOk()->assertJsonPath('items.0.nutrient_snapshot.calories', 400)
            ->assertJsonPath('items.0.nutrient_snapshot.name', 'Frozen measured Snack')
            ->assertJsonPath('items.0.nutrient_snapshot.recipe_snapshot_source', 'current_recipe_fallback')
            ->assertJsonPath('items.0.nutrient_snapshot.recipe_yield', 2)
            ->assertJsonPath('items.0.nutrient_snapshot.recipe_ingredients.0.quantity', 200);
        $this->assertSame('100.000000', $ingredient->fresh()->quantity);
        $this->assertSame(200, $line->fresh()->nutrient_snapshot['calories']);
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_failed_template_replacement_restores_the_original_week_and_items(): void
    {
        [$ncp, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        [$template] = $this->measuredTemplate();
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $patient->id]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $day->id, 'quantity' => 1.5]);
        $this->partialMock(MealPlanService::class, function ($mock): void {
            $mock->shouldReceive('scaleToPrescription')->once()->andThrow(new \RuntimeException('Injected replacement failure.'));
        });
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid, 'intervention_plan_id' => $intervention->uuid,
            'meal_plan_id' => $plan->uuid, 'week_start_date' => '2026-10-12', 'scale' => true,
        ])->assertStatus(500);
        $this->assertDatabaseCount('meal_plans', 1);
        $this->assertDatabaseCount('meal_plan_days', 1);
        $this->assertDatabaseCount('meal_plan_items', 1);
        $this->assertSame($day->id, $item->fresh()->meal_plan_day_id);
        $this->assertSame('1.50', $item->fresh()->quantity);
    }

    // --- MealPlan CRUD ---

    public function test_generation_requires_a_public_carb_menu_item_unless_carbs_are_excluded(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        $this->seedRecipes();
        $meal = Recipe::query()->firstOrFail();
        foreach ([null, $meal->uuid, (string) $meal->id, '00000000-0000-4000-8000-000000000000'] as $choice) {
            $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", [
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => now()->startOfWeek()->toDateString(),
                'exclude_carbs' => false,
                'carb_menu_item_id' => $choice,
            ])->assertUnprocessable()->assertJsonValidationErrors('carb_menu_item_id')
                ->assertJsonPath('errors.carb_menu_item_id.0', 'Choose a valid Carb Menu Item or select Exclude carbs.');
        }
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", [
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'exclude_carbs' => true,
            'carb_menu_item_id' => $meal->uuid,
        ])->assertUnprocessable()->assertJsonValidationErrors('carb_menu_item_id')
            ->assertJsonPath('errors.carb_menu_item_id.0', 'Clear the Carb selection when Exclude carbs is selected.');
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_new_generation_requests_reject_the_retired_rice_switch(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        $this->seedRecipes();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", [
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'exclude_carbs' => true,
            'use_rice_as_carb' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('use_rice_as_carb');
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_generation_uses_the_selected_public_carb_uuid_for_every_main_slot(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord(['goal_type' => 'liver_disease']);
        $this->seedRecipes();
        $carb = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'category' => 'carb', 'servings' => 1]);
        $food = FoodItem::factory()->create(['allergens' => []]);
        $carb->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $carb->recalculateTotals();
        $response = $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", [
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'exclude_snacks' => true,
            'exclude_carbs' => false,
            'carb_menu_item_id' => $carb->uuid,
        ])->assertCreated();
        $slots = collect($response->json('data.days'));
        $this->assertCount(35, $slots);
        foreach ($slots as $slot) {
            $items = MealPlanDay::where('uuid', $slot['id'])->firstOrFail()->items;
            if (in_array($slot['meal_type'], ['am_snack', 'pm_snack'], true)) {
                $this->assertCount(0, $items);
            } else {
                $this->assertCount(2, $items);
                $this->assertCount(1, $items->where('recipe_id', $carb->id));
                $this->assertGreaterThan(0, (float) $items->firstWhere('recipe_id', $carb->id)->quantity);
                foreach ($items as $item) {
                    $this->assertGreaterThan(0, $item->nutrient_snapshot['recipe_yield'] ?? 0);
                    $this->assertNotEmpty($item->nutrient_snapshot['recipe_original_ingredients'] ?? []);
                    $this->assertNotEmpty($item->nutrient_snapshot['recipe_ingredients'] ?? []);
                }
            }
        }
    }

    public function test_generation_rejects_measured_carb_and_allergy_errors_without_writes(): void
    {
        [$ncp, $intervention] = $this->makeInterventionWithNcpRecord();
        $this->seedRecipes();
        $carb = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'category' => 'carb', 'servings' => 1]);
        $food = FoodItem::factory()->create(['allergens' => []]);
        $ingredient = $carb->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'mL']);
        $payload = [
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'exclude_carbs' => false,
            'carb_menu_item_id' => $carb->uuid,
        ];
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", $payload)
            ->assertUnprocessable()->assertJsonPath('invalid_carb', true);
        $ingredient->update(['unit' => 'g']);
        $food->update(['allergens' => ['peanuts']]);
        Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'allergies' => ['peanuts']]);
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/generate", $payload)
            ->assertUnprocessable()->assertJsonPath('invalid_carb', true);
        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }

    public function test_new_meal_plan_requires_explicit_owned_intervention_plan(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $otherNcp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        $foreignPlan = Intervention::factory()->create(['ncp_record_id' => $otherNcp->id]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'week_start_date' => '2026-06-09',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('intervention_plan_id');

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
            'intervention_plan_id' => $foreignPlan->uuid,
            'week_start_date' => '2026-06-09',
        ])->assertNotFound();

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-06-09',
        ])->assertCreated();
    }

    public function test_store_meal_plan_manually(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-09',
                'generation_type' => 'manual',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.generation_type', 'manual')
            ->assertJsonPath('data.intervention_plan_id', $intervention->uuid)
            ->assertJsonMissingPath('data.intervention_id')
            ->assertJsonMissingPath('data.patient_id');

        $this->assertDatabaseHas('meal_plans', [
            'intervention_id' => $intervention->id,
            'generation_type' => 'manual',
        ]);
        $mealPlan = MealPlan::where('intervention_id', $intervention->id)->firstOrFail();
        $this->assertNull($mealPlan->intervention_revision_id);
        $response->assertJsonMissingPath('data.revision');
    }

    public function test_each_saved_intervention_retains_its_own_single_weekly_menu(): void
    {
        [$ncpRecord, $first] = $this->makeInterventionWithNcpRecord();
        $url = "/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans";
        $firstMenu = $this->actingAs($this->rnd)->postJson($url, [
            'intervention_plan_id' => $first->uuid, 'week_start_date' => '2026-10-05',
        ])->assertCreated()->json('data.id');
        $second = Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);
        $secondMenu = $this->postJson($url, [
            'intervention_plan_id' => $second->uuid, 'week_start_date' => '2026-10-12',
        ])->assertCreated()->json('data.id');
        $this->assertNotSame($firstMenu, $secondMenu);
        $this->postJson($url, [
            'intervention_plan_id' => $second->uuid, 'week_start_date' => '2026-10-19',
        ])->assertConflict();
        $this->getJson("{$url}/{$firstMenu}")->assertOk()->assertJsonPath('data.intervention_plan_id', $first->uuid);
        $this->getJson("{$url}/{$secondMenu}")->assertOk()->assertJsonPath('data.intervention_plan_id', $second->uuid);
        $this->assertDatabaseCount('meal_plans', 2);
    }

    public function test_second_meal_plan_for_same_intervention_returns_conflict(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-09',
                'generation_type' => 'manual',
            ])
            ->assertCreated();

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-16',
                'generation_type' => 'manual',
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'This Intervention Plan already has a menu plan.');

        $this->assertSame(1, MealPlan::where('intervention_id', $intervention->id)->count());
    }

    public function test_show_meal_plan_with_days(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $mealPlan->uuid)
            ->assertJsonStructure(['data' => ['id', 'week_start_date', 'generation_type', 'days']]);
    }

    public function test_index_meal_plans_for_ncp_record(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        Intervention::factory(3)->create(['ncp_record_id' => $ncpRecord->id])
            ->each(fn (Intervention $plan) => MealPlan::factory()->create([
                'intervention_id' => $plan->id,
                'patient_id' => $patient->id,
            ]));

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_meal_plan_status(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->rnd)
            ->patchJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$mealPlan->uuid}", [
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('meal_plans', ['id' => $mealPlan->id, 'status' => 'active']);
    }

    public function test_store_meal_plan_requires_week_start_date(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans", [
                'intervention_plan_id' => $intervention->uuid,
                'generation_type' => 'manual',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    // --- AI auto-generate ---

    public function test_generate_meal_plan_auto_creates_7_days(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $this->seedRecipes(15);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'exclude_carbs' => true,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-09',
                'conditions' => ['DM'],
                'allergens' => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.generation_type', 'auto')
            ->assertJsonStructure(['data' => ['id', 'days']]);

        $days = $response->json('data.days');
        $this->assertIsArray($days);
        $this->assertCount(35, $days); // 7 days × 5 meal types
        foreach ($days as $day) {
            $this->assertArrayNotHasKey('water', $day['variance'] ?? []);
        }

        $mealPlan = MealPlan::where('intervention_id', $intervention->id)->first();
        $this->assertNotNull($mealPlan);
        $this->assertNull($mealPlan->intervention_revision_id);
        $this->assertSame(1, Activity::where('event', 'created')
            ->whereJsonContains('properties->details->subject_label', 'Meal plan generated')
            ->count());
    }

    public function test_generate_meal_plan_requires_week_start_date(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'exclude_carbs' => true,
                'intervention_plan_id' => $intervention->uuid,
                'conditions' => ['DM'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['week_start_date']);
    }

    public function test_generate_meal_plan_rejects_non_boolean_carb_exclusion(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-09',
                'exclude_carbs' => 'sometimes',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['exclude_carbs']);
    }

    public function test_cannot_generate_meal_plan_without_intervention(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'exclude_carbs' => true,
                'week_start_date' => '2026-06-09',
                'conditions' => ['DM'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('intervention_plan_id');
        $this->assertSame(0, Activity::where('event', 'created')
            ->whereJsonContains('properties->details->subject_label', 'Meal plan generated')
            ->count());
    }

    public function test_cannot_generate_meal_plan_without_complete_prescription(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        // Intervention exists but has no prescription targets.
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'goal_type' => 'renal_diet',
            'energy_kcal' => null,
            'protein_g' => null,
            'carbs_g' => null,
            'fat_g' => null,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'exclude_carbs' => true,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-06-09',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['intervention']]);
    }

    public function test_rnd_can_delete_meal_plan(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->deleteJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plans', ['id' => $plan->id]);
    }

    // ─── Template tests ────────────────────────────────────────────────────────

    public function test_rnd_can_save_meal_plan_as_template(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/save-template", [
                'name' => 'CKD Stage 4 — Week A',
                'goal_type' => 'renal_diet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CKD Stage 4 — Week A');

        $this->assertDatabaseHas('meal_plan_templates', ['name' => 'CKD Stage 4 — Week A']);
    }

    public function test_saved_template_records_confirmed_maternal_context(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $ncpRecord->assessment()->create(['pregnancy_lactation_status' => 'pregnant_t2']);
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/save-template", [
                'name' => 'Pregnancy pattern',
            ])
            ->assertCreated()
            ->assertJsonPath('data.maternal_status', 'pregnant');

        $this->assertDatabaseHas('meal_plan_templates', [
            'name' => 'Pregnancy pattern',
            'maternal_status' => 'pregnant',
        ]);
    }

    public function test_saving_template_preserves_every_item_and_its_snapshot(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);
        $day = MealPlanDay::factory()->create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        $rice = FoodItem::factory()->create();
        $fish = FoodItem::factory()->create();
        $riceSnapshot = ['name' => 'Cooked rice', 'calories' => 205.5, 'protein' => 4.3];
        $fishSnapshot = ['name' => 'Paksiw na bangus', 'calories' => 220.5, 'protein' => 27.5];

        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'food_item_id' => $rice->id,
            'quantity' => 1.25,
            'unit' => 'cup',
            'nutrient_snapshot' => $riceSnapshot,
        ]);
        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'food_item_id' => $fish->id,
            'quantity' => 1,
            'unit' => 'serving',
            'nutrient_snapshot' => $fishSnapshot,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/save-template", [
                'name' => 'Two-item lunch',
                'goal_type' => 'renal_diet',
                'disease_stage' => 'stage_4',
            ])
            ->assertCreated();

        $template = MealPlanTemplate::where('uuid', $response->json('data.id'))->sole();
        $templateDay = $template->days()->where('day_of_week', 'Monday')->where('meal_type', 'lunch')->sole();
        $items = MealPlanTemplateItem::where('template_day_id', $templateDay->id)->orderBy('line_order')->get();

        $this->assertCount(2, $items);
        $this->assertSame(['1.25', '1.00'], $items->pluck('quantity')->all());
        $this->assertSame(['cup', 'serving'], $items->pluck('unit')->all());
        $this->assertEquals($riceSnapshot, $items[0]->nutrient_snapshot);
        $this->assertEquals($fishSnapshot, $items[1]->nutrient_snapshot);
        $this->assertSame('stage_4', $template->disease_stage);
    }

    public function test_rnd_can_list_templates(): void
    {
        MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Template A', 'goal_type' => 'renal_diet',
        ]);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/meal-plan-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rnd_can_create_and_edit_an_owned_template_from_food_library(): void
    {
        $food = FoodItem::factory()->create(['name' => 'Apple', 'calories' => 95]);
        $snack = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'name' => 'Apple', 'category' => 'snack', 'servings' => 1]);
        $snack->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $snack->recalculateTotals();
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'name' => 'Vegetable soup', 'servings' => 1]);
        $vegetable = FoodItem::factory()->create(['water_g' => 90]);
        $recipe->ingredients()->create(['food_item_id' => $vegetable->id, 'quantity' => 200, 'unit' => 'g']);
        $recipe->recalculateTotals();
        $created = $this->actingAs($this->rnd)->postJson('/api/rnd/meal-plan-templates', [
            'name' => 'Simple week',
            'lines' => [
                ['day_of_week' => 'Monday', 'meal_type' => 'breakfast', 'recipe_id' => $snack->uuid, 'quantity' => 1, 'unit' => 'serving'],
                ['day_of_week' => 'Monday', 'meal_type' => 'breakfast', 'recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving'],
            ],
        ])->assertCreated();
        $template = MealPlanTemplate::where('uuid', $created->json('data.id'))->firstOrFail();
        $this->assertSame($this->rnd->id, $template->rnd_user_id);
        $this->assertCount(2, $template->days()->firstOrFail()->items);
        $this->assertSame(['Apple', 'Vegetable soup'], $template->days()->firstOrFail()->items->pluck('nutrient_snapshot.name')->all());
        $this->assertEquals(180.0, $template->days()->firstOrFail()->items->last()->nutrient_snapshot['water_g']);

        $this->putJson("/api/rnd/meal-plan-templates/{$template->uuid}", [
            'name' => 'Simple week revised',
            'lines' => [
                ['day_of_week' => 'Tuesday', 'meal_type' => 'lunch', 'recipe_id' => $snack->uuid, 'quantity' => 2, 'unit' => 'serving'],
            ],
        ])->assertOk()->assertJsonPath('data.name', 'Simple week revised');
        $this->assertSame(1, $template->days()->count());
        $this->assertSame('Tuesday', $template->days()->firstOrFail()->day_of_week);
    }

    public function test_food_library_template_mutation_rejects_other_owner_and_invalid_items(): void
    {
        $other = User::factory()->rnd()->create();
        $template = MealPlanTemplate::forceCreate(['rnd_user_id' => $other->id, 'name' => 'Private template']);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $other->id]);

        $this->actingAs($this->rnd)->putJson("/api/rnd/meal-plan-templates/{$template->uuid}", [
            'name' => 'Changed', 'lines' => [],
        ])->assertNotFound();
        $this->postJson('/api/rnd/meal-plan-templates', [
            'name' => 'Cross-owned recipe',
            'lines' => [['day_of_week' => 'Monday', 'meal_type' => 'lunch', 'recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'serving']],
        ])->assertUnprocessable();
        $this->postJson('/api/rnd/meal-plan-templates', [
            'name' => 'No items', 'lines' => [],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('meal_plan_templates', 1);
    }

    public function test_new_library_templates_reject_direct_ingredients_and_non_serving_units(): void
    {
        $food = FoodItem::factory()->create();
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        foreach ([
            ['food_item_id' => $food->uuid, 'quantity' => 100, 'unit' => 'g'],
            ['recipe_id' => $recipe->uuid, 'quantity' => 1, 'unit' => 'cup'],
            ['recipe_id' => $recipe->uuid, 'quantity' => 1.001, 'unit' => 'serving'],
        ] as $source) {
            $this->actingAs($this->rnd)->postJson('/api/rnd/meal-plan-templates', [
                'name' => 'Invalid new line', 'lines' => [['day_of_week' => 'Monday', 'meal_type' => 'breakfast'] + $source],
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('meal_plan_templates', 0);
    }

    public function test_rnd_can_create_plan_from_template(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id, 'name' => 'Template A',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                // template_id is the template's public uuid (its Resource 'id'), which is
                // what the picker submits; the endpoint resolves it server-side.
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.generation_type', 'manual');
    }

    public function test_second_template_plan_for_same_intervention_returns_conflict_without_partial_rows(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Single menu template',
        ]);
        MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        $payload = [
            'template_id' => $template->uuid,
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => '2026-07-13',
        ];

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", $payload)
            ->assertCreated();

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", $payload)
            ->assertConflict()
            ->assertJsonPath('message', 'This Intervention Plan already has a menu plan.');

        $this->assertSame(1, MealPlan::where('intervention_id', $intervention->id)->count());
        $this->assertSame(35, MealPlanDay::whereHas('mealPlan', fn ($query) => $query->where('intervention_id', $intervention->id))->count());
    }

    public function test_generation_validates_the_explicit_plan_instead_of_the_latest_plan(): void
    {
        [$ncpRecord, $incompletePlan] = $this->makeInterventionWithNcpRecord([
            'energy_kcal' => null,
            'protein_g' => null,
            'carbs_g' => null,
            'fat_g' => null,
        ]);
        Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'goal_type' => 'custom',
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 55,
            'created_at' => now()->addMinute(),
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/generate", [
                'exclude_carbs' => true,
                'intervention_plan_id' => $incompletePlan->uuid,
                'week_start_date' => '2026-07-13',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.intervention.0', 'Energy target');

        $this->assertDatabaseMissing('meal_plans', ['intervention_id' => $incompletePlan->id]);
    }

    public function test_template_detail_delete_and_load_are_owner_scoped(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $otherRnd = User::factory()->create(['role' => 'RND']);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $otherRnd->id,
            'name' => 'Private template',
        ]);

        $this->actingAs($this->rnd)->getJson("/api/rnd/meal-plan-templates/{$template->uuid}")->assertNotFound();
        $this->actingAs($this->rnd)->deleteJson("/api/rnd/meal-plan-templates/{$template->uuid}")->assertNotFound();
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
            'template_id' => $template->uuid,
            'intervention_plan_id' => $intervention->uuid,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        ])->assertNotFound();

        $this->assertDatabaseHas('meal_plan_templates', ['id' => $template->id]);
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_loading_template_copies_all_items_and_warns_when_goal_or_stage_differs(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord([
            'goal_type' => 'diabetes_management',
            'disease_stage' => null,
        ]);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'CKD template',
            'goal_type' => 'renal_diet',
            'disease_stage' => 'stage_4',
        ]);
        $templateDay = MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        $recipes = Recipe::factory()->count(2)->create([
            'rnd_user_id' => $this->rnd->id, 'servings' => 1, 'category' => 'meal',
            'prepared_portion_amount' => 1, 'prepared_portion_unit' => 'cup',
        ]);
        foreach ($recipes as $recipe) {
            $food = FoodItem::factory()->create(['allergens' => []]);
            $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        }
        MealPlanTemplateItem::forceCreate([
            'template_day_id' => $templateDay->id,
            'recipe_id' => $recipes[0]->id,
            'quantity' => 1.5,
            'unit' => 'cup',
            'nutrient_snapshot' => ['name' => 'Cooked rice', 'calories' => 205.0, 'protein' => 4, 'carbs' => 44, 'fat' => 0.5, 'serving_size' => 1, 'serving_unit' => 'serving', 'micronutrients' => []],
            'line_order' => 1,
        ]);
        MealPlanTemplateItem::forceCreate([
            'template_day_id' => $templateDay->id,
            'recipe_id' => $recipes[1]->id,
            'quantity' => 1,
            'unit' => 'serving',
            'nutrient_snapshot' => ['name' => 'Fish', 'calories' => 220.0, 'protein' => 27, 'carbs' => 0, 'fat' => 10, 'serving_size' => 1, 'serving_unit' => 'serving', 'micronutrients' => []],
            'line_order' => 2,
        ]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('meta.template_compatibility.goal_matches', false)
            ->assertJsonPath('meta.template_compatibility.disease_stage_matches', false);

        $plan = MealPlan::where('uuid', $response->json('data.id'))->sole();
        $items = MealPlanItem::whereHas('mealPlanDay', fn ($query) => $query->where('meal_plan_id', $plan->id))
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame(['1.50', '1.00'], $items->pluck('quantity')->all());
        $this->assertSame('Cooked rice', $items[0]->nutrient_snapshot['name']);
        $this->assertSame('Fish', $items[1]->nutrient_snapshot['name']);
    }

    public function test_general_maternal_template_matches_confirmed_status_but_requires_goal_review(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord([
            'goal_type' => 'high_protein',
            'disease_stage' => 'moderate_stress',
        ]);
        $ncpRecord->assessment()->create(['pregnancy_lactation_status' => 'lactating']);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'General maternal pattern',
            'goal_type' => null,
            'maternal_status' => 'lactating',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('meta.template_compatibility.goal_matches', true)
            ->assertJsonPath('meta.template_compatibility.maternal_status_matches', true)
            ->assertJsonPath(
                'meta.template_compatibility.warning',
                'This general maternal template still needs review against the intervention goal before scaling.'
            );
    }

    public function test_maternal_template_flags_non_maternal_patient_as_incompatible(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $ncpRecord->assessment()->create(['pregnancy_lactation_status' => 'none']);
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Maternal-only pattern',
            'maternal_status' => 'pregnant_or_lactating',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('meta.template_compatibility.maternal_status_matches', false)
            ->assertJsonPath(
                'meta.template_compatibility.warning',
                'This template was created for a different intervention goal, disease stage, or maternal status. Review and scale it before use.'
            );
    }

    public function test_scale_to_prescription_changes_only_existing_quantities_using_practical_increments(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord([
            'energy_kcal' => 1000,
            'protein_g' => 40,
            'carbs_g' => 120,
            'fat_g' => 40,
        ]);
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'generation_type' => 'manual',
            'needs_rescaling' => true,
        ]);
        $day = MealPlanDay::factory()->create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
        ]);
        $snapshot = [
            'name' => 'Test dish',
            'calories' => 250,
            'protein' => 10,
            'carbs' => 30,
            'fat' => 10,
            'serving_size' => 100,
            'serving_unit' => 'g',
            'micronutrients' => [],
        ];
        MealPlanItem::factory()->count(2)->create([
            'meal_plan_day_id' => $day->id,
            'quantity' => 100,
            'unit' => 'g',
            'nutrient_snapshot' => $snapshot,
        ]);
        $originalIds = $day->items()->orderBy('id')->pluck('id')->all();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/scale-to-prescription")
            ->assertOk()
            ->assertJsonPath('data.scale_status', 'already_scaled')
            ->assertJsonPath('meta.scaling.inserted_items', 0)
            ->assertJsonPath('meta.scaling.substituted_items', 0);

        $this->assertSame($originalIds, $day->items()->orderBy('id')->pluck('id')->all());
        $this->assertSame(['200.00', '200.00'], $day->items()->orderBy('id')->pluck('quantity')->all());
        $this->assertFalse($plan->fresh()->needs_rescaling);
        $this->assertNotNull($plan->fresh()->scaled_at);
    }

    public function test_scaling_keeps_exact_fractional_recipe_servings_and_identity(): void
    {
        [$ncp, $intervention, $patient] = $this->makeInterventionWithNcpRecord([
            'energy_kcal' => 600, 'protein_g' => 24, 'carbs_g' => 60, 'fat_g' => 24,
        ]);
        $recipe = Recipe::factory()->create(['rnd_user_id' => $this->rnd->id, 'category' => 'carb']);
        $plan = MealPlan::factory()->create(['intervention_id' => $intervention->id, 'patient_id' => $patient->id, 'needs_rescaling' => true]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id, 'day_of_week' => 'Monday', 'meal_type' => 'breakfast']);
        $item = MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id, 'recipe_id' => $recipe->id, 'food_item_id' => null,
            'quantity' => 1.2, 'unit' => 'serving',
            'nutrient_snapshot' => [
                'name' => 'Measured Carb', 'calories' => 500, 'protein' => 20, 'carbs' => 50, 'fat' => 20,
                'serving_size' => 1, 'serving_unit' => 'serving', 'micronutrients' => [],
            ],
        ]);
        $this->actingAs($this->rnd)->postJson("/api/rnd/ncp-records/{$ncp->uuid}/meal-plans/{$plan->uuid}/scale-to-prescription")
            ->assertOk()->assertJsonPath('meta.scaling.inserted_items', 0)->assertJsonPath('meta.scaling.substituted_items', 0);
        $this->assertSame('1.20', $item->fresh()->quantity);
        $this->assertSame($recipe->id, $item->fresh()->recipe_id);
        $this->assertSame(1, $day->items()->count());
    }

    public function test_full_maternal_template_can_scale_against_its_dated_plan(): void
    {
        $this->seed(FoodItemsSeeder::class);
        $this->seed(RecipeSeeder::class);
        $this->seed(InterventionMealPlanTemplateSeeder::class);
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord([
            'goal_type' => 'custom',
            'energy_kcal' => 2081,
            'protein_g' => 69,
            'carbs_g' => 323,
            'fat_g' => 57,
            'micronutrient_limits' => [
                'fiber' => ['min' => 22, 'unit' => 'g'],
                'sodium' => ['max' => 2000, 'unit' => 'mg'],
            ],
        ]);
        $template = MealPlanTemplate::query()->where('name', 'Demo — Maternal Nutrition — Pregnancy — Balanced')->sole();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-10-05',
            ])
            ->assertCreated();

        $this->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$response->json('data.id')}/scale-to-prescription")
            ->assertOk()
            ->assertJsonPath('data.scale_status', 'already_scaled');
    }

    public function test_unchanged_auto_generated_plan_rejects_meaningless_scaling(): void
    {
        [$ncpRecord, $intervention, $patient] = $this->makeInterventionWithNcpRecord();
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'generation_type' => 'auto',
            'needs_rescaling' => false,
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/{$plan->uuid}/scale-to-prescription")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This automatically generated plan is already scaled to the current prescription.');
    }

    public function test_plan_from_template_fails_closed_without_partial_graph_when_audit_unavailable(): void
    {
        [$ncpRecord, $intervention] = $this->makeInterventionWithNcpRecord();
        $template = MealPlanTemplate::forceCreate([
            'rnd_user_id' => $this->rnd->id,
            'name' => 'Atomic template',
        ]);
        MealPlanTemplateDay::forceCreate([
            'template_id' => $template->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
            'quantity' => 1,
            'unit' => 'serving',
        ]);
        config(['activitylog.enabled' => false]);

        $this->actingAs($this->rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/meal-plans/from-template", [
                'template_id' => $template->uuid,
                'intervention_plan_id' => $intervention->uuid,
                'week_start_date' => '2026-07-13',
            ])->assertServerError();

        $this->assertDatabaseCount('meal_plans', 0);
        $this->assertDatabaseCount('meal_plan_days', 0);
        $this->assertDatabaseCount('meal_plan_items', 0);
    }
}
