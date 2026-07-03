<?php

namespace Tests\Feature;

use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
}
