<?php

namespace App\Enums;

enum AuditModule: string
{
    case SecurityAdministration = 'security_administration';
    case NutritionCare = 'nutrition_care';
    case FoodServiceOperations = 'food_service_operations';
    case Reports = 'reports';

    public function label(): string
    {
        return match ($this) {
            self::SecurityAdministration => 'Security & Administration',
            self::NutritionCare => 'Nutrition Care',
            self::FoodServiceOperations => 'Food Service Operations',
            self::Reports => 'Reports',
        };
    }
}
