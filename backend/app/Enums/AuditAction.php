<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Archived = 'archived';
    case Failed = 'failed';
    case Suspended = 'suspended';
    case Unsuspended = 'unsuspended';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Failed => 'Failed',
            self::Suspended => 'Suspended',
            self::Unsuspended => 'Unsuspended',
            self::Discontinued => 'Discontinued',
            default => ucfirst($this->value),
        };
    }
}
