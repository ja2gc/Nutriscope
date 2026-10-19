<?php

namespace Tests\Unit;

use App\Models\FsItem;
use Tests\TestCase;

/**
 * Pure-logic test (no DB): the food-service catalog derives a per-base-unit cost
 * from how the item is purchased, reusing App\Support\UnitConverter for physical
 * units. Nonconvertible pack sizes never change the saved unit price.
 */
class FsItemUnitCostTest extends TestCase
{
    public function test_per_kilo_price_derives_peso_per_gram(): void
    {
        // Buy carrots at ₱80/kg, recipes use grams → ₱0.08/g
        $item = new FsItem(['purchase_price' => 80, 'purchase_unit' => 'kg', 'base_unit' => 'g']);
        $this->assertEqualsWithDelta(0.08, $item->unit_cost, 1e-6);
    }

    public function test_per_litre_price_derives_peso_per_ml(): void
    {
        $item = new FsItem(['purchase_price' => 120, 'purchase_unit' => 'L', 'base_unit' => 'mL']);
        $this->assertEqualsWithDelta(0.12, $item->unit_cost, 1e-6);
    }

    public function test_same_unit_is_the_purchase_price(): void
    {
        // Countable item bought and used per piece → cost is just the price
        $item = new FsItem(['purchase_price' => 12.5, 'purchase_unit' => 'pc', 'base_unit' => 'pc']);
        $this->assertEqualsWithDelta(12.5, $item->unit_cost, 1e-6);
    }

    public function test_count_pack_does_not_change_the_saved_unit_price(): void
    {
        // A legacy pack-size field must not imply a piece-to-pack conversion.
        $item = new FsItem([
            'purchase_price' => 200, 'purchase_unit' => 'pack', 'base_unit' => 'pc', 'units_per_purchase' => 100,
        ]);
        $this->assertEqualsWithDelta(200.0, $item->unit_cost, 1e-6);
    }

    public function test_misconfigured_count_item_is_safe_not_fatal(): void
    {
        // Nonconvertible units keep the saved catalog unit and price.
        $item = new FsItem(['purchase_price' => 50, 'purchase_unit' => 'pack', 'base_unit' => 'pc']);
        $this->assertSame(50.0, $item->unit_cost);
    }
}
