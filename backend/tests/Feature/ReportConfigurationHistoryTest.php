<?php

namespace Tests\Feature;

use App\Exceptions\AuditLoggingUnavailable;
use App\Models\AuditActivity;
use App\Models\ReportBranding;
use App\Models\ReportConfigurationRevision;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportConfigurationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_card_history_is_read_only_paginated_and_audited_on_change(): void
    {
        $rnd = User::factory()->rnd()->create();
        $template = ReportTemplate::create([
            'type' => 'procurement_pack', 'name' => 'Procurement Pack',
            'blade_view' => 'reports.procurement-pack', 'is_active' => true,
            'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'First', 'title' => 'Chief']],
        ]);

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/report-templates/{$template->uuid}", [
            'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'Second', 'title' => 'Chief']],
        ])->assertOk();

        $history = $this->getJson("/api/rnd/report-templates/{$template->uuid}/history?per_page=1&page=1")
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.signatories.0.name', 'Second')
            ->assertJsonPath('data.0.editor.name', $rnd->display_name);
        $this->getJson("/api/rnd/report-templates/{$template->uuid}/history?per_page=1&page=2")
            ->assertOk()->assertJsonPath('data.0.signatories.0.name', 'First');

        $this->patchJson("/api/rnd/report-templates/{$template->uuid}", [
            'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'Second', 'title' => 'Chief']],
        ])->assertOk();
        $this->getJson("/api/rnd/report-templates/{$template->uuid}/history")
            ->assertJsonPath('meta.total', 2);
        $this->assertSame(1, AuditActivity::query()->where('subject_type', ReportTemplate::class)
            ->where('subject_id', $template->id)->where('event', 'updated')->count());
        $this->assertNotNull($history->json('data.0.created_at'));
    }

    public function test_branding_card_keeps_its_previous_values_and_audits_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $branding = ReportBranding::singleton();
        $before = $branding->hospital_name;

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/report-branding', [
            'hospital_name' => 'New Hospital',
        ])->assertOk();

        $this->getJson('/api/admin/report-branding/history?per_page=1&page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.hospital_name', 'New Hospital')
            ->assertJsonPath('data.0.editor.name', $admin->display_name);
        $this->getJson('/api/admin/report-branding/history?per_page=1&page=2')
            ->assertOk()->assertJsonPath('data.0.hospital_name', $before);
        $this->assertSame(1, AuditActivity::query()->where('subject_type', ReportBranding::class)
            ->where('subject_id', $branding->id)->where('event', 'updated')->count());
    }

    public function test_history_requires_a_report_editor_and_rejects_oversized_pages(): void
    {
        $template = ReportTemplate::create([
            'type' => 'procurement_pack', 'name' => 'Procurement Pack',
            'blade_view' => 'reports.procurement-pack', 'is_active' => true,
            'signatories' => [],
        ]);

        $this->getJson("/api/rnd/report-templates/{$template->uuid}/history")
            ->assertUnauthorized();

        $fss = User::factory()->create(['role' => 'FSS']);
        $this->actingAs($fss, 'sanctum')
            ->getJson("/api/rnd/report-templates/{$template->uuid}/history")
            ->assertForbidden();

        $rnd = User::factory()->rnd()->create();
        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/report-templates/{$template->uuid}/history?per_page=11")
            ->assertUnprocessable();
    }

    public function test_configuration_change_rolls_back_if_audit_is_unavailable(): void
    {
        $rnd = User::factory()->rnd()->create();
        $template = ReportTemplate::create([
            'type' => 'procurement_pack', 'name' => 'Procurement Pack',
            'blade_view' => 'reports.procurement-pack', 'is_active' => true,
            'signatories' => [],
        ]);
        $revisionCount = $template->revisions()->count();
        $audit = $this->createMock(AuditLogger::class);
        $audit->expects($this->once())->method('assertAvailable')
            ->willThrowException(new AuditLoggingUnavailable('Audit is unavailable.'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/report-templates/{$template->uuid}", [
                'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'New name', 'title' => 'Chief']],
            ]);
            $this->fail('The edit should fail closed when audit logging is unavailable.');
        } catch (AuditLoggingUnavailable) {
        }

        $this->assertSame([], $template->fresh()->signatories);
        $this->assertSame($revisionCount, $template->revisions()->count());
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_branding_history_streams_the_original_private_logo_without_copying_it(): void
    {
        Storage::fake('private_uploads');
        $admin = User::factory()->admin()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($admin, 'sanctum')->post('/api/admin/report-branding', [
            'logo_left' => UploadedFile::fake()->createWithContent('first.png', $png),
        ], ['Accept' => 'application/json'])->assertOk();
        $firstRevision = ReportConfigurationRevision::query()->where('kind', 'branding')->latest('id')->firstOrFail();
        $originalObjectId = $firstRevision->snapshot['logo_left_stored_object_id'];

        $this->post('/api/admin/report-branding', [
            'logo_left' => UploadedFile::fake()->createWithContent('second.png', $png),
        ], ['Accept' => 'application/json'])->assertOk();

        $history = $this->getJson('/api/admin/report-branding/history?per_page=3')->assertOk()->json('data');
        $firstListedRevision = collect($history)->firstWhere('id', $firstRevision->uuid);
        $this->assertSame('private', $firstListedRevision['logo_left_path']);
        $this->assertArrayNotHasKey('logo_left_stored_object_id', $firstListedRevision);
        $this->assertDatabaseHas('stored_objects', ['id' => $originalObjectId]);
        $this->get("/api/admin/report-branding/history/{$firstRevision->uuid}/logo/left")
            ->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}
