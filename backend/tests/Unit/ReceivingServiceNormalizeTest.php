<?php

namespace Tests\Unit;

use App\Services\FSS\ReceivingService;
use Tests\TestCase;

class ReceivingServiceNormalizeTest extends TestCase
{
    public function test_base_unit_line_is_unchanged(): void
    {
        // Suggested-list flow: line already in base (g) at ₱/g.
        [$qty, $cost] = ReceivingService::normalizeLine(500.0, 'g', 0.08, 'g');
        $this->assertEqualsWithDelta(500.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(0.08, $cost, 1e-6);
    }

    public function test_purchase_unit_line_is_converted_to_base(): void
    {
        // Manual PO: 25 kg at ₱280/kg, base unit g → 25000 g at ₱0.28/g.
        [$qty, $cost] = ReceivingService::normalizeLine(25.0, 'kg', 280.0, 'g');
        $this->assertEqualsWithDelta(25000.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(0.28, $cost, 1e-6);
    }

    public function test_unknown_unit_is_treated_as_base(): void
    {
        // 'tray' is not a known physical unit → degrade, don't throw.
        [$qty, $cost] = ReceivingService::normalizeLine(10.0, 'tray', 240.0, 'pc');
        $this->assertEqualsWithDelta(10.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(240.0, $cost, 1e-6);
    }
}
