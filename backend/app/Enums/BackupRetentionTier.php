<?php

namespace App\Enums;

enum BackupRetentionTier: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
