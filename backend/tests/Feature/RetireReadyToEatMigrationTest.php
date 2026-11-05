<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RetireReadyToEatMigrationTest extends TestCase
{
    public static function databaseDrivers(): array
    {
        return [['sqlite'], ['mysql']];
    }

    #[DataProvider('databaseDrivers')]
    public function test_upgrade_preserves_food_nutrients_and_historical_snapshots(string $driver): void
    {
        $database = 'nutriscope_retired_flag_contract_'.bin2hex(random_bytes(6));
        if ($driver === 'mysql') {
            DB::connection('mysql')->statement("CREATE DATABASE `{$database}`");
            config(['database.default' => 'retired_flag_contract', 'database.connections.retired_flag_contract' => [
                ...config('database.connections.mysql'), 'url' => null, 'database' => $database,
            ]]);
        } else {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
            DB::purge('sqlite');
        }

        try {
            $this->assertRetirementPreservesOtherData();
        } finally {
            if ($driver === 'mysql') {
                DB::purge('retired_flag_contract');
                DB::connection('mysql')->statement("DROP DATABASE `{$database}`");
            }
        }
    }

    private function assertRetirementPreservesOtherData(): void
    {
        Schema::create('food_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->decimal('calories', 8, 2);
            $table->boolean('ready_to_eat')->nullable();
        });
        Schema::create('audit_revisions', function (Blueprint $table): void {
            $table->id();
            $table->json('before');
        });
        foreach ([true, false, null] as $index => $flag) {
            DB::table('food_items')->insert([
                'name' => 'Historical food '.$index, 'category' => 'fruit',
                'calories' => 80 + $index, 'ready_to_eat' => $flag,
            ]);
        }
        DB::table('audit_revisions')->insert(['before' => json_encode(['ready_to_eat' => true])]);
        $foods = DB::table('food_items')->get(['id', 'name', 'category', 'calories'])->toArray();
        $history = DB::table('audit_revisions')->get()->toArray();

        $path = database_path('migrations/2026_11_06_000001_drop_retired_ready_to_eat_from_food_items.php');
        $this->assertFileExists($path);
        (require $path)->up();

        $this->assertFalse(Schema::hasColumn('food_items', 'ready_to_eat'));
        $this->assertEquals($foods, DB::table('food_items')->get()->toArray());
        $this->assertEquals($history, DB::table('audit_revisions')->get()->toArray());
    }
}
