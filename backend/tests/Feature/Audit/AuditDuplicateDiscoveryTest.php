<?php

namespace Tests\Feature\Audit;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\AuditActivity;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDuplicateDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private const BEHAVIORAL_CONTRACTS = [
        'model observer plus explicit writer' => [
            'tests/Feature/OperationsAuditTest.php',
            'test_explicit_fs_item_event_replaces_model_event',
            ['created'],
        ],
        'purchase-order conversion and ordering' => [
            'tests/Feature/Audit/PurchaseOrderTrailTest.php',
            'test_draft_to_ordered_uses_ordered_not_generic_updated',
            ['ordered'],
        ],
        'purchase-order receiving' => [
            'tests/Feature/Audit/PurchaseOrderTrailTest.php',
            'test_vendor_receipt_transition_emits_exactly_one_received_and_no_generic_lifecycle_update',
            ['received'],
        ],
        'budget purchase-order deduction' => [
            'tests/Feature/BudgetAuditTest.php',
            'test_po_deduction_ledger_creation_writes_system_audit_event',
            ['adjusted'],
        ],
        'report view download and delete' => [
            'tests/Feature/Audit/ReportAuditTest.php',
            'test_report_views_downloads_and_deletes_emit_safe_semantic_events',
            ['viewed', 'downloaded', 'deleted'],
        ],
        'report generation retry' => [
            'tests/Feature/Audit/ReportAuditTest.php',
            'test_generate_report_duplicate_delivery_is_terminal_and_failure_is_once',
            ['generated'],
        ],
        'RND Food Library create' => [
            'tests/Feature/OperationsAuditTest.php',
            'test_successful_operation_is_explicitly_logged_but_failed_validation_is_not',
            ['created'],
        ],
        'RND Food Library update' => [
            'tests/Feature/FoodItemControllerTest.php',
            'test_rnd_can_update_food_item',
            ['updated'],
        ],
        'RND Food Library delete' => [
            'tests/Feature/FoodItemControllerTest.php',
            'test_rnd_can_delete_food_item',
            ['deleted'],
        ],
        'RND Food Library no-op update' => [
            'tests/Feature/OperationsAuditTest.php',
            'test_same_value_food_item_update_emits_no_event',
            [],
        ],
        'RND recipe create' => [
            'tests/Feature/RecipeControllerTest.php',
            'test_rnd_can_create_recipe_with_ingredients',
            ['created'],
        ],
        'RND recipe update' => [
            'tests/Feature/RecipeControllerTest.php',
            'test_rnd_can_update_recipe_ingredients',
            ['updated'],
        ],
        'RND recipe delete' => [
            'tests/Feature/RecipeControllerTest.php',
            'test_rnd_can_delete_recipe',
            ['deleted'],
        ],
        'patient and NCP clinical changes' => [
            'tests/Feature/Audit/ClinicalTrailTest.php',
            'test_every_clinical_model_create_update_delete_is_rooted_and_phi_free',
            ['created', 'updated', 'deleted'],
        ],
        'authentication and security failures' => [
            'tests/Feature/Audit/SecurityAuditTest.php',
            'test_each_rejected_http_request_increments_recurrence_once',
            ['authorization_denied'],
        ],
    ];

    public function test_explicit_food_service_writer_suppresses_the_model_observer_duplicate(): void
    {
        $actor = User::factory()->rnd()->create();

        $this->actingAs($actor, 'sanctum')->postJson('/api/fss/fs-items', [
            'name' => 'Duplicate discovery rice',
            'kind' => 'ingredient',
            'base_unit' => 'kg',
            'purchase_price' => 55,
        ])->assertCreated();

        $event = AuditActivity::query()->sole();
        $this->assertSame('created', $event->event);
        $this->assertSame('food_service', $event->domain->value);
        $this->assertSame($actor->id, $event->causer_id);
    }

    public function test_duplicate_budget_listener_delivery_keeps_one_ledger_and_one_event(): void
    {
        $shoppingList = ShoppingList::factory()->create(['period_start' => '2026-06-01']);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'shopping_list_id' => $shoppingList->id,
            'total_amount' => 45000,
        ]);
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);

        $listener = app(BudgetLedgerListener::class);
        $listener->handle(new PurchaseOrderCompleted($purchaseOrder));
        $listener->handle(new PurchaseOrderCompleted($purchaseOrder));

        $this->assertSame(1, BudgetLedger::query()->where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertSame(1, AuditActivity::query()->where('event', 'adjusted')->count());
    }

    public function test_every_required_duplicate_family_is_bound_to_a_behavioral_contract(): void
    {
        $this->assertSame([
            'model observer plus explicit writer',
            'purchase-order conversion and ordering',
            'purchase-order receiving',
            'budget purchase-order deduction',
            'report view download and delete',
            'report generation retry',
            'RND Food Library create',
            'RND Food Library update',
            'RND Food Library delete',
            'RND Food Library no-op update',
            'RND recipe create',
            'RND recipe update',
            'RND recipe delete',
            'patient and NCP clinical changes',
            'authentication and security failures',
        ], array_keys(self::BEHAVIORAL_CONTRACTS));

        foreach (self::BEHAVIORAL_CONTRACTS as [$path, $method, $events]) {
            $source = file_get_contents(base_path($path));
            $this->assertStringContainsString($method, $source, $path);
            foreach ($events as $event) {
                $this->assertStringContainsString("'{$event}'", $source, $method);
            }
        }
    }
}
