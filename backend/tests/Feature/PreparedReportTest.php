<?php

namespace Tests\Feature;

use App\Actions\Reports\PrepareSavedReport;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreparedReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function preparation_preserves_identity_and_creation_details_while_refreshing_changed_content(): void
    {
        Storage::fake('report_cache');
        Carbon::setTestNow('2026-08-07 09:00:00');
        ReportBranding::singleton()->update(['hospital_name' => 'Creation Hospital']);
        $actor = User::factory()->rnd()->create(['first_name' => 'Report', 'last_name' => 'Author']);
        $bytes = '%PDF-first';
        $service = $this->createMock(ReportService::class);
        $service->method('signatoriesFor')->willReturn([['role' => 'prepared_by', 'name' => 'Report Author']]);
        $service->method('buildPdf')->willReturnCallback(function () use (&$bytes): array {
            return ['bytes' => $bytes, 'meta' => []];
        });
        $this->app->instance(ReportService::class, $service);
        $action = app(PrepareSavedReport::class);

        $first = $action->execute($actor, 'procurement_pack', ['purchase_order_id' => 10]);
        $createdAt = $first->created_at->copy();
        $updatedAt = $first->updated_at->copy();
        $identity = $first->uuid;
        $snapshot = $first->snapshot;

        Carbon::setTestNow(now()->addHour());
        $same = $action->execute($actor, 'procurement_pack', ['purchase_order_id' => 10]);
        $this->assertSame($identity, $same->uuid);
        $this->assertTrue($same->created_at->equalTo($createdAt));
        $this->assertTrue($same->updated_at->equalTo($updatedAt));

        $bytes = '%PDF-current';
        $changed = $action->execute($actor, 'procurement_pack', ['purchase_order_id' => 10]);
        $this->assertSame($identity, $changed->uuid);
        $this->assertTrue($changed->created_at->equalTo($createdAt));
        $this->assertTrue($changed->updated_at->greaterThan($updatedAt));
        $this->assertSame($snapshot, $changed->snapshot);
        $this->assertSame('v1', $changed->appearance_version);
        $this->assertDatabaseCount('reports', 1);
    }

    #[Test]
    public function preview_and_download_stream_prepared_bytes_without_mutating_report(): void
    {
        Storage::fake('report_cache');
        $actor = User::factory()->rnd()->create();
        $report = Report::factory()->create([
            'user_id' => $actor->id,
            'type' => 'procurement_pack',
            'status' => 'completed',
            'cache_path' => 'reports/prepared.pdf',
            'cache_expires_at' => now()->addHour(),
            'content_hash' => hash('sha256', '%PDF-current'),
        ]);
        Storage::disk('report_cache')->put($report->cache_path, '%PDF-current');
        $updatedAt = $report->updated_at->copy();

        $this->actingAs($actor, 'sanctum')->get("/api/rnd/reports/{$report->uuid}/view")->assertOk();
        $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();

        $this->assertTrue($report->fresh()->updated_at->equalTo($updatedAt));
    }
}
