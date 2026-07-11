<?php

namespace App\Enums;

enum AuditOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Blocked = 'blocked';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
