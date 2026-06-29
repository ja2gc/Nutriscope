<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FoodServiceDemoSeederSourceTest extends TestCase
{
    public function test_demo_po_budget_deductions_are_system_processed(): void
    {
        $content = file_get_contents(__DIR__.'/../../database/seeders/FoodServiceDemoSeeder.php');

        $this->assertStringContainsString(
            'PurchaseOrderLifecycleService',
            $content,
            'FoodServiceDemoSeeder must run demo purchase orders through the lifecycle service so calculations are system-produced.'
        );

        $this->assertStringNotContainsString(
            "'type'              => 'po_deduction'",
            $content,
            'FoodServiceDemoSeeder must not manually insert po_deduction ledger rows.'
        );
    }

    public function test_demo_food_procurement_uses_tuesday_to_thursday_and_friday_to_monday_spans(): void
    {
        $content = file_get_contents(__DIR__.'/../../database/seeders/FoodServiceDemoSeeder.php');

        $this->assertStringContainsString('$weekStart->copy()->addDay()->toDateString()', $content);
        $this->assertStringContainsString('$weekStart->copy()->addDays(3)->toDateString()', $content);
        $this->assertStringContainsString("'Marketing - Tue-Thu '", $content);

        $this->assertStringContainsString('$weekStart->copy()->addDays(4)->toDateString()', $content);
        $this->assertStringContainsString('$weekStart->copy()->addDays(7)->toDateString()', $content);
        $this->assertStringContainsString("'Marketing - Fri-Mon '", $content);

        $this->assertStringContainsString('planRange($start, $end, $estimate)', $content);
        $this->assertMatchesRegularExpression("/'days_span'\s*=>\s*Carbon::parse\\(\\\$start\\)->diffInDays\\(Carbon::parse\\(\\\$end\\)\\) \+ 1/", $content);
    }
}
