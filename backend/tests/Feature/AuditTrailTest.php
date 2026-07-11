<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditMiddleware;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function test_operational_edit_logs_full_values_with_causer(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create();
        $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);

        $inv->update(['quantity_in_stock' => 25]);

        $activity = Activity::where('subject_type', Inventory::class)->where('subject_id', $inv->id)
            ->where('event', 'updated')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->fss->id, $activity->causer_id);
        $this->assertEquals(25, (float) $activity->properties['attributes']['quantity_in_stock']);
        $this->assertEquals(10, (float) $activity->properties['old']['quantity_in_stock']);
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
        $fs = FsItem::factory()->create();
        $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);
        Activity::query()->delete();

        $inv->update(['quantity_in_stock' => 10]); // same value → not dirty

        $this->assertSame(0, Activity::where('subject_type', Inventory::class)->where('event', 'updated')->count());
    }

    public function test_access_log_skips_reads_logs_mutations(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $mw = new AuditMiddleware;
        $next = fn ($r) => new Response('ok');

        Activity::query()->delete();

        $get = Request::create('/api/rnd/patients', 'GET');
        $get->setUserResolver(fn () => $rnd);
        $mw->handle($get, $next);
        $this->assertSame(0, Activity::where('description', 'like', 'Accessed%')->count(), 'GET must not be access-logged');

        $post = Request::create('/api/rnd/patients', 'POST');
        $post->setUserResolver(fn () => $rnd);
        $mw->handle($post, $next);
        $this->assertSame(1, Activity::where('description', 'like', 'Accessed%')->count(), 'mutation must be access-logged');
    }

    public function test_subject_history_endpoint_returns_that_records_changes(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create();
        $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);
        $inv->update(['quantity_in_stock' => 25]);

        $other = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id, 'quantity_in_stock' => 1]);
        $other->update(['quantity_in_stock' => 2]);

        $res = $this->getJson("/api/fss/inventory/{$inv->uuid}/activity");
        $res->assertOk();

        $events = collect($res->json('data'));
        $this->assertGreaterThanOrEqual(1, $events->count());
        $this->assertTrue($events->every(fn ($e) => $e['subject_id'] === $inv->id));
    }

    public function test_patient_history_includes_child_subject_events(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->for($patient)->create();
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
