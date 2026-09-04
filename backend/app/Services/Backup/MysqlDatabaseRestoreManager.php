<?php

namespace App\Services\Backup;

use App\Contracts\DatabaseRestoreManager;
use App\Models\BackupRun;
use Illuminate\Support\Facades\Config;
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

    /** @param array{name:string,disposable:bool,promotable:bool,connection:string} $candidate */
    public function promoteTemporary(array $candidate): void
    {
        $this->assertName($candidate['name']);
        if (! $candidate['disposable'] || ! $candidate['promotable']) {
            throw new RuntimeException('Recovery database is not promotable.');
        }

        $liveName = (string) config('database.connections.mysql.database');
        $this->assertDatabaseIdentifier($liveName);
        $this->configureCandidate($candidate['name']);
        $tables = $this->compatibleApplicationTables($liveName, $candidate['name']);
        $connection = DB::connection('mysql');
        $this->assertTransactionalTables($connection, $liveName, $tables);
        $this->assertRecoveryActorsRemainValid($connection, $liveName, $candidate['name']);

        $connection->beginTransaction();
        try {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($tables as $table) {
                $quoted = $this->quoteIdentifier($table);
                $connection->statement("DELETE FROM `{$liveName}`.{$quoted}");
                $connection->statement("INSERT INTO `{$liveName}`.{$quoted} SELECT * FROM `{$candidate['name']}`.{$quoted}");
            }
            $this->reconcileControlReferences($connection, $liveName);
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
            throw $exception;
        }

        DB::purge('mysql');
    }

    public function candidateContainsUser(string $databaseName, int $userId): bool
    {
        $this->assertName($databaseName);
        $this->configureCandidate($databaseName);

        return DB::connection('recovery_candidate')->table('users')->where('id', $userId)->exists();
    }

    private function assertName(string $databaseName): void
    {
        if (preg_match('/^nutriscope_recovery_[a-f0-9]{12}$/D', $databaseName) !== 1) {
            throw new RuntimeException('Recovery database name is invalid.');
        }
    }

    private function configureCandidate(string $databaseName): void
    {
        Config::set('database.connections.recovery_candidate', [
            ...config('database.connections.mysql'),
            'database' => $databaseName,
        ]);
        DB::purge('recovery_candidate');
    }

    /** @return list<string> */
    private function compatibleApplicationTables(string $liveName, string $candidateName): array
    {
        $live = $this->tableSignatures($liveName);
        $candidate = $this->tableSignatures($candidateName);
        $preserved = config('nutriscope-backups.recovery_control_tables', []);
        $liveApplication = collect($live)->except($preserved)->all();
        $candidateApplication = collect($candidate)->except($preserved)->all();

        if ($liveApplication !== $candidateApplication || $liveApplication === []) {
            throw new RuntimeException('Recovery database schema is not compatible with the running application.');
        }

        return array_keys($liveApplication);
    }

    /** @return array<string,list<string>> */
    private function tableSignatures(string $databaseName): array
    {
        $rows = DB::connection('mysql')->select(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, ORDINAL_POSITION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION',
            [$databaseName],
        );

        return collect($rows)
            ->groupBy(fn (object $row): string => (string) $row->TABLE_NAME)
            ->map(fn ($columns): array => $columns->map(
                fn (object $column): string => implode('|', [$column->COLUMN_NAME, $column->COLUMN_TYPE, $column->IS_NULLABLE]),
            )->values()->all())
            ->sortKeys()
            ->all();
    }

    private function reconcileControlReferences(object $connection, string $liveName): void
    {
        $connection->statement(
            "UPDATE `{$liveName}`.`backup_runs` backups LEFT JOIN `{$liveName}`.`users` users ON users.id = backups.requested_by SET backups.requested_by = NULL WHERE backups.requested_by IS NOT NULL AND users.id IS NULL",
        );
        $connection->statement(
            "UPDATE `{$liveName}`.`backup_manifest_objects` bmo LEFT JOIN `{$liveName}`.`stored_objects` stored_object ON stored_object.uuid = bmo.stored_object_uuid SET bmo.stored_object_id = stored_object.id",
        );
    }

    private function assertRecoveryActorsRemainValid(object $connection, string $liveName, string $candidateName): void
    {
        $missing = $connection->selectOne(
            "SELECT COUNT(*) AS failures FROM `{$liveName}`.`recovery_requests` recoveries LEFT JOIN `{$candidateName}`.`users` users ON users.id = recoveries.requested_by WHERE users.id IS NULL",
        );
        if ((int) $missing->failures > 0) {
            throw new RuntimeException('Recovery would remove an administrator required by preserved recovery history.');
        }
    }

    /** @param list<string> $tables */
    private function assertTransactionalTables(object $connection, string $liveName, array $tables): void
    {
        $engines = collect($connection->select(
            'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
            [$liveName, 'BASE TABLE'],
        ))->whereIn('TABLE_NAME', $tables);
        if ($engines->count() !== count($tables) || $engines->contains(fn (object $table): bool => strtoupper((string) $table->ENGINE) !== 'INNODB')) {
            throw new RuntimeException('Recovery requires transactional InnoDB application tables.');
        }
    }

    private function assertDatabaseIdentifier(string $databaseName): void
    {
        if (preg_match('/^[A-Za-z0-9_]+$/D', $databaseName) !== 1) {
            throw new RuntimeException('Production database name is invalid.');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/D', $identifier) !== 1) {
            throw new RuntimeException('Database table name is invalid.');
        }

        return '`'.$identifier.'`';
    }
}
