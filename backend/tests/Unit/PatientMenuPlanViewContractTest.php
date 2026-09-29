<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PatientMenuPlanViewContractTest extends TestCase
{
    public function test_patient_plan_uses_three_column_independent_rows_and_no_legacy_recipe_copy(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');

        $this->assertStringContainsString('NUTRITION INTERVENTION PLAN', $source);
        $this->assertStringContainsString('class="portion-row-table"', $source);
        $this->assertStringContainsString('array_chunk($portionPage, 3)', $source);
        $this->assertStringContainsString('class="portion-page', $source);
        $this->assertStringContainsString('class="portion-row"', $source);
        $layout = file_get_contents(__DIR__.'/../../resources/views/reports/layout.blade.php');
        $this->assertStringContainsString("(\$paper_orientation ?? 'portrait') === 'landscape'", $layout);
        $this->assertStringContainsString("'13in 8.5in'", $layout);
        $this->assertStringContainsString("'8.5in 13in'", $layout);
        $this->assertLessThan(strpos($source, 'patient_guidance'), strpos($source, 'meal-plan-heading'));
        $this->assertStringNotContainsString('meal-plan-page-break', $source);
        $this->assertStringContainsString("patient_guidance['barriers']", $source);
        $this->assertStringNotContainsString('Recipe Details', $source);
        $this->assertStringNotContainsString('prep_notes', $source);
        $this->assertStringNotContainsString('medium piece', $source);
        $this->assertStringNotContainsString('USDA source', $source);
        $this->assertStringNotContainsString('class="page-break"', $source);
        $this->assertStringNotContainsString('count($portion_details) > 21', $source);
    }
}
