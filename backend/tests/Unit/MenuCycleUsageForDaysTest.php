<?php

namespace Tests\Unit;

use App\Services\MenuCycleCostService;
use Tests\TestCase;

class MenuCycleUsageForDaysTest extends TestCase
{
    public function test_usage_for_entries_returns_base_unit_quantities(): void
    {
        // One Monday lunch: rice 5000 g / 50 servings, scaled to 100 heads → 10000 g.
        $entries = [[
            'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null,
            'recipe' => [
                'servings' => 50,
                'ingredients' => [
                    ['fs_item_id' => 1, 'name' => 'Rice', 'quantity' => 5000, 'unit' => 'g', 'base_unit' => 'g', 'unit_cost' => 0.052],
                ],
            ],
        ]];

        $usage = MenuCycleCostService::aggregate($entries, 100)['ingredient_usage'];

        $this->assertCount(1, $usage);
        $this->assertSame(1, $usage[0]['fs_item_id']);
        $this->assertEqualsWithDelta(10000.0, $usage[0]['quantity'], 1e-6);
        $this->assertSame('g', $usage[0]['unit']);
    }
}
