<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Exceptions\AuditLoggingUnavailable;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\FoodServiceSetting;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\PurchaseOrderAttachmentStorage;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class PurchaseOrderTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_attachment_and_price_correction_are_semantic_and_rooted_to_the_po(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        [$list] = $this->shoppingList($rnd);

        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::query()->where('shopping_list_id', $list->id)->firstOrFail();
        $group = $po->vendorGroups()->firstOrFail();
        $item = $group->items()->firstOrFail();

        $this->postJson("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'proof',
            'caption' => 'PRIVATE-CAPTION-SENTINEL',
            'file' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ])->assertCreated();
        $this->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'or_number' => 'OR-100',
            'items' => [['id' => $item->id, 'unit_price' => 30, 'reason' => 'PRIVATE-REASON-SENTINEL']],
        ])->assertOk();

        $events = AuditActivity::query()->auditOnly()->where('context_type', $po->getMorphClass())
            ->where('context_id', $po->id)->get();
        $this->assertContains(AuditAction::Approved->value, $events->pluck('event'));
        $this->assertContains(AuditAction::Uploaded->value, $events->pluck('event'));
        $this->assertContains(AuditAction::PriceCorrected->value, $events->pluck('event'));
        $this->assertSame(1, $events->where('event', AuditAction::Approved->value)->count());
        $uploaded = $events->firstWhere('event', AuditAction::Uploaded->value);
        $corrected = $events->firstWhere('event', AuditAction::PriceCorrected->value);
        $this->assertSame($po->getMorphClass(), $uploaded->subject_type);
        $this->assertCount(0, $uploaded->revision->before['attachments']);
        $this->assertCount(1, $uploaded->revision->after['attachments']);
        $this->assertSame($po->getMorphClass(), $corrected->subject_type);
        $this->assertEquals(20.0, $corrected->revision->before['lines'][0]['unit_price']);
        $this->assertEquals(30.0, $corrected->revision->after['lines'][0]['unit_price']);
        $encoded = $events->pluck('properties')->toJson();
        $this->assertStringNotContainsString('PRIVATE-CAPTION-SENTINEL', $encoded);
        $this->assertStringNotContainsString('PRIVATE-REASON-SENTINEL', $encoded);
        $this->assertStringNotContainsString('proof.jpg', $encoded);

        $actions = array_column($this->getJson("/api/fss/purchase-orders/{$po->uuid}/activity")
            ->assertOk()->json('data'), 'action');
        $this->assertContains(AuditAction::Approved->value, $actions);
        $this->assertContains(AuditAction::Uploaded->value, $actions);
        $this->assertContains(AuditAction::PriceCorrected->value, $actions);

        $this->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertUnprocessable();
        $this->assertSame(1, PurchaseOrder::query()->where('shopping_list_id', $list->id)->count());
        $this->assertSame(1, AuditActivity::query()->where('context_type', $po->getMorphClass())
            ->where('context_id', $po->id)->where('event', AuditAction::Approved->value)->count());
    }

    public function test_receiving_completion_and_budget_deduction_appear_in_the_po_trail(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        [$list] = $this->shoppingList($rnd, 'supplies');
        Budget::factory()->create(['fiscal_year' => 2026, 'created_by' => $rnd->id]);

        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::query()->where('shopping_list_id', $list->id)->firstOrFail();
        $group = $po->vendorGroups()->firstOrFail();
        $this->postJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}/attachments", [
            'type' => 'receipt', 'file' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $this->assertSame('completed', $po->fresh()->lifecycle_status);
        $this->assertDatabaseHas('budget_ledger', ['purchase_order_id' => $po->id, 'type' => 'po_deduction']);
        $deduction = BudgetLedger::query()->where('purchase_order_id', $po->id)->firstOrFail();
        $deductionEvent = AuditActivity::query()
            ->where('subject_type', $deduction->getMorphClass())
            ->where('subject_id', $deduction->id)
            ->where('event', AuditAction::Adjusted->value)
            ->sole();
        $this->assertSame($po->getMorphClass(), $deductionEvent->context_type);
        $this->assertSame($po->id, $deductionEvent->context_id);
        $this->assertSame(0, AuditActivity::query()->where('subject_type', (new Inventory)->getMorphClass())->count());
        $actions = array_column($this->getJson("/api/fss/purchase-orders/{$po->uuid}/activity")
            ->assertOk()->json('data'), 'action');
        $this->assertContains(AuditAction::Received->value, $actions);
        $this->assertContains(AuditAction::Completed->value, $actions);
        $this->assertContains(AuditAction::Adjusted->value, $actions);
        app(PurchaseOrderLifecycleService::class)->refresh($po);
        app(PurchaseOrderLifecycleService::class)->refresh($po);
        $this->assertSame(1, AuditActivity::query()->where('context_type', $po->getMorphClass())
            ->where('context_id', $po->id)->where('event', AuditAction::Completed->value)->count());
        $this->assertSame(1, AuditActivity::query()->where('subject_type', $deduction->getMorphClass())
            ->where('subject_id', $deduction->id)->where('event', AuditAction::Adjusted->value)->count());
        $this->assertTrue(Schema::hasIndex('budget_ledger', ['po_deduction_guard'], 'unique'));
    }

    public function test_po_update_delete_and_archive_emit_one_rich_event_without_model_duplicates(): void
    {
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id, 'status' => 'draft']);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($rnd)->patchJson("/api/fss/purchase-orders/{$po->uuid}", ['or_number' => 'OR-200'])->assertOk();
        $this->assertSame([AuditAction::Updated->value], AuditActivity::query()->pluck('event')->all());
        $this->deleteJson("/api/fss/purchase-orders/{$po->uuid}")->assertNoContent();
        $this->assertSame(
            [AuditAction::Updated->value, AuditAction::Deleted->value],
            AuditActivity::query()->orderBy('id')->pluck('event')->all(),
        );

        $completed = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'lifecycle_status' => 'completed',
            'completed_at' => now(),
        ]);
        AuditFixture::delete(AuditActivity::query());
        $this->patchJson("/api/fss/purchase-orders/{$completed->uuid}", ['lifecycle_status' => 'archived'])->assertOk();
        $this->assertSame([AuditAction::Archived->value], AuditActivity::query()->pluck('event')->all());
    }

    public function test_draft_to_ordered_uses_ordered_not_generic_updated(): void
    {
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id, 'status' => 'draft']);
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($rnd)->patchJson("/api/fss/purchase-orders/{$po->uuid}", [
            'status' => 'ordered',
            'or_number' => 'OR-MIXED',
        ])->assertOk();

        $this->assertSame(['ordered'], AuditActivity::query()->pluck('event')->all());
        $this->assertSame(
            ['or_number', 'status'],
            AuditActivity::query()->sole()->properties['details']['changed_fields'],
        );
    }

    public function test_attachment_upload_failure_removes_files_and_rows_and_delete_failure_restores_both(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id]);
        AuditFixture::delete(AuditActivity::query());

        $this->app->instance(AuditLogger::class, $this->failingAuditLogger());
        $this->actingAs($rnd)->postJson("/api/fss/purchase-orders/{$po->uuid}/attachments", [
            'type' => 'proof',
            'file' => UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg'),
        ])->assertServerError();
        $this->assertDatabaseMissing('purchase_order_attachments', ['purchase_order_id' => $po->id]);
        $this->assertSame([], Storage::disk('public')->allFiles('po-attachments'));

        $path = 'po-attachments/existing.jpg';
        Storage::disk('public')->put($path, 'existing-file');
        $attachment = $po->attachments()->create(['type' => 'proof', 'path' => $path]);
        $this->deleteJson("/api/fss/purchase-order-attachments/{$attachment->uuid}")->assertServerError();
        $this->assertDatabaseHas('purchase_order_attachments', ['id' => $attachment->id, 'path' => $path]);
        Storage::disk('public')->assertExists($path);

        $secondPath = 'po-attachments/second.jpg';
        Storage::disk('public')->put($secondPath, 'second-file');
        $po->attachments()->create(['type' => 'proof', 'path' => $secondPath]);
        $this->deleteJson("/api/fss/purchase-orders/{$po->uuid}")->assertServerError();
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_successful_po_delete_removes_all_attachment_files(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id]);
        foreach (['po-attachments/one.jpg', 'po-attachments/two.jpg'] as $path) {
            Storage::disk('public')->put($path, 'file');
            $po->attachments()->create(['type' => 'proof', 'path' => $path]);
        }

        $this->actingAs($rnd)->deleteJson("/api/fss/purchase-orders/{$po->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertSame([], Storage::disk('public')->allFiles('po-attachments'));
        $this->assertSame([], Storage::disk('public')->allFiles('po-attachments-quarantine'));
    }

    public function test_queue_outage_keeps_committed_deletes_and_rolled_back_uploads_quarantined(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id]);
        Storage::disk('public')->put('po-attachments/delete.jpg', 'file');
        $po->attachments()->create(['type' => 'proof', 'path' => 'po-attachments/delete.jpg']);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->actingAs($rnd)->deleteJson("/api/fss/purchase-orders/{$po->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertSame([], Storage::disk('public')->allFiles('po-attachments'));
        $this->assertCount(1, Storage::disk('public')->allFiles('po-attachments-quarantine'));

        $uploadPo = PurchaseOrder::factory()->create(['rnd_user_id' => $rnd->id]);
        $this->app->instance(AuditLogger::class, $this->failingAuditLogger());
        $this->postJson("/api/fss/purchase-orders/{$uploadPo->uuid}/attachments", [
            'type' => 'proof',
            'file' => UploadedFile::fake()->create('upload.jpg', 100, 'image/jpeg'),
        ])->assertServerError();
        $this->assertDatabaseMissing('purchase_order_attachments', ['purchase_order_id' => $uploadPo->id]);
        $this->assertSame([], Storage::disk('public')->allFiles('po-attachments'));
        $this->assertCount(2, Storage::disk('public')->allFiles('po-attachments-quarantine'));
    }

    public function test_partial_restore_attempts_every_quarantined_file_without_throwing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('po-attachments-quarantine/good.jpg', 'file');

        app(PurchaseOrderAttachmentStorage::class)->restoreMany([
            ['original' => 'po-attachments/good.jpg', 'quarantine' => 'po-attachments-quarantine/good.jpg'],
            ['original' => '../invalid.jpg', 'quarantine' => 'po-attachments-quarantine/invalid.jpg'],
        ]);

        Storage::disk('public')->assertExists('po-attachments/good.jpg');
    }

    public function test_vendor_receipt_transition_emits_exactly_one_received_and_no_generic_lifecycle_update(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        [$list] = $this->shoppingList($rnd, 'supplies');
        Budget::factory()->create(['fiscal_year' => 2026, 'created_by' => $rnd->id]);
        $this->actingAs($rnd)->postJson("/api/fss/shopping-lists/{$list->uuid}/approve")->assertCreated();
        $po = PurchaseOrder::query()->where('shopping_list_id', $list->id)->firstOrFail();
        $group = $po->vendorGroups()->firstOrFail();
        $group->attachments()->create([
            'purchase_order_id' => $po->id,
            'type' => 'receipt',
            'path' => 'po-attachments/receipt.jpg',
        ]);
        AuditFixture::delete(AuditActivity::query());

        $this->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", [
            'status' => 'received',
            'or_number' => 'OR-MIXED-RECEIPT',
        ])->assertOk();

        $rootEvents = AuditActivity::query()->where('context_type', $po->getMorphClass())
            ->where('context_id', $po->id)->get();
        $this->assertSame(1, $rootEvents->where('event', AuditAction::Received->value)->count());
        $this->assertSame(0, $rootEvents->where('event', AuditAction::Updated->value)->count());
        $this->assertSame(
            ['or_number', 'received_at', 'status'],
            $rootEvents->firstWhere('event', AuditAction::Received->value)->properties['details']['changed_fields'],
        );
        $catalogPriceAfterFirstReceipt = (float) $group->items()->firstOrFail()->fsItem->fresh()->purchase_price;
        $this->patchJson("/api/fss/purchase-order-vendor-groups/{$group->uuid}", ['status' => 'received'])
            ->assertStatus(422);
        $this->assertSame($catalogPriceAfterFirstReceipt, (float) $group->items()->firstOrFail()->fsItem->fresh()->purchase_price);
        $this->assertSame(1, AuditActivity::query()->where('context_type', $po->getMorphClass())
            ->where('context_id', $po->id)->where('event', AuditAction::Received->value)->count());
    }

    public function test_completion_ledger_and_audit_roll_back_together_when_audit_is_unavailable(): void
    {
        $rnd = User::factory()->rnd()->create();
        Budget::factory()->create(['fiscal_year' => 2026, 'created_by' => $rnd->id]);
        [$list] = $this->shoppingList($rnd, 'supplies');
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id, 'shopping_list_id' => $list->id,
            'procurement_track' => 'supplies', 'lifecycle_status' => 'open_execution', 'status' => 'ordered',
        ]);
        $group = $po->vendorGroups()->create(['status' => 'received', 'total_amount' => 250]);
        $group->attachments()->create([
            'purchase_order_id' => $po->id, 'type' => 'receipt', 'path' => 'po-attachments/receipt.jpg',
        ]);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->method('withoutModelEvents')->willReturnCallback(fn (Closure $callback): mixed => $callback());
        $auditLogger->method('record')->willThrowException(new AuditLoggingUnavailable('forced audit failure'));
        $this->app->instance(AuditLogger::class, $auditLogger);
        $this->expectException(AuditLoggingUnavailable::class);
        try {
            $this->actingAs($rnd);
            app(PurchaseOrderLifecycleService::class)->refresh($po);
        } finally {
            $this->assertSame('open_execution', $po->fresh()->lifecycle_status);
            $this->assertDatabaseMissing('budget_ledger', ['purchase_order_id' => $po->id]);
        }
    }

    public function test_budget_store_records_creator_and_resource_exposes_safe_creator(): void
    {
        $rnd = User::factory()->rnd()->create();
        $response = $this->actingAs($rnd)->postJson('/api/fss/budgets', [
            'fiscal_year' => 2097, 'allocated_amount' => 500000, 'per_head_day_limit' => 250,
        ])->assertCreated();
        $budget = Budget::query()->where('fiscal_year', 2097)->firstOrFail();
        $this->assertSame($rnd->id, $budget->created_by);
        $response->assertJsonPath('data.creator.id', $rnd->uuid)
            ->assertJsonPath('data.creator.name', $rnd->name)
            ->assertJsonStructure(['data' => ['created_at']]);
    }

    public function test_fss_and_admin_budget_activity_routes_use_budget_context_and_are_read_only(): void
    {
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        $admin = User::factory()->admin()->create();
        $budget = Budget::factory()->create(['fiscal_year' => 2096, 'created_by' => $rnd->id]);
        $this->actingAs($rnd)->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2096, 'type' => 'manual_addition', 'amount' => 1000, 'reason' => 'Adjustment',
        ])->assertCreated();

        foreach ([$fss, $admin] as $viewer) {
            $event = $this->actingAs($viewer)->getJson('/api/'.($viewer->role === 'Admin' ? 'admin' : 'fss')."/budgets/{$budget->uuid}/activity")
                ->assertOk()->assertJsonCount(1, 'data')->json('data.0');
            $this->assertSame([
                'id', 'module', 'category', 'domain', 'record_type', 'action', 'action_label',
                'summary', 'severity', 'outcome', 'actor', 'subject', 'context', 'patient',
                'ncp_reference', 'detail_mode', 'reason', 'history', 'current_record_url',
                'occurred_at', 'details', 'changes',
            ], array_keys($event));
            $this->assertSame('operations', $event['category']);
            $this->assertSame('budget', $event['domain']);
            $this->assertArrayNotHasKey('subject_id', $event);
        }
        $this->postJson("/api/admin/budgets/{$budget->uuid}/activity")->assertMethodNotAllowed();
    }

    public function test_ledger_response_identifies_manual_and_system_actors(): void
    {
        $rnd = User::factory()->rnd()->create();
        Budget::factory()->create(['fiscal_year' => 2095, 'created_by' => $rnd->id]);
        BudgetLedger::create(['fiscal_year' => 2095, 'type' => 'manual_addition', 'source' => 'manual', 'amount' => 100, 'created_by' => $rnd->id]);
        BudgetLedger::create(['fiscal_year' => 2095, 'type' => 'po_deduction', 'source' => 'system', 'amount' => 50]);

        $rows = collect($this->actingAs($rnd)->getJson('/api/fss/budgets/ledger?fiscal_year=2095')
            ->assertOk()->json('data'))->keyBy('source');
        $this->assertSame(['kind' => 'user', 'id' => $rnd->uuid, 'name' => $rnd->name], $rows['manual']['actor']);
        $this->assertSame(['kind' => 'system', 'id' => null, 'name' => 'Budget ledger'], $rows['system']['actor']);
    }

    public function test_setting_update_audits_only_a_real_change_with_old_and_new_limit(): void
    {
        $admin = User::factory()->admin()->create();
        FoodServiceSetting::query()->create(['per_head_day_limit' => 100]);
        $this->actingAs($admin)->getJson('/api/admin/food-service-settings')->assertOk();
        $this->assertDatabaseCount('activity_log', 0);
        $this->putJson('/api/admin/food-service-settings', [])->assertOk();
        $this->assertDatabaseCount('activity_log', 0);

        $this->putJson('/api/admin/food-service-settings', ['per_head_day_limit' => 125])->assertOk();
        $activity = AuditActivity::query()->auditOnly()->sole();
        $this->assertSame(AuditAction::SettingsChanged->value, $activity->event);
        $this->assertSame(AuditCategory::Operations, $activity->category);
        $this->assertSame(AuditDomain::FoodService, $activity->domain);
        $this->assertSame(100.0, (float) $activity->properties['details']['old_limit']);
        $this->assertSame(125.0, (float) $activity->properties['details']['new_limit']);
        $this->assertTrue($activity->causer->is($admin));

        $this->putJson('/api/admin/food-service-settings', ['per_head_day_limit' => 125])->assertOk();
        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_meal_service_completion_served_adjustment_and_reversal_use_semantic_po_rooted_events(): void
    {
        $rnd = User::factory()->rnd()->create();
        $fss = User::factory()->fss()->create();
        $item = FsItem::factory()->create();
        $cycle = MenuCycle::factory()->create([
            'rnd_user_id' => $rnd->id,
            'week_start_date' => '2026-06-01',
            'cycle_days' => 1,
        ]);
        MenuCycleDay::factory()->create([
            'menu_cycle_id' => $cycle->id,
            'day_of_week' => 'Monday',
            'meal_type' => 'lunch',
            'fs_item_id' => $item->id,
            'estimate_population' => 20,
        ]);
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-01',
            'procurement_track' => 'food',
        ]);
        $overlapList = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-01',
            'procurement_track' => 'food',
        ]);
        $overlapPo = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'shopping_list_id' => $overlapList->id,
            'procurement_track' => 'food',
        ]);
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'shopping_list_id' => $list->id,
            'procurement_track' => 'food',
        ]);
        MenuCycleDay::query()->where('menu_cycle_id', $cycle->id)
            ->update(['snapshot_purchase_order_id' => $po->id]);
        $suppliesList = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-01',
            'procurement_track' => 'supplies',
        ]);
        $suppliesPo = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'shopping_list_id' => $suppliesList->id,
            'procurement_track' => 'supplies',
        ]);
        AuditFixture::delete(AuditActivity::query());

        $complete = $this->actingAs($fss)->postJson("/api/fss/menu-cycles/{$cycle->uuid}/complete-day", [
            'service_date' => '2026-06-01',
            'served_population' => 18,
        ])->assertCreated();
        $log = MealPrepLog::query()->where('uuid', $complete->json('data.id'))->firstOrFail();
        $this->patchJson("/api/fss/menu-cycles/{$cycle->uuid}/served-population", [
            'service_date' => '2026-06-01',
            'served_population' => 19,
        ])->assertOk();
        $this->postJson("/api/fss/meal-prep-logs/{$log->uuid}/reverse")->assertOk();
        $this->postJson("/api/fss/meal-prep-logs/{$log->uuid}/reverse")->assertUnprocessable();

        $events = AuditActivity::query()->auditOnly()
            ->where('context_type', $po->getMorphClass())->where('context_id', $po->id)->get();
        $this->assertContains(AuditAction::Completed->value, $events->pluck('event'));
        $this->assertContains(AuditAction::Adjusted->value, $events->pluck('event'));
        $this->assertContains(AuditAction::Reversed->value, $events->pluck('event'));
        $this->assertSame(1, $events->where('event', AuditAction::Reversed->value)->count());
        $this->assertSame(0, $events->whereIn('event', ['created', 'updated'])->count());
        $this->assertSame(0, AuditActivity::query()
            ->where('context_type', $suppliesPo->getMorphClass())
            ->where('context_id', $suppliesPo->id)
            ->count());
        $this->assertSame(0, AuditActivity::query()
            ->where('context_type', $overlapPo->getMorphClass())
            ->where('context_id', $overlapPo->id)
            ->count());

        $legacy = AuditActivity::query()->create([
            'log_name' => 'audit',
            'event' => 'reversed',
            'description' => 'Legacy meal reversal',
            'subject_type' => $log->getMorphClass(),
            'subject_id' => $log->id,
        ]);
        $log->update(['purchase_order_id' => null]);
        $ids = collect($this->getJson("/api/fss/purchase-orders/{$po->uuid}/activity")->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($legacy->public_id));
        $overlapIds = collect($this->getJson("/api/fss/purchase-orders/{$overlapPo->uuid}/activity")->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($overlapIds->contains($legacy->public_id));
    }

    public function test_diet_list_count_has_one_safe_food_service_event(): void
    {
        $fss = User::factory()->fss()->create();
        $this->actingAs($fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-01',
            'ward' => 'WARD-SENTINEL',
            'population' => 25,
            'helped_food_prep' => true,
        ])->assertCreated();

        $activity = AuditActivity::query()->auditOnly()->sole();
        $this->assertSame(AuditAction::Created->value, $activity->event);
        $this->assertSame(AuditDomain::FoodService, $activity->domain);
        $this->assertStringNotContainsString('WARD-SENTINEL', $activity->properties->toJson());
    }

    public function test_every_task_seven_route_is_marked_implemented(): void
    {
        $policies = collect(config('audit.route_coverage'))->where('owner_task', 7);
        $this->assertNotEmpty($policies);
        $this->assertTrue($policies->every(fn (array $policy): bool => $policy['implementation_state'] === 'implemented'));
    }

    public function test_deduction_guard_migration_preserves_legacy_duplicates_and_round_trips_exactly(): void
    {
        $database = 'nutriscope_operations_contract_'.Str::lower(Str::random(10));
        $admin = 'operations_contract_admin';
        $connection = 'operations_contract_migration';
        $originalDefault = config('database.default');
        $base = config('database.connections.mysql');

        config([
            "database.connections.{$admin}" => [...$base, 'url' => null, 'database' => 'information_schema'],
            "database.connections.{$connection}" => [...$base, 'url' => null, 'database' => $database],
        ]);

        try {
            DB::connection($admin)->statement("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $schema = Schema::connection($connection);
            $schema->create('purchase_orders', fn (Blueprint $table) => $table->id());
            $schema->create('budget_ledger', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->string('type');
            });
            DB::connection($connection)->table('purchase_orders')->insert(['id' => 1]);
            DB::connection($connection)->table('budget_ledger')->insert([
                ['purchase_order_id' => 1, 'type' => 'po_deduction'],
                ['purchase_order_id' => 1, 'type' => 'po_deduction'],
            ]);
            $beforeIndexes = $schema->getIndexes('budget_ledger');
            $beforeForeignKeys = $schema->getForeignKeys('budget_ledger');

            config(['database.default' => $connection]);
            DB::purge($connection);
            $migration = require database_path('migrations/2026_07_12_034159_enforce_unique_po_budget_deductions.php');
            $migration->up();

            $this->assertSame(2, DB::connection($connection)->table('budget_ledger')->whereNull('po_deduction_guard')->count());
            DB::connection($connection)->table('budget_ledger')->insert([
                'purchase_order_id' => 1,
                'po_deduction_guard' => 1,
                'type' => 'po_deduction',
            ]);
            try {
                DB::connection($connection)->table('budget_ledger')->insert([
                    'purchase_order_id' => 1,
                    'po_deduction_guard' => 1,
                    'type' => 'po_deduction',
                ]);
                $this->fail('Future duplicate deduction guard was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }

            $migration->down();
            $this->assertFalse($schema->hasColumn('budget_ledger', 'po_deduction_guard'));
            $this->assertSame($beforeIndexes, $schema->getIndexes('budget_ledger'));
            $this->assertSame($beforeForeignKeys, $schema->getForeignKeys('budget_ledger'));
        } finally {
            config(['database.default' => $originalDefault]);
            DB::purge($connection);
            DB::connection($admin)->statement("DROP DATABASE IF EXISTS `{$database}`");
            DB::purge($admin);
        }
    }

    public function test_budget_index_uses_bounded_queries_for_multiple_budgets(): void
    {
        $fss = User::factory()->fss()->create();
        foreach ([2091, 2092, 2093] as $year) {
            Budget::factory()->create(['fiscal_year' => $year]);
            BudgetLedger::create(['fiscal_year' => $year, 'type' => 'manual_addition', 'amount' => 100]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($fss)->getJson('/api/fss/budgets')->assertOk()->assertJsonCount(3, 'data');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, count($queries));
    }

    private function shoppingList(User $rnd, string $track = 'food'): array
    {
        $supplier = Supplier::factory()->create();
        $item = FsItem::factory()->create(['default_supplier_id' => $supplier->id]);
        $list = ShoppingList::factory()->create([
            'rnd_user_id' => $rnd->id, 'period_start' => '2026-06-01', 'period_end' => '2026-06-01',
            'procurement_track' => $track, 'status' => 'draft',
        ]);
        $list->items()->create([
            'fs_item_id' => $item->id, 'ingredient_name' => $item->name, 'qty' => 5, 'unit' => 'kg',
            'supplier_id' => $supplier->id, 'unit_price' => 20, 'total' => 100,
        ]);

        return [$list, $supplier];
    }

    private function failingAuditLogger(): AuditLogger
    {
        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->method('withoutModelEvents')->willReturnCallback(fn (Closure $callback): mixed => $callback());
        $auditLogger->method('record')->willThrowException(new AuditLoggingUnavailable('forced audit failure'));

        return $auditLogger;
    }
}
