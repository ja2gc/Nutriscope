<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PatientMenuPlanViewContractTest extends TestCase
{
    public function test_recipe_details_heading_stays_with_the_first_recipe_block(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');

        $this->assertMatchesRegularExpression(
            '/@foreach\(\$recipe_details as \$recipe\).*@if\(\$loop->first\).*Recipe Details/s',
            $source,
        );
    }
}
