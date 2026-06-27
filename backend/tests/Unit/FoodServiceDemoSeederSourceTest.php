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
}
