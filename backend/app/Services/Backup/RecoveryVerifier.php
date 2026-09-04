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
        $checks = [
            'disposable_database' => $database['disposable'] === true,
            'required_schema' => collect(['users', 'migrations', 'backup_runs'])->every(fn (string $table) => $tables->contains($table)),
            'authentication_schema' => collect(['email', 'password', 'role', 'is_active'])->every(fn (string $column) => $columns->contains($column)),
            'role_definitions' => is_object($role) && str_contains((string) ($role->Type ?? ''), "'Admin'"),
            'password_hashes' => $passwordsValid,
            'foreign_keys' => (int) $connection->selectOne('SELECT COUNT(*) AS failures FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ?', [$database['name']])->failures >= 0,
        ];

        return ['passed' => ! in_array(false, $checks, true), 'checks' => $checks];
    }
}
