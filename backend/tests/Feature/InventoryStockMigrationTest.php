<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class InventoryStockMigrationTest extends TestCase
{
    public function test_forward_only_rollback_fails_without_losing_migration_history(): void
    {
        $database = 'nutriscope_inventory_contract_'.Str::lower(Str::random(10));
        $admin = 'inventory_contract_admin';
        $connection = 'inventory_contract_migration';
        $base = config('database.connections.mysql');

        config([
            "database.connections.{$admin}" => [...$base, 'url' => null, 'database' => 'information_schema'],
            "database.connections.{$connection}" => [...$base, 'url' => null, 'database' => $database],
        ]);

        try {
            DB::connection($admin)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            Schema::connection($connection)->create('inventory', function (Blueprint $table): void {
                $table->id();
                $table->decimal('quantity_in_stock', 10, 2)->default(0);
                $table->string('unit');
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->text('notes')->nullable();
            });
            $path = database_path('migrations/2026_07_11_000004_drop_retired_inventory_stock_fields.php');

            $this->artisan('migrate', [
                '--database' => $connection,
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ])->assertExitCode(0);

            try {
                $this->artisan('migrate:rollback', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--realpath' => true,
                    '--step' => 1,
                    '--force' => true,
                ]);
                $this->fail('Forward-only inventory migration rollback unexpectedly succeeded.');
            } catch (LogicException $exception) {
                $this->assertSame(
                    'Retired inventory stock fields cannot be restored safely.',
                    $exception->getMessage(),
                );
            }

            $this->assertSame(1, DB::connection($connection)->table('migrations')
                ->where('migration', '2026_07_11_000004_drop_retired_inventory_stock_fields')->count());
            $this->assertFalse(Schema::connection($connection)->hasColumn('inventory', 'quantity_in_stock'));

            $this->artisan('migrate', [
                '--database' => $connection,
                '--path' => $path,
                '--realpath' => true,
                '--force' => true,
            ])->assertExitCode(0);
            $this->assertSame(1, DB::connection($connection)->table('migrations')->count());
        } finally {
            DB::purge($connection);
            DB::connection($admin)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($admin);
        }
    }
}
