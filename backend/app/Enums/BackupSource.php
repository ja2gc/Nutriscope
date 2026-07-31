<?php

namespace App\Enums;

enum BackupSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
}
