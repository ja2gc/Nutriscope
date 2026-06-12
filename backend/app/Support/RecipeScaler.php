<?php

namespace App\Support;

/**
 * Scales a recipe from its baseline yield to a target number of servings.
 *
 * The recipe is the immutable baseline ("serves N with these amounts"); scaling
 * happens at use time — on the recipe page preview and, crucially, in the menu
 * planner where the target is the ward population. One primitive, used everywhere.
 */
class RecipeScaler
{
    /** target ÷ base, with a 0-base guard (falls back to 1) and no negative targets. */
    public static function factor(int $baseServings, int $targetServings): float
    {
        $base   = $baseServings > 0 ? $baseServings : 1;
        $target = max(0, $targetServings);

        return $target / $base;
    }

    /** Scale any per-baseline value (ingredient quantity or cost) to the target yield. */
    public static function scale(float $value, int $baseServings, int $targetServings): float
    {
        return $value * self::factor($baseServings, $targetServings);
    }
}
