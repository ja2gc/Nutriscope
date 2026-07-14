<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Deterministic unit conversion for recipe-ingredient → food-serving scaling.
 *
 * Mass↔mass and volume↔volume conversions are exact. Volume↔mass crossings use a
 * density (default 1.0 g/mL — accurate for water and aqueous foods; pass an
 * explicit density for non-aqueous items). Unknown units THROW rather than being
 * silently divided, so a recipe can never quietly produce wrong nutrient totals.
 * Hospital-grade rule: fail loud, never approximate an unrecognised unit.
 */
class UnitConverter
{
    /** grams per unit */
    private const MASS_TO_G = [
        'mg' => 0.001, 'g' => 1.0, 'gram' => 1.0, 'grams' => 1.0,
        'kg' => 1000.0, 'oz' => 28.349523125, 'lb' => 453.59237,
    ];

    /** millilitres per unit */
    private const VOLUME_TO_ML = [
        'ml' => 1.0, 'milliliter' => 1.0, 'millilitre' => 1.0, 'cc' => 1.0,
        'l' => 1000.0, 'liter' => 1000.0, 'litre' => 1000.0,
        'tsp' => 4.92892, 'teaspoon' => 4.92892,
        'tbsp' => 14.7868, 'tablespoon' => 14.7868,
        'cup' => 236.588,
    ];

    public static function normalize(string $unit): string
    {
        return strtolower(trim($unit));
    }

    public static function isMass(string $unit): bool
    {
        return isset(self::MASS_TO_G[self::normalize($unit)]);
    }

    public static function isVolume(string $unit): bool
    {
        return isset(self::VOLUME_TO_ML[self::normalize($unit)]);
    }

    public static function isKnown(string $unit): bool
    {
        return self::isMass($unit) || self::isVolume($unit);
    }

    /**
     * Convert `qty` of `from` unit into the equivalent amount expressed in `to` unit.
     *
     * @param  float  $density  grams per millilitre, used only for mass↔volume crossings.
     *
     * @throws InvalidArgumentException on an unrecognised unit.
     */
    public static function convert(float $qty, string $from, string $to, float $density = 1.0): float
    {
        $f = self::normalize($from);
        $t = self::normalize($to);
        if ($f === $t) {
            return $qty;
        }

        if (! self::isKnown($f)) {
            throw new InvalidArgumentException("Unknown ingredient unit: '{$from}'");
        }
        if (! self::isKnown($t)) {
            throw new InvalidArgumentException("Unknown target/serving unit: '{$to}'");
        }
        if ($density <= 0) {
            throw new InvalidArgumentException('Density must be positive.');
        }

        $fMass = self::isMass($f);
        $tMass = self::isMass($t);

        if ($fMass && $tMass) {
            return $qty * self::MASS_TO_G[$f] / self::MASS_TO_G[$t];
        }
        if (! $fMass && ! $tMass) { // both volume
            return $qty * self::VOLUME_TO_ML[$f] / self::VOLUME_TO_ML[$t];
        }
        if (! $fMass && $tMass) { // volume → mass: ml * density = g
            return ($qty * self::VOLUME_TO_ML[$f] * $density) / self::MASS_TO_G[$t];
        }

        // mass → volume: g / density = ml
        return ($qty * self::MASS_TO_G[$f] / $density) / self::VOLUME_TO_ML[$t];
    }

    /**
     * Scaling factor: how many "servings" of a food an ingredient quantity represents.
     * factor = amount(expressed in the food's serving unit) / serving_size.
     *
     * @throws InvalidArgumentException on an unrecognised unit.
     */
    public static function scalingFactor(
        float $quantity,
        string $ingredientUnit,
        float $servingSize,
        string $servingUnit,
        float $density = 1.0
    ): float {
        $size = $servingSize > 0 ? $servingSize : 100.0;

        return self::convert($quantity, $ingredientUnit, $servingUnit, $density) / $size;
    }
}
