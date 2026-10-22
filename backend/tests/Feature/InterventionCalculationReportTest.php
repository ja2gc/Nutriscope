<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Services\NutritionPrescriptionService;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterventionCalculationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_prescription_display_supplies_missing_known_units_and_preserves_entered_units(): void
    {
        foreach (['fiber' => 'g', 'free_sugars' => 'g', 'sodium' => 'mg', 'vitamin_d' => 'mcg'] as $nutrient => $unit) {
            $html = view('reports.partials.nutrient-prescription', ['nutrient' => $nutrient, 'limit' => ['max' => 25]])->render();
            $this->assertSame('≤25 '.$unit, trim($html));
        }
        $html = view('reports.partials.nutrient-prescription', ['nutrient' => 'sodium', 'limit' => ['min' => 1, 'unit' => 'g']])->render();
        $this->assertSame('≥1 g', trim($html));
    }

    public function test_report_uses_saved_targets_without_recalculating_or_mutating_history(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Fictional', 'last_name' => 'Calculation Review']);
        $record = NcpRecord::factory()->create(['patient_id' => $patient->id]);
        $result = (new NutritionPrescriptionService)->autofill('general_diet', null, [
            'weightKg' => 70, 'heightCm' => 170, 'ageYears' => 40, 'sex' => 'Male', 'activityFactor' => 1.2,
            'energyAdjustmentMode' => 'deficit', 'energyAdjustmentKcal' => 300,
            'fluidMode' => 'restricted', 'fluidLimitMl' => 1700, 'measurementDate' => '2026-10-21',
        ]);
        $plan = Intervention::factory()->create(['ncp_record_id' => $record->id, 'goal_type' => 'general_diet',
            ...array_intersect_key($result, array_flip(['energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml', 'calculation_snapshot', 'fluid_mode', 'nutrient_operators'])),
            'education_notes' => 'Choose suitable portions and review intake with the care team.',
            'counseling_goals' => 'Follow the confirmed final energy target.', 'strategies' => 'Gradual changes require clinical supervision.',
        ]);
        $menu = MealPlan::factory()->create(['patient_id' => $patient->id, 'intervention_id' => $plan->id]);
        $food = FoodItem::factory()->create(['name' => 'Fictional cooked vegetable portion']);
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            foreach (['breakfast', 'lunch', 'dinner'] as $meal) {
                $slot = $menu->days()->create(['day_of_week' => $day, 'meal_type' => $meal]);
                $slot->items()->create(['food_item_id' => $food->id, 'quantity' => 100, 'unit' => 'g',
                    'nutrient_snapshot' => ['name' => $food->name, 'serving_size' => 100, 'water_g' => 90],
                ]);
            }
        }
        $before = $plan->refresh()->getAttributes();
        $report = new Report(['type' => 'patient_menu_plan', 'parameters' => ['intervention_plan_id' => $plan->uuid]]);
        $data = app(PatientMenuPlanGenerator::class)->data($report);
        $html = view('reports.patient-menu-plan', [...$data, 'report' => $report, 'branding' => new ReportBranding, 'signatories' => []])->render();
        $this->assertStringContainsString('1,881 kcal', $html);
        $this->assertStringContainsString('fixed adjustment 300 kcal', $html);
        $this->assertStringContainsString('calculated final 1,581 kcal/day', $html);
        $this->assertStringContainsString('Remaining upper allowance', $html);
        $this->assertSame($before, $plan->fresh()->getAttributes());
        $bytes = app(ReportService::class)->buildPdf($report)['bytes'];
        $this->assertStringStartsWith('%PDF-', $bytes);
        if ($path = getenv('NUTRISCOPE_INTERVENTION_PDF_QA_PATH')) {
            $this->assertSame(strlen($bytes), file_put_contents($path, $bytes));
        }
        $plan->update(['goal_type' => 'malnutrition', 'disease_stage' => 'moderate', 'energy_kcal' => 1300, 'calculation_snapshot' => null, 'fluid_mode' => null]);
        $legacy = app(PatientMenuPlanGenerator::class)->data($report);
        $this->assertSame(1300.0, $legacy['prescription']['energy_kcal']);
        $this->assertNull($legacy['prescription']['calculation_snapshot']);
        $this->assertNull($legacy['maternal_note']);
        $this->assertSame('malnutrition', $plan->fresh()->goal_type);
    }
}
