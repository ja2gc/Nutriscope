<?php

namespace Tests\Feature;

use App\Models\ExtractionTemplate;
use App\Models\OcrDocument;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\OCRService;
use App\Services\ExtractionService;
use App\Jobs\ProcessDocumentExtraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OcrExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_service_calls_paddleocr_and_returns_text(): void
    {
        Http::fake([
            'http://localhost:8000/*' => Http::response(['text' => 'Weight: 75.2 kg, Height: 178 cm'], 200),
        ]);

        config(['services.paddleocr.url' => 'http://localhost:8000/ocr']);

        $ocrService = resolve(OCRService::class);
        $result = $ocrService->extractText('fake_path.png');

        $this->assertStringContainsString('Weight: 75.2 kg', $result);
    }

    public function test_ocr_service_uses_fallback_when_http_fails(): void
    {
        Http::fake([
            'http://localhost:8000/*' => Http::response(null, 500),
        ]);

        config(['services.paddleocr.url' => 'http://localhost:8000/ocr']);

        $ocrService = resolve(OCRService::class);
        $result = $ocrService->extractText('fake_path.png');

        // Fallback should return mock text or sensible placeholder rather than throwing
        $this->assertNotEmpty($result);
    }

    public function test_extraction_service_matches_regex_patterns(): void
    {
        $template = ExtractionTemplate::forceCreate([
            'document_type' => 'screening_adult',
            'field_mappings' => [
                'weight' => 'Weight:\s*([\d\.]+)',
                'height' => 'Height:\s*([\d\.]+)',
            ],
            'validation_rules' => [],
            'version' => '1.0.0',
            'is_active' => true,
        ]);

        $extractionService = resolve(ExtractionService::class);
        $extracted = $extractionService->extract($template, 'Weight: 68.5 Height: 165');

        $this->assertEquals(68.5, $extracted['weight']);
        $this->assertEquals(165, $extracted['height']);
    }

    public function test_process_document_extraction_job_runs_ocr_and_extraction(): void
    {
        $user = User::forceCreate([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role' => 'Admin',
            'is_active' => true,
        ]);

        $patient = Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => now()->toDateString(),
        ]);

        $screeningDoc = ScreeningDocument::forceCreate([
            'patient_id' => $patient->id,
            'type' => 'adult',
            'file_path' => 'documents/screening.png',
            'status' => 'pending',
            'reviewed_by' => $user->id,
        ]);


        $template = ExtractionTemplate::forceCreate([
            'document_type' => 'screening_adult',
            'field_mappings' => [
                'weight' => 'weight\s*:\s*([\d\.]+)',
                'height' => 'height\s*:\s*([\d\.]+)',
            ],
            'validation_rules' => [],
            'version' => '1.0.0',
            'is_active' => true,
        ]);

        config(['services.paddleocr.url' => 'http://localhost:8000/ocr']);

        Http::fake([
            'http://localhost:8000/*' => Http::response(['text' => 'weight: 80.0, height: 180.0'], 200),
        ]);

        // Run the job synchronously
        ProcessDocumentExtraction::dispatchSync($screeningDoc);

        $screeningDoc->refresh();
        $this->assertEquals('completed', $screeningDoc->status);
        $this->assertNotNull($screeningDoc->extracted_data);
        $this->assertEquals(80.0, $screeningDoc->extracted_data['weight']);
        $this->assertEquals(180.0, $screeningDoc->extracted_data['height']);
    }

    public function test_process_document_extraction_job_handles_missing_reviewer_gracefully(): void
    {
        $admin = User::forceCreate([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'role' => 'Admin',
            'is_active' => true,
        ]);

        $patient = Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => now()->toDateString(),
        ]);

        $screeningDoc = ScreeningDocument::forceCreate([
            'patient_id' => $patient->id,
            'type' => 'adult',
            'file_path' => 'documents/screening.png',
            'status' => 'pending',
            'reviewed_by' => null,
        ]);

        $template = ExtractionTemplate::forceCreate([
            'document_type' => 'screening_adult',
            'field_mappings' => [
                'weight' => 'weight\s*:\s*([\d\.]+)',
                'height' => 'height\s*:\s*([\d\.]+)',
            ],
            'validation_rules' => [],
            'version' => '1.0.0',
            'is_active' => true,
        ]);

        config(['services.paddleocr.url' => 'http://localhost:8000/ocr']);

        Http::fake([
            'http://localhost:8000/*' => Http::response(['text' => 'weight: 80.0, height: 180.0'], 200),
        ]);

        ProcessDocumentExtraction::dispatchSync($screeningDoc);

        $screeningDoc->refresh();
        $this->assertEquals('completed', $screeningDoc->status);

        $this->assertDatabaseHas('ocr_documents', [
            'user_id' => $admin->id,
            'file_path' => 'documents/screening.png',
        ]);
    }
}
