<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\Generators\InventoryReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spec 4 — browse-don't-generate: instance enumeration per axis, on-demand render
 * (no persisted row), and Archive freezing an as-filed copy.
 */
class ReportsBrowseTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
        ReportBranding::singleton(); // ensure a branding row exists
    }

    /** Seed a received PO in a given month. */
    private function receivedPo(string $date, float $amount = 1000): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'supplier_id' => Supplier::factory(),
            'status'      => 'received',
            'order_date'  => $date,
            'total_amount' => $amount,
        ]);
    }

    // ── Enumeration ─────────────────────────────────────────────────────────

    public function test_period_axis_lists_only_months_with_data(): void
    {
        $this->receivedPo('2026-05-10');
        $this->receivedPo('2026-05-22');
        $this->receivedPo('2026-03-04');
        // a draft PO must NOT create a bucket
        PurchaseOrder::factory()->create(['status' => 'draft', 'order_date' => '2026-01-01']);

        $res = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/dietary_cash_book/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'period');

        $instances = $res->json('data.instances');
        $this->assertCount(2, $instances);                       // May + March only
        $this->assertSame('2026-05', $instances[0]['key']);       // newest first
        $this->assertSame('2026-05-01', $instances[0]['params']['start']);
        $this->assertSame('2026-05-31', $instances[0]['params']['end']);
    }

    public function test_period_axis_filters_by_year_and_month(): void
    {
        $this->receivedPo('2026-05-10');
        $this->receivedPo('2026-03-04');

        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/dietary_cash_book/instances?year=2026&month=3')
            ->assertOk()
            ->json('data.instances');

        $this->assertCount(1, $instances);
        $this->assertSame('2026-03', $instances[0]['key']);
    }

    public function test_entity_axis_lists_records(): void
    {
        Budget::factory()->create(['name' => 'Q2 Subsistence']);
        Budget::factory()->create(['name' => 'Q1 Subsistence']);

        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/budget_report/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'entity')
            ->json('data.instances');

        $this->assertCount(2, $instances);
        $this->assertArrayHasKey('budget_id', $instances[0]['params']);
    }

    public function test_menu_cycle_axis_lists_cycles(): void
    {
        MenuCycle::factory()->create(['name' => 'Cycle A']);

        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/program_project_activity/instances')
            ->assertOk()
            ->json('data.instances');

        $this->assertCount(1, $instances);
        $this->assertSame('Cycle A', $instances[0]['label']);
        $this->assertArrayHasKey('menu_cycle_id', $instances[0]['params']);
    }

    public function test_singleton_axis_has_one_current_instance(): void
    {
        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/inventory_report/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'singleton')
            ->json('data.instances');

        $this->assertCount(1, $instances);
        $this->assertSame('current', $instances[0]['key']);
    }

    public function test_unknown_type_instances_is_404(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/not_a_report/instances')
            ->assertNotFound();
    }

    // ── Clinical reports are RND-only (PHI guard) ───────────────────────────

    public function test_fss_cannot_browse_clinical_reports(): void
    {
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($fss)
            ->getJson('/api/fss/reports/patient_menu_plan/instances')
            ->assertForbidden();
        $this->actingAs($fss)
            ->get('/api/fss/reports/demographic_census/render?start=2026-05-01&end=2026-05-31')
            ->assertForbidden();
    }

    public function test_rnd_can_browse_clinical_reports(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/patient_menu_plan/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'entity');
    }

    public function test_archive_prepared_by_is_the_authenticated_user_not_client_supplied(): void
    {
        Storage::fake('public');
        $this->receivedPo('2026-05-10');

        // A client tries to spoof the filer via query params.
        $id = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports/dietary_cash_book/archive?start=2026-05-01&end=2026-05-31&prepared_by_name=Someone%20Else')
            ->json('data.id');

        $report = Report::findOrFail($id);
        $this->assertSame($this->rnd->name, $report->parameters['prepared_by_name']);
    }

    // ── On-demand render ────────────────────────────────────────────────────

    public function test_render_streams_pdf_without_persisting(): void
    {
        $this->receivedPo('2026-05-10', 2500);
        $before = Report::count();

        $res = $this->actingAs($this->rnd)
            ->get('/api/rnd/reports/dietary_cash_book/render?start=2026-05-01&end=2026-05-31');

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame($before, Report::count(), 'render must not persist a Report');
    }

    public function test_budget_report_renders_pdf_with_svg_chart(): void
    {
        // A2b — budget report includes a server-side SVG trend chart; this guards the
        // blade + SVG actually render through DomPDF (no JS).
        $budget = Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'allocated_amount' => 5000,
            'budget_per_head_day' => 100, 'population' => 10,
            'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
        ]);
        $cycle = MenuCycle::factory()->create();
        \App\Models\MealPrepLog::create([
            'menu_cycle_id' => $cycle->id, 'service_date' => '2026-05-10',
            'status' => 'completed', 'total_value' => 1200, 'population' => 10, 'has_shortfall' => false,
        ]);

        $res = $this->actingAs($this->rnd)
            ->get("/api/rnd/reports/budget_report/render?budget_id={$budget->id}&start=2026-05-01&end=2026-05-31&granularity=day");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_cashbook_derives_replenishment_from_budget(): void
    {
        // A5 — the Dietary Cash Book is now reproducible: replenishment is derived from
        // Budget allocations overlapping the period, not from a report parameter.
        Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'scope' => 'monthly',
            'allocated_amount' => 8000, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
        ]);
        $this->receivedPo('2026-05-10', 2000);

        $gen  = new \App\Services\Reports\Generators\DietaryCashBookGenerator();
        $data = $gen->data(new Report([
            'type' => 'dietary_cash_book',
            'parameters' => ['start' => '2026-05-01', 'end' => '2026-05-31'],
        ]));

        $this->assertEqualsWithDelta(8000, $data['total_replenishment'], 0.01);
        $this->assertEqualsWithDelta(2000, $data['total_disbursement'], 0.01);
        $this->assertEqualsWithDelta(6000, $data['ending_balance'], 0.01); // 0 + 8000 − 2000
    }

    public function test_explicit_replenishment_param_overrides_budget_derivation(): void
    {
        // Back-compat: an explicit replenishment param still wins over budget derivation.
        Budget::factory()->create([
            'rnd_user_id' => $this->rnd->id, 'scope' => 'monthly',
            'allocated_amount' => 8000, 'period_start' => '2026-05-01', 'period_end' => '2026-05-31',
        ]);

        $gen  = new \App\Services\Reports\Generators\DietaryCashBookGenerator();
        $data = $gen->data(new Report([
            'type' => 'dietary_cash_book',
            'parameters' => ['start' => '2026-05-01', 'end' => '2026-05-31', 'replenishment' => 3000],
        ]));

        $this->assertEqualsWithDelta(3000, $data['total_replenishment'], 0.01);
    }

    public function test_render_404_when_no_data_for_period(): void
    {
        $this->receivedPo('2026-05-10');

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/dietary_cash_book/render?start=2030-01-01&end=2030-01-31')
            ->assertNotFound();
    }

    // ── Archive ─────────────────────────────────────────────────────────────

    public function test_archive_persists_row_with_file_and_snapshot(): void
    {
        Storage::fake('public');
        $this->receivedPo('2026-05-10', 4000);

        $res = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports/dietary_cash_book/archive?start=2026-05-01&end=2026-05-31')
            ->assertCreated()
            ->assertJsonPath('data.status', 'archived');

        $report = Report::firstOrFail();
        $this->assertSame('archived', $report->status);
        $this->assertNotNull($report->file_path);
        Storage::disk('public')->assertExists($report->file_path);
        $this->assertNotNull($report->snapshot['branding']['hospital_name'] ?? null);
        $this->assertSame('2026-05-01', $report->snapshot['params']['start']);
    }

    public function test_archive_404_when_no_data(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports/dietary_cash_book/archive?start=2030-01-01&end=2030-01-31')
            ->assertNotFound();
    }

    public function test_download_serves_frozen_bytes_after_branding_change(): void
    {
        Storage::fake('public');
        $this->receivedPo('2026-05-10');

        $report = Report::findOrFail(
            $this->actingAs($this->rnd)
                ->postJson('/api/rnd/reports/dietary_cash_book/archive?start=2026-05-01&end=2026-05-31')
                ->json('data.id')
        );

        $frozenBytes = Storage::disk('public')->get($report->file_path);
        $snapshotName = $report->snapshot['branding']['hospital_name'];

        // Mutate branding AFTER archiving.
        ReportBranding::singleton()->update(['hospital_name' => 'COMPLETELY NEW HOSPITAL NAME']);

        // The archived copy is frozen: download serves the same stored bytes, and the
        // snapshot still holds the as-filed branding (not the new value).
        $download = $this->actingAs($this->rnd)->get("/api/rnd/reports/{$report->id}/download");
        $download->assertOk();
        $this->assertSame($frozenBytes, $download->streamedContent());
        $this->assertNotSame('COMPLETELY NEW HOSPITAL NAME', $snapshotName);
    }

    /** Seed an archived report with a stored (fake) PDF — no DomPDF render needed. */
    private function archivedReport(): Report
    {
        Storage::disk('public')->put('reports/seeded.pdf', '%PDF-1.4 seeded');

        return Report::factory()->create([
            'user_id'   => $this->rnd->id,
            'type'      => 'dietary_cash_book',
            'status'    => 'archived',
            'file_path' => 'reports/seeded.pdf',
        ]);
    }

    public function test_view_streams_archived_copy_inline(): void
    {
        Storage::fake('public');
        $report = $this->archivedReport();

        $res = $this->actingAs($this->rnd)->get("/api/rnd/reports/{$report->id}/view");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_view_is_owner_scoped(): void
    {
        Storage::fake('public');
        $report = $this->archivedReport();

        $other = User::factory()->create(['role' => 'RND']);
        $this->actingAs($other)->get("/api/rnd/reports/{$report->id}/view")->assertForbidden();
    }

    // ── Spec-1 immutability through the new path ────────────────────────────

    public function test_received_figures_are_stable_when_catalog_price_changes(): void
    {
        $item = FsItem::factory()->create(['purchase_price' => 100]);
        Inventory::factory()->create([
            'item_type'         => 'ingredient',
            'fs_item_id'        => $item->id,
            'quantity_in_stock' => 20,
            'unit_price'        => 25, // frozen last-received cost (₱/base)
        ]);

        $generator = new InventoryReportGenerator();
        $report    = new Report(['type' => 'inventory_report', 'parameters' => []]);

        $before = $generator->data($report)['total_value'];

        $item->update(['purchase_price' => 99999]); // catalog price drifts (unit_cost accessor)

        $after = $generator->data($report)['total_value'];

        $this->assertSame($before, $after, 'stored unit_price must win over live catalog');
        $this->assertEqualsWithDelta(500.0, $after, 0.001); // 20 × 25
    }
}
