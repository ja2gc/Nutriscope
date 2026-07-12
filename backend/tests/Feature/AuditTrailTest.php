<?php

namespace Tests\Feature;

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
        Activity::query()->delete();

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
        $this->assertArrayHasKey($field, $activity->properties['attributes']);
        $this->assertSame('••• redacted', $activity->properties['attributes'][$field]);
        $this->assertStringNotContainsString('CHANGED-PHI-VALUE', json_encode($activity->properties));
    }

    public function test_noop_save_writes_no_activity(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create(['category' => 'Dry goods']);
        Activity::query()->delete();

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
        Activity::query()->delete();

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

        Activity::query()->delete();
        $childEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
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
        $eventIds = collect($response->json('data'))->pluck('id');

        $this->assertSame([
            'target child included' => true,
            'unrelated child excluded' => true,
        ], [
            'target child included' => $eventIds->contains($childEvent->id),
            'unrelated child excluded' => ! $eventIds->contains($unrelatedEvent->id),
        ]);
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

        Activity::query()->delete();
        $childEvent = Activity::create([
            'log_name' => 'audit',
            'event' => 'created',
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

        $response = $this->actingAs($this->fss, 'sanctum')
            ->getJson("/api/fss/purchase-orders/{$purchaseOrder->uuid}/activity");

        $response->assertOk();
        $eventIds = collect($response->json('data'))->pluck('id');

        $this->assertSame([
            'target child included' => true,
            'unrelated child excluded' => true,
        ], [
            'target child included' => $eventIds->contains($childEvent->id),
            'unrelated child excluded' => ! $eventIds->contains($unrelatedEvent->id),
        ]);
    }

    public function test_clinical_trail_never_exposes_raw_values_or_arbitrary_properties(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        NcpRecord::factory()->for($patient)->create(['rnd_user_id' => $rnd->id]);

        Activity::query()->delete();
        $activity = Activity::create([
            'log_name' => 'audit',
            'event' => 'updated',
            'description' => 'Updated patient',
            'subject_type' => Patient::class,
            'subject_id' => $patient->id,
            'properties' => [
                'attributes' => [
                    'medical_diagnosis' => 'CLINICAL-VALUE-SENTINEL',
                    'unexpected' => 'ARBITRARY-PROPERTY-SENTINEL',
                ],
            ],
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/activity");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $activity->id)
            ->assertJsonPath('data.0.event', 'updated')
            ->assertJsonPath('data.0.description', 'Updated patient');
        $payload = $response->getContent();

        $leaks = collect([
            'clinical raw value' => 'CLINICAL-VALUE-SENTINEL',
            'arbitrary property key' => 'unexpected',
            'arbitrary property value' => 'ARBITRARY-PROPERTY-SENTINEL',
        ])->filter(fn (string $needle) => str_contains($payload, $needle))->keys()->all();

        $this->assertSame([], $leaks, 'Clinical trail response leaked forbidden clinical or arbitrary properties.');
    }
}
