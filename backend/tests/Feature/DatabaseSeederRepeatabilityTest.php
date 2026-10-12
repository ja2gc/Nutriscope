<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederRepeatabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_demo_seed_is_repeatable_across_all_application_tables(): void
    {
        Storage::fake((string) config('filesystems.private_uploads'));

        $this->seed(DatabaseSeeder::class);
        $first = $this->tableCounts();
        $lastActivityId = (int) DB::table('activity_log')->max('id');
        $firstRevisionCount = DB::table('audit_revisions')->count();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($first, $this->tableCounts());
        $this->assertSame($lastActivityId, (int) DB::table('activity_log')->max('id'));
        $this->assertSame($firstRevisionCount, DB::table('audit_revisions')->count());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        return collect(Schema::getTableListing(DB::connection()->getDatabaseName()))
            ->reject(fn (string $table): bool => in_array(
                str($table)->afterLast('.')->toString(),
                ['migrations', 'activity_log', 'audit_revisions'],
                true,
            ))
            ->sort()
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
