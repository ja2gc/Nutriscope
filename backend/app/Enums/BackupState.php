<?php

namespace App\Enums;

enum BackupState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Failed = 'failed';
    case RecentlyDeleted = 'recently_deleted';
    case Purged = 'purged';
}
