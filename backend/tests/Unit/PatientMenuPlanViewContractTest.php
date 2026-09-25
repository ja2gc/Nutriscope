<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PatientMenuPlanViewContractTest extends TestCase
{
    public function test_patient_plan_uses_compact_page_safe_portions_and_no_legacy_recipe_copy(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');

        $this->assertStringContainsString('NUTRITION INTERVENTION PLAN', $source);
        $this->assertStringContainsString('portion-block', $source);
        $this->assertStringContainsString('page-break-inside:avoid', $source);
        $this->assertMatchesRegularExpression(
            '/@foreach\(\$portion_details as \$portion\).*portion-block.*@if\(\$loop->first\).*Portion details/s',
            $source,
            'The portion heading must stay inside the first page-safe portion block.',
        );
        $this->assertStringNotContainsString('Recipe Details', $source);
        $this->assertStringNotContainsString('prep_notes', $source);
        $this->assertStringNotContainsString('medium piece', $source);
        $this->assertStringNotContainsString('USDA source', $source);
    }
}
