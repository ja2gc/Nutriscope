<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AuditActivity;
use App\Models\DietListCount;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MenuCycle;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ProgramProjectActivity;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\Reports\StoreMonthlyDemographicCensuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_browse_filters_selected_report_month_and_sorts_newest_first_before_pagination(): void
    {
        $this->receivedPo('2026-05-10');
        $this->receivedPo('2026-05-11');
        $this->receivedPo('2026-05-12');

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/procurement_pack/instances?covered_month=2026-05&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.instances.0.date', '2026-05-12');

        $this->getJson('/api/rnd/reports/procurement_pack/instances?covered_month=not-a-month')
            ->assertUnprocessable();
    }

    public function test_browse_date_filter_uses_source_date_even_if_report_was_saved_later(): void
    {
        $po = $this->receivedPo('2026-05-10');
        Report::factory()->create([
            'user_id' => $this->rnd->id,
            'type' => 'procurement_pack',
            'status' => 'completed',
            'parameters' => ['purchase_order_id' => $po->id],
            'created_at' => '2026-06-12 08:00:00',
        ]);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/procurement_pack/instances?covered_month=2026-05')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/rnd/reports/procurement_pack/instances?covered_month=2026-06')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_browse_month_includes_periods_that_overlap_it(): void
    {
        DietListCount::factory()->create(['service_date' => '2026-05-10']);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/accomplishment_report/instances?covered_month=2026-05')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/rnd/reports/accomplishment_report/instances?covered_month=2026-06')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_browse_month_includes_menu_week_that_crosses_month_boundary(): void
    {
        MenuCycle::factory()->create(['week_start_date' => '2026-05-30']);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/menu_calendar/instances?covered_month=2026-06')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/rnd/reports/menu_calendar/instances?covered_month=2026-07')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_browse_month_includes_spanning_procurement_and_ppa_reports(): void
    {
        $list = ShoppingList::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-15']);
        $po = $this->receivedPo('2026-05-20');
        $po->update(['shopping_list_id' => $list->id, 'procurement_track' => 'food']);
        ProgramProjectActivity::create([
            'purchase_order_id' => $po->id,
            'activity' => 'Food Subsistence for Patients',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
            'estimated_total_cost' => 1000,
            'estimated_output_patients' => 90,
        ]);

        foreach (['procurement_pack', 'program_project_activity'] as $type) {
            $this->actingAs($this->rnd)
                ->getJson("/api/rnd/reports/{$type}/instances?covered_month=2026-06")
                ->assertOk()->assertJsonPath('meta.total', 1);
            $this->getJson("/api/rnd/reports/{$type}/instances?covered_month=2026-07")
                ->assertOk()->assertJsonPath('meta.total', 0);
        }
    }

    public function test_browse_orders_spanning_reports_by_the_last_date_they_cover(): void
    {
        $longer = ShoppingList::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-15']);
        $shorter = ShoppingList::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-10']);
        $firstOrder = $this->receivedPo('2026-05-20');
        $firstOrder->update(['shopping_list_id' => $longer->id]);
        $secondOrder = $this->receivedPo('2026-05-25');
        $secondOrder->update(['shopping_list_id' => $shorter->id]);

        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/procurement_pack/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.params.purchase_order_id', $firstOrder->id)
            ->assertJsonPath('data.instances.1.params.purchase_order_id', $secondOrder->id);
    }

    public function test_browse_labels_use_po_date_and_menu_week_instead_of_cycle_name(): void
    {
        $po = $this->receivedPo('2026-05-10');
        $menu = MenuCycle::factory()->create(['name' => 'Substance Cycle', 'week_start_date' => '2026-05-11']);

        $poInstance = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/procurement_pack/instances')->assertOk()->json('data.instances.0');
        $menuInstance = $this->getJson('/api/rnd/reports/menu_calendar/instances')->assertOk()->json('data.instances.0');

        $this->assertSame($po->po_number.' — May 10, 2026', $poInstance['label']);
        $this->assertSame('Menu — week of May 11, 2026', $menuInstance['label']);
        $this->assertSame($menu->id, $menuInstance['params']['menu_cycle_id']);
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

    public function test_rnd_can_browse_and_prepare_another_rnds_clinical_context_without_archiving(): void
    {
        Storage::fake('public');
        Storage::fake('private_uploads');
        $creator = User::factory()->rnd()->create();
        $patient = Patient::factory()->create(['admission_date' => '2026-05-10']);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $mealPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);
        app(StoreMonthlyDemographicCensuses::class)->handle(Carbon::parse('2026-06-01', 'Asia/Manila'));

        $this->actingAs($this->rnd, 'sanctum');
        $this->getJson('/api/rnd/reports/ncp_summary/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.params.ncp_record_id', $ncp->id);
        $this->getJson('/api/rnd/reports/patient_menu_plan/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.params.intervention_plan_id', $intervention->uuid);
        $this->getJson('/api/rnd/reports/demographic_census/summary?year=2026&month=5')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('streamBytes')->willReturn("%PDF-1.4\nshared context\n%%EOF");
        $reports->method('buildPdf')->willReturn(['bytes' => "%PDF-1.4\nshared context\n%%EOF", 'meta' => []]);
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('generate')->willReturnCallback(function (Report $report): string {
            $path = "reports/{$report->uuid}.pdf";
            Storage::disk('public')->put($path, '%PDF-shared-context');

            return $path;
        });
        $this->app->instance(ReportService::class, $reports);

        $prepared = $this->post("/api/rnd/reports/ncp_summary/prepare?ncp_record_id={$ncp->id}")
            ->assertOk()->assertJsonPath('data.created_by.id', $this->rnd->uuid)->json('data');
        $this->postJson("/api/rnd/reports/{$prepared['id']}/archive")->assertForbidden();

        $this->assertDatabaseHas('reports', [
            'uuid' => $prepared['id'],
            'user_id' => $this->rnd->id,
            'audit_ncp_record_id' => $ncp->id,
        ]);
        $this->assertSame(0, AuditActivity::query()->where('event', 'archived')->count());
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
            ->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}&prepared_by_name=Someone%20Else")
            ->assertOk()->json('data.id');
        $this->postJson("/api/rnd/reports/{$id}/archive")->assertOk();

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

    public function test_operational_archive_freezes_prepared_bytes_and_unarchive_restores_browse(): void
    {
        Storage::fake('private_uploads');
        $po = $this->receivedPo('2026-05-10', 4000);
        $this->actingAs($this->rnd);
        $prepared = $this->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")
            ->assertOk()->json('data.id');
        $report = Report::query()->where('uuid', $prepared)->firstOrFail();
        $original = Storage::disk('report_cache')->get($report->cache_path);
        $po->update(['total_amount' => 9000]);

        $this->postJson("/api/rnd/reports/{$report->uuid}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $report->refresh();
        $this->assertSame($original, Storage::disk($report->officialFile->storage_disk)->get($report->officialFile->object_key));
        $this->getJson('/api/rnd/reports/procurement_pack/instances')
            ->assertOk()->assertJsonPath('meta.total', 0);

        $this->postJson("/api/rnd/reports/{$report->uuid}/unarchive")
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->getJson('/api/rnd/reports/procurement_pack/instances')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $report->refresh();
        $officialId = $report->official_file_stored_object_id;
        $report->update(['cache_expires_at' => now()->subMinute()]);
        ReportBranding::singleton()->update(['hospital_name' => 'Changed after unarchive']);
        $download = $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();
        $this->assertSame(hash('sha256', $original), hash('sha256', $download->streamedContent()));
        $this->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")->assertOk();
        $this->assertSame($officialId, $report->fresh()->official_file_stored_object_id);
        $this->assertSame($original, Storage::disk($report->officialFile->storage_disk)->get($report->officialFile->object_key));
        $this->postJson("/api/rnd/reports/{$report->uuid}/archive")
            ->assertOk()->assertJsonPath('data.status', 'archived');
        $this->assertSame($officialId, $report->fresh()->official_file_stored_object_id);
    }

    public function test_clinical_report_cannot_be_archived_or_deleted(): void
    {
        $report = Report::factory()->create([
            'user_id' => $this->rnd->id,
            'type' => 'ncp_summary',
            'status' => 'completed',
        ]);

        $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/{$report->uuid}/archive")
            ->assertForbidden();
        $this->deleteJson("/api/rnd/reports/{$report->uuid}")
            ->assertStatus(405);
    }

    // ── Archive ─────────────────────────────────────────────────────────────

    public function test_archive_persists_row_with_file_and_snapshot(): void
    {
        Storage::fake('report_cache');
        $po = $this->receivedPo('2026-05-10', 4000);

        $id = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")
            ->assertOk()->json('data.id');
        $this->postJson("/api/rnd/reports/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $report = Report::firstOrFail();
        $this->assertSame('archived', $report->status);
        $this->assertSame('2026-05-10', $report->report_covered_from?->toDateString());
        $this->assertSame('2026-05-10', $report->report_covered_until?->toDateString());
        $this->assertNull($report->file_path);
        Storage::disk('report_cache')->assertExists($report->cache_path);
        $this->assertNotNull($report->snapshot['branding']['hospital_name'] ?? null);
    }

    public function test_download_serves_frozen_bytes_after_branding_change(): void
    {
        Storage::fake('report_cache');
        $po = $this->receivedPo('2026-05-10');

        $id = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")
            ->assertOk()->json('data.id');
        $this->postJson("/api/rnd/reports/{$id}/archive")->assertOk();
        $report = Report::where('uuid', $id)->firstOrFail();

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
