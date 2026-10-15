<?php

namespace Tests\Unit;

use App\Support\UnitConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitConverterTest extends TestCase
{
    public function test_same_unit_is_identity(): void
    {
        $this->assertSame(200.0, UnitConverter::convert(200, 'g', 'g'));
    }

    public function test_clinical_units_use_one_canonical_spelling(): void
    {
        foreach (['grams' => 'g', 'millilitre' => 'mL', 'cc' => 'mL', 'liter' => 'L', 'teaspoon' => 'tsp', 'tablespoon' => 'tbsp', 'PCS' => 'piece'] as $alias => $unit) {
            $this->assertSame($unit, UnitConverter::canonical($alias));
        }
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
        $this->assertSame(1.5, UnitConverter::scalingFactor(150, 'ml', 100, 'ml'));
    }

    #[DataProvider('directFactors')]
    public function test_clinical_scaling_uses_only_direct_compatible_factors(string $unit, string $baselineUnit, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, UnitConverter::scalingFactor(1, $unit, 1, $baselineUnit), 0.000000001);
    }

    public static function directFactors(): array
    {
        return [
            ['mg', 'g', 0.001], ['g', 'g', 1], ['kg', 'g', 1000],
            ['oz', 'g', 28.349523125], ['lb', 'g', 453.59237],
            ['mL', 'mL', 1], ['L', 'mL', 1000], ['tsp', 'mL', 4.92892],
            ['tbsp', 'mL', 14.7868], ['cup', 'mL', 236.588],
            [' grams ', 'g', 1], ['gram', 'g', 1], ['milliliter', 'mL', 1],
            ['millilitre', 'mL', 1], ['cc', 'mL', 1], ['liter', 'mL', 1000],
            ['litre', 'mL', 1000], ['teaspoon', 'mL', 4.92892], ['tablespoon', 'mL', 14.7868],
            ['piece', 'piece', 1], ['PC', 'piece', 1], ['pcs', 'piece', 1], ['pieces', 'piece', 1],
            ['slice', 'slice', 1],
        ];
    }

    #[DataProvider('invalidClinicalFactors')]
    public function test_clinical_scaling_rejects_unverified_units_and_nonpositive_amounts(float $quantity, string $unit, float $size, string $baselineUnit): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UnitConverter::scalingFactor($quantity, $unit, $size, $baselineUnit);
    }

    public static function invalidClinicalFactors(): array
    {
        return [
            [1, 'piece', 100, 'g'], [1, 'cup', 100, 'g'], [1, 'g', 100, 'mL'],
            [1, 'slice', 1, 'piece'], [1, 'pinch', 100, 'g'], [1, '', 1, ''],
            [1, 'g', 0, 'g'], [1, 'g', -1, 'g'], [0, 'g', 100, 'g'],
            [-1, 'g', 100, 'g'], [INF, 'g', 100, 'g'], [NAN, 'g', 100, 'g'],
        ];
    }
}
