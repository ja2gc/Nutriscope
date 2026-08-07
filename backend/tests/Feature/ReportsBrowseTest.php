<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\ReportService;
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
        Storage::fake('report_cache');
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

    public function test_rnd_can_browse_render_and_archive_another_rnds_clinical_context(): void
    {
        Storage::fake('public');
        $creator = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['admission_date' => '2026-05-10']);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
        ]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->actingAs($this->rnd, 'sanctum');
        $this->getJson('/api/rnd/reports/ncp_summary/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.params.ncp_record_id', $ncp->id);
        $this->getJson('/api/rnd/reports/patient_menu_plan/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.params.meal_plan_id', $mealPlan->id);
        $this->getJson('/api/rnd/reports/demographic_census/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.key', '2026-05');

        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('streamBytes')->willReturn('%PDF-shared-context');
        $reports->method('buildPdf')->willReturn(['bytes' => '%PDF-shared-context', 'meta' => []]);
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('generate')->willReturnCallback(function (Report $report): string {
            $path = "reports/{$report->uuid}.pdf";
            Storage::disk('public')->put($path, '%PDF-shared-context');

            return $path;
        });
        $this->app->instance(ReportService::class, $reports);

        $this->post("/api/rnd/reports/ncp_summary/prepare?ncp_record_id={$ncp->id}")
            ->assertOk();
        $archived = $this->postJson("/api/rnd/reports/ncp_summary/archive?ncp_record_id={$ncp->id}")
            ->assertOk()
            ->assertJsonPath('data.created_by.id', $this->rnd->uuid)
            ->json('data');

        $this->assertDatabaseHas('reports', [
            'uuid' => $archived['id'],
            'user_id' => $this->rnd->id,
            'audit_ncp_record_id' => $ncp->id,
        ]);
        $archiveEvent = AuditActivity::query()->where('event', 'archived')->sole();
        $this->assertSame('clinical', $archiveEvent->category->value);
        $this->assertSame('reports', $archiveEvent->domain->value);
        $this->assertSame('reports', $archiveEvent->module->value);
        $this->assertSame($this->rnd->uuid, $archiveEvent->properties['actor']['public_id']);
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

    public function test_prepare_persists_once_and_legacy_render_requires_preparation(): void
    {
        $po = $this->receivedPo('2026-05-10', 2500);
        $before = Report::count();

        Storage::fake('report_cache');
        $prepared = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")
            ->assertOk();

        $this->assertSame($before + 1, Report::count());
        $this->get("/api/rnd/reports/procurement_pack/render?purchase_order_id={$po->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'preparation_required');
        $this->get('/api/rnd/reports/'.$prepared->json('data.id').'/view')->assertOk();
    }

    // ── Archive ─────────────────────────────────────────────────────────────

    public function test_archive_persists_row_with_file_and_snapshot(): void
    {
        Storage::fake('report_cache');
        $po = $this->receivedPo('2026-05-10', 4000);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/archive?purchase_order_id={$po->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $report = Report::firstOrFail();
        $this->assertSame('archived', $report->status);
        $this->assertNull($report->file_path);
        Storage::disk('report_cache')->assertExists($report->cache_path);
        $this->assertNotNull($report->snapshot['branding']['hospital_name'] ?? null);
    }

    public function test_download_serves_frozen_bytes_after_branding_change(): void
    {
        Storage::fake('report_cache');
        $po = $this->receivedPo('2026-05-10');

        $report = Report::where('uuid',
            $this->actingAs($this->rnd)
                ->postJson("/api/rnd/reports/procurement_pack/archive?purchase_order_id={$po->id}")
                ->json('data.id')
        )->firstOrFail();

        $frozenBytes = Storage::disk('report_cache')->get($report->cache_path);
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

    public function test_view_is_shared_across_active_rnds(): void
    {
        Storage::fake('public');
        $report = $this->archivedReport();

        $other = User::factory()->create(['role' => 'RND']);
        $this->actingAs($other)->get("/api/rnd/reports/{$report->uuid}/view")->assertOk();
    }
}
