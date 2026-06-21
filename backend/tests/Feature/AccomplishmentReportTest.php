<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->fss1 = User::factory()->create(['role' => 'FSS', 'name' => 'Alice Reyes']);
        $this->fss2 = User::factory()->create(['role' => 'FSS', 'name' => 'Bob Santos']);
        ReportBranding::singleton(); // ensure branding row exists for PDF render
    }

    // ─── helpers ────────────────────────────────────────────────────────────────

    /** Build a transient Report for the generator. */
    private function makeReport(array $params): Report
    {
        $r = new Report();
        $r->type       = 'accomplishment_report';
        $r->parameters = $params;

        return $r;
    }

    /** Seed a DietListCount row. */
    private function seedCount(User $user, string $date, array $overrides = []): DietListCount
    {
        return DietListCount::factory()->create(array_merge([
            'fss_user_id'  => $user->id,
            'service_date' => $date,
            'population'   => 30,
        ], $overrides));
    }

    // ─── generator data() unit tests ────────────────────────────────────────────

    public function test_data_contains_both_staff_sheets(): void
    {
        $this->seedCount($this->fss1, '2026-06-01', ['helped_food_prep' => true, 'population' => 25]);
        $this->seedCount($this->fss2, '2026-06-01', ['helped_food_prep' => true, 'population' => 15]);

        $data = (new AccomplishmentReportGenerator())->data(
            $this->makeReport(['from' => '2026-06-01', 'to' => '2026-06-01'])
        );

        $names = collect($data['staff_sheets'])->pluck('user.name')->all();
        $this->assertContains('Alice Reyes', $names);
        $this->assertContains('Bob Santos', $names);
    }

    public function test_checkmark_row_marks_true_as_tick_and_false_as_dash(): void
    {
        $this->seedCount($this->fss1, '2026-06-02', [
            'helped_food_prep' => true,
            'stored_supplies'  => false,
        ]);

        $data = (new AccomplishmentReportGenerator())->data(
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
            'population'       => 42,
        ]);

        $data = (new AccomplishmentReportGenerator())->data(
            $this->makeReport(['from' => '2026-06-03', 'to' => '2026-06-03'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        $this->assertSame(42, $sheet['task_rows']['apportioned_food']['2026-06-03']);
    }

    public function test_off_duty_day_renders_as_off_duty_string(): void
    {
        $this->seedCount($this->fss1, '2026-06-04', ['off_duty' => true]);

        $data = (new AccomplishmentReportGenerator())->data(
            $this->makeReport(['from' => '2026-06-04', 'to' => '2026-06-04'])
        );

        $sheet = collect($data['staff_sheets'])
            ->first(fn ($s) => $s['user']->id === $this->fss1->id);

        // All task columns should be 'off-duty' for this day.
        foreach (array_keys(AccomplishmentReportGenerator::TASKS) as $task) {
            $this->assertSame('off-duty', $sheet['task_rows'][$task]['2026-06-04'],
                "Task [{$task}] should be 'off-duty' on an off-duty day");
        }
    }

    public function test_daily_population_sums_across_all_staff(): void
    {
        // Two staff contribute different headcounts on the same day.
        $this->seedCount($this->fss1, '2026-06-05', ['population' => 20]);
        $this->seedCount($this->fss2, '2026-06-05', ['population' => 18]);

        $data = (new AccomplishmentReportGenerator())->data(
            $this->makeReport(['from' => '2026-06-05', 'to' => '2026-06-05'])
        );

        $this->assertSame(38, $data['daily_population']['2026-06-05']);
    }

    public function test_days_with_no_data_show_dash(): void
    {
        // Only seed day 1; day 2 has no row for fss1.
        $this->seedCount($this->fss1, '2026-06-01', ['helped_food_prep' => true]);

        $data = (new AccomplishmentReportGenerator())->data(
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

        $data = (new AccomplishmentReportGenerator())->data(
            $this->makeReport([
                'from'        => '2026-06-01',
                'to'          => '2026-06-01',
                'fss_user_id' => $this->fss1->id,
            ])
        );

        $this->assertCount(1, $data['staff_sheets']);
        $this->assertSame($this->fss1->id, $data['staff_sheets'][0]['user']->id);
    }

    // ─── HTTP / render tests ─────────────────────────────────────────────────────

    public function test_render_streams_pdf_without_persisting(): void
    {
        $this->seedCount($this->fss1, '2026-06-10', ['helped_food_prep' => true]);
        $before = Report::count();

        $res = $this->actingAs($this->fss1)
            ->get('/api/fss/reports/accomplishment_report/render?start=2026-06-10&end=2026-06-10');

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame($before, Report::count(), 'render must not persist a Report row');
    }

    public function test_render_returns_404_when_no_data(): void
    {
        $this->actingAs($this->fss1)
            ->get('/api/fss/reports/accomplishment_report/render?start=2099-01-01&end=2099-01-31')
            ->assertNotFound();
    }

    public function test_instances_lists_months_with_data(): void
    {
        $this->seedCount($this->fss1, '2026-06-01');
        $this->seedCount($this->fss2, '2026-05-15');

        $data = $this->actingAs($this->fss1)
            ->getJson('/api/fss/reports/accomplishment_report/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'period')
            ->json('data');

        $keys = collect($data['instances'])->pluck('key')->all();
        $this->assertContains('2026-06', $keys);
        $this->assertContains('2026-05', $keys);
    }

    public function test_rnd_can_also_render_accomplishment_report(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $this->seedCount($this->fss1, '2026-06-10');

        $this->actingAs($rnd)
            ->get('/api/rnd/reports/accomplishment_report/render?start=2026-06-10&end=2026-06-10')
            ->assertOk();
    }
}
