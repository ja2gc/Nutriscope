<?php

namespace App\Enums;

enum RecoveryStatus: string
{
    case Requested = 'requested';
    case Preparing = 'preparing';
    case Checking = 'checking';
    case Ready = 'ready';
    case Switching = 'switching';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
    case Cancelled = 'cancelled';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::RolledBack, self::Cancelled], true);
    }
}
