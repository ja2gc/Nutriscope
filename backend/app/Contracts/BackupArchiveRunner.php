<?php

namespace App\Contracts;

use App\Data\BackupArchiveResult;

interface BackupArchiveRunner
{
    public function runDatabaseOnly(): BackupArchiveResult;
}
