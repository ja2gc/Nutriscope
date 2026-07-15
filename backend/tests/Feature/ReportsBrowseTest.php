<?php

namespace Tests\Feature;

use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spec 4 — browse-don't-generate: instance enumeration per axis, on-demand render
 * (no persisted row), and Archive freezing an as-filed copy.
 *
 * Retired report types (dietary_cash_book, budget_report, inventory_report) were
 * removed in the food-service redesign; coverage now uses surviving types.
 */
class ReportsBrowseTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'name' => 'LEGACY BROWSE PREPARER',
            'first_name' => 'Liza Mae',
            'last_name' => 'Del Rosario',
        ]);
        ReportBranding::singleton(); // ensure a branding row exists
    }

    /** Seed a completed PO in a given month (procurement_pack browse source). */
    private function receivedPo(string $date, float $amount = 1000): PurchaseOrder
    {
        return PurchaseOrder::factory()->create([
            'rnd_user_id' => $this->rnd->id,
            'supplier_id' => Supplier::factory(),
            'procurement_track' => 'food',
            'lifecycle_status' => 'completed',
            'completed_at' => $date,
            'order_date' => $date,
            'total_amount' => $amount,
        ]);
    }

    // ── Enumeration ─────────────────────────────────────────────────────────

    public function test_entity_axis_lists_procurement_pack_records(): void
    {
        $this->receivedPo('2026-05-10');
        $this->receivedPo('2026-03-04');

        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/procurement_pack/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'entity')
            ->json('data.instances');

        $this->assertCount(2, $instances);
        $this->assertArrayHasKey('purchase_order_id', $instances[0]['params']);
    }

    public function test_ppa_axis_lists_completed_food_pos(): void
    {
        $po = $this->receivedPo('2026-05-10');
        ProgramProjectActivity::create([
            'purchase_order_id' => $po->id,
            'activity' => 'Food Subsistence for Patients',
            'period_start' => '2026-05-05',
            'period_end' => '2026-05-07',
            'estimated_total_cost' => 1000,
            'estimated_output_patients' => 90,
            'actual_total_cost' => 950,
            'actual_output_patients' => 87,
            'execution_frozen_at' => now(),
        ]);

        $instances = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/program_project_activity/instances')
            ->assertOk()
            ->json('data.instances');

        $this->assertCount(1, $instances);
        $this->assertArrayHasKey('purchase_order_id', $instances[0]['params']);
        $this->assertSame($po->id, $instances[0]['params']['purchase_order_id']);
    }

    public function test_unknown_type_instances_is_404(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/not_a_report/instances')
            ->assertNotFound();
    }

    public function test_retired_report_types_are_404(): void
    {
        foreach (['dietary_cash_book', 'budget_report', 'inventory_report'] as $type) {
            $this->actingAs($this->rnd)
                ->getJson("/api/rnd/reports/{$type}/instances")
                ->assertNotFound();
        }
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

    public function test_fss_cannot_download_a_clinical_report_even_if_owner(): void
    {
        // Defense-in-depth (PO-03): even if a clinical report row were owned by a
        // non-RND, the by-id endpoints reject it on the clinical-type guard.
        $fss = User::factory()->create(['role' => 'FSS']);
        $report = Report::create([
            'user_id' => $fss->id,
            'title' => 'NCP Summary',
            'type' => 'ncp_summary',
            'parameters' => ['ncp_record_id' => 1],
            'status' => 'completed',
        ]);

        $this->actingAs($fss)
            ->get("/api/fss/reports/{$report->uuid}/download")
            ->assertForbidden();
    }

    public function test_archive_prepared_by_is_the_authenticated_user_not_client_supplied(): void
    {
        Storage::fake('public');
        $po = $this->receivedPo('2026-05-10');

        // A client tries to spoof the filer via query params.
        $id = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/archive?purchase_order_id={$po->id}&prepared_by_name=Someone%20Else")
            ->json('data.id');

        $report = Report::where('uuid', $id)->firstOrFail();
        $this->assertSame('Liza Mae Del Rosario', $report->parameters['prepared_by_name']);
    }

    // ── On-demand render ────────────────────────────────────────────────────

    public function test_render_streams_pdf_without_persisting(): void
    {
        $po = $this->receivedPo('2026-05-10', 2500);
        $before = Report::count();

        $res = $this->actingAs($this->rnd)
            ->get("/api/rnd/reports/procurement_pack/render?purchase_order_id={$po->id}");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame($before, Report::count(), 'render must not persist a Report');
    }

    // ── Archive ─────────────────────────────────────────────────────────────

    public function test_archive_persists_row_with_file_and_snapshot(): void
    {
        Storage::fake('public');
        $po = $this->receivedPo('2026-05-10', 4000);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/archive?purchase_order_id={$po->id}")
            ->assertCreated()
            ->assertJsonPath('data.status', 'archived');

        $report = Report::firstOrFail();
        $this->assertSame('archived', $report->status);
        $this->assertNotNull($report->file_path);
        Storage::disk('public')->assertExists($report->file_path);
        $this->assertNotNull($report->snapshot['branding']['hospital_name'] ?? null);
    }

    public function test_download_serves_frozen_bytes_after_branding_change(): void
    {
        Storage::fake('public');
        $po = $this->receivedPo('2026-05-10');

        $report = Report::where('uuid',
            $this->actingAs($this->rnd)
                ->postJson("/api/rnd/reports/procurement_pack/archive?purchase_order_id={$po->id}")
                ->json('data.id')
        )->firstOrFail();

        $frozenBytes = Storage::disk('public')->get($report->file_path);
        $snapshotName = $report->snapshot['branding']['hospital_name'];

        // Mutate branding AFTER archiving.
        ReportBranding::singleton()->update(['hospital_name' => 'COMPLETELY NEW HOSPITAL NAME']);

        // The archived copy is frozen: download serves the same stored bytes.
        $download = $this->actingAs($this->rnd)->get("/api/rnd/reports/{$report->uuid}/download");
        $download->assertOk();
        $this->assertSame($frozenBytes, $download->streamedContent());
        $this->assertNotSame('COMPLETELY NEW HOSPITAL NAME', $snapshotName);
    }

    /** Seed an archived report with a stored (fake) PDF — no DomPDF render needed. */
    private function archivedReport(): Report
    {
        Storage::disk('public')->put('reports/seeded.pdf', '%PDF-1.4 seeded');

        return Report::factory()->create([
            'user_id' => $this->rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'file_path' => 'reports/seeded.pdf',
        ]);
    }

    public function test_view_streams_archived_copy_inline(): void
    {
        Storage::fake('public');
        $report = $this->archivedReport();

        $res = $this->actingAs($this->rnd)->get("/api/rnd/reports/{$report->uuid}/view");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_view_is_owner_scoped(): void
    {
        Storage::fake('public');
        $report = $this->archivedReport();

        $other = User::factory()->create(['role' => 'RND']);
        $this->actingAs($other)->get("/api/rnd/reports/{$report->uuid}/view")->assertForbidden();
    }
}
