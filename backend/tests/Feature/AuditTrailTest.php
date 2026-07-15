<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\BudgetLedger;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\AuditFixture;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_catalog_edit_logs_safe_values_with_causer(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create();
        AuditFixture::delete(Activity::query());

        $fs->update(['category' => 'Dry goods']);

        $activity = Activity::where('subject_type', FsItem::class)->where('subject_id', $fs->id)
            ->where('event', 'updated')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->fss->id, $activity->causer_id);
        $this->assertSame('Dry goods', $activity->properties['attributes']['category']);
    }

    public function test_clinical_edit_logs_field_names_but_redacts_values(): void
    {
        $rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
        $this->actingAs($rnd);

        $patient = Patient::factory()->create();
        $field = collect($patient->getFillable())->first(fn ($f) => is_string($patient->{$f}) && $patient->{$f} !== '') ?? $patient->getFillable()[0];
        $patient->update([$field => 'CHANGED-PHI-VALUE']);

        $activity = Activity::where('subject_type', Patient::class)
            ->where('subject_id', $patient->id)->where('event', 'updated')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertContains($field, $activity->properties['details']['changed_fields']);
        $this->assertArrayNotHasKey('attributes', $activity->properties);
        $this->assertArrayNotHasKey('old', $activity->properties);
        $this->assertStringNotContainsString('CHANGED-PHI-VALUE', json_encode($activity->properties));
    }

    public function test_noop_save_writes_no_activity(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create(['category' => 'Dry goods']);
        AuditFixture::delete(Activity::query());

        $fs->update(['category' => 'Dry goods']); // same value -> not dirty

        $this->assertSame(0, Activity::where('subject_type', FsItem::class)->where('event', 'updated')->count());
    }

    public function test_inventory_history_endpoint_is_retired(): void
    {
        $this->actingAs($this->fss);
        $inventory = Inventory::factory()->create();

        $this->getJson("/api/fss/inventory/{$inventory->uuid}/activity")->assertNotFound();
    }

    public function test_inventory_model_no_longer_writes_audit_events(): void
    {
        $inventory = Inventory::factory()->create();
        AuditFixture::delete(Activity::query());

        $inventory->update(['item_type' => 'supply']);

        $this->assertSame(0, Activity::query()
            ->where('subject_type', Inventory::class)
            ->where('subject_id', $inventory->id)
            ->count());
    }

    public function test_patient_history_includes_child_subject_events(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->for($patient)->create(['rnd_user_id' => $rnd->id]);
        $unrelatedNcpRecord = NcpRecord::factory()->create();

        AuditFixture::delete(Activity::query());
        $childEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'category' => 'clinical',
            'domain' => 'ncp',
            'description' => 'Updated NCP record',
            'subject_type' => NcpRecord::class,
            'subject_id' => $ncpRecord->id,
        ]);
        $unrelatedEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated unrelated NCP record',
            'subject_type' => NcpRecord::class,
            'subject_id' => $unrelatedNcpRecord->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/activity");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.category', 'clinical')->assertJsonPath('data.0.domain', 'ncp');
        $this->assertStructuredEvent($response->json('data.0'));
        $this->assertNotSame((string) $childEvent->id, $response->json('data.0.id'));
    }

    public function test_purchase_order_history_includes_child_subject_events(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id]);
        $attachment = PurchaseOrderAttachment::create([
            'purchase_order_id' => $purchaseOrder->id,
            'type' => 'proof',
            'path' => 'purchase-orders/proof.pdf',
        ]);
        $unrelatedPurchaseOrder = PurchaseOrder::factory()->create(['rnd_user_id' => $this->fss->id]);
        $unrelatedAttachment = PurchaseOrderAttachment::create([
            'purchase_order_id' => $unrelatedPurchaseOrder->id,
            'type' => 'proof',
            'path' => 'purchase-orders/unrelated-proof.pdf',
        ]);
        $ledger = BudgetLedger::create([
            'fiscal_year' => now()->year, 'type' => 'po_deduction', 'source' => 'system',
            'amount' => 100, 'purchase_order_id' => $purchaseOrder->id,
        ]);

        AuditFixture::delete(Activity::query());
        $childEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'category' => 'operations',
            'domain' => 'procurement',
            'description' => 'Uploaded purchase order attachment',
            'subject_type' => PurchaseOrderAttachment::class,
            'subject_id' => $attachment->id,
        ]);
        $unrelatedEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'created',
            'description' => 'Uploaded unrelated purchase order attachment',
            'subject_type' => PurchaseOrderAttachment::class,
            'subject_id' => $unrelatedAttachment->id,
        ]);
        Activity::create([
            'log_name' => 'audit', 'event' => 'created', 'category' => 'operations', 'domain' => 'budget',
            'description' => 'Created budget ledger', 'subject_type' => BudgetLedger::class, 'subject_id' => $ledger->id,
        ]);

        $response = $this->actingAs($this->fss, 'sanctum')
            ->getJson("/api/fss/purchase-orders/{$purchaseOrder->uuid}/activity");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $taxonomy = collect($response->json('data'))->mapWithKeys(fn (array $event): array => [
            $event['subject']['type'] => [$event['category'], $event['domain']],
        ]);
        $this->assertSame(['operations', 'procurement'], $taxonomy['purchase_order_attachment']);
        $this->assertSame(['operations', 'budget'], $taxonomy['budget_ledger']);
        $this->assertStructuredEvent($response->json('data.0'));
        $this->assertNotSame((string) $childEvent->id, $response->json('data.0.id'));
    }

    public function test_clinical_trail_never_exposes_raw_values_or_arbitrary_properties(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        NcpRecord::factory()->for($patient)->create(['rnd_user_id' => $rnd->id]);

        AuditFixture::delete(Activity::query());
        $activity = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated patient',
            'category' => 'clinical',
            'domain' => 'patients',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'properties' => [
                'details' => ['changed_fields' => ['medical_diagnosis']],
                'attributes' => [
                    'medical_diagnosis' => 'CLINICAL-VALUE-SENTINEL',
                    'unexpected' => 'ARBITRARY-PROPERTY-SENTINEL',
                ],
            ],
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/activity");

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.changes.0.field', 'medical_diagnosis')
            ->assertJsonPath('data.0.changes.0.redacted', true);
        $this->assertStructuredEvent($response->json('data.0'));
        $this->assertNotSame((string) $activity->id, $response->json('data.0.id'));
        $payload = $response->getContent();

        $leaks = collect([
            'clinical raw value' => 'CLINICAL-VALUE-SENTINEL',
            'arbitrary property key' => 'unexpected',
            'arbitrary property value' => 'ARBITRARY-PROPERTY-SENTINEL',
        ])->filter(fn (string $needle) => str_contains($payload, $needle))->keys()->all();

        $this->assertSame([], $leaks, 'Clinical trail response leaked forbidden clinical or arbitrary properties.');
    }

    /** @param array<string, mixed> $event */
    private function assertStructuredEvent(array $event): void
    {
        $this->assertSame([
            'id', 'module', 'category', 'domain', 'record_type', 'action', 'action_label',
            'summary', 'severity', 'outcome', 'actor', 'subject', 'context', 'patient',
            'ncp_reference', 'detail_mode', 'reason', 'history', 'current_record_url',
            'occurred_at', 'details', 'changes',
        ], array_keys($event));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $event['id']);
        foreach (['event', 'description', 'subject_id', 'causer', 'created_at', 'properties'] as $legacyKey) {
            $this->assertArrayNotHasKey($legacyKey, $event);
        }
    }

    public function test_public_cursor_round_trips_without_numeric_ids_or_overlap(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->for($patient)->create(['rnd_user_id' => $rnd->id]);
        AuditFixture::delete(Activity::query());
        foreach (range(1, 101) as $sequence) {
            AuditActivity::create([
                'log_name' => 'audit', 'event' => 'updated', 'category' => 'clinical', 'domain' => 'patients',
                'description' => "Event {$sequence}", 'subject_type' => Patient::class, 'subject_id' => $patient->id,
            ]);
        }

        $first = $this->actingAs($rnd, 'sanctum')->getJson("/api/rnd/patients/{$patient->uuid}/activity")->assertOk();
        $cursor = $first->json('meta.next_before_id');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/iD', $cursor);
        $this->assertDoesNotMatchRegularExpression('/^[0-9]+$/D', $cursor);

        $second = $this->getJson("/api/rnd/patients/{$patient->uuid}/activity?before_id={$cursor}")->assertOk();
        $this->assertSame([], array_values(array_intersect($first->json('data.*.id'), $second->json('data.*.id'))));
    }

    public function test_contextual_event_uses_safe_soft_deleted_causer_when_snapshot_is_absent(): void
    {
        $viewer = User::factory()->rnd()->create();
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        NcpRecord::factory()->for($patient)->create(['rnd_user_id' => $viewer->id]);
        $actor->delete();
        AuditFixture::delete(AuditActivity::query());
        AuditActivity::create([
            'log_name' => 'audit', 'event' => 'updated', 'category' => 'clinical', 'domain' => 'patients',
            'description' => 'Updated patient', 'subject_type' => Patient::class, 'subject_id' => $patient->id,
            'causer_type' => User::class, 'causer_id' => $actor->id, 'properties' => [],
        ]);

        $this->actingAs($viewer, 'sanctum')->getJson("/api/rnd/patients/{$patient->uuid}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.actor.id', $actor->uuid)
            ->assertJsonPath('data.0.actor.name', $actor->name)
            ->assertJsonPath('data.0.actor.role', 'Admin');
    }
}
