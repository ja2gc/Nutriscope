<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Exceptions\AuditLoggingUnavailable;
use App\Jobs\GenerateReport;
use App\Jobs\ProcessReportFileOperation;
use App\Models\AuditActivity;
use App\Models\DietListCount;
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
use App\Services\Reports\ReportAuditReference;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportService;
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
        $rnd = User::factory()->rnd()->create();
        Report::factory()->count(3)->create([
            'user_id' => $rnd->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
            'generated_at' => now(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($rnd, 'sanctum')->getJson('/api/rnd/reports')->assertOk();

        $response->assertJsonPath('data.0.created_by.id', $rnd->uuid)
            ->assertJsonPath('data.0.created_by.name', $rnd->name)
            ->assertJsonStructure(['data' => [['created_at', 'generated_at', 'updated_at']]]);
        $this->assertLessThanOrEqual(3, count(DB::getQueryLog()));
    }

    public function test_archived_report_rejects_later_mutation(): void
    {
        $report = Report::factory()->create(['status' => 'archived']);

        $this->expectException(RuntimeException::class);
        $report->update(['title' => 'Rewritten archive']);
    }

    public function test_report_views_downloads_and_deletes_emit_safe_semantic_events(): void
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
        $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();
        $this->deleteJson("/api/rnd/reports/{$report->uuid}")->assertNoContent();

        $events = AuditActivity::query()->whereIn('event', ['viewed', 'downloaded', 'deleted'])->get();
        $this->assertEqualsCanonicalizing(['viewed', 'downloaded', 'deleted'], $events->pluck('event')->all());
        foreach ($events as $event) {
            $encoded = json_encode($event->properties, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('PHI-SENTINEL', $encoded);
            $this->assertStringNotContainsString('TOKEN-SENTINEL', $encoded);
            $this->assertSame('procurement_pack', $event->properties['details']['report_type']);
            $this->assertSame($report->uuid, $event->properties['details']['report_public_id']);
        }
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

        $events = AuditActivity::query()->where('domain', 'reports')->get();
        $this->assertCount(2, $events);
        $encoded = json_encode($events->pluck('properties'), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Safe Signatory', $encoded);
        $this->assertStringNotContainsString('IMAGE-SENTINEL', $encoded);
        $this->assertStringNotContainsString('TEMPLATE-IMAGE-SENTINEL', $encoded);
        $this->assertStringNotContainsString('logo_left_path', $encoded);
        $this->assertSame($branding->id, $events->firstWhere('event', 'updated')->subject_id);
    }

    public function test_rnd_and_admin_report_activity_routes_are_authorized_and_not_shadowed(): void
    {
        $owner = User::factory()->rnd()->create();
        $other = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $report = Report::factory()->create([
            'user_id' => $owner->id,
            'type' => 'procurement_pack',
            'status' => 'archived',
        ]);
        AuditActivity::create([
            'log_name' => config('audit.log_name'),
            'event' => AuditAction::Archived->value,
            'category' => 'operations',
            'domain' => 'reports',
            'description' => 'Archived report',
            'subject_type' => Report::class,
            'subject_id' => $report->id,
            'subject_public_id' => $report->uuid,
        ]);

        $event = $this->actingAs($owner, 'sanctum')->getJson("/api/rnd/reports/{$report->uuid}/activity")
            ->assertOk()->assertJsonPath('data.0.action', 'archived')->json('data.0');
        $this->assertSame([
            'id', 'category', 'domain', 'action', 'action_label', 'summary', 'severity', 'outcome',
            'actor', 'subject', 'context', 'occurred_at', 'details', 'changes',
        ], array_keys($event));
        $this->assertSame('operations', $event['category']);
        $this->assertSame('reports', $event['domain']);
        $this->assertArrayNotHasKey('subject_id', $event);
        $this->actingAs($other, 'sanctum')->getJson("/api/rnd/reports/{$report->uuid}/activity")->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/reports/{$report->uuid}/activity")
            ->assertOk()->assertJsonPath('data.0.action', 'archived');
        $this->actingAs($owner, 'sanctum')->getJson("/api/admin/reports/{$report->uuid}/activity")->assertForbidden();
    }

    public function test_generation_job_emits_one_system_lifecycle_event_without_parameters(): void
    {
        $report = Report::factory()->create([
            'type' => 'procurement_pack',
            'parameters' => ['patient_name' => 'JOB-PHI-SENTINEL'],
            'status' => 'pending',
        ]);
        $service = $this->createMock(ReportService::class);
        $service->method('generate')->willReturn('reports/generated.pdf');

        (new GenerateReport($report))->handle($service, app(AuditLogger::class), app(ReportArchiveStorage::class), app(ReportAuditReference::class));

        $event = AuditActivity::query()->where('event', 'generated')->sole();
        $this->assertSame('system', $event->properties['actor']['kind']);
        $this->assertSame('report_generation', $event->properties['actor']['name']);
        $this->assertStringNotContainsString('JOB-PHI-SENTINEL', json_encode($event->properties, JSON_THROW_ON_ERROR));
    }

    public function test_deprecated_report_commands_are_removed(): void
    {
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/reports', [])->assertMethodNotAllowed();
        $this->postJson('/api/rnd/reports/generate-all', [])->assertMethodNotAllowed();
    }

    public function test_archive_creates_no_record_when_required_audit_is_unavailable(): void
    {
        $rnd = User::factory()->rnd()->create();
        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->method('assertAvailable')->willThrowException(new AuditLoggingUnavailable('offline'));
        $source = $this->createMock(InstanceSource::class);
        $source->method('hasData')->willReturn(true);
        $browser = $this->createMock(ReportBrowser::class);
        $browser->method('supports')->willReturn(true);
        $browser->method('sourceFor')->willReturn($source);
        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('generate')->willReturn('reports/should-not-persist.pdf');
        $this->app->instance(AuditLogger::class, $auditLogger);
        $this->app->instance(ReportBrowser::class, $browser);
        $this->app->instance(ReportService::class, $reports);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($rnd, 'sanctum')
                ->postJson('/api/rnd/reports/procurement_pack/archive');
            $this->fail('Expected required audit failure.');
        } catch (AuditLoggingUnavailable) {
            $this->assertDatabaseCount('reports', 0);
        }
    }

    public function test_automatic_accomplishment_archive_fails_closed_without_orphans(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 7) as $day) {
            DietListCount::factory()->create([
                'fss_user_id' => $fss->id,
                'service_date' => "2026-06-0{$day}",
            ]);
        }
        $unavailable = $this->createMock(AuditLogger::class);
        $unavailable->method('assertAvailable')->willThrowException(new AuditLoggingUnavailable('offline'));
        $unusedReports = $this->createMock(ReportService::class);
        $unusedReports->expects($this->never())->method('generate');
        try {
            (new AccomplishmentReportArchiveService($unusedReports, $unavailable, app(ReportArchiveStorage::class), app(ReportAuditReference::class)))
                ->archiveCompletedWeek($fss, '2026-06-07');
        } catch (AuditLoggingUnavailable) {
            $this->assertDatabaseCount('reports', 0);
        }

        $audit = app(AuditLogger::class);
        $reports = $this->createMock(ReportService::class);
        $transientUuid = null;
        $reports->method('generate')->willReturnCallback(function (Report $report) use (&$transientUuid): never {
            $transientUuid = $report->uuid;
            throw new RuntimeException('generation failed');
        });
        $service = new AccomplishmentReportArchiveService($reports, $audit, app(ReportArchiveStorage::class), app(ReportAuditReference::class));

        try {
            $service->archiveCompletedWeek($fss, '2026-06-07');
            $this->fail('Expected generation failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('reports', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('reports'));
            $event = AuditActivity::query()->where('event', 'generated')->sole();
            $this->assertSame('failure', $event->outcome->value);
            $this->assertSame('accomplishment_report_archive', $event->properties['actor']['name']);
            $publicId = $event->properties['details']['report_public_id'];
            $this->assertSame($transientUuid, $publicId);
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/D', $publicId);
            $this->assertSame($publicId, $event->properties['details']['instance_reference']);
            $this->assertStringNotContainsString($fss->name, json_encode($event->properties, JSON_THROW_ON_ERROR));
        }
    }

    public function test_report_file_mutations_restore_or_cleanup_files_when_audit_fails(): void
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
        $audit->method('record')->willThrowException(new AuditLoggingUnavailable('offline'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->actingAs($rnd, 'sanctum')->deleteJson("/api/rnd/reports/{$report->uuid}")->assertServerError();

        $this->assertDatabaseHas('reports', ['id' => $report->id]);
        Storage::disk('public')->assertExists('reports/archive.pdf');

        $queued = Report::factory()->create(['user_id' => $rnd->id, 'status' => 'pending', 'type' => 'procurement_pack']);
        $reports = $this->createMock(ReportService::class);
        $reports->method('generate')->willReturnCallback(function (): string {
            Storage::disk('public')->put('reports/job.pdf', '%PDF');

            return 'reports/job.pdf';
        });
        try {
            (new GenerateReport($queued))->handle($reports, $audit, app(ReportArchiveStorage::class), app(ReportAuditReference::class));
        } catch (AuditLoggingUnavailable) {
            Storage::disk('public')->assertMissing('reports/job.pdf');
            $this->assertSame([], Storage::disk('public')->allFiles('reports-quarantine'));
        }
    }

    public function test_live_preview_and_attachment_export_emit_distinct_actions(): void
    {
        $rnd = User::factory()->rnd()->create();
        $source = $this->createMock(InstanceSource::class);
        $source->method('hasData')->willReturn(true);
        $browser = $this->createMock(ReportBrowser::class);
        $browser->method('supports')->willReturn(true);
        $browser->method('sourceFor')->willReturn($source);
        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->method('streamBytes')->willReturn('%PDF-safe');
        $this->app->instance(ReportBrowser::class, $browser);
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($rnd, 'sanctum')->get('/api/rnd/reports/procurement_pack/render')->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="procurement_pack.pdf"');
        $this->get('/api/rnd/reports/procurement_pack/export')->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="procurement_pack.pdf"');
        $this->assertEqualsCanonicalizing(['viewed', 'downloaded'], AuditActivity::query()->pluck('event')->all());
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

        $details = AuditActivity::query()->sole()->properties['details'];
        $this->assertContains('signatories.0.name', $details['changed_fields']);
        $this->assertContains('signatories.1.name', $details['changed_fields']);
        $this->assertSame([], $details['signatory_names']);
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

    public function test_http_auto_archive_failure_keeps_diet_audit_and_safe_failure_event(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 6) as $day) {
            DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => "2026-06-0{$day}"]);
        }
        $reports = $this->createMock(ReportService::class);
        $reports->method('generate')->willThrowException(new RuntimeException('GENERATION-SENTINEL'));
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($fss, 'sanctum')->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-07', 'ward' => 'SAFE', 'population' => 1,
        ])->assertServerError();

        $this->assertDatabaseCount('diet_list_counts', 7);
        $this->assertDatabaseCount('reports', 0);
        $failure = AuditActivity::query()->where('event', 'generated')->sole();
        $this->assertSame('failure', $failure->outcome->value);
        $this->assertNotNull($failure->properties['details']['report_public_id']);
        $this->assertStringNotContainsString('GENERATION-SENTINEL', json_encode($failure->properties, JSON_THROW_ON_ERROR));
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

    public function test_http_auto_archive_audit_failure_compensates_file_after_diet_commit(): void
    {
        Storage::fake('public');
        $fss = User::factory()->create(['role' => 'FSS']);
        foreach (range(1, 6) as $day) {
            DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => "2026-06-0{$day}"]);
        }
        $actual = app(AuditLogger::class);
        $calls = 0;
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('assertAvailable')->willReturnCallback(fn () => $actual->assertAvailable());
        $audit->method('record')->willReturnCallback(function (...$arguments) use ($actual, &$calls) {
            $calls++;
            if ($calls === 2) {
                throw new AuditLoggingUnavailable('report audit unavailable');
            }

            return $actual->record(...$arguments);
        });
        $reports = $this->createMock(ReportService::class);
        $reports->method('generate')->willReturnCallback(function (): string {
            Storage::disk('public')->put('reports/http-audit.pdf', '%PDF');

            return 'reports/http-audit.pdf';
        });
        $this->app->instance(AuditLogger::class, $audit);
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($fss, 'sanctum')->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-07', 'ward' => 'SAFE', 'population' => 1,
        ])->assertServerError();

        $this->assertDatabaseCount('diet_list_counts', 7);
        $this->assertDatabaseCount('reports', 0);
        Storage::disk('public')->assertMissing('reports/http-audit.pdf');
        $failure = AuditActivity::query()->where('event', 'generated')->sole();
        $this->assertNotNull($failure->properties['details']['report_public_id']);
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

    public function test_branding_upload_audit_failure_restores_old_and_cleans_new_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/old.png', 'old');
        $branding = ReportBranding::singleton();
        $branding->update(['logo_left_path' => 'branding/old.png']);
        $rnd = User::factory()->rnd()->create();
        $audit = $this->createMock(AuditLogger::class);
        $audit->method('record')->willThrowException(new AuditLoggingUnavailable('offline'));
        $this->app->instance(AuditLogger::class, $audit);

        $this->actingAs($rnd, 'sanctum')->post('/api/rnd/report-branding', [
            'logo_left' => UploadedFile::fake()->create('new.png', 1, 'image/png'),
        ], ['Accept' => 'application/json'])->assertServerError();

        $this->assertSame('branding/old.png', $branding->refresh()->logo_left_path);
        Storage::disk('public')->assertExists('branding/old.png');
        $this->assertSame(['branding/old.png'], Storage::disk('public')->allFiles('branding'));
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

    public function test_branding_success_replaces_and_cleans_old_without_auditing_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/old.png', 'old');
        $branding = ReportBranding::singleton();
        $branding->update(['logo_left_path' => 'branding/old.png']);
        $rnd = User::factory()->rnd()->create();

        $this->actingAs($rnd, 'sanctum')->post('/api/rnd/report-branding', [
            'logo_left' => UploadedFile::fake()->create('new.png', 1, 'image/png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $newPath = $branding->refresh()->logo_left_path;
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('branding/old.png');
        $properties = json_encode(AuditActivity::query()->sole()->properties, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($newPath, $properties);
        $this->assertStringNotContainsString('branding/old.png', $properties);
    }

    public function test_report_delete_acquisition_intent_exists_before_file_move(): void
    {
        $rnd = User::factory()->rnd()->create();
        $report = Report::factory()->create(['user_id' => $rnd->id, 'status' => 'archived', 'file_path' => 'reports/crash.pdf']);
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->method('exists')->willReturn(true);
        $disk->method('move')->willReturnCallback(function (): bool {
            $this->assertDatabaseHas('report_file_operations', ['operation' => 'restore', 'original_path' => 'reports/crash.pdf']);

            return false;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->actingAs($rnd, 'sanctum')->deleteJson("/api/rnd/reports/{$report->uuid}")->assertServerError();
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
        $this->assertDatabaseHas('report_file_operations', ['operation' => 'restore']);
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
        $reports->expects($this->once())->method('generate')->willReturnCallback(function (Report $report): string {
            $path = "reports/{$report->uuid}.pdf";
            Storage::disk('public')->put($path, '%PDF');

            return $path;
        });
        $service = new AccomplishmentReportArchiveService($reports, app(AuditLogger::class), app(ReportArchiveStorage::class), app(ReportAuditReference::class));

        $first = $service->archiveCompletedWeek($fss, '2026-06-07');
        $second = $service->archiveCompletedWeek($fss, '2026-06-07');

        $this->assertSame($first->id, $second->id);
        $this->assertNotNull($first->archive_identity);
        $this->assertDatabaseCount('reports', 1);
        $this->assertSame(1, AuditActivity::query()->where('event', 'generated')->count());
    }

    public function test_generate_report_duplicate_delivery_is_terminal_and_failure_is_once(): void
    {
        $completed = Report::factory()->create(['status' => 'completed']);
        $reports = $this->createMock(ReportService::class);
        $reports->expects($this->never())->method('generate');
        (new GenerateReport($completed))->handle($reports, app(AuditLogger::class), app(ReportArchiveStorage::class), app(ReportAuditReference::class));

        $failed = Report::factory()->create(['status' => 'pending']);
        $failing = $this->createMock(ReportService::class);
        $failing->method('generate')->willThrowException(new RuntimeException('failure'));
        foreach (range(1, 2) as $_) {
            try {
                (new GenerateReport($failed))->handle($failing, app(AuditLogger::class), app(ReportArchiveStorage::class), app(ReportAuditReference::class));
            } catch (RuntimeException) {
            }
        }

        $this->assertSame('failed', $failed->refresh()->status);
        $this->assertSame(1, AuditActivity::query()->where('event', 'generated')->where('subject_id', $failed->id)->count());
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
}
