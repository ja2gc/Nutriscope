<?php

namespace Tests\Feature;

use App\Actions\Reports\PrepareSavedReport;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\StoredObjectStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreparedReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function letterhead_renders_private_data_uri_logos(): void
    {
        $branding = new ReportBranding(['hospital_name' => 'Romana Pangan District Hospital']);
        $branding->setAttribute('logo_left_data_uri', 'data:image/png;base64,bGVmdA==');
        $branding->setAttribute('logo_right_data_uri', 'data:image/png;base64,cmlnaHQ=');

        $html = view('reports.partials.letterhead', [
            'branding' => $branding,
            'title' => 'Test Report',
        ])->render();

        $this->assertStringContainsString('src="data:image/png;base64,bGVmdA=="', $html);
        $this->assertStringContainsString('src="data:image/png;base64,cmlnaHQ="', $html);
    }

    #[Test]
    public function preparation_preserves_identity_creation_snapshot_and_original_bytes(): void
    {
        Storage::fake('report_cache');
        Storage::fake('private_uploads');
        Carbon::setTestNow('2026-08-07 09:00:00');
        ReportBranding::singleton()->update(['hospital_name' => 'Creation Hospital']);
        $actor = User::factory()->rnd()->create(['first_name' => 'Report', 'last_name' => 'Author']);
        $bytes = "%PDF-1.4\nfirst\n%%EOF";
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
        $officialObjectId = $first->official_file_stored_object_id;
        $originalHash = $first->content_hash;

        $this->assertNotNull($officialObjectId);
        $this->assertSame($originalHash, $first->officialFile->sha256);
        $this->assertSame($bytes, Storage::disk('private_uploads')->get($first->officialFile->object_key));

        Carbon::setTestNow(now()->addHour());
        $same = $action->execute($actor, 'procurement_pack', ['purchase_order_id' => 10]);
        $this->assertSame($identity, $same->uuid);
        $this->assertTrue($same->created_at->equalTo($createdAt));
        $this->assertTrue($same->updated_at->equalTo($updatedAt));

        $bytes = "%PDF-1.4\nchanged source and template\n%%EOF";
        $frozen = $action->execute($actor, 'procurement_pack', ['purchase_order_id' => 10]);
        $this->assertSame($identity, $frozen->uuid);
        $this->assertTrue($frozen->created_at->equalTo($createdAt));
        $this->assertTrue($frozen->updated_at->equalTo($updatedAt));
        $this->assertSame($snapshot, $frozen->snapshot);
        $this->assertSame($officialObjectId, $frozen->official_file_stored_object_id);
        $this->assertSame($originalHash, $frozen->content_hash);
        $this->assertSame("%PDF-1.4\nfirst\n%%EOF", Storage::disk('private_uploads')->get($frozen->officialFile->object_key));
        $this->assertSame('v1', $frozen->appearance_version);
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseCount('stored_objects', 1);
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

    #[Test]
    public function expired_cache_still_streams_the_immutable_official_file(): void
    {
        Storage::fake('private_uploads');
        Storage::fake('report_cache');
        $actor = User::factory()->rnd()->create();
        $bytes = "%PDF-1.4\nfrozen official report\n%%EOF";
        $official = app(StoredObjectStorage::class)->storeBytes(
            $bytes,
            'application/pdf',
            'pdf',
            'report',
            'frozen.pdf',
        );
        $report = Report::factory()->create([
            'user_id' => $actor->id,
            'type' => 'procurement_pack',
            'status' => 'completed',
            'official_file_stored_object_id' => $official->id,
            'cache_path' => 'reports/expired.pdf',
            'cache_expires_at' => now()->subMinute(),
            'content_hash' => $official->sha256,
        ]);

        $view = $this->actingAs($actor, 'sanctum')->get("/api/rnd/reports/{$report->uuid}/view");
        $download = $this->get("/api/rnd/reports/{$report->uuid}/download");

        $view->assertOk();
        $download->assertOk();
        $this->assertSame($bytes, $view->streamedContent());
        $this->assertSame($bytes, $download->streamedContent());
    }
}
