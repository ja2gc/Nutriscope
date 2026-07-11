<?php

namespace App\Enums;

enum AuditSeverity: string
{
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
