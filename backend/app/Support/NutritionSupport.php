<?php

namespace App\Support;

class NutritionSupport
{
    public const VALUES = ['oral', 'tube_feeding', 'no_food_menu'];

    public static function label(?string $value): string
    {
        return match ($value) {
            'oral' => 'Per Orem',
            'tube_feeding' => 'Tube Feeding',
            'no_food_menu' => 'NPO / TPN',
            default => 'Not recorded',
        };
    }
}
