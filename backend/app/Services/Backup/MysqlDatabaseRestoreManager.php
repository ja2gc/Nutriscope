<?php

namespace App\Services\Backup;

use App\Contracts\DatabaseRestoreManager;
use App\Models\BackupRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class MysqlDatabaseRestoreManager implements DatabaseRestoreManager
{
    public function restoreToTemporary(BackupRun $run, string $databaseName): array
    {
        $this->assertName($databaseName);
        DB::statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $archive = tempnam(sys_get_temp_dir(), 'nutriscope-restore-');
        $sql = tempnam(sys_get_temp_dir(), 'nutriscope-sql-');
        if (! is_string($archive) || ! is_string($sql)) {
            throw new RuntimeException('Temporary recovery storage is unavailable.');
        }
        try {
            file_put_contents($archive, Storage::disk($run->storage_disk)->get($run->object_key));
            $zip = new ZipArchive;
            if ($zip->open($archive) !== true || ! $zip->setPassword((string) config('backup.backup.password'))) {
                throw new RuntimeException('Recovery archive cannot be decrypted.');
            }
            $entry = collect(range(0, $zip->numFiles - 1))
                ->map(fn (int $index): string|false => $zip->getNameIndex($index))
                ->first(fn (string|false $name): bool => is_string($name) && str_ends_with(strtolower($name), '.sql'));
            if (! is_string($entry) || file_put_contents($sql, $zip->getFromName($entry)) === false) {
                throw new RuntimeException('Recovery SQL dump is unavailable.');
            }
            $zip->close();
            $mysql = (string) config('nutriscope-backups.mysql_binary', 'mysql');
            $connection = config('database.connections.mysql');
            $process = new Process([
                $mysql, '--host='.$connection['host'], '--port='.$connection['port'],
                '--user='.$connection['username'], $databaseName,
            ]);
            $process->setInput(fopen($sql, 'rb'));
            $process->setEnv(['MYSQL_PWD' => (string) $connection['password']]);
            $process->setTimeout(840);
            $process->mustRun();

            return ['name' => $databaseName, 'disposable' => true, 'promotable' => true, 'connection' => 'recovery_candidate'];
        } catch (\Throwable $exception) {
            $this->dropTemporary($databaseName);
            throw $exception;
        } finally {
            @unlink($archive);
            @unlink($sql);
        }
    }

    public function dropTemporary(string $databaseName): void
    {
        $this->assertName($databaseName);
        DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
    }

    private function assertName(string $databaseName): void
    {
        if (preg_match('/^nutriscope_recovery_[a-f0-9]{12}$/D', $databaseName) !== 1) {
            throw new RuntimeException('Recovery database name is invalid.');
        }
    }
}
