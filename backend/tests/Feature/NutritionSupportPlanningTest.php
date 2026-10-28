<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DemographicCensusPeriod;
use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlanTemplate;
use App\Models\NcpRecord;
use App\Models\Recipe;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\FormulaPlanService;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\ReportService;
use App\Support\NutritionSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class NutritionSupportPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function formula(): Recipe
    {
        $food = FoodItem::factory()->create(['calories' => 100, 'protein' => 10, 'carbs' => 10, 'fat' => 2, 'serving_size' => 100, 'serving_unit' => 'g']);
        $recipe = Recipe::factory()->create(['category' => 'tube_feeding', 'servings' => 2]);
        $recipe->ingredients()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g']);
        $recipe->recalculateTotals();

        return $recipe->load('ingredients.foodItem');
    }

    public function test_patient_recipe_edits_survive_portion_changes_and_reset_without_changing_library(): void
    {
        Http::preventStrayRequests();
        $recipe = $this->formula();
        $service = app(FormulaPlanService::class);
        $line = ['id' => (string) Str::uuid(), 'recipe_id' => $recipe->uuid, 'portion_multiplier' => 2];
        $saved = $service->normalize([$line], [], 1);
        $line['ingredients'] = [['food_item_id' => $recipe->ingredients[0]->foodItem->uuid, 'quantity' => 75, 'unit' => 'g']];
        $line['portion_multiplier'] = 3;
        $edited = $service->normalize([$line], $saved, 1);
        $this->assertSame(75.0, (float) $edited[0]['nutrient_snapshot']['recipe_original_ingredients'][0]['quantity']);
        $this->assertSame(225.0, $edited[0]['nutrient_snapshot']['calories'] * 2 * $edited[0]['portion_multiplier']);
        $this->assertSame(100.0, (float) $recipe->ingredients[0]->fresh()->quantity);
        $line['reset_recipe'] = true;
        $reset = $service->normalize([$line], $edited, 1)[0];
        $this->assertSame(1.0, $reset['portion_multiplier']);
        $this->assertSame(100.0, (float) $reset['nutrient_snapshot']['recipe_original_ingredients'][0]['quantity']);
    }

    public function test_plan_only_creation_does_not_write_a_library_menu_item(): void
    {
        $recipe = $this->formula();
        $before = Recipe::count();
        $items = app(FormulaPlanService::class)->normalize([['id' => (string) Str::uuid(), 'recipe_id' => null, 'portion_multiplier' => 1,
            'new_formula' => ['name' => 'Fictional patient formula', 'servings' => 2, 'ingredients' => [['food_item_id' => $recipe->ingredients[0]->foodItem->uuid, 'quantity' => 100, 'unit' => 'g']]]]], [], 1);
        $this->assertNull($items[0]['recipe_id']);
        $this->assertSame('Fictional patient formula', $items[0]['nutrient_snapshot']['name']);
        $this->assertSame($before, Recipe::count());
    }

    public function test_template_copy_keeps_frozen_portion_and_owner_scope(): void
    {
        $owner = User::factory()->rnd()->create();
        $recipe = $this->formula();
        $service = app(FormulaPlanService::class);
        $items = $service->normalize([['id' => (string) Str::uuid(), 'recipe_id' => $recipe->uuid, 'portion_multiplier' => 2]], [], $owner->id);
        $template = MealPlanTemplate::create(['rnd_user_id' => $owner->id, 'name' => 'Fictional formulas', 'nutrition_support' => 'tube_feeding', 'formula_items' => $items]);
        $recipe->update(['name' => 'Changed catalog name']);
        $copy = $service->normalize([['id' => (string) Str::uuid(), 'recipe_id' => $recipe->uuid, 'portion_multiplier' => 3, 'source_template_id' => $template->uuid, 'source_template_item_id' => $items[0]['id']]], [], $owner->id);
        $this->assertSame($items[0]['nutrient_snapshot']['name'], $copy[0]['nutrient_snapshot']['name']);
        $this->assertSame(2, $template->fresh()->formula_items[0]['portion_multiplier']);
        $this->expectException(ModelNotFoundException::class);
        $service->normalize([['id' => (string) Str::uuid(), 'recipe_id' => $recipe->uuid, 'portion_multiplier' => 1, 'source_template_id' => $template->uuid, 'source_template_item_id' => $items[0]['id']]], [], $owner->id + 1);
    }

    public function test_census_has_exactly_one_support_bucket_per_cycle_and_preserves_unknowns(): void
    {
        $counts = DemographicCensusGenerator::aggregate([['nutrition_support' => 'oral'], ['nutrition_support' => 'tube_feeding'], ['nutrition_support' => 'no_food_menu'], []]);
        $this->assertSame(['oral' => 1, 'tube_feeding' => 1, 'no_food_menu' => 1, 'unrecorded' => 1], $counts['by_nutrition_support']);
    }

    public function test_no_food_menu_intervention_report_does_not_require_a_weekly_menu(): void
    {
        $ncp = NcpRecord::factory()->create(['nutrition_support' => 'no_food_menu']);
        $plan = Intervention::factory()->create(['ncp_record_id' => $ncp->id, 'nutrition_support' => 'no_food_menu']);
        $data = app(PatientMenuPlanGenerator::class)->data(new Report(['parameters' => ['intervention_plan_id' => $plan->uuid]]));
        $this->assertTrue($plan->reportPlanAvailable());
        $this->assertSame('no_food_menu', $data['nutrition_support']);
        $this->assertSame([], $data['formula_items']);
    }

    public function test_census_recalculates_old_basis_without_changing_stored_snapshots_and_renders_recorded_buckets(): void
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $owner = User::factory()->rnd()->create();
        $record = NcpRecord::factory()->create(['rnd_user_id' => $owner->id, 'created_at' => '2026-05-10 08:00:00']);
        Assessment::factory()->create(['ncp_record_id' => $record->id]);
        $legacy = DemographicCensusGenerator::aggregate([[]]);
        unset($legacy['by_nutrition_support']);
        DemographicCensusPeriod::create(['period_start' => '2026-05-01', 'period_end' => '2026-05-31', 'census' => $legacy, 'source_count' => 1, 'basis_version' => 7, 'frozen_at' => now()]);
        $this->actingAs($owner)->getJson('/api/rnd/reports/demographic_census/summary?year=2026&month=5')
            ->assertOk()->assertJsonPath('data.by_nutrition_support.unrecorded', 0)->assertJsonPath('data.by_nutrition_support.oral', 1);
        $counts = DemographicCensusGenerator::aggregate([['nutrition_support' => 'oral'], ['nutrition_support' => 'tube_feeding'], ['nutrition_support' => 'no_food_menu']]);
        DemographicCensusPeriod::create(['period_start' => '2026-06-01', 'period_end' => '2026-06-30', 'census' => $counts, 'source_count' => 3, 'basis_version' => DemographicCensusGenerator::BASIS_VERSION, 'frozen_at' => now()]);
        $summary = $this->getJson('/api/rnd/reports/demographic_census/summary?year=2026&month=6')->assertOk()
            ->assertJsonPath('data.by_nutrition_support.oral', 1)->assertJsonPath('data.by_nutrition_support.tube_feeding', 1)
            ->assertJsonPath('data.by_nutrition_support.no_food_menu', 1)->json('data');
        $bytes = app(ReportService::class)->buildCensusPdf($summary);
        $this->assertStringStartsWith('%PDF-', $bytes);
        if (getenv('NUTRISCOPE_QA_PDF_DIR')) {
            file_put_contents(getenv('NUTRISCOPE_QA_PDF_DIR').'/census.pdf', $bytes);
        }
    }

    public function test_oral_search_hides_tube_formulas_and_new_assessment_requires_support(): void
    {
        $owner = User::factory()->rnd()->create();
        $formula = $this->formula();
        $this->actingAs($owner)->getJson('/api/rnd/recipes')->assertOk()->assertJsonMissing(['id' => $formula->uuid]);
        $this->getJson('/api/rnd/recipes?nutrition_support=tube_feeding')->assertOk()->assertJsonFragment(['id' => $formula->uuid]);
        $record = NcpRecord::factory()->create(['rnd_user_id' => $owner->id, 'nutrition_support' => null, 'status' => 'draft']);
        $this->postJson('/api/rnd/ncp-records/'.$record->uuid.'/assessment', ['weight' => 60, 'height' => 170])->assertUnprocessable()->assertJsonValidationErrors('nutrition_support');
    }

    public function test_formula_template_is_an_ordered_collection_without_calendar_days(): void
    {
        $owner = User::factory()->rnd()->create();
        $formula = $this->formula();
        $response = $this->actingAs($owner)->postJson('/api/rnd/meal-plan-templates', ['name' => 'Fictional collection', 'nutrition_support' => 'tube_feeding',
            'formula_items' => [['id' => (string) Str::uuid(), 'recipe_id' => $formula->uuid, 'portion_multiplier' => 1]]])->assertCreated();
        $this->assertSame([], $response->json('data.days'));
        $this->assertCount(1, $response->json('data.formula_items'));
        $this->getJson('/api/rnd/meal-plan-templates?nutrition_support=oral')->assertJsonMissing(['name' => 'Fictional collection']);
    }

    public function test_plan_only_label_formula_has_no_catalog_side_effects(): void
    {
        $before = FoodItem::count();
        $items = app(FormulaPlanService::class)->normalize([['id' => (string) Str::uuid(), 'recipe_id' => null, 'portion_multiplier' => 1,
            'new_formula' => ['name' => 'Fictional label formula', 'servings' => 2, 'nutrition_label' => ['serving_size' => 100, 'serving_unit' => 'mL', 'calories' => 100, 'protein' => 10, 'carbs' => 10, 'fat' => 2]]]], [], 1);
        $this->assertSame(2.0, (float) $items[0]['nutrient_snapshot']['recipe_yield']);
        $this->assertSame(100.0, $items[0]['nutrient_snapshot']['calories']);
        $this->assertSame($before, FoodItem::count());
        $line = ['id' => $items[0]['id'], 'recipe_id' => null, 'portion_multiplier' => 3,
            'ingredients' => [['food_item_id' => $items[0]['nutrient_snapshot']['recipe_original_ingredients'][0]['food_item']['id'], 'quantity' => 150, 'unit' => 'mL']]];
        $edited = app(FormulaPlanService::class)->normalize([$line], $items, 1);
        $this->assertSame(450.0, $edited[0]['nutrient_snapshot']['calories'] * 2 * 3);
        $this->assertSame($before, FoodItem::count());
    }

    public function test_existing_missing_support_is_backfilled_without_changing_selected_modes(): void
    {
        $missing = NcpRecord::factory()->create(['nutrition_support' => null]);
        $tube = NcpRecord::factory()->create(['nutrition_support' => 'tube_feeding']);
        $migration = require database_path('migrations/2026_10_27_090152_backfill_existing_nutrition_support_as_oral.php');
        $migration->up();
        $this->assertSame('oral', $missing->fresh()->nutrition_support);
        $this->assertSame('tube_feeding', $tube->fresh()->nutrition_support);
    }

    public function test_nonoral_intervention_reports_render_the_saved_recipe_and_selected_nutrients(): void
    {
        $owner = User::factory()->rnd()->create();
        $formula = $this->formula();
        $formula->ingredients[0]->foodItem->update(['micronutrients' => ['sodium' => 20, 'iron' => 2]]);
        $items = app(FormulaPlanService::class)->normalize([['id' => (string) Str::uuid(), 'recipe_id' => $formula->uuid, 'portion_multiplier' => 2]], [], $owner->id);
        foreach (['tube_feeding', 'no_food_menu'] as $mode) {
            $record = NcpRecord::factory()->create(['nutrition_support' => $mode, 'rnd_user_id' => $owner->id]);
            $record->patient->update(['first_name' => 'Fictional', 'last_name' => 'QA Patient', 'dob' => '1990-01-01', 'admission_date' => now()->toDateString()]);
            $plan = Intervention::factory()->create(['ncp_record_id' => $record->id, 'nutrition_support' => $mode, 'formula_items' => $mode === 'tube_feeding' ? $items : [], 'displayed_nutrients' => ['sodium', 'potassium'], 'micronutrient_limits' => ['sodium' => ['max' => 1500, 'unit' => 'mg']]]);
            $data = app(PatientMenuPlanGenerator::class)->data(new Report(['parameters' => ['intervention_plan_id' => $plan->uuid]]));
            $html = view('reports.patient-menu-plan', [...$data, 'branding' => new ReportBranding, 'report' => new Report, 'signatories' => []])->render();
            $this->assertStringContainsString(NutritionSupport::label($mode), $html);
            $this->assertStringNotContainsString('Monday', $html);
            $this->assertStringNotContainsString('× recipe', $html);
            $this->assertStringNotContainsString('Original recipe', $html);
            if ($mode === 'tube_feeding') {
                $this->assertStringContainsString('200 g', $html);
                $text = preg_replace('/\s+/', ' ', strip_tags($html));
                $this->assertStringContainsString('Sodium 40 mg', $text);
                $this->assertStringContainsString('Potassium Not recorded', $text);
                $this->assertStringNotContainsString('Iron', $text);
                $this->assertLessThan(strpos($html, 'Nutrient profile'), strpos($html, 'Portion details'));
            }
            $bytes = app(ReportService::class)->streamBytes('patient_menu_plan', ['intervention_plan_id' => $plan->uuid]);
            $this->assertStringStartsWith('%PDF-', $bytes);
            if (getenv('NUTRISCOPE_QA_PDF_DIR')) {
                file_put_contents(getenv('NUTRISCOPE_QA_PDF_DIR').'/'.$mode.'.pdf', $bytes);
                file_put_contents(getenv('NUTRISCOPE_QA_PDF_DIR').'/'.$mode.'-ncp.pdf', app(ReportService::class)->streamBytes('ncp_summary', ['ncp_record_id' => $record->uuid]));
            }
        }
    }
}
