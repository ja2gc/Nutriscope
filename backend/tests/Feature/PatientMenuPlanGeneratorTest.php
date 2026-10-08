<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\ReportBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientMenuPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(?string $createdAt = null): MealPlan
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create([
            'name' => 'LEGACY PATIENT LABEL',
            'first_name' => 'Maria Luisa',
            'last_name' => 'De la Cruz',
        ]);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800,
            'protein_g' => 75,
            'carbs_g' => 225,
            'fat_g' => 60,
            'fluid_ml' => 2000,
            'micronutrient_limits' => [
                'sodium' => ['max' => 2000, 'unit' => 'mg'],
                'fiber' => ['min' => 25, 'unit' => 'g'],
            ],
            'education_notes' => 'Choose lower-sodium foods.',
            'counseling_goals' => 'Follow the planned meal pattern.',
            'strategies' => 'Prepare measured portions before meals.',
            'barriers' => 'Limited access to cooking equipment.',
            'created_at' => $createdAt ?? '2026-06-14 09:00:00',
        ]);

        return MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
            'week_start_date' => '2026-06-15',
            'generation_type' => 'manual',
            'status' => 'draft',
        ]);
    }

    public function test_plan_uuid_uses_the_saved_complete_plan_instead_of_a_newer_plan(): void
    {
        $mealPlan = $this->makePlan();
        $savedPlan = $mealPlan->intervention;
        Intervention::factory()->create([
            'ncp_record_id' => $savedPlan->ncp_record_id,
            'energy_kcal' => 2400,
            'education_notes' => 'Later education that must not leak backward.',
            'created_at' => '2026-06-20 09:00:00',
        ]);

        $report = new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['intervention_plan_id' => $savedPlan->uuid],
        ]);
        $data = app(PatientMenuPlanGenerator::class)->data($report);

        $this->assertSame(1800.0, $data['prescription']['energy_kcal']);
        $this->assertSame('Choose lower-sodium foods.', $data['patient_guidance']['education']);
        $this->assertSame($savedPlan->uuid, $data['intervention_plan']->uuid);
        $this->assertSame($mealPlan->uuid, $data['meal_plan']->uuid);
        $this->assertArrayNotHasKey('revision', $data);
    }

    public function test_report_uses_patient_title_complete_guidance_and_short_maternal_note(): void
    {
        $plan = $this->makePlan();
        Assessment::factory()->create([
            'ncp_record_id' => $plan->intervention->ncp_record_id,
            'pregnancy_lactation_status' => 'pregnant_t2',
        ]);
        $report = new Report([
            'title' => 'Nutrition Intervention Plan',
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $html = view($generator->view(), [
            ...$generator->data($report),
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();
        $plain = preg_replace('/\s+/', ' ', strip_tags($html));

        $this->assertStringContainsString('NUTRITION INTERVENTION PLAN', $plain);
        $this->assertStringContainsString('Choose lower-sodium foods.', $plain);
        $this->assertStringContainsString('Follow the planned meal pattern.', $plain);
        $this->assertStringContainsString('Limited access to cooking equipment.', $plain);
        $this->assertStringContainsString('Prepare measured portions before meals.', $plain);
        $this->assertStringContainsString('second trimester', strtolower($plain));
        $this->assertStringNotContainsString('Recipe Details', $plain);
        $this->assertStringNotContainsString('USDA source', $plain);
    }

    public function test_report_uses_current_lactating_status_for_maternal_note(): void
    {
        $plan = $this->makePlan();
        Assessment::factory()->create([
            'ncp_record_id' => $plan->intervention->ncp_record_id,
            'pregnancy_lactation_status' => 'lactating',
        ]);

        $data = app(PatientMenuPlanGenerator::class)->data(new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]));

        $this->assertSame(
            'Final targets include the confirmed lactation adjustment.',
            $data['maternal_note'],
        );
    }

    public function test_report_omits_empty_snack_rows_and_keeps_populated_snack_rows(): void
    {
        $plan = $this->makePlan();
        $breakfast = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        $snack = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'pm_snack',
        ]);
        $food = FoodItem::factory()->create(['name' => 'Papaya']);

        foreach ([$breakfast, $snack] as $day) {
            MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'food_item_id' => $food->id,
                'quantity' => 1,
                'unit' => 'serving',
                'nutrient_snapshot' => ['name' => 'Papaya'],
            ]);
        }

        $data = app(PatientMenuPlanGenerator::class)->data(new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]));

        $this->assertSame(['Breakfast', 'Lunch', 'PM Snack', 'Dinner'], $data['meals']);
    }

    public function test_portion_details_are_metric_precise_linked_and_deduplicated(): void
    {
        $plan = $this->makePlan();
        $rice = FoodItem::factory()->create([
            'name' => 'Cooked rice',
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);
        $recipe = Recipe::factory()->create([
            'name' => 'Rice bowl',
            'servings' => 2,
            'prepared_portion_amount' => 1,
            'prepared_portion_unit' => 'medium piece',
            'prep_notes' => 'PREPARATION SENTINEL',
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $rice->id,
            'quantity' => 400,
            'unit' => 'g',
        ]);

        foreach (['Monday', 'Tuesday'] as $dayName) {
            $day = MealPlanDay::create([
                'meal_plan_id' => $plan->id,
                'day_of_week' => $dayName,
                'meal_type' => 'lunch',
            ]);
            MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'recipe_id' => $recipe->id,
                'quantity' => 1,
                'unit' => 'serving',
                'nutrient_snapshot' => [
                    'name' => 'Rice bowl',
                    'serving_size' => 1,
                    'serving_unit' => 'medium piece',
                ],
            ]);
        }

        $report = new Report(['type' => 'patient_menu_plan', 'parameters' => ['meal_plan_id' => $plan->id]]);
        $data = app(PatientMenuPlanGenerator::class)->data($report);

        $this->assertCount(1, $data['portion_details']);
        $detail = $data['portion_details'][0];
        $this->assertSame('Rice bowl', $detail['dish']);
        $this->assertSame('Cooked rice', $detail['foods'][0]['food']);
        $this->assertSame(200.0, $detail['foods'][0]['metric_amount']);
        $this->assertSame('g', $detail['foods'][0]['metric_unit']);
        $this->assertNull($detail['foods'][0]['household_measure']);
        $this->assertSame($detail['id'], $data['grid']['Lunch']['Monday'][0]['portion_id']);
        $this->assertSame($detail['id'], $data['grid']['Lunch']['Tuesday'][0]['portion_id']);
    }

    public function test_recipe_portions_use_the_persisted_prepared_amount_instead_of_multiplying_ingredients_by_grams(): void
    {
        $plan = $this->makePlan();
        $bread = FoodItem::factory()->create([
            'name' => 'Pandesal',
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);
        $recipe = Recipe::factory()->create([
            'name' => 'Pandesal with egg',
            'servings' => 1,
            'prepared_portion_amount' => 100,
            'prepared_portion_unit' => 'g',
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'food_item_id' => $bread->id,
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        MealPlanItem::create([
            'meal_plan_day_id' => $day->id,
            'recipe_id' => $recipe->id,
            'quantity' => 125,
            'unit' => 'g',
            'nutrient_snapshot' => [
                'name' => 'Pandesal with egg',
                'serving_size' => 100,
                'serving_unit' => 'g',
            ],
        ]);

        $report = new Report(['type' => 'patient_menu_plan', 'parameters' => ['meal_plan_id' => $plan->id]]);
        $data = app(PatientMenuPlanGenerator::class)->data($report);

        $this->assertSame(125.0, $data['portion_details'][0]['foods'][0]['metric_amount']);
    }

    public function test_repeated_food_with_different_portions_uses_one_detail_card_with_linked_variants(): void
    {
        $plan = $this->makePlan();
        $guava = FoodItem::factory()->create([
            'name' => 'Guava',
            'serving_size' => 100,
            'serving_unit' => 'g',
        ]);

        foreach ([['Monday', 250], ['Tuesday', 275]] as [$dayName, $quantity]) {
            $day = MealPlanDay::create([
                'meal_plan_id' => $plan->id,
                'day_of_week' => $dayName,
                'meal_type' => 'am_snack',
            ]);
            MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'food_item_id' => $guava->id,
                'quantity' => $quantity,
                'unit' => 'g',
                'nutrient_snapshot' => ['name' => 'Guava', 'serving_size' => 100, 'serving_unit' => 'g'],
            ]);
        }

        $report = new Report(['type' => 'patient_menu_plan', 'parameters' => ['meal_plan_id' => $plan->id]]);
        $generator = app(PatientMenuPlanGenerator::class);
        $data = $generator->data($report);

        $this->assertCount(1, $data['portion_details']);
        $this->assertSame('Guava', $data['portion_details'][0]['dish']);
        $this->assertCount(2, $data['portion_details'][0]['variants']);
        $this->assertNotSame(
            $data['grid']['AM Snack']['Monday'][0]['portion_id'],
            $data['grid']['AM Snack']['Tuesday'][0]['portion_id'],
        );

        $html = view($generator->view(), [
            ...$data,
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();
        $this->assertSame(1, substr_count($html, '>Guava</p>'));
    }

    public function test_usda_item_appears_in_the_grid(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id, 'day_of_week' => 'Monday', 'meal_type' => 'breakfast',
        ]);
        // USDA item: no food_item_id / recipe_id, name only in the snapshot.
        MealPlanItem::create([
            'meal_plan_day_id' => $day->id,
            'fdc_id' => '331960',
            'quantity' => 1,
            'unit' => 'serving',
            'nutrient_snapshot' => ['name' => 'USDA Chicken breast', 'calories' => 165],
        ]);

        $report = new Report;
        $report->type = 'patient_menu_plan';
        $report->parameters = ['meal_plan_id' => $plan->id];

        $data = app(PatientMenuPlanGenerator::class)->data($report);

        $names = collect($data['grid']['Breakfast']['Monday'])->pluck('name')->all();
        $this->assertContains('USDA Chicken breast', $names);
    }

    public function test_browse_lists_saved_plans_newest_first_with_plan_identity_and_menu_availability(): void
    {
        $mealPlan = $this->makePlan();
        $firstPlan = $mealPlan->intervention;
        $latestPlan = Intervention::factory()->create([
            'ncp_record_id' => $firstPlan->ncp_record_id,
            'created_at' => '2026-06-20 09:00:00',
        ]);

        $instances = app(ReportBrowser::class)
            ->sourceFor('patient_menu_plan')
            ->instances([]);

        $this->assertCount(2, $instances);
        $this->assertSame($latestPlan->uuid, $instances[0]['params']['intervention_plan_id']);
        $this->assertSame($latestPlan->uuid, $instances[0]['intervention_plan_id']);
        $this->assertNull($instances[0]['meal_plan_id']);
        $this->assertFalse($instances[0]['available']);
        $this->assertSame($firstPlan->uuid, $instances[1]['params']['intervention_plan_id']);
        $this->assertSame($mealPlan->uuid, $instances[1]['meal_plan_id']);
        $this->assertTrue($instances[1]['available']);
        $this->assertStringContainsString('Maria Luisa De la Cruz', $instances[1]['label']);
        $this->assertStringContainsString('Nutrition Intervention Plan', $instances[1]['label']);
        $this->assertStringContainsString('Jun 14, 2026', $instances[1]['label']);
        $this->assertStringNotContainsString('LEGACY PATIENT LABEL', $instances[0]['label']);
        $this->assertArrayNotHasKey('revision', $instances[1]);
        $this->assertArrayNotHasKey('version', $instances[1]);
    }

    public function test_saved_plan_without_a_menu_is_listed_but_prepare_returns_clear_422(): void
    {
        $mealPlan = $this->makePlan();
        $plan = Intervention::factory()->create([
            'ncp_record_id' => $mealPlan->intervention->ncp_record_id,
            'created_at' => '2026-06-20 09:00:00',
        ]);

        $instance = app(ReportBrowser::class)
            ->sourceFor('patient_menu_plan')
            ->instances([])[0];

        $this->assertSame($plan->uuid, $instance['intervention_plan_id']);
        $this->assertFalse($instance['available']);
        $this->assertSame('Save a menu plan before preparing this report.', $instance['unavailable_reason']);

        $this->actingAs($mealPlan->intervention->ncpRecord->rnd, 'sanctum')
            ->postJson('/api/rnd/reports/patient_menu_plan/prepare?intervention_plan_id='.$plan->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Save a menu plan before preparing this report.');
    }

    public function test_saved_plan_without_a_menu_is_rejected_by_every_report_action(): void
    {
        $mealPlan = $this->makePlan();
        $plan = Intervention::factory()->create([
            'ncp_record_id' => $mealPlan->intervention->ncp_record_id,
        ]);
        $user = $mealPlan->intervention->ncpRecord->rnd;
        $query = '?intervention_plan_id='.$plan->uuid;

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/render'.$query)
            ->assertUnprocessable();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/export'.$query)
            ->assertUnprocessable();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rnd/reports/patient_menu_plan/prepare'.$query)
            ->assertUnprocessable();
    }

    public function test_intervention_plan_instances_are_server_paginated_newest_first(): void
    {
        $mealPlan = $this->makePlan();
        $firstPlan = $mealPlan->intervention;
        $latestPlan = Intervention::factory()->create([
            'ncp_record_id' => $firstPlan->ncp_record_id,
            'created_at' => '2026-06-20 09:00:00',
        ]);
        $user = $firstPlan->ncpRecord->rnd;

        $firstPage = $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/instances?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->json('data.instances.0');
        $secondPage = $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/instances?per_page=1&page=2')
            ->assertOk()
            ->json('data.instances.0');

        $this->assertSame($latestPlan->uuid, $firstPage['intervention_plan_id']);
        $this->assertSame($firstPlan->uuid, $secondPage['intervention_plan_id']);
    }

    public function test_intervention_plan_context_rejects_mismatched_patient_or_cycle(): void
    {
        $mealPlan = $this->makePlan();
        $plan = $mealPlan->intervention;
        $otherPatient = Patient::factory()->create();
        $otherCycle = NcpRecord::factory()->create([
            'patient_id' => $otherPatient->id,
            'rnd_user_id' => $plan->ncpRecord->rnd_user_id,
        ]);
        $user = $plan->ncpRecord->rnd;

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/render?intervention_plan_id='.$plan->uuid.'&patient_id='.$otherPatient->uuid)
            ->assertForbidden();
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rnd/reports/patient_menu_plan/render?intervention_plan_id='.$plan->uuid.'&ncp_record_id='.$otherCycle->uuid)
            ->assertForbidden();
    }

    public function test_patient_report_feed_lists_every_saved_plan_with_plan_uuid_and_disables_missing_menu(): void
    {
        $mealPlan = $this->makePlan();
        $firstPlan = $mealPlan->intervention;
        $latestPlan = Intervention::factory()->create([
            'ncp_record_id' => $firstPlan->ncp_record_id,
            'created_at' => '2026-06-20 09:00:00',
        ]);

        $reports = $this->actingAs($firstPlan->ncpRecord->rnd, 'sanctum')
            ->getJson('/api/rnd/reports/patients/'.$firstPlan->ncpRecord->patient->uuid.'/instances')
            ->assertOk()
            ->json('data.0.reports');

        $plans = collect($reports)->where('type', 'patient_menu_plan')->values();
        $this->assertCount(2, $plans);
        $this->assertSame($latestPlan->uuid, $plans[0]['intervention_plan_id']);
        $this->assertFalse($plans[0]['available']);
        $this->assertSame($firstPlan->uuid, $plans[1]['params']['intervention_plan_id']);
        $this->assertSame($mealPlan->uuid, $plans[1]['meal_plan_id']);
        $this->assertTrue($plans[1]['available']);
    }

    public function test_new_plan_report_dates_use_manila_calendar_day(): void
    {
        $mealPlan = $this->makePlan('2026-10-04 16:30:00');
        $plan = $mealPlan->intervention;
        $rnd = $plan->ncpRecord->rnd;

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/reports/patients/'.$plan->ncpRecord->patient->uuid.'/instances')
            ->assertOk()
            ->assertJsonPath('data.0.reports.1.label', 'Nutrition Intervention Plan — Oct 5, 2026')
            ->assertJsonPath('data.0.reports.1.intervention_plan_date', '2026-10-05');

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/rnd/reports/patient_menu_plan/prepare?intervention_plan_id='.$plan->uuid)
            ->assertOk()
            ->assertJsonPath('data.title', 'Nutrition Intervention Plan — Oct 5, 2026');
    }

    public function test_report_view_uses_current_patient_display_name(): void
    {
        $plan = $this->makePlan();
        $report = new Report([
            'title' => 'Patient menu plan',
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $data = $generator->data($report);
        $html = view($generator->view(), [
            ...$data,
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $this->assertStringContainsString('Maria Luisa De la Cruz', $html);
        $this->assertStringNotContainsString('LEGACY PATIENT LABEL', $html);
    }

    public function test_report_displays_the_authoritative_prescription_and_fluid_scope(): void
    {
        $plan = $this->makePlan();
        $report = new Report([
            'title' => 'Patient menu plan',
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $data = $generator->data($report);
        $html = view($generator->view(), [
            ...$data,
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $this->assertSame(1800.0, $data['prescription']['energy_kcal']);
        $plainText = preg_replace('/\s+/', ' ', strip_tags($html));
        $this->assertStringContainsString('Energy: 1,800 kcal', $plainText);
        $this->assertStringContainsString('Sodium: max 2,000 mg', $plainText);
        $this->assertStringContainsString('Fiber: min 25 g', $plainText);
        $this->assertStringNotContainsString('Fluid guidance:', $plainText);
        $this->assertStringContainsString('Required fluid:', $plainText);
        $this->assertStringContainsString('Daily nutrient targets. These are not the nutrient totals of this menu.', $plainText);
        $this->assertStringContainsString('Estimated fluid from this menu if followed. The remaining amount should come from drinks.', $plainText);
        $this->assertStringContainsString('Remaining drinking fluid:', $plainText);
        $this->assertStringNotContainsString('Fluid is excluded from automatic food scaling', $plainText);
        $this->assertStringNotContainsString('not counted as satisfied by foods', $plainText);
    }

    public function test_report_calculates_average_food_fluid_and_remaining_drink_guidance(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        MealPlanItem::factory()->create([
            'meal_plan_day_id' => $day->id,
            'quantity' => 200,
            'unit' => 'g',
            'nutrient_snapshot' => [
                'name' => 'Water-rich food',
                'serving_size' => 100,
                'serving_unit' => 'g',
                'water_g' => 80,
            ],
        ]);

        $data = app(PatientMenuPlanGenerator::class)->data(new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]));

        $this->assertSame(23.0, $data['fluid_balance']['food_fluid_ml']);
        $this->assertSame(2000.0, $data['fluid_balance']['required_fluid_ml']);
        $this->assertSame(1977.0, $data['fluid_balance']['remaining_ml']);
    }

    public function test_weekly_meal_plan_precedes_intervention_guidance_without_a_forced_early_break(): void
    {
        $plan = $this->makePlan();
        $report = new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $html = view($generator->view(), [
            ...$generator->data($report),
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $document = new \DOMDocument;
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);
        $xpath = new \DOMXPath($document);

        $this->assertCount(0, $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " meal-plan-page-break ")]'));
        $this->assertCount(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " meal-plan-heading ") and normalize-space()="Weekly Meal Plan"]'));
        $this->assertLessThan(
            strpos($html, 'Choose lower-sodium foods.'),
            strpos($html, '<table class="grid menu-grid"'),
        );
    }

    public function test_portion_blocks_are_kept_together_during_pdf_pagination(): void
    {
        $source = file_get_contents(resource_path('views/reports/patient-menu-plan.blade.php'));

        $this->assertStringContainsString('class="portion-row-table"', $source);
        $this->assertStringContainsString('class="portion-row"', $source);
        $this->assertStringContainsString('<thead>', $source);
        $this->assertStringNotContainsString('recipe-ingredients', $source);
    }

    public function test_portion_details_render_three_cards_per_independent_row(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);

        foreach (['Rice porridge', 'Boiled egg', 'Papaya', 'Milk'] as $name) {
            $food = FoodItem::factory()->create([
                'name' => $name,
                'serving_size' => 100,
                'serving_unit' => 'g',
            ]);
            MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'food_item_id' => $food->id,
                'quantity' => 100,
                'unit' => 'g',
                'nutrient_snapshot' => ['name' => $name],
            ]);
        }

        $report = new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $html = view($generator->view(), [
            ...$generator->data($report),
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $document = new \DOMDocument;
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//tr[contains(concat(" ", normalize-space(@class), " "), " portion-row ")]');
        $menuGrid = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " menu-grid ")]');

        $this->assertCount(1, $menuGrid);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertCount(
                3,
                $xpath->query('./td[contains(concat(" ", normalize-space(@class), " "), " portion-cell ")]', $row),
            );
        }
    }

    public function test_long_portion_details_render_as_one_flowing_three_column_grid(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);

        foreach (range(1, 28) as $number) {
            $name = "Portion {$number}";
            $food = FoodItem::factory()->create([
                'name' => $name,
                'serving_size' => 100,
                'serving_unit' => 'g',
            ]);
            MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'food_item_id' => $food->id,
                'quantity' => $number,
                'unit' => 'g',
                'nutrient_snapshot' => ['name' => $name],
            ]);
        }

        $report = new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);
        $generator = app(PatientMenuPlanGenerator::class);
        $html = view($generator->view(), [
            ...$generator->data($report),
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $document = new \DOMDocument;
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);
        $xpath = new \DOMXPath($document);
        $groups = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " portion-page ")]');
        $pageBreaks = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " page-break ")]');
        $headings = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " portion-heading-table ")]');

        $this->assertCount(1, $groups);
        $this->assertCount(0, $pageBreaks);
        $this->assertCount(1, $headings);
        $this->assertCount(
            28,
            $xpath->query('.//td[contains(concat(" ", normalize-space(@class), " "), " portion-cell ") and not(contains(concat(" ", normalize-space(@class), " "), " portion-cell-empty "))]', $groups->item(0)),
        );
        $this->assertCount(1, $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " portion-heading ") and normalize-space()="Portion details"]'));
    }

    public function test_prepare_persists_patient_menu_plan_and_view_and_download_stream_pdf(): void
    {
        $plan = $this->makePlan();
        $rnd = $plan->intervention->ncpRecord->rnd;

        $prepared = $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/rnd/reports/patient_menu_plan/prepare?intervention_plan_id='.$plan->intervention->uuid)
            ->assertOk()
            ->assertJsonPath('data.title', 'Nutrition Intervention Plan — Jun 14, 2026');
        $id = $prepared->json('data.id');

        $this->assertDatabaseHas('reports', ['uuid' => $id, 'type' => 'patient_menu_plan']);
        $before = $this->get('/api/rnd/reports/'.$id.'/view')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->streamedContent();
        Intervention::factory()->create([
            'ncp_record_id' => $plan->intervention->ncp_record_id,
            'energy_kcal' => 2600,
            'created_at' => '2026-06-25 09:00:00',
        ]);
        $after = $this->get('/api/rnd/reports/'.$id.'/view')->assertOk()->streamedContent();
        $this->assertSame(hash('sha256', $before), hash('sha256', $after));
        $this->get('/api/rnd/reports/'.$id.'/download')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_legacy_meal_plan_parameter_remains_readable_for_frozen_reports(): void
    {
        $mealPlan = $this->makePlan();

        $data = app(PatientMenuPlanGenerator::class)->data(new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $mealPlan->uuid],
        ]));

        $this->assertSame($mealPlan->uuid, $data['meal_plan']->uuid);
        $this->assertSame($mealPlan->intervention->uuid, $data['intervention_plan']->uuid);
    }

    public function test_report_query_count_does_not_grow_with_more_plan_items(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'breakfast',
        ]);
        MealPlanItem::create([
            'meal_plan_day_id' => $day->id,
            'fdc_id' => 'one',
            'nutrient_snapshot' => ['name' => 'One'],
        ]);
        $report = new Report([
            'type' => 'patient_menu_plan',
            'parameters' => ['meal_plan_id' => $plan->id],
        ]);

        DB::enableQueryLog();
        app(PatientMenuPlanGenerator::class)->data($report);
        $baseQueries = count(DB::getQueryLog());

        MealPlanItem::create([
            'meal_plan_day_id' => $day->id,
            'fdc_id' => 'two',
            'nutrient_snapshot' => ['name' => 'Two'],
        ]);
        DB::flushQueryLog();
        app(PatientMenuPlanGenerator::class)->data($report);

        $this->assertSame($baseQueries, count(DB::getQueryLog()));
    }
}
