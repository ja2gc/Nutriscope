<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentExtraction;
use App\Models\NcpRecord;
use App\Models\OcrDocument;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A8 — screening/OCR file paths must be stored disk-relative (portable), not as
 * absolute filesystem paths that break when the app root moves.
 */
class ScreeningUploadPathTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name' => 'RND', 'email' => 'rnd-upload@example.com',
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

    public function test_upload_screening_stores_disk_relative_path(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->id}/upload-screening",
            ['file' => UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf')]
        )->assertStatus(202);

        $doc = ScreeningDocument::firstOrFail();
        $this->assertStringStartsWith('documents/screening/', $doc->file_path);
        $this->assertStringNotContainsString(storage_path(), $doc->file_path);
    }

    public function test_upload_labs_stores_disk_relative_path(): void
    {
        Storage::fake('local');
        Queue::fake();
        $rnd = $this->rnd();
        $ncp = $this->ncp($rnd);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/ncp-records/{$ncp->id}/upload-labs",
            ['file' => UploadedFile::fake()->create('labs.pdf', 10, 'application/pdf')]
        )->assertStatus(202);

        $doc = OcrDocument::firstOrFail();
        $this->assertStringStartsWith('documents/labs/', $doc->file_path);
        $this->assertStringNotContainsString(storage_path(), $doc->file_path);
    }
}
