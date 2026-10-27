<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PatientMenuPlanViewContractTest extends TestCase
{
    public function test_ingredient_rows_avoid_nested_tables_that_lose_content_during_page_reflow(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');
        $this->assertStringNotContainsString('<table class="portion-amounts">', $source);
        $this->assertStringContainsString('class="portion-ingredient-row"', $source);
    }

    public function test_portion_cards_use_one_recipe_list_and_a_plain_serving_guide_without_fallback_prose(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');
        $this->assertStringContainsString('Ingredient', $source);
        $this->assertStringContainsString('Whole recipe', $source);
        $this->assertStringContainsString('Recipe makes:', $source);
        $this->assertStringContainsString('Your servings:', $source);
        $this->assertStringContainsString('recipe_quantity', $source);
        $this->assertStringContainsString('Amount to use = listed ingredient amount', $source);
        $this->assertStringNotContainsString('fallback', $source);
        $this->assertStringNotContainsString('Unavailable', $source);
    }

    public function test_patient_plan_uses_four_card_independent_rows_and_no_legacy_recipe_copy(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');

        $this->assertStringContainsString('NUTRITION INTERVENTION PLAN', $source);
        $this->assertStringContainsString('class="portion-row-table"', $source);
        $this->assertStringContainsString('array_chunk($portionPage, 4)', $source);
        $this->assertStringContainsString('class="portion-page', $source);
        $this->assertStringContainsString('class="portion-row"', $source);
        $this->assertStringContainsString('class="portion-row-block"', $source);
        $layout = file_get_contents(__DIR__.'/../../resources/views/reports/layout.blade.php');
        $this->assertStringContainsString('.portion-row-block { page-break-inside: avoid; }', $layout);
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

    public function test_portion_heading_and_formula_are_grouped_with_first_card_row(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');
        $layout = file_get_contents(__DIR__.'/../../resources/views/reports/layout.blade.php');

        $this->assertStringContainsString('class="portion-intro-block portion-row-block"', $source);
        $this->assertStringContainsString('class="portion-row-table portion-heading-table"', $source);
        $this->assertStringContainsString('.portion-intro-block { page-break-inside: avoid; }', $layout);
    }

    public function test_energy_summary_omits_baseline_label_and_renders_the_saved_calculation(): void
    {
        $source = file_get_contents(__DIR__.'/../../resources/views/reports/patient-menu-plan.blade.php');

        $this->assertStringContainsString('Energy calculation:', $source);
        $this->assertStringContainsString('$prescription[\'energy_calculation\']', $source);
        $this->assertStringNotContainsString('Baseline:', $source);
    }
}
