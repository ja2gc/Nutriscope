<?php

namespace Tests\Feature;

use App\Http\Resources\PurchaseOrderResource;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\FsItem;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FSS\PurchaseOrderLifecycleService;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseOrderCompletionPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_po_display_total_sums_current_vendor_totals(): void
    {
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'total_amount' => 300,
            'procurement_track' => 'supplies',
        ]);
        $po->vendorGroups()->create(['status' => 'received', 'total_amount' => 100]);
        $po->vendorGroups()->create(['status' => 'pending', 'total_amount' => 250]);

        $payload = (new PurchaseOrderResource($po->load('vendorGroups')))->toArray(Request::create('/'));

        $this->assertSame(350.0, $payload['current_total_amount']);
        $this->assertSame('300.00', $po->fresh()->total_amount);
    }

    public function test_food_po_waits_for_every_span_day_served_population_then_calculates_actual_per_head(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        ReportBranding::singleton()->update(['hospital_name' => 'Hospital at PO completion']);
        $template = ReportTemplate::create([
            'type' => 'procurement_pack',
            'name' => 'Procurement Pack',
            'blade_view' => 'reports.procurement-pack',
            'signatories' => [['role' => 'approved_by', 'label' => 'Approved by', 'name' => 'Original Director', 'title' => 'Director']],
        ]);
        $inspectionTemplate = ReportTemplate::create([
            'type' => 'inspection_report',
            'name' => 'Inspection Report',
            'blade_view' => 'reports.procurement-pack',
            'signatories' => [['role' => 'inspected_by', 'label' => 'Inspected by', 'name' => 'Original Inspector', 'title' => 'Inspector']],
        ]);
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        $supplier = Supplier::factory()->create();
        $previousSupplier = Supplier::factory()->create();
        $item = FsItem::factory()->create([
            'default_supplier_id' => $previousSupplier->id,
        ]);
        Budget::factory()->create(['fiscal_year' => 2026]);

        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $rnd->id,
            'week_start_date' => '2026-06-01',
            'cycle_days' => 2,
            'status' => 'active',
            'is_active' => true,
        ]);

        foreach (['Monday', 'Tuesday'] as $day) {
            MenuCycleDay::factory()->create([
                'menu_cycle_id' => $cycle->id,
                'day_of_week' => $day,
                'meal_type' => 'lunch',
                'fs_item_id' => $item->id,
                'estimate_population' => 100,
            ]);
        }

        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id,
            'name' => 'June subsistence',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-02',
            'days_span' => 2,
            'procurement_track' => 'food',
            'list_type' => 'suggested',
            'status' => 'converted',
            'estimate_population' => 100,
        ]);

        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'shopping_list_id' => $list->id,
            'supplier_id' => null,
            'procurement_track' => 'food',
            'lifecycle_status' => 'open_execution',
            'status' => 'draft',
            'total_amount' => 200,
        ]);
        $group = $po->vendorGroups()->create([
            'supplier_id' => $supplier->id,
            'status' => 'received',
            'total_amount' => 200,
            'received_at' => now(),
        ]);
        $po->items()->create([
            'vendor_group_id' => $group->id,
            'fs_item_id' => $item->id,
            'description' => $item->name,
            'qty' => 10,
            'unit' => 'g',
            'unit_price' => 20,
            'total_value' => 200,
            'actual_qty' => 10,
            'actual_unit_price' => 20,
        ]);
        $group->attachments()->create([
            'purchase_order_id' => $po->id,
            'type' => 'receipt',
            'path' => 'po-attachments/receipt.jpg',
        ]);
        $group->attachments()->create([
            'purchase_order_id' => $po->id,
            'type' => 'proof',
            'path' => 'po-attachments/proof.jpg',
        ]);

        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-01',
            'served_population' => 80,
            'status' => 'completed',
            'completed_by' => $fss->id,
        ]);

        $this->actingAs($rnd);
        $service = app(PurchaseOrderLifecycleService::class);
        $service->createPpaSnapshot($po, $list);
        $service->refresh($po);

        $po->refresh();
        $this->assertSame('open_execution', $po->lifecycle_status);
        $this->assertNull($po->actual_budget_per_head_per_day);
        $this->assertNull($group->fresh()->stocked_at);
        $this->assertNotEquals(20, (float) $item->fresh()->purchase_price);

        MealPrepLog::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'service_date' => '2026-06-02',
            'served_population' => 120,
            'status' => 'completed',
            'completed_by' => $fss->id,
        ]);

        $service->refresh($po);

        $po->refresh();
        $this->assertSame('completed', $po->lifecycle_status);
        $this->assertNotNull($group->fresh()->stocked_at);
        $this->assertSame(20.0, (float) $item->fresh()->purchase_price);
        $this->assertSame($supplier->id, $item->fresh()->default_supplier_id);
        $this->assertSame('1.00', $po->actual_budget_per_head_per_day);
        $deduction = BudgetLedger::query()->where('purchase_order_id', $po->id)->where('type', 'po_deduction')->sole();
        $this->assertSame(2026, $deduction->fiscal_year);
        $this->assertEqualsWithDelta(200, (float) $deduction->amount, 0.01);
        $this->assertSame('Hospital at PO completion', $po->report_configuration_snapshot['branding']['hospital_name']);
        $this->assertSame('Original Director', $po->report_configuration_snapshot['templates']['procurement_pack']['signatories'][0]['name']);
        $this->assertSame(1, Report::query()->where('type', 'procurement_pack')->where('parameters->purchase_order_id', $po->id)->count());
        $this->assertSame(1, Report::query()->where('type', 'program_project_activity')->where('parameters->purchase_order_id', $po->id)->count());
        $originalPack = Report::query()->where('type', 'procurement_pack')->where('parameters->purchase_order_id', $po->id)->sole();
        $originalBranding = $originalPack->snapshot['branding']['hospital_name'];
        $originalDirector = $originalPack->snapshot['signatories'][0]['name'];
        $originalInspector = app(ProcurementPackGenerator::class)->data($originalPack)['air_signatories'][0]['name'];
        $this->assertNotNull($originalPack->official_file_stored_object_id);
        $frozenBytes = Storage::disk($originalPack->officialFile->storage_disk)->get($originalPack->officialFile->object_key);
        $originalPack->forceFill(['cache_expires_at' => now()->subMinute()])->save();
        $view = $this->actingAs($rnd)->get("/api/rnd/reports/{$originalPack->uuid}/view")->assertOk();
        $this->assertSame($frozenBytes, $view->streamedContent());
        $download = $this->actingAs($rnd)->get("/api/rnd/reports/{$originalPack->uuid}/download")->assertOk();
        $this->assertSame($frozenBytes, $download->streamedContent());
        $originalPpa = Report::query()->where('type', 'program_project_activity')->where('parameters->purchase_order_id', $po->id)->sole();
        $frozenPpaBytes = Storage::disk($originalPpa->officialFile->storage_disk)->get($originalPpa->officialFile->object_key);
        $originalPpa->forceFill(['cache_expires_at' => now()->subMinute()])->save();
        $ppaView = $this->actingAs($rnd)->get("/api/rnd/reports/{$originalPpa->uuid}/view")->assertOk();
        $this->assertSame($frozenPpaBytes, $ppaView->streamedContent());

        ReportBranding::singleton()->update(['hospital_name' => 'Hospital after PO completion']);
        $template->update(['signatories' => [['role' => 'approved_by', 'label' => 'Approved by', 'name' => 'New Director', 'title' => 'Director']]]);
        $inspectionTemplate->update(['signatories' => [['role' => 'inspected_by', 'label' => 'Inspected by', 'name' => 'New Inspector', 'title' => 'Inspector']]]);
        $prepared = $this->actingAs($rnd)->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")->assertOk();
        $this->assertSame($originalBranding, $prepared->json('data.snapshot.branding.hospital_name'));
        $this->assertSame($originalDirector, $prepared->json('data.snapshot.signatories.0.name'));
        $report = Report::query()->where('uuid', $prepared->json('data.id'))->firstOrFail();
        $this->assertSame($originalPack->official_file_stored_object_id, $report->official_file_stored_object_id);
        $this->assertSame($originalInspector, app(ProcurementPackGenerator::class)->data($report)['air_signatories'][0]['name']);
        $originalVendor = $supplier->name;
        $supplier->update(['name' => 'Vendor renamed after completion']);
        $this->assertSame($originalVendor, app(ProcurementPackGenerator::class)->data($report)['packs'][0]['supplier']->name);
        $completed = AuditActivity::query()
            ->whereIn('event', ['completed', 'updated'])
            ->where('subject_type', PurchaseOrder::class)
            ->where('subject_id', $po->id)
            ->sole();
        $this->assertSame(PurchaseOrder::class, $completed->subject_type);
        $this->assertSame('open_execution', $completed->revision->before['lifecycle_status']);
        $this->assertSame('completed', $completed->revision->after['lifecycle_status']);

        $payload = (new PurchaseOrderResource($po->fresh([
            'vendorGroups.attachments',
            'shoppingList',
            'programProjectActivity',
        ])))->toArray(Request::create('/'));
        $this->assertSame('PO — June subsistence', $payload['display_name']);

        $this->assertSame([
            'expected' => 2,
            'done' => 2,
            'served' => 200,
        ], $payload['served_population_progress']);
    }
}
