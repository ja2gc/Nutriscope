<?php

namespace App\Enums;

enum AuditDomain: string
{
    case Accounts = 'accounts';
    case Patients = 'patients';
    case Ncp = 'ncp';
    case Reports = 'reports';
    case Budget = 'budget';
    case Procurement = 'procurement';
    case FoodService = 'food_service';
    case NutritionLibrary = 'nutrition_library';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Ncp => 'NCP',
            self::FoodService => 'Food service',
            self::NutritionLibrary => 'Nutrition library',
            default => ucfirst($this->value),
        };
    }
}
