<?php

namespace Tests\Feature;

use App\Jobs\DeleteQuarantinedClinicalFile;
use App\Jobs\RestoreQuarantinedClinicalFile;
use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ClinicalDocumentStorage;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * NCP supporting-document attachments (post-OCR-removal, rnd.md §3.1):
 * plain file storage linked to an NCP cycle — no extraction, no field population.
 * Attachments are scoped per NCP record so a patient's cycles never mix.
 */
class AttachmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name' => 'RND', 'email' => 'rnd-attach@example.com',
            'password' => Hash::make('password'), 'role' => 'RND', 'is_active' => true,
        ]);
    }

    private function ncp(User $rnd): NcpRecord
    {
        $patient = Patient::forceCreate([
            'name' => 'P', 'dob' => '1990-01-01', 'sex' => 'Male',
            'screening_type' => 'adult', 'admission_date' => '2026-06-01',
        ]);

        return NcpRecord::forceCreate([
            'patient_id' => $patient->id, 'rnd_user_id' => $rnd->id,
            'type' => 'new', 'status' => 'draft',
        ]);
    }

    public function test_upload_attachment_stores_disk_relative_path_and_links_to_cycle(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('referral.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        $doc = ScreeningDocument::firstOrFail();
        $this->assertStringStartsWith('documents/ncp/', $doc->file_path);
        $this->assertStringNotContainsString(storage_path(), $doc->file_path);
        $this->assertSame('referral.pdf', $doc->original_name);
        // Linked directly to this cycle…
        $this->assertSame($ncp->id, $doc->ncp_record_id);
        // …and the upload must NOT have created an assessment row (AS-02).
        $this->assertDatabaseMissing('assessments', ['ncp_record_id' => $ncp->id]);
    }

    public function test_upload_attachment_does_not_create_assessment(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('referral.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        // The Assessment gate must still report "not recorded" after an upload.
        $this->assertDatabaseMissing('assessments', ['ncp_record_id' => $ncp->id]);
        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment")
            ->assertNotFound();
    }

    public function test_attachments_are_scoped_per_ncp_cycle(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $cycleA = $this->ncp($rnd);
        $cycleB = NcpRecord::forceCreate([
            'patient_id' => $cycleA->patient_id, 'rnd_user_id' => $rnd->id,
            'type' => 'followup', 'status' => 'draft',
        ]);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$cycleA->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$cycleB->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        $res = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$cycleA->uuid}/attachments")
            ->assertOk();

        $names = collect($res->json('data'))->pluck('original_name')->all();
        $this->assertContains('a.pdf', $names);
        $this->assertNotContains('b.pdf', $names);
    }

    public function test_uploaded_attachment_file_can_be_retrieved(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('labs.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        $doc = ScreeningDocument::firstOrFail();

        $this->actingAs($rnd, 'sanctum')
            ->get("/api/rnd/screening-documents/{$doc->uuid}/file")
            ->assertOk();
    }

    public function test_attachment_can_be_deleted(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]
        )->assertStatus(201);

        $doc = ScreeningDocument::firstOrFail();

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/screening-documents/{$doc->uuid}")
            ->assertOk();

        $this->assertModelMissing($doc);
    }

    public function test_attachment_download_rejects_traversal_and_absolute_outside_roots_without_audit(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        $outside = tempnam(sys_get_temp_dir(), 'nutriscope-outside-');
        file_put_contents($outside, 'OUTSIDE-FILE-SENTINEL');

        try {
            foreach (['../outside.pdf', $outside] as $unsafe) {
                $doc = ScreeningDocument::create([
                    'patient_id' => $ncp->patient_id,
                    'ncp_record_id' => $ncp->id,
                    'file_path' => $unsafe,
                    'original_name' => 'safe.pdf',
                ]);
                AuditActivity::query()->delete();

                $this->actingAs($rnd, 'sanctum')
                    ->get("/api/rnd/screening-documents/{$doc->uuid}/file")
                    ->assertNotFound();
                $this->assertDatabaseMissing('activity_log', ['event' => 'downloaded']);
            }
        } finally {
            @unlink($outside);
        }
    }

    public function test_attachment_download_rejects_same_disk_nonclinical_path(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('reports/not-a-clinical-document.pdf', 'PRIVATE-SUBSYSTEM-DATA');
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => 'reports/not-a-clinical-document.pdf',
            'original_name' => 'document.pdf',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->get("/api/rnd/screening-documents/{$document->uuid}/file")
            ->assertNotFound();
    }

    public function test_attachment_delete_quarantines_then_queues_verified_cleanup_after_commit(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('documents/ncp/delete-me.pdf', 'safe');
        $doc = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/delete-me.pdf',
            'original_name' => 'delete-me.pdf',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/screening-documents/{$doc->uuid}")
            ->assertOk();

        $this->assertModelMissing($doc);
        Storage::assertMissing('documents/ncp/delete-me.pdf');
        Queue::assertPushed(DeleteQuarantinedClinicalFile::class, function (DeleteQuarantinedClinicalFile $job): bool {
            $job->handle(app(ClinicalDocumentStorage::class));

            return ! is_file($job->quarantinePath) && $job->tries === 5;
        });
    }

    public function test_attachment_delete_succeeds_when_file_is_already_missing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/already-missing.pdf',
            'original_name' => 'already-missing.pdf',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/screening-documents/{$document->uuid}")
            ->assertOk();

        $this->assertModelMissing($document);
        Queue::assertNothingPushed();
    }

    public function test_attachment_delete_audit_failure_preserves_database_and_file(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('documents/ncp/keep-me.pdf', 'safe');
        $doc = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/keep-me.pdf',
            'original_name' => 'keep-me.pdf',
        ]);
        config(['activitylog.enabled' => false]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/screening-documents/{$doc->uuid}")
            ->assertServerError();

        $this->assertModelExists($doc);
        Storage::assertExists('documents/ncp/keep-me.pdf');
    }

    public function test_legacy_contained_absolute_attachment_can_be_downloaded(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('documents/ncp/legacy.pdf', 'safe');
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => Storage::path('documents/ncp/legacy.pdf'),
            'original_name' => 'legacy.pdf',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->get("/api/rnd/screening-documents/{$document->uuid}/file")
            ->assertOk();
    }

    public function test_upload_audit_failure_removes_stored_file_and_database_row(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        config(['activitylog.enabled' => false]);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('rollback.pdf', 10, 'application/pdf')],
        )->assertServerError();

        $this->assertDatabaseCount('screening_documents', 0);
        $this->assertSame([], Storage::allFiles('documents/ncp'));
    }

    public function test_upload_audit_failure_quarantines_and_queues_cleanup_when_direct_delete_fails(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        config(['activitylog.enabled' => false]);
        $disk = Storage::disk('local');
        Storage::partialMock()->shouldReceive('disk')->andReturn($disk);
        Storage::shouldReceive('delete')->once()->andReturnFalse();

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('durable-rollback.pdf', 10, 'application/pdf')],
        )->assertServerError();

        $this->assertDatabaseCount('screening_documents', 0);
        $files = $disk->allFiles('documents/ncp');
        $this->assertCount(1, $files);
        Queue::assertPushed(
            'App\\Jobs\\CleanupFailedClinicalUpload',
            function ($job) use ($disk): bool {
                $job->handle(app(ClinicalDocumentStorage::class));

                return $disk->allFiles('documents/ncp') === [];
            },
        );
    }

    public function test_upload_rollback_queues_durable_cleanup_when_direct_delete_throws(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        config(['activitylog.enabled' => false]);
        $disk = Storage::disk('local');
        Storage::partialMock()->shouldReceive('disk')->andReturn($disk);
        Storage::shouldReceive('delete')->once()->andThrow(new \RuntimeException('delete failed'));

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
            ['file' => UploadedFile::fake()->create('retry-cleanup.pdf', 10, 'application/pdf')],
        )->assertServerError();

        $this->assertDatabaseCount('screening_documents', 0);
        Queue::assertPushed('App\\Jobs\\CleanupFailedClinicalUpload');
    }

    public function test_upload_rollback_cleanup_failures_do_not_mask_original_mutation_error(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        $disk = Storage::disk('local');
        Storage::partialMock()->shouldReceive('disk')->andReturn($disk);
        Storage::shouldReceive('delete')->once()->andThrow(new \RuntimeException('delete cleanup failed'));
        Storage::shouldReceive('exists')->once()->andThrow(new \RuntimeException('exists cleanup failed'));
        $logger = \Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('assertAvailable')->once()->andThrow(new \RuntimeException('original mutation failed'));
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('dispatch cleanup failed'));
        $documentStorage = \Mockery::mock(ClinicalDocumentStorage::class);
        $documentStorage->shouldReceive('deleteIfPresent')->once()->andThrow(new \RuntimeException('fallback cleanup failed'));
        $this->app->instance(AuditLogger::class, $logger);
        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->app->instance(ClinicalDocumentStorage::class, $documentStorage);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($rnd, 'sanctum')->postJson(
                "/api/rnd/ncp-records/{$ncp->uuid}/attachments",
                ['file' => UploadedFile::fake()->create('masking.pdf', 10, 'application/pdf')],
            );
            $this->fail('The mutation failure was not rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('original mutation failed', $exception->getMessage());
        }
    }

    public function test_attachment_download_rejects_symlink_escape_when_platform_supports_symlinks(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        $outside = tempnam(sys_get_temp_dir(), 'clinical-symlink-');
        $link = Storage::path('documents/ncp/link.pdf');
        @mkdir(dirname($link), 0700, true);
        if (! @symlink($outside, $link)) {
            @unlink($outside);
            $this->markTestSkipped('Platform does not permit symlink creation.');
        }
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id, 'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/link.pdf', 'original_name' => 'link.pdf',
        ]);

        try {
            $this->actingAs($rnd, 'sanctum')
                ->get("/api/rnd/screening-documents/{$document->uuid}/file")
                ->assertNotFound();
        } finally {
            @unlink($link);
            @unlink($outside);
        }
    }

    public function test_attachment_delete_restores_quarantined_file_when_audit_insert_fails(): void
    {
        Storage::fake('local');
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('documents/ncp/compensate.pdf', 'safe');
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id, 'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/compensate.pdf', 'original_name' => 'compensate.pdf',
        ]);
        $logger = \Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('assertAvailable')->twice();
        $logger->shouldReceive('withoutModelEvents')->once()->andReturnUsing(fn (\Closure $callback) => $callback());
        $logger->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit insert failed'));
        $this->app->instance(AuditLogger::class, $logger);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/screening-documents/{$document->uuid}")
            ->assertServerError();

        $this->assertModelExists($document);
        Storage::assertExists('documents/ncp/compensate.pdf');
    }

    public function test_attachment_delete_restore_failure_does_not_mask_original_and_queues_retry(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);
        Storage::put('documents/ncp/restore-retry.pdf', 'safe');
        $document = ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'file_path' => 'documents/ncp/restore-retry.pdf',
            'original_name' => 'restore-retry.pdf',
        ]);
        $logger = \Mockery::mock(AuditLogger::class);
        $logger->shouldReceive('assertAvailable')->twice();
        $logger->shouldReceive('withoutModelEvents')->once()->andReturnUsing(fn (\Closure $callback) => $callback());
        $logger->shouldReceive('record')->once()->andThrow(new \RuntimeException('original mutation failed'));
        $storage = \Mockery::mock(ClinicalDocumentStorage::class)->makePartial();
        $storage->shouldReceive('restore')->once()->andThrow(new \RuntimeException('restore failed'));
        $this->app->instance(AuditLogger::class, $logger);
        $this->app->instance(ClinicalDocumentStorage::class, $storage);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($rnd, 'sanctum')->deleteJson("/api/rnd/screening-documents/{$document->uuid}");
            $this->fail('The mutation failure was not rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('original mutation failed', $exception->getMessage());
        }

        $this->assertModelExists($document);
        Queue::assertCount(1);
    }

    public function test_restore_job_rejects_malformed_or_outside_move_paths(): void
    {
        Storage::fake('local');
        $storage = app(ClinicalDocumentStorage::class);
        $clinical = Storage::path('documents/ncp/valid.pdf');
        $quarantine = Storage::path('documents/ncp/.clinical-quarantine/valid');
        $outside = tempnam(sys_get_temp_dir(), 'restore-outside-');

        try {
            foreach ([
                ['original' => '../outside.pdf', 'quarantine' => $quarantine],
                ['original' => $clinical, 'quarantine' => $outside],
            ] as $move) {
                $rejected = false;
                try {
                    (new RestoreQuarantinedClinicalFile($move))->handle($storage);
                } catch (\RuntimeException) {
                    $rejected = true;
                }
                $this->assertTrue($rejected, 'Unsafe restore move was accepted.');
            }
        } finally {
            @unlink($outside);
        }
    }

    public function test_restore_job_rejects_valid_move_when_both_files_are_missing(): void
    {
        Storage::fake('local');
        Storage::makeDirectory('documents/ncp/.clinical-quarantine');
        $job = new RestoreQuarantinedClinicalFile([
            'original' => Storage::path('documents/ncp/missing.pdf'),
            'quarantine' => Storage::path('documents/ncp/.clinical-quarantine/missing'),
        ]);

        $this->expectException(\RuntimeException::class);
        $job->handle(app(ClinicalDocumentStorage::class));
    }
}
