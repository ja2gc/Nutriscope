<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\ReportBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientMenuPlanGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(): MealPlan
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create(['name' => 'Jane Cruz']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        return MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $patient->id,
            'week_start_date' => '2026-06-15',
            'generation_type' => 'manual',
            'status'          => 'draft',
        ]);
    }

    public function test_usda_item_appears_in_the_grid(): void
    {
        $plan = $this->makePlan();
        $day = MealPlanDay::create([
            'meal_plan_id' => $plan->id, 'day_of_week' => 'Monday', 'meal_type' => 'breakfast',
        ]);
        // USDA item: no food_item_id / recipe_id, name only in the snapshot.
        MealPlanItem::create([
            'meal_plan_day_id'  => $day->id,
            'fdc_id'            => '331960',
            'quantity'          => 1,
            'unit'              => 'serving',
            'nutrient_snapshot' => ['name' => 'USDA Chicken breast', 'calories' => 165],
        ]);

        $report = new Report();
        $report->type = 'patient_menu_plan';
        $report->parameters = ['meal_plan_id' => $plan->id];

        $data = app(PatientMenuPlanGenerator::class)->data($report);

        $names = collect($data['grid']['Breakfast']['Monday'])->pluck('name')->all();
        $this->assertContains('USDA Chicken breast', $names);
    }

    public function test_browse_lists_each_meal_plan_with_meal_plan_id_param(): void
    {
        $plan = $this->makePlan();

        $instances = app(ReportBrowser::class)
            ->sourceFor('patient_menu_plan')
            ->instances([]);

        $this->assertNotEmpty($instances);
        $this->assertArrayHasKey('meal_plan_id', $instances[0]['params']);
        $this->assertSame($plan->id, $instances[0]['params']['meal_plan_id']);
    }
}
