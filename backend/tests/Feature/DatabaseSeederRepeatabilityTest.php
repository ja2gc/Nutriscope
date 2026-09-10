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

        $newActivities = DB::table('activity_log')
            ->where('id', '>', $lastActivityId)
            ->get(['id', 'event', 'description', 'subject_type', 'subject_id', 'properties']);

        $this->assertSame($first, $this->tableCounts());
        $this->assertSame([
            'accomplishment_report_preparation' => 2,
            'budget-ledger-listener' => 2,
            'purchase-order-lifecycle' => 2,
        ], $newActivities
            ->countBy(fn (object $activity): string => (string) data_get(json_decode($activity->properties, true), 'actor.name'))
            ->sortKeys()
            ->all());
        $this->assertSame($firstRevisionCount + 4, DB::table('audit_revisions')->count());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        return collect(Schema::getTableListing())
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
