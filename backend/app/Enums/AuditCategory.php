<?php

namespace App\Enums;

enum AuditCategory: string
{
    case Security = 'security';
    case Clinical = 'clinical';
    case Operations = 'operations';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function logName(): string
    {
        return 'audit';
    }
}
