<?php

namespace App\Contracts;

use App\Models\BackupRun;

interface DatabaseRestoreManager
{
    /** @return array{name:string,disposable:bool,promotable:bool,connection:string} */
    public function restoreToTemporary(BackupRun $run, string $databaseName): array;

    public function dropTemporary(string $databaseName): void;
}
