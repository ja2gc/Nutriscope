<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NameMigrationTest extends TestCase
{
    public function test_split_name_migrations_backfill_rollback_and_re_forward_safely(): void
    {
        $database = 'nutriscope_name_contract_'.Str::lower(Str::random(10));
        $admin = 'name_contract_admin';
        $connection = 'name_contract_migration';
        $base = config('database.connections.mysql');
        $schemaPath = database_path('migrations/2026_07_15_000001_add_split_names_to_users_and_patients.php');
        $dataPath = database_path('migrations/2026_07_15_000002_backfill_split_names_for_users_and_patients.php');

        $this->assertFileExists($schemaPath);
        $this->assertFileExists($dataPath);

        config([
            "database.connections.{$admin}" => [...$base, 'url' => null, 'database' => 'information_schema'],
            "database.connections.{$connection}" => [...$base, 'url' => null, 'database' => $database],
        ]);

        try {
            DB::connection($admin)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->createLegacyTables($connection);

            $this->migratePath($connection, $schemaPath);
            $this->insertLegacyRows($connection);
            $this->migratePath($connection, $dataPath);

            $this->assertBackfilledState($connection);
            $this->assertSame(0, DB::connection($connection)->table('activity_log')->count());

            $this->rollbackPath($connection, $dataPath);
            $this->rollbackPath($connection, $schemaPath);

            $this->assertFalse(Schema::connection($connection)->hasColumn('users', 'first_name'));
            $this->assertFalse(Schema::connection($connection)->hasColumn('patients', 'last_name'));
            $this->assertSame(
                'Maria Luisa De la Cruz',
                DB::connection($connection)->table('users')->where('id', 1)->value('name'),
            );

            $this->migratePath($connection, $schemaPath);
            $this->migratePath($connection, $dataPath);

            $this->assertSame(
                ['first_name' => 'Maria Luisa De la Cruz', 'last_name' => null],
                (array) DB::connection($connection)->table('users')
                    ->select('first_name', 'last_name')
                    ->where('id', 1)
                    ->first(),
            );
            $this->assertSame(
                ['first_name' => 'Legacy Value Must Stay', 'last_name' => null],
                (array) DB::connection($connection)->table('users')
                    ->select('first_name', 'last_name')
                    ->where('id', 3)
                    ->first(),
            );
        } finally {
            DB::purge($connection);
            DB::connection($admin)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($admin);
        }
    }

    private function createLegacyTables(string $connection): void
    {
        Schema::connection($connection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::connection($connection)->create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::connection($connection)->create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('description')->nullable();
        });
    }

    private function insertLegacyRows(string $connection): void
    {
        $users = [
            ['id' => 1, 'name' => 'Maria Luisa De la Cruz', 'first_name' => null, 'last_name' => null, 'deleted_at' => null],
            ['id' => 2, 'name' => 'Ana Mae San Jose', 'first_name' => null, 'last_name' => null, 'deleted_at' => now()],
            ['id' => 3, 'name' => 'Legacy Value Must Stay', 'first_name' => 'Existing Split Name', 'last_name' => 'Existing Last', 'deleted_at' => null],
            ['id' => 4, 'name' => 'Partial Legacy Must Stay', 'first_name' => null, 'last_name' => 'Existing Surname', 'deleted_at' => null],
        ];
        for ($id = 5; $id <= 505; $id++) {
            $users[] = [
                'id' => $id,
                'name' => "Legacy User {$id}",
                'first_name' => null,
                'last_name' => null,
                'deleted_at' => null,
            ];
        }
        DB::connection($connection)->table('users')->insert($users);
        DB::connection($connection)->table('patients')->insert([
            ['id' => 1, 'name' => 'Juan Miguel Dela Cruz III', 'first_name' => null, 'last_name' => null],
            ['id' => 2, 'name' => 'Patient Legacy Must Stay', 'first_name' => 'Existing Patient', 'last_name' => 'Existing Last'],
        ]);
    }

    private function assertBackfilledState(string $connection): void
    {
        $this->assertSame(
            ['name' => 'Maria Luisa De la Cruz', 'first_name' => 'Maria Luisa De la Cruz', 'last_name' => null],
            (array) DB::connection($connection)->table('users')
                ->select('name', 'first_name', 'last_name')
                ->where('id', 1)
                ->first(),
        );
        $this->assertSame('Ana Mae San Jose', DB::connection($connection)->table('users')->where('id', 2)->value('first_name'));
        $this->assertSame(
            ['first_name' => 'Existing Split Name', 'last_name' => 'Existing Last'],
            (array) DB::connection($connection)->table('users')
                ->select('first_name', 'last_name')
                ->where('id', 3)
                ->first(),
        );
        $this->assertSame(
            ['first_name' => null, 'last_name' => 'Existing Surname'],
            (array) DB::connection($connection)->table('users')
                ->select('first_name', 'last_name')
                ->where('id', 4)
                ->first(),
        );
        $this->assertSame('Legacy User 505', DB::connection($connection)->table('users')->where('id', 505)->value('first_name'));
        $this->assertSame(
            ['name' => 'Juan Miguel Dela Cruz III', 'first_name' => 'Juan Miguel Dela Cruz III', 'last_name' => null],
            (array) DB::connection($connection)->table('patients')
                ->select('name', 'first_name', 'last_name')
                ->where('id', 1)
                ->first(),
        );
        $this->assertSame(
            ['first_name' => 'Existing Patient', 'last_name' => 'Existing Last'],
            (array) DB::connection($connection)->table('patients')
                ->select('first_name', 'last_name')
                ->where('id', 2)
                ->first(),
        );
    }

    private function migratePath(string $connection, string $path): void
    {
        $this->artisan('migrate', [
            '--database' => $connection,
            '--path' => $path,
            '--realpath' => true,
            '--force' => true,
        ])->assertExitCode(0);
    }

    private function rollbackPath(string $connection, string $path): void
    {
        $this->artisan('migrate:rollback', [
            '--database' => $connection,
            '--path' => $path,
            '--realpath' => true,
            '--step' => 1,
            '--force' => true,
        ])->assertExitCode(0);
    }
}
