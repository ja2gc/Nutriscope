<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportArchiveSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportArchiveRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_enable_five_year_archived_report_retention(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd)->putJson('/api/admin/reports/archive-settings', ['enabled' => true])->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/reports/archive-settings')
            ->assertOk()->assertJsonPath('enabled', false)->assertJsonPath('years', 5);
        $this->putJson('/api/admin/reports/archive-settings', ['enabled' => true])
            ->assertOk()->assertJsonPath('enabled', true);
        $this->getJson('/api/admin/reports/archive-settings')->assertJsonPath('enabled', true);
    }

    public function test_authenticated_report_roles_can_read_retention_setting_without_editing_it(): void
    {
        ReportArchiveSetting::query()->create(['key' => ReportArchiveSetting::RETENTION, 'enabled' => true]);
        $this->getJson('/api/reports/archive-settings')->assertUnauthorized();

        foreach (['RND', 'FSS', 'Admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user, 'sanctum')->getJson('/api/reports/archive-settings')
                ->assertOk()->assertJsonPath('enabled', true)->assertJsonPath('years', 5);
        }

        $this->putJson('/api/reports/archive-settings', ['enabled' => false])->assertMethodNotAllowed();
        $this->assertTrue(ReportArchiveSetting::enabled());
    }

    public function test_retention_date_is_five_years_after_archiving_only_when_enabled(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        $admin = User::factory()->create(['role' => 'Admin']);
        $report = Report::factory()->create(['user_id' => $admin->id, 'type' => 'procurement_pack', 'status' => 'archived', 'archived_at' => now()]);
        $this->assertNotNull($report->fresh()->archived_at);

        $this->actingAs($admin)->getJson('/api/admin/reports?status=archived')
            ->assertOk()->assertJsonPath('data.0.retention_expires_at', null);
        $this->putJson('/api/admin/reports/archive-settings', ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
        $this->assertTrue(ReportArchiveSetting::enabled());
        $this->assertNotNull($report->fresh()->retention_expires_at);
        $this->getJson('/api/admin/reports?status=archived')
            ->assertOk()->assertJsonPath('data.0.retention_expires_at', '2031-10-06T09:00:00+00:00');
        Carbon::setTestNow();
    }

    public function test_enabling_retention_backfills_legacy_archived_report_dates(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        $admin = User::factory()->create(['role' => 'Admin']);
        $report = Report::factory()->create([
            'user_id' => $admin->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'archived_at' => null,
            'retention_expires_at' => null,
        ]);

        Carbon::setTestNow('2026-10-07 09:00:00');
        $this->actingAs($admin)->putJson('/api/admin/reports/archive-settings', ['enabled' => true])->assertOk();

        $this->assertSame('2026-10-06', $report->fresh()->archived_at?->toDateString());
        $this->assertSame('2031-10-06', $report->fresh()->retention_expires_at?->toDateString());
        Carbon::setTestNow();
    }

    public function test_expired_legacy_archive_without_an_official_file_still_retires(): void
    {
        Carbon::setTestNow('2032-10-06 09:00:00');
        ReportArchiveSetting::query()->create(['key' => ReportArchiveSetting::RETENTION, 'enabled' => true]);
        $report = Report::factory()->create([
            'type' => 'procurement_pack',
            'status' => 'archived',
            'parameters' => ['purchase_order_id' => 456],
            'official_file_stored_object_id' => null,
            'archived_at' => now()->subYears(6),
            'retention_expires_at' => now()->subYear(),
        ]);

        $this->artisan('reports:purge-expired-archives')->assertSuccessful();
        $this->assertModelMissing($report);
        $this->assertDatabaseHas('report_retired_sources', ['type' => 'procurement_pack']);
        Carbon::setTestNow();
    }

    public function test_expired_archive_purges_pdf_and_record_without_recreating_source(): void
    {
        Carbon::setTestNow('2026-10-06 09:00:00');
        Storage::fake('report_cache');
        Storage::fake('private_uploads');
        $admin = User::factory()->create(['role' => 'Admin']);
        $rnd = User::factory()->rnd()->create();
        $po = PurchaseOrder::factory()->create([
            'rnd_user_id' => $rnd->id,
            'lifecycle_status' => 'completed',
            'completed_at' => now(),
            'order_date' => now(),
        ]);
        $this->actingAs($admin)->putJson('/api/admin/reports/archive-settings', ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
        $id = $this->actingAs($rnd)->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")
            ->assertOk()->json('data.id');
        $this->postJson("/api/rnd/reports/{$id}/archive")->assertOk();
        $this->assertTrue(ReportArchiveSetting::enabled());
        $report = Report::query()->where('uuid', $id)->firstOrFail();
        $objectKey = $report->officialFile->object_key;
        $this->assertSame('2031-10-06', $report->retention_expires_at->toDateString());

        Carbon::setTestNow('2031-10-07 09:00:00');
        $this->artisan('reports:purge-expired-archives')->assertSuccessful();
        $this->assertModelMissing($report);
        Storage::disk('private_uploads')->assertMissing($objectKey);
        $this->getJson('/api/rnd/reports/procurement_pack/instances')->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/rnd/reports/procurement_pack/prepare?purchase_order_id={$po->id}")->assertStatus(410);
        Carbon::setTestNow();
    }
}
