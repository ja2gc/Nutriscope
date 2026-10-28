<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Support\NutritionSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class NutritionSupportTest extends TestCase
{
    public function test_support_modes_keep_no_menu_distinct_from_oral_and_formulas(): void
    {
        $this->assertSame(['oral', 'tube_feeding', 'no_food_menu'], NutritionSupport::VALUES);
        $this->assertSame('Per Orem', NutritionSupport::label('oral'));
        $this->assertSame('Tube Feeding', NutritionSupport::label('tube_feeding'));
        $this->assertSame('NPO / TPN', NutritionSupport::label('no_food_menu'));
        $this->assertSame('Not recorded', NutritionSupport::label(null));
    }

    public function test_npo_reports_show_clinical_content_without_explaining_the_absent_menu(): void
    {
        $plan = new Intervention(['nutrition_support' => 'no_food_menu', 'energy_kcal' => 1800, 'protein_g' => 80, 'carbs_g' => 250, 'fat_g' => 55, 'fluid_ml' => 2000]);
        $plan->created_at = now();
        $shared = ['branding' => new ReportBranding(['hospital_name' => 'Fictional QA Hospital']), 'report' => new Report, 'signatories' => [], 'generated_at' => now(), 'paper_orientation' => 'portrait'];
        $views = [
            'patient-menu-plan' => [...$shared, 'patient' => new Patient(['first_name' => 'Fictional', 'last_name' => 'QA']), 'intervention_plan' => $plan, 'nutrition_support' => 'no_food_menu', 'nutrition_support_label' => 'NPO / TPN', 'displayed_nutrients' => [], 'formula_items' => [], 'prescription' => ['energy_kcal' => 1800, 'protein_g' => 80, 'carbs_g' => 250, 'fat_g' => 55, 'fluid_ml' => 2000, 'education_notes' => 'Recorded education notes.']],
            'ncp-summary' => [...$shared, 'patient' => ['name' => 'Fictional QA', 'hospital_number' => null, 'age' => 36, 'sex' => 'Male', 'physician' => null, 'admission_date' => 'Oct 28, 2026', 'diagnosis' => null, 'religion' => null], 'assessment' => new Assessment, 'assessment_age_months' => 432, 'nutrition_support' => 'NPO / TPN', 'biochem' => null, 'nutritional_status' => 'Normal', 'risk_score' => 1, 'risk_band' => 'Low', 'diagnoses' => [], 'intervention' => $plan, 'monitorings' => collect(), 'attachments' => collect(), 'attachment_images' => [], 'is_complete' => true, 'completion_stage' => 'Initial ADI', 'incomplete_items' => [], 'meal_plan' => null],
        ];
        foreach ($views as $view => $data) {
            $html = view('reports.'.$view, $data)->render();
            $this->assertStringContainsString('NPO / TPN', $html);
            $this->assertStringContainsString('1800', $html);
            $this->assertStringNotContainsString('No food menu is planned', $html);
            $this->assertStringNotContainsString('Weekly Meal Plan', $html);
            $bytes = Pdf::loadView('reports.'.$view, $data)->setPaper([0, 0, 612, 936])->output();
            $this->assertStringStartsWith('%PDF-', $bytes);
            if (getenv('NUTRISCOPE_QA_PDF_DIR')) {
                file_put_contents(getenv('NUTRISCOPE_QA_PDF_DIR').'/npo-'.$view.'.pdf', $bytes);
            }
        }
    }
}
