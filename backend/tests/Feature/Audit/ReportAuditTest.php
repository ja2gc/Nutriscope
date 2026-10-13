<?php

namespace Tests\Feature\Audit;

use App\Actions\Reports\PrepareSavedReport;
use App\Jobs\GenerateReport;
use App\Jobs\ProcessReportFileOperation;
use App\Models\AuditActivity;
use App\Models\DietListCount;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportFileOperation;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FSS\AccomplishmentReportArchiveService;
use App\Services\Reports\BrandingAssetStorage;
use App\Services\Reports\Contracts\InstanceSource;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportConfigurationSnapshot;
use App\Services\Reports\ReportService;
use App\Services\StoredObjectStorage;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ReportAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_index_eager_loads_creator_and_returns_archive_attribution(): void
    {
        $rnd = User::factory()->rnd()->create([
            'name' => 'LEGACY REPORT ACTOR',
            'first_name' => 'Juan Miguel',
            'last_name' => 'De los Santos',
        ]);
        Report::factory()->count(12)->create([
            'user_id' => $rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'generated_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($rnd, 'sanctum')->getJson('/api/rnd/reports')->assertOk();

        $response->assertJsonPath('data.0.created_by.id', $rnd->uuid)
            ->assertJsonPath('data.0.created_by.name', 'Juan Miguel De los Santos')
            ->assertJsonStructure(['data' => [['created_at', 'generated_at', 'updated_at']]]);
        $this->assertLessThanOrEqual(4, count(DB::getQueryLog()));
    }

    public function test_archived_report_is_hidden_without_becoming_immutable(): void
    {
        $report = Report::factory()->create(['status' => 'archived']);

        $report->update(['title' => 'Rewritten archive']);

        $this->assertSame('archived', $report->fresh()->status);
        $this->assertSame('Rewritten archive', $report->fresh()->title);
    }

    public function test_report_view_and_download_are_audited_in_the_report_domain(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        Storage::disk('public')->put('reports/archive.pdf', '%PDF-safe');
        $report = Report::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'file_path' => 'reports/archive.pdf',
            'parameters' => ['patient_name' => 'PHI-SENTINEL', 'token' => 'TOKEN-SENTINEL'],
            'snapshot' => ['patient_name' => 'PHI-SENTINEL'],
        ]);

        $this->actingAs($rnd, 'sanctum')->getJson("/api/rnd/reports/{$report->uuid}")->assertOk();
        $this->get("/api/rnd/reports/{$report->uuid}/view")->assertOk();
        $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();
        $this->postJson("/api/rnd/reports/{$report->uuid}/unarchive")->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'audit',
            'event' => 'viewed',
            'module' => 'food_service_operations',
            'domain' => 'reports',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'audit',
            'event' => 'downloaded',
            'module' => 'food_service_operations',
            'domain' => 'reports',
        ]);
        $this->assertSame(1, AuditActivity::query()->where('event', 'viewed')->count());
        $this->assertSame(1, AuditActivity::query()->where('event', 'downloaded')->count());
    }

    public function test_patient_linked_report_view_is_listed_in_nutrition_care(): void
    {
        Storage::fake('public');
        $creator = User::factory()->rnd()->create();
        $actor = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create([
            'first_name' => 'Clinical',
            'last_name' => 'Patient',
            'name' => 'Clinical Patient',
        ]);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $creator->id,
        ]);
        Storage::disk('public')->put('reports/clinical.pdf', '%PDF-private');
        $report = Report::factory()->create([
            'user_id' => $creator->id,
            'type' => 'ncp_summary',
            'status' => 'archived',
            'file_path' => 'reports/clinical.pdf',
            'audit_patient_id' => $patient->id,
            'audit_ncp_record_id' => $ncp->id,
            'audit_owner_id' => $creator->id,
            'parameters' => [
                'patient_name' => 'REPORT-PHI-SENTINEL',
                'clinical_notes' => 'REPORT-CONTENT-SENTINEL',
            ],
            'snapshot' => ['contents' => 'REPORT-SNAPSHOT-SENTINEL'],
        ]);

        $this->actingAs($actor, 'sanctum')
            ->get("/api/rnd/reports/{$report->uuid}/view")
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'audit',
            'event' => 'viewed',
            'module' => 'nutrition_care',
            'domain' => 'reports',
        ]);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?module=nutrition_care&context=ncp_summary&action=viewed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.label', 'NCP Summary')
            ->assertJsonPath('data.0.actor.ip', '127.0.0.1');
        $this->getJson('/api/admin/audit-logs?module=nutrition_care&context=procurement_pack')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertSame(0, AuditActivity::query()->where('module', 'reports')->count());
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/audit-logs?module=reports')
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/reports/{$report->uuid}")
            ->assertForbidden();
    }

    public function test_branding_and_template_updates_log_only_safe_allowlisted_fields(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        $branding = ReportBranding::singleton();
        $template = ReportTemplate::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'procurement_pack',
            'name' => 'Procurement Pack',
            'blade_view' => 'reports.procurement-pack',
            'is_active' => true,
            'signatories' => [['role' => 'approver', 'label' => 'Approved by', 'name' => 'Old Name', 'title' => 'Chief']],
        ]);

        $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/report-branding', [
            'hospital_name' => 'Safe Hospital',
            'address' => 'data:image/png;base64,IMAGE-SENTINEL',
        ])->assertOk();
        $this->patchJson("/api/rnd/report-templates/{$template->uuid}", [
            'signatories' => [[
                'role' => 'approver',
                'label' => 'Approved by',
                'name' => 'Safe Signatory',
                'title' => 'data:image/png;base64,TEMPLATE-IMAGE-SENTINEL',
            ]],
        ])->assertOk();

        $events = AuditActivity::query()->whereIn('subject_type', [ReportBranding::class, ReportTemplate::class])->get();
        $this->assertCount(2, $events);
        $this->assertSame('security_administration', $events->first()->module->value);
        $this->assertContains('hospital_name', data_get($events->firstWhere('subject_type', ReportBranding::class)?->properties, 'details.changed_fields', []));
        $this->assertContains('signatories', data_get($events->firstWhere('subject_type', ReportTemplate::class)?->properties, 'details.changed_fields', []));
        foreach ($events as $event) {
            $properties = json_encode($event->properties, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('IMAGE-SENTINEL', $properties);
            $this->assertStringNotContainsString('TEMPLATE-IMAGE-SENTINEL', $properties);
        }
    }

    public function test_report_activity_routes_are_removed(): void
    {
        $owner = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        User::factory()->admin()->create();
        $report = Report::factory()->create([
            'user_id' => $owner->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
        ]);
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/rnd/reports/{$report->uuid}")
            ->assertOk();
        $this->getJson("/api/rnd/reports/{$report->uuid}/activity")->assertNotFound();
        $this->actingAs($owner, 'sanctum')->getJson("/api/admin/reports/{$report->uuid}/activity")->assertNotFound();
    }

    public function test_generation_job_does_not_emit_audit_events(): void
    {
        $report = Report::factory()->create([
            'type' => 'procurement_pack',
            'parameters' => ['patient_name' => 'JOB-PHI-SENTINEL'],
            'status' => 'pending',
        ]);
        $service = $this->createMock(ReportService::class);
        $service->method('generate')->willReturn('reports/generated.pdf');

        (new GenerateReport($report))->handle($service, app(ReportArchiveStorage::class));

        $this->assertNoReportAuditEvents();
    }

    public function test_deprecated_report_commands_are_removed(): void
    {
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/reports', [])->assertMethodNotAllowed();
        $this->postJson('/api/rnd/reports/generate-all', [])->assertMethodNotAllowed();
    }

    public function test_report_preparation_does_not_require_audit_availability(): void
    {
        $rnd = User::factory()->rnd()->create();
        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->never())->method('assertAvailable');
        $source = $this->createMock(InstanceSource::class);
        $source->method('hasData')->willReturn(true);
        $browser = $this->createMock(ReportBrowser::class);
        $browser->method('supports')->willReturn(true);
        $browser->method('sourceFor')->willReturn($source);
        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('buildPdf')->willReturn(['bytes' => '%PDF-safe', 'meta' => []]);
        $this->app->instance(AuditLogger::class, $auditLogger);
        $this->app->instance(ReportBrowser::class, $browser);
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($rnd, 'sanctum')
            ->postJson('/api/rnd/reports/procurement_pack/prepare')
            ->assertOk();

        $this->assertDatabaseCount('reports', 1);
        $this->assertNoReportAuditEvents();
    }

    public function test_automatic_accomplishment_archive_cleans_up_after_generation_failure(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 7) as $day) {
            DietListCount::factory()->create([
                'fss_user_id' => $fss->id,
                'service_date' => "2026-06-0{$day}",
            ]);
        }
        $reports = $this->createMock(ReportService::class);
        $transientUuid = null;
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('buildPdf')->willReturnCallback(function (Report $report) use (&$transientUuid): never {
            $transientUuid = $report->uuid;
            throw new RuntimeException('generation failed');
        });
        $service = new AccomplishmentReportArchiveService(new PrepareSavedReport($reports, app(StoredObjectStorage::class), app(ReportConfigurationSnapshot::class)));

        try {
            $service->archiveCompletedWeek($fss, '2026-06-07');
            $this->fail('Expected generation failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('reports', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('reports'));
            $this->assertNoReportAuditEvents();
        }
    }

    public function test_report_file_mutations_do_not_depend_on_audit_availability(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        Storage::disk('public')->put('reports/archive.pdf', '%PDF');
        $report = Report::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'file_path' => 'reports/archive.pdf',
        ]);
        $audit = $this->createMock(AuditLogger::class);
        $audit->expects($this->never())->method('record');
        $this->app->instance(AuditLogger::class, $audit);

        $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/reports/{$report->uuid}/unarchive")->assertOk();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'completed']);
        Storage::disk('public')->assertExists('reports/archive.pdf');
        $this->assertNoReportAuditEvents();
    }

    public function test_prepare_preview_and_download_do_not_create_report_audit_events(): void
    {
        $rnd = User::factory()->rnd()->create();
        $source = $this->createMock(InstanceSource::class);
        $source->method('hasData')->willReturn(true);
        $browser = $this->createMock(ReportBrowser::class);
        $browser->method('supports')->willReturn(true);
        $browser->method('sourceFor')->willReturn($source);
        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('buildPdf')->willReturn(['bytes' => "%PDF-1.4\n%%EOF", 'meta' => []]);
        $this->app->instance(ReportBrowser::class, $browser);
        $this->app->instance(ReportService::class, $reports);

        $prepared = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/reports/procurement_pack/prepare')->assertOk();
        $id = $prepared->json('data.id');
        $this->get("/api/rnd/reports/{$id}/view")->assertOk();
        $this->get("/api/rnd/reports/{$id}/download")->assertOk();
        $this->assertNoReportAuditEvents();
    }

    public function test_template_audit_normalizes_cleared_and_removed_signatories(): void
    {
        $rnd = User::factory()->rnd()->create();
        $template = ReportTemplate::create([
            'uuid' => (string) Str::uuid(), 'type' => 'procurement_pack', 'name' => 'Pack',
            'blade_view' => 'reports.pack', 'is_active' => true,
            'signatories' => [
                ['role' => 'prepared', 'label' => 'Prepared', 'name' => 'First Safe', 'title' => 'Dietitian'],
                ['role' => 'approved', 'label' => 'Approved', 'name' => 'Second Safe', 'title' => 'Chief'],
            ],
        ]);

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/report-templates/{$template->uuid}", [
            'signatories' => [['role' => 'prepared', 'label' => 'Prepared', 'name' => null, 'title' => null]],
        ])->assertOk();

        $event = AuditActivity::query()->where('subject_type', ReportTemplate::class)
            ->where('subject_id', $template->id)->sole();
        $this->assertSame(['signatories'], $event->properties['details']['changed_fields']);
        $this->assertSame('system', $event->domain->value);
    }

    public function test_archived_actions_derive_only_strict_safe_period_reference(): void
    {
        Storage::fake('public');
        $rnd = User::factory()->rnd()->create();
        Storage::disk('public')->put('reports/period.pdf', '%PDF');
        $report = Report::factory()->create([
            'user_id' => $rnd->id, 'status' => 'archived', 'type' => 'procurement_pack',
            'file_path' => 'reports/period.pdf',
            'parameters' => ['start' => '2026-06-01', 'end' => '2026-06-30', 'patient_name' => 'PERIOD-PHI-SENTINEL'],
        ]);

        $this->actingAs($rnd, 'sanctum')->get("/api/rnd/reports/{$report->uuid}/view")->assertOk();
        $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();

        foreach (AuditActivity::query()->get() as $event) {
            $this->assertSame('2026-06-01/2026-06-30', $event->properties['details']['period_reference']);
            $this->assertStringNotContainsString('PERIOD-PHI-SENTINEL', json_encode($event->properties, JSON_THROW_ON_ERROR));
        }
    }

    public function test_http_auto_prepare_failure_keeps_diet_audit_and_safe_failure_event(): void
    {
        Storage::fake('public');
        Storage::fake('report_cache');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 6) as $day) {
            DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => "2026-06-0{$day}"]);
        }
        $reports = $this->createMock(ReportService::class);
        $reports->method('buildPdf')->willThrowException(new RuntimeException('GENERATION-SENTINEL'));
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($fss, 'sanctum')->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-07',
            'collected_ward_diet_lists' => 1,
            'apportioned_distributed_meals' => 1,
        ])->assertServerError();

        $this->assertDatabaseCount('diet_list_counts', 7);
        $this->assertDatabaseCount('reports', 0);
        $this->assertNoReportAuditEvents();
    }

    public function test_report_service_fails_when_storage_put_returns_false(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('put')->willReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        $service = new class(app(ReportArchiveStorage::class)) extends ReportService
        {
            public function buildPdf(Report $report): array
            {
                return ['bytes' => '%PDF', 'meta' => []];
            }
        };

        $report = Report::factory()->create();
        try {
            $service->generate($report);
            $this->fail('Expected storage failure.');
        } catch (RuntimeException) {
            $intent = ReportFileOperation::query()->sole();
            $this->assertSame("reports/{$report->uuid}.pdf", $intent->original_path);
            $this->assertSame('quarantine_delete', $intent->operation);
        }
    }

    public function test_put_exception_with_partial_file_persists_cleanup_intent_and_original_exception(): void
    {
        $fake = Storage::fake('public');
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('put')->willReturnCallback(function (string $path, string $bytes) use ($fake): never {
            $fake->put($path, $bytes);
            throw new RuntimeException('ORIGINAL-PUT-EXCEPTION');
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        $service = new class(app(ReportArchiveStorage::class)) extends ReportService
        {
            public function buildPdf(Report $report): array
            {
                return ['bytes' => '%PDF', 'meta' => []];
            }
        };
        $report = Report::factory()->create();

        try {
            $service->generate($report);
            $this->fail('Expected put exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ORIGINAL-PUT-EXCEPTION', $exception->getMessage());
            $intent = ReportFileOperation::query()->sole();
            $this->assertSame("reports/{$report->uuid}.pdf", $intent->original_path);
            $this->assertSame('quarantine_delete', $intent->operation);
            $this->assertTrue($fake->exists($intent->original_path));
        }
    }

    public function test_uncertain_original_cleanup_intent_survives_move_false_then_recovers(): void
    {
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        app(ReportArchiveStorage::class)->scheduleOriginalCleanup('reports/uncertain.pdf');
        $intent = ReportFileOperation::query()->sole();

        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturnOnConsecutiveCalls(false, true, false, true, true);
        $disk->method('move')->willReturnOnConsecutiveCalls(false, true);
        $disk->method('delete')->willReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        try {
            (new ProcessReportFileOperation($intent->id))->handle(app(ReportArchiveStorage::class), app(BrandingAssetStorage::class));
        } catch (RuntimeException) {
            $this->assertSame(1, $intent->refresh()->attempts);
        }

        (new ProcessReportFileOperation($intent->id))->handle(app(ReportArchiveStorage::class), app(BrandingAssetStorage::class));
        $this->assertDatabaseCount('report_file_operations', 0);
    }

    public function test_uncertain_original_cleanup_intent_survives_move_exception(): void
    {
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        app(ReportArchiveStorage::class)->scheduleOriginalCleanup('reports/exception.pdf');
        $intent = ReportFileOperation::query()->sole();
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturnOnConsecutiveCalls(false, true, false, true, true);
        $moveCalls = 0;
        $disk->method('move')->willReturnCallback(function () use (&$moveCalls): bool {
            if ($moveCalls++ === 0) {
                throw new RuntimeException('move exception');
            }

            return true;
        });
        $disk->method('delete')->willReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            (new ProcessReportFileOperation($intent->id))->handle(app(ReportArchiveStorage::class), app(BrandingAssetStorage::class));
        } catch (RuntimeException) {
            $this->assertSame(1, $intent->refresh()->attempts);
            $this->assertSame('quarantine_delete', $intent->operation);
        }
        (new ProcessReportFileOperation($intent->id))->handle(app(ReportArchiveStorage::class), app(BrandingAssetStorage::class));
        $this->assertDatabaseCount('report_file_operations', 0);
    }

    public function test_http_auto_prepare_does_not_require_audit_availability(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 6) as $day) {
            DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => "2026-06-0{$day}"]);
        }
        $reports = $this->createMock(ReportService::class);
        Storage::fake('report_cache');
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->method('buildPdf')->willReturn(['bytes' => '%PDF-safe', 'meta' => []]);
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($fss, 'sanctum')->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-07',
            'collected_ward_diet_lists' => 1,
            'apportioned_distributed_meals' => 1,
        ])->assertCreated();

        $this->assertDatabaseCount('diet_list_counts', 7);
        $this->assertDatabaseCount('reports', 1);
        $report = Report::query()->sole();
        Storage::disk('report_cache')->assertExists($report->cache_path);
        $this->assertNoReportAuditEvents();
    }

    public function test_report_file_outbox_survives_dispatch_outage_with_safe_mapping(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/outbox.pdf', '%PDF');
        $move = app(ReportArchiveStorage::class)->quarantine('reports/outbox.pdf');
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        app(ReportArchiveStorage::class)->deleteAfterCommit($move);

        $operation = ReportFileOperation::query()->sole();
        $this->assertSame('delete', $operation->operation);
        $this->assertSame($move['original'], $operation->original_path);
        $this->assertSame($move['quarantine'], $operation->quarantine_path);
        Storage::disk('public')->assertExists($move['quarantine']);
    }

    public function test_report_quarantine_move_false_preserves_pre_move_restore_intent(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $disk->method('move')->willReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            app(ReportArchiveStorage::class)->quarantine('reports/safe.pdf');
            $this->fail('Expected quarantine failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('report_file_operations', [
                'asset_scope' => 'report',
                'operation' => 'restore',
                'original_path' => 'reports/safe.pdf',
            ]);
        }
    }

    public function test_report_file_worker_retains_retry_intent_when_delete_returns_false(): void
    {
        $operation = ReportFileOperation::query()->create([
            'operation' => 'delete',
            'original_path' => 'reports/safe.pdf',
            'quarantine_path' => 'reports-quarantine/safe.pdf',
        ]);
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $disk->method('delete')->willReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            (new ProcessReportFileOperation($operation->id))->handle(app(ReportArchiveStorage::class), app(BrandingAssetStorage::class));
            $this->fail('Expected cleanup failure.');
        } catch (RuntimeException) {
            $this->assertSame(1, $operation->refresh()->attempts);
            $this->assertSame(RuntimeException::class, $operation->last_error_code);
        }
    }

    public function test_report_file_outbox_sweeper_processes_pending_safe_operations(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports-quarantine/sweep.pdf', '%PDF');
        ReportFileOperation::query()->create([
            'operation' => 'delete',
            'original_path' => 'reports/sweep.pdf',
            'quarantine_path' => 'reports-quarantine/sweep.pdf',
        ]);

        $this->assertSame(0, Artisan::call('reports:process-file-operations'));
        $this->assertDatabaseCount('report_file_operations', 0);
        Storage::disk('public')->assertMissing('reports-quarantine/sweep.pdf');
    }

    public function test_branding_upload_is_audited_and_preserves_legacy_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/old.png', 'old');
        $branding = ReportBranding::singleton();
        $branding->update(['logo_left_path' => 'branding/old.png']);
        $rnd = User::factory()->rnd()->create();
        $this->actingAs($rnd, 'sanctum')->post('/api/rnd/report-branding', [
            'logo_left' => UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertNotSame('branding/old.png', $branding->refresh()->logo_left_path);
        Storage::disk('public')->assertExists('branding/old.png');
        $this->assertSame(['branding/old.png'], Storage::disk('public')->allFiles('branding'));
        $event = AuditActivity::query()->where('subject_type', ReportBranding::class)
            ->where('subject_id', $branding->id)->sole();
        $this->assertSame(['logo_left'], $event->properties['details']['changed_fields']);
    }

    public function test_branding_store_false_and_exception_create_safe_cleanup_intent(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $attempt = 0;
        $disk->method('putFileAs')->willReturnCallback(function () use (&$attempt): bool {
            $attempt++;
            if ($attempt === 2) {
                throw new RuntimeException('storage unavailable');
            }

            return false;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('dispatch')->willThrowException(new RuntimeException('queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        foreach (['false.png', 'exception.png'] as $name) {
            try {
                app(BrandingAssetStorage::class)->store(UploadedFile::fake()->create($name, 1, 'image/png'));
                $this->fail('Expected branding store failure.');
            } catch (RuntimeException) {
            }
        }

        $this->assertDatabaseCount('report_file_operations', 2);
        ReportFileOperation::query()->each(function (ReportFileOperation $intent): void {
            $this->assertSame('branding', $intent->asset_scope);
            $this->assertSame('quarantine_delete', $intent->operation);
            $this->assertStringStartsWith('branding/', $intent->original_path);
        });
    }

    public function test_branding_upload_preserves_legacy_logo_without_exposing_paths_in_audit(): void
    {
        Storage::fake('public');
        Storage::fake('private_uploads');
        Storage::disk('public')->put('branding/old.png', 'old');
        $branding = ReportBranding::singleton();
        $branding->update(['logo_left_path' => 'branding/old.png']);
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd, 'sanctum')->post('/api/rnd/report-branding', [
            'logo_left' => UploadedFile::fake()->createWithContent('new.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
        ], ['Accept' => 'application/json'])->assertOk();

        $branding->refresh();
        $this->assertNotNull($branding->logo_left_stored_object_id);
        Storage::disk('public')->assertExists('branding/old.png');
        $event = AuditActivity::query()->where('subject_type', ReportBranding::class)
            ->where('subject_id', $branding->id)->sole();
        $this->assertSame(['logo_left'], $event->properties['details']['changed_fields']);
        $this->assertStringNotContainsString('branding/old.png', json_encode($event->properties, JSON_THROW_ON_ERROR));
    }

    public function test_report_archive_hides_without_moving_legacy_file(): void
    {
        $rnd = User::factory()->rnd()->create();
        $report = Report::factory()->create([
            'user_id' => $rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'file_path' => 'reports/crash.pdf',
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('reports/crash.pdf', '%PDF-safe');

        $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/reports/{$report->uuid}/archive")->assertOk();
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'archived']);
        Storage::disk('public')->assertExists('reports/crash.pdf');
        $this->assertDatabaseCount('report_file_operations', 0);
    }

    public function test_report_acquisition_intent_is_not_swept_during_move(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $moves = 0;
        $disk->method('move')->willReturnCallback(function () use (&$moves): bool {
            $moves++;
            if ($moves === 1) {
                Artisan::call('reports:process-file-operations');
                $this->assertDatabaseHas('report_file_operations', [
                    'operation' => 'restore',
                    'phase' => ReportFileOperation::PHASE_ACQUISITION,
                ]);
            }

            return true;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        app(ReportArchiveStorage::class)->quarantine('reports/request-owned.pdf');

        $this->assertSame(1, $moves);
        $this->assertDatabaseHas('report_file_operations', [
            'operation' => 'restore',
            'phase' => ReportFileOperation::PHASE_ACQUISITION,
        ]);
    }

    public function test_branding_old_asset_acquisition_is_not_swept_before_finalization(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $moves = 0;
        $disk->method('move')->willReturnCallback(function () use (&$moves): bool {
            $moves++;
            if ($moves === 1) {
                Artisan::call('reports:process-file-operations');
                $this->assertDatabaseHas('report_file_operations', [
                    'asset_scope' => 'branding',
                    'operation' => 'restore',
                    'phase' => ReportFileOperation::PHASE_ACQUISITION,
                ]);
            }

            return true;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        app(BrandingAssetStorage::class)->quarantine('branding/old.png');

        $this->assertSame(1, $moves);
        $this->assertDatabaseHas('report_file_operations', [
            'asset_scope' => 'branding',
            'operation' => 'restore',
            'phase' => ReportFileOperation::PHASE_ACQUISITION,
        ]);
    }

    public function test_branding_new_asset_acquisition_is_not_swept_before_release(): void
    {
        Storage::fake('public');
        $asset = app(BrandingAssetStorage::class)->store(UploadedFile::fake()->create('new.png', 1, 'image/png'));

        Artisan::call('reports:process-file-operations');

        Storage::disk('public')->assertExists($asset['path']);
        $this->assertDatabaseHas('report_file_operations', [
            'id' => $asset['intent']->id,
            'phase' => ReportFileOperation::PHASE_ACQUISITION,
        ]);
    }

    public function test_stale_acquisition_is_recovered_after_grace_period(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/stale.pdf', '%PDF');
        $move = app(ReportArchiveStorage::class)->quarantine('reports/stale.pdf');
        ReportFileOperation::query()->where('quarantine_path', $move['quarantine'])->update(['available_at' => now()->subSecond()]);

        Artisan::call('reports:process-file-operations');

        Storage::disk('public')->assertExists('reports/stale.pdf');
        Storage::disk('public')->assertMissing($move['quarantine']);
        $this->assertDatabaseCount('report_file_operations', 0);
    }

    public function test_accomplishment_archive_identity_is_idempotent_for_user_and_week(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 7) as $day) {
            DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => "2026-06-0{$day}"]);
        }
        $reports = $this->createMock(ReportService::class);
        Storage::fake('report_cache');
        $reports->method('signatoriesFor')->willReturn([]);
        $reports->expects($this->exactly(2))->method('buildPdf')->willReturn(['bytes' => "%PDF-1.4\n%%EOF", 'meta' => []]);
        $service = new AccomplishmentReportArchiveService(new PrepareSavedReport($reports, app(StoredObjectStorage::class), app(ReportConfigurationSnapshot::class)));

        $first = $service->archiveCompletedWeek($fss, '2026-06-07');
        $second = $service->archiveCompletedWeek($fss, '2026-06-07');

        $this->assertSame($first->id, $second->id);
        $this->assertNotNull($first->archive_identity);
        $this->assertDatabaseCount('reports', 1);
        $this->assertNoReportAuditEvents();
    }

    public function test_generate_report_duplicate_delivery_is_terminal_and_failure_is_once(): void
    {
        $completed = Report::factory()->create(['status' => 'completed']);
        $reports = $this->createMock(ReportService::class);
        $reports->expects($this->never())->method('generate');
        (new GenerateReport($completed))->handle($reports, app(ReportArchiveStorage::class));

        $failed = Report::factory()->create(['status' => 'pending']);
        $failing = $this->createMock(ReportService::class);
        $failing->method('generate')->willThrowException(new RuntimeException('failure'));
        foreach (range(1, 2) as $_) {
            try {
                (new GenerateReport($failed))->handle($failing, app(ReportArchiveStorage::class));
            } catch (RuntimeException) {
            }
        }

        $this->assertSame('failed', $failed->refresh()->status);
        $this->assertNoReportAuditEvents();
    }

    public function test_report_outbox_and_archive_identity_migrations_roll_back_and_forward(): void
    {
        $outbox = require database_path('migrations/2026_07_12_000003_create_report_file_operations_table.php');
        $identity = require database_path('migrations/2026_07_12_000004_add_archive_identity_to_reports_table.php');

        $identity->down();
        $outbox->down();
        $this->assertFalse(Schema::hasColumn('reports', 'archive_identity'));
        $this->assertFalse(Schema::hasTable('report_file_operations'));

        $outbox->up();
        $identity->up();
        $this->assertTrue(Schema::hasTable('report_file_operations'));
        $this->assertTrue(Schema::hasColumn('reports', 'archive_identity'));
    }

    private function assertNoReportAuditEvents(): void
    {
        $this->assertSame(0, AuditActivity::query()->where('module', 'reports')->count());
    }
}
