<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\FSS\AccomplishmentReportArchiveService;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Accomplishment Report generator — per-staff pay-period duty sheet (FSS §4).
 *
 * Covers:
 *  - generator data() returns correct task marks and headcount per day per staff
 *  - off-duty day renders as 'off-duty'
 *  - numeric row (apportioned_food) carries the population figure
 *  - checkmark rows carry '✓' for true, '–' for false
 *  - on-demand render streams a PDF (no persisted Report row)
 *  - instances endpoint lists the period buckets that contain data
 */
class AccomplishmentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $fss1;

    private User $fss2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('report_cache');
        $this->fss1 = User::factory()->create([
            'role' => 'FSS',
            'name' => 'LEGACY ALICE',
            'first_name' => 'Alice',
            'last_name' => 'Reyes',
        ]);
        $this->fss2 = User::factory()->create([
            'role' => 'FSS',
            'name' => 'LEGACY BOB',
            'first_name' => 'Bob',
            'last_name' => 'Santos',
        ]);
        ReportBranding::singleton(); // ensure branding row exists for PDF render
    }

    // ─── helpers ────────────────────────────────────────────────────────────────

    /** Build a transient Report for the generator. */
    private function makeReport(array $params): Report
    {
        $r = new Report;
        $r->type = 'accomplishment_report';
        $r->parameters = $params;

        return $r;
    }

    /** Seed a DietListCount row. */
    private function seedCount(User $user, string $date, array $overrides = []): DietListCount
    {
        return DietListCount::factory()->create(array_merge([
            'fss_user_id' => $user->id,
            'service_date' => $date,
            'population' => 30,
        ], $overrides));
    }

    // ─── generator data() unit tests ────────────────────────────────────────────

    public function test_data_contains_both_staff_sheets(): void
    {
        $this->seedCount($this->fss1, '2026-06-01', ['helped_food_prep' => true, 'population' => 25]);
        $this->seedCount($this->fss2, '2026-06-01', ['helped_food_prep' => true, 'population' => 15]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-01', 'to' => '2026-06-01'])
        );

        $names = collect($data['staff_sheets'])->pluck('user.display_name')->all();
        $this->assertContains('Alice Reyes', $names);
        $this->assertContains('Bob Santos', $names);
    }

    public function test_current_report_view_uses_staff_display_name(): void
    {
        $this->seedCount($this->fss1, '2026-06-01');
        $report = $this->makeReport(['from' => '2026-06-01', 'to' => '2026-06-01']);
        $data = (new AccomplishmentReportGenerator)->data($report);
        $html = view('reports.accomplishment', [
            ...$data,
            'branding' => ReportBranding::singleton(),
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $this->assertStringContainsString('Alice Reyes', $html);
        $this->assertStringNotContainsString('LEGACY ALICE', $html);
    }

    public function test_checkmark_row_marks_true_as_tick_and_false_as_dash(): void
    {
        $this->seedCount($this->fss1, '2026-06-02', [
            'helped_food_prep' => true,
            'stored_supplies' => false,
        ]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-02', 'to' => '2026-06-02'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        $this->assertSame('✓', $sheet['task_rows']['helped_food_prep']['2026-06-02']);
        $this->assertSame('–', $sheet['task_rows']['stored_supplies']['2026-06-02']);
    }

    public function test_apportioned_food_row_carries_population_number(): void
    {
        $this->seedCount($this->fss1, '2026-06-03', [
            'apportioned_food' => true,
            'population' => 42,
        ]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-03', 'to' => '2026-06-03'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        $this->assertSame(42, $sheet['task_rows']['apportioned_food']['2026-06-03']);
    }

    public function test_multiple_ward_rows_are_combined_for_one_staff_day(): void
    {
        $this->seedCount($this->fss1, '2026-06-03', [
            'ward' => 'Ward A',
            'apportioned_food' => true,
            'helped_food_prep' => true,
            'population' => 18,
        ]);
        $this->seedCount($this->fss1, '2026-06-03', [
            'ward' => 'Ward B',
            'apportioned_food' => true,
            'stored_supplies' => true,
            'population' => 24,
        ]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-03', 'to' => '2026-06-03'])
        );
        $sheet = collect($data['staff_sheets'])
            ->first(fn ($sheet) => $sheet['user']->id === $this->fss1->id);

        $this->assertSame(42, $sheet['task_rows']['apportioned_food']['2026-06-03']);
        $this->assertSame('✓', $sheet['task_rows']['helped_food_prep']['2026-06-03']);
        $this->assertSame('✓', $sheet['task_rows']['stored_supplies']['2026-06-03']);
    }

    public function test_off_duty_day_renders_as_x(): void
    {
        $this->seedCount($this->fss1, '2026-06-04', ['off_duty' => true]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-04', 'to' => '2026-06-04'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        // All task columns should be 'off-duty' for this day.
        foreach (array_keys(AccomplishmentReportGenerator::TASKS) as $task) {
            $this->assertSame('X', $sheet['task_rows'][$task]['2026-06-04'],
                "Task [{$task}] should be 'X' on an off-duty day");
        }
    }

    public function test_daily_population_sums_across_all_staff(): void
    {
        // Two staff contribute different headcounts on the same day.
        $this->seedCount($this->fss1, '2026-06-05', ['population' => 20]);
        $this->seedCount($this->fss2, '2026-06-05', ['population' => 18]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-05', 'to' => '2026-06-05'])
        );

        $this->assertSame(38, $data['daily_population']['2026-06-05']);
    }

    public function test_days_with_no_data_show_dash(): void
    {
        // Only seed day 1; day 2 has no row for fss1.
        $this->seedCount($this->fss1, '2026-06-01', ['helped_food_prep' => true]);

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport(['from' => '2026-06-01', 'to' => '2026-06-02'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        $this->assertSame('–', $sheet['task_rows']['helped_food_prep']['2026-06-02']);
    }

    public function test_fss_user_id_filter_restricts_to_one_staff(): void
    {
        $this->seedCount($this->fss1, '2026-06-01');
        $this->seedCount($this->fss2, '2026-06-01');

        $data = (new AccomplishmentReportGenerator)->data(
            $this->makeReport([
                'from' => '2026-06-01',
                'to' => '2026-06-01',
                'fss_user_id' => $this->fss1->id,
            ])
        );

        $this->assertCount(1, $data['staff_sheets']);
        $this->assertSame($this->fss1->id, $data['staff_sheets'][0]['user']->id);
    }

    // ─── HTTP / render tests ─────────────────────────────────────────────────────

    public function test_prepare_saves_report_and_preview_streams_current_pdf(): void
    {
        $this->seedCount($this->fss1, '2026-06-10', ['helped_food_prep' => true]);
        $before = Report::count();

        $prepared = $this->actingAs($this->fss1)
            ->postJson('/api/fss/reports/accomplishment_report/prepare', ['start' => '2026-06-10', 'end' => '2026-06-10'])
            ->assertOk();
        $res = $this->get('/api/fss/reports/'.$prepared->json('data.id').'/view')->assertOk();

        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->streamedContent());
        $this->assertSame($before + 1, Report::count());
    }

    public function test_render_returns_404_when_no_data(): void
    {
        $this->actingAs($this->fss1)
            ->postJson('/api/fss/reports/accomplishment_report/prepare', ['start' => '2099-01-01', 'end' => '2099-01-31'])
            ->assertNotFound();
    }

    public function test_instances_lists_months_with_data(): void
    {
        $this->seedCount($this->fss1, '2026-06-01');
        $this->seedCount($this->fss2, '2026-05-15');

        // FSS sees only their own months (fss.md §8 — FSS can only view their own).
        $data = $this->actingAs($this->fss1)
            ->getJson('/api/fss/reports/accomplishment_report/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'period')
            ->json('data');

        $keys = collect($data['instances'])->pluck('key')->all();
        $this->assertContains('2026-06', $keys);
        $this->assertNotContains('2026-05', $keys, 'FSS must not see another staff member\'s months');
    }

    public function test_rnd_instances_lists_all_fss_months(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->seedCount($this->fss1, '2026-06-01');
        $this->seedCount($this->fss2, '2026-05-15');

        $data = $this->actingAs($rnd)
            ->getJson('/api/rnd/reports/accomplishment_report/instances')
            ->assertOk()
            ->json('data');

        $keys = collect($data['instances'])->pluck('key')->all();
        $this->assertContains('2026-06', $keys);
        $this->assertContains('2026-05', $keys, 'RND must see all FSS staff months');
    }

    public function test_rnd_can_also_render_accomplishment_report(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->seedCount($this->fss1, '2026-06-10');

        $this->actingAs($rnd)
            ->postJson('/api/rnd/reports/accomplishment_report/prepare', ['start' => '2026-06-10', 'end' => '2026-06-10'])
            ->assertOk();
    }

    public function test_rnd_index_sees_fss_filed_accomplishment_reports(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        // An accomplishment report archived by FSS staff (owned by an FSS user).
        $fssReport = Report::create([
            'user_id' => $this->fss1->id,
            'title' => 'Accomplishment May 01-15',
            'type' => 'accomplishment_report',
            'status' => 'archived',
        ]);

        $ids = collect(
            $this->actingAs($rnd)->getJson('/api/rnd/reports')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertContains($fssReport->uuid, $ids,
            'RND should see accomplishment reports filed by FSS staff');
    }

    public function test_fss_index_still_only_shows_own_accomplishment_reports(): void
    {
        // FSS staff sees their own accomplishment report...
        $own = Report::create([
            'user_id' => $this->fss1->id,
            'title' => 'Mine',
            'type' => 'accomplishment_report',
            'status' => 'archived',
        ]);
        // ...but not another FSS staff's row (index is owner-scoped for FSS).
        $other = Report::create([
            'user_id' => $this->fss2->id,
            'title' => 'Theirs',
            'type' => 'accomplishment_report',
            'status' => 'archived',
        ]);

        $ids = collect(
            $this->actingAs($this->fss1)->getJson('/api/fss/reports')->assertOk()->json('data')
        )->pluck('id')->all();

        $this->assertContains($own->uuid, $ids);
        $this->assertNotContains($other->uuid, $ids);
    }

    public function test_weekly_accomplishment_report_is_prepared_after_each_day_has_staff_entry(): void
    {
        foreach (CarbonPeriod::create('2026-06-01', '2026-06-06') as $date) {
            $this->actingAs($this->fss1)
                ->postJson('/api/fss/diet-list-counts', [
                    'service_date' => $date->toDateString(),
                    'ward' => 'Ward A',
                    'population' => 10,
                    'helped_food_prep' => true,
                    'apportioned_food' => true,
                ])
                ->assertCreated();
        }

        $this->assertDatabaseMissing('reports', [
            'user_id' => $this->fss1->id,
            'type' => 'accomplishment_report',
        ]);

        $this->actingAs($this->fss1)
            ->postJson('/api/fss/diet-list-counts', [
                'service_date' => '2026-06-07',
                'ward' => 'Off duty',
                'population' => 0,
                'off_duty' => true,
            ])
            ->assertCreated();

        $report = Report::where('user_id', $this->fss1->id)
            ->where('type', 'accomplishment_report')
            ->firstOrFail();

        $this->assertSame('completed', $report->status);
        $this->assertSame('2026-06-01', $report->parameters['start']);
        $this->assertSame('2026-06-07', $report->parameters['end']);
        $this->assertSame($this->fss1->id, $report->parameters['fss_user_id']);
        $this->assertSame('Alice Reyes', $report->parameters['prepared_by_name']);
        $this->assertSame('Alice Reyes', $report->snapshot['params']['prepared_by_name']);
        $this->assertNull($report->file_path);
        $this->assertNotNull($report->cache_path);
    }

    public function test_manual_archive_snapshots_current_prepared_by_display_name(): void
    {
        Storage::fake('public');
        $this->seedCount($this->fss1, '2026-06-10');

        $this->actingAs($this->fss1, 'sanctum')
            ->postJson('/api/fss/reports/accomplishment_report/archive', [
                'start' => '2026-06-10',
                'end' => '2026-06-10',
            ])
            ->assertOk();

        $report = Report::query()->where('user_id', $this->fss1->id)->latest('id')->firstOrFail();
        $this->assertSame('Alice Reyes', $report->parameters['prepared_by_name']);
        $this->assertSame('Alice Reyes', $report->snapshot['params']['prepared_by_name']);
    }

    public function test_historical_staff_name_snapshot_remains_unchanged(): void
    {
        $report = new Report([
            'type' => 'accomplishment_report',
            'snapshot' => [
                'accomplishment' => [
                    'from' => '2026-06-01',
                    'to' => '2026-06-01',
                    'period_label' => 'June 1-1, 2026',
                    'days' => ['2026-06-01'],
                    'tasks' => AccomplishmentReportGenerator::TASKS,
                    'numeric_task' => 'apportioned_food',
                    'daily_population' => ['2026-06-01' => 1],
                    'staff_sheets' => [[
                        'user' => ['id' => 999, 'name' => 'Historical Staff Label'],
                        'task_rows' => [],
                    ]],
                ],
            ],
        ]);

        $data = (new AccomplishmentReportGenerator)->data($report);

        $this->assertSame('Historical Staff Label', $data['staff_sheets'][0]['user']->display_name);
        $this->assertSame('Historical Staff Label', $data['staff_sheets'][0]['user']->name);
    }

    public function test_prepared_weekly_report_refreshes_after_later_diet_list_changes(): void
    {
        foreach (CarbonPeriod::create('2026-06-01', '2026-06-07') as $date) {
            $this->seedCount($this->fss1, $date->toDateString(), [
                'helped_food_prep' => true,
                'population' => $date->toDateString() === '2026-06-07' ? 0 : 10,
                'off_duty' => $date->toDateString() === '2026-06-07',
            ]);
        }

        app(AccomplishmentReportArchiveService::class)
            ->archiveCompletedWeek($this->fss1, '2026-06-07');

        $report = Report::where('user_id', $this->fss1->id)
            ->where('type', 'accomplishment_report')
            ->firstOrFail();

        $beforeHash = $report->content_hash;
        $createdAt = $report->created_at->copy();

        DietListCount::where('fss_user_id', $this->fss1->id)
            ->whereDate('service_date', '2026-06-07')
            ->update(['off_duty' => false, 'helped_food_prep' => true, 'population' => 99]);

        app(AccomplishmentReportArchiveService::class)
            ->archiveCompletedWeek($this->fss1, '2026-06-07');

        $report->refresh();
        $this->assertNotSame($beforeHash, $report->content_hash);
        $this->assertTrue($report->created_at->equalTo($createdAt));
    }
}
