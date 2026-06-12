<?php

namespace Tests\Unit;

use App\Support\UnitConverter;
use Tests\TestCase;

class UnitConverterTest extends TestCase
{
    public function test_same_unit_is_identity(): void
    {
        $this->assertSame(200.0, UnitConverter::convert(200, 'g', 'g'));
    }

    public function test_mass_conversions_are_exact(): void
    {
        $this->assertSame(1000.0, UnitConverter::convert(1, 'kg', 'g'));
        $this->assertSame(0.5, UnitConverter::convert(500, 'mg', 'g'));
        $this->assertEqualsWithDelta(28.35, UnitConverter::convert(1, 'oz', 'g'), 0.01);
    }

    public function test_volume_conversions_are_exact(): void
    {
        $this->assertSame(1000.0, UnitConverter::convert(1, 'l', 'ml'));
        $this->assertEqualsWithDelta(14.79, UnitConverter::convert(1, 'tbsp', 'ml'), 0.01);
    }

    public function test_volume_to_mass_uses_density_one_by_default(): void
    {
        // 150 ml of an aqueous food ≈ 150 g at density 1.0
        $this->assertSame(150.0, UnitConverter::convert(150, 'ml', 'g'));
    }

    public function test_volume_to_mass_honours_explicit_density(): void
    {
        // oil ~0.92 g/mL: 100 mL → 92 g
        $this->assertEqualsWithDelta(92.0, UnitConverter::convert(100, 'ml', 'g', 0.92), 0.001);
    }

    public function test_unknown_unit_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UnitConverter::convert(1, 'pinch', 'g');
    }

    public function test_scaling_factor_matches_serving_size(): void
    {
        // 200 g against a 100 g serving → factor 2.0
        $this->assertSame(2.0, UnitConverter::scalingFactor(200, 'g', 100, 'g'));
        // 150 ml milk against a 100 g serving (density 1.0) → factor 1.5
        $this->assertSame(1.5, UnitConverter::scalingFactor(150, 'ml', 100, 'g'));
    }
}
