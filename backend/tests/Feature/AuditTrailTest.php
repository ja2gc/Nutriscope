<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\Patient;
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
        $this->actingAs($this->fss);

        Activity::query()->delete();
        $this->getJson('/api/fss/inventory');
        $this->assertSame(0, Activity::where('log_name', 'audit')->where('description', 'like', 'Accessed%')->count());

        $fs = FsItem::factory()->create();
        $this->postJson('/api/fss/inventory', ['fs_item_id' => $fs->id, 'quantity_in_stock' => 5, 'unit' => 'g']);
        $this->assertGreaterThanOrEqual(1, Activity::where('log_name', 'audit')->where('description', 'like', 'Accessed%')->count());
    }

    public function test_subject_history_endpoint_returns_that_records_changes(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create();
        $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);
        $inv->update(['quantity_in_stock' => 25]);

        $other = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id, 'quantity_in_stock' => 1]);
        $other->update(['quantity_in_stock' => 2]);

        $res = $this->getJson("/api/fss/inventory/{$inv->id}/activity");
        $res->assertOk();

        $events = collect($res->json('data'));
        $this->assertGreaterThanOrEqual(1, $events->count());
        $this->assertTrue($events->every(fn ($e) => $e['subject_id'] === $inv->id));
    }
}
