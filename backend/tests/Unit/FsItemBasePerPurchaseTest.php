<?php

namespace Tests\Unit;

use App\Models\FsItem;
use Tests\TestCase;

class FsItemBasePerPurchaseTest extends TestCase
{
    public function test_physical_units_use_converter(): void
    {
        $item = new FsItem(['purchase_unit' => 'kg', 'base_unit' => 'g']);
        $this->assertEqualsWithDelta(1000.0, $item->basePerPurchase(), 1e-6);
    }

    public function test_same_unit_is_one(): void
    {
        $item = new FsItem(['purchase_unit' => 'pc', 'base_unit' => 'pc']);
        $this->assertSame(1.0, $item->basePerPurchase());
    }

    public function test_nonconvertible_pack_sizes_do_not_convert_pieces(): void
    {
        $item = new FsItem(['purchase_unit' => 'pack', 'base_unit' => 'pc', 'units_per_purchase' => 100]);
        $this->assertSame(1.0, $item->basePerPurchase());
    }

    public function test_nonconvertible_units_retain_the_original_base_unit(): void
    {
        $item = new FsItem(['purchase_unit' => 'pack', 'base_unit' => 'pc']); // no units_per_purchase
        $this->assertSame(1.0, $item->basePerPurchase());
    }

    public function test_unit_cost_round_trips_through_base_per_purchase(): void
    {
        // ₱80/kg → 0.08/g; 0.08 × 1000 = ₱80 back
        $item = new FsItem(['purchase_price' => 80, 'purchase_unit' => 'kg', 'base_unit' => 'g']);
        $this->assertEqualsWithDelta(0.08, $item->unit_cost, 1e-6);
        $this->assertEqualsWithDelta(80.0, $item->unit_cost * $item->basePerPurchase(), 1e-4);
    }
}
