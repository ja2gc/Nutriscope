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
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Ncp => 'NCP',
            self::FoodService => 'Food service',
            default => ucfirst($this->value),
        };
    }
}
