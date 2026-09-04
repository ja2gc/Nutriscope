<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class RecoveryVerifier
{
    /** @param array{name:string,disposable:bool,promotable:bool,connection:string} $database
     * @return array{passed:bool,checks:array<string,bool>}
     */
    public function verify(array $database): array
    {
        $base = config('database.connections.mysql');
        Config::set('database.connections.recovery_candidate', [...$base, 'database' => $database['name']]);
        DB::purge('recovery_candidate');
        $connection = DB::connection('recovery_candidate');
        $tables = collect($connection->select('SHOW TABLES'))
            ->map(fn (object|array $row): string => (string) array_values((array) $row)[0]);
        $columns = collect($connection->select('SHOW COLUMNS FROM users'))->pluck('Field');
        $role = collect($connection->select("SHOW COLUMNS FROM users WHERE Field = 'role'"))->first();
        $passwordsValid = ! $connection->table('users')->whereNotNull('password')->limit(100)->pluck('password')
            ->contains(fn (string $hash): bool => ! preg_match('/^\$(2[ayb]|argon2(id|i))\$/', $hash));
        $foreignKeysValid = $this->foreignKeysAreValid($connection, $database['name']);
        $checks = [
            'disposable_database' => $database['disposable'] === true,
            'required_schema' => collect(['users', 'migrations', 'backup_runs'])->every(fn (string $table) => $tables->contains($table)),
            'authentication_schema' => collect(['email', 'password', 'role', 'is_active'])->every(fn (string $column) => $columns->contains($column)),
            'role_definitions' => is_object($role) && str_contains((string) ($role->Type ?? ''), "'Admin'"),
            'password_hashes' => $passwordsValid,
            'foreign_keys' => $foreignKeysValid,
        ];

        return ['passed' => ! in_array(false, $checks, true), 'checks' => $checks];
    }

    private function foreignKeysAreValid(object $connection, string $databaseName): bool
    {
        $constraints = collect($connection->select(
            'SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION',
            [$databaseName],
        ))->groupBy(fn (object $row): string => $row->TABLE_NAME.'|'.$row->CONSTRAINT_NAME);

        return $constraints->every(function ($columns) use ($connection, $databaseName): bool {
            $first = $columns->first();
            $child = $this->identifier($first->TABLE_NAME);
            $parent = $this->identifier($first->REFERENCED_TABLE_NAME);
            $joins = $columns->map(fn (object $column): string => 'parent.'.$this->identifier($column->REFERENCED_COLUMN_NAME).' = child.'.$this->identifier($column->COLUMN_NAME))->implode(' AND ');
            $present = $columns->map(fn (object $column): string => 'child.'.$this->identifier($column->COLUMN_NAME).' IS NOT NULL')->implode(' AND ');
            $missing = 'parent.'.$this->identifier($first->REFERENCED_COLUMN_NAME).' IS NULL';
            $database = $this->identifier($databaseName);
            $result = $connection->selectOne("SELECT COUNT(*) AS failures FROM {$database}.{$child} child LEFT JOIN {$database}.{$parent} parent ON {$joins} WHERE {$present} AND {$missing}");

            return (int) $result->failures === 0;
        });
    }

    private function identifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/D', $identifier) !== 1) {
            throw new \RuntimeException('Recovery schema contains an invalid identifier.');
        }

        return '`'.$identifier.'`';
    }
}
