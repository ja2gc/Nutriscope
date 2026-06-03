<?php

namespace App\Jobs;

use App\Events\DocumentExtractionCompleted;
use App\Models\ExtractionLog;
use App\Models\ExtractionTemplate;
use App\Models\OcrDocument;
use App\Models\ScreeningDocument;
use App\Services\ExtractionService;
use App\Services\OCRService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ScreeningDocument $screeningDocument) {}

    public function handle(OCRService $ocrService, ExtractionService $extractionService): void
    {
        $startTime = microtime(true);
        $this->screeningDocument->update(['status' => 'processing']);

        try {
            // 1. Run OCR
            $rawText = $ocrService->extractText($this->screeningDocument->file_path);
            $endTime = microtime(true);
            $processingTimeMs = (int) (($endTime - $startTime) * 1000);

            // 2. Find template matching the screening document type
            $docType = $this->screeningDocument->type === 'pediatric' ? 'screening_pediatric' : 'screening_adult';
            $template = ExtractionTemplate::where('document_type', $docType)
                ->where('is_active', true)
                ->first();

            $extractedData = [];
            $confidence = 1.0;
            $templateId = null;

            if ($template) {
                $templateId = $template->id;
                // 3. Extract fields via regex patterns
                $extractedData = $extractionService->extract($template, $rawText);
                $confidence = $extractionService->calculateConfidence($extractedData);
            }

            $ocrDocType = match ($docType) {
                'screening_adult', 'screening_pediatric' => 'screening',
                'lab_result' => 'lab',
                default => 'screening',
            };

            $userId = $this->screeningDocument->reviewed_by;
            if (!$userId) {
                $userId = \App\Models\User::where('role', 'Admin')->first()?->id
                    ?? \App\Models\User::where('role', 'RND')->first()?->id
                    ?? \App\Models\User::first()?->id
                    ?? 1;
            }

            // 4. Save to ocr_documents
            $ocrDoc = OcrDocument::create([
                'user_id' => $userId,
                'assessment_id' => $this->screeningDocument->assessment_id,
                'file_path' => $this->screeningDocument->file_path,
                'extracted_text' => $rawText,
                'document_type' => $ocrDocType,
                'extraction_template_id' => $templateId,
                'parsed_fields' => $extractedData,
                'confidence_score' => $confidence,
                'processing_time_ms' => $processingTimeMs,
                'status' => 'completed',
            ]);


            // 5. Save to extraction_logs
            ExtractionLog::create([
                'screening_document_id' => $this->screeningDocument->id,
                'ocr_document_id' => $ocrDoc->id,
                'source_type' => 'screening',
                'raw_text' => $rawText,
                'parsed_fields' => $extractedData,
                'confidence_scores' => ['overall' => $confidence],
                'errors' => [],
                'processing_time_ms' => $processingTimeMs,
            ]);

            // 6. Update assessment and ncpRecord if attached
            if ($this->screeningDocument->assessment_id) {
                $assessment = $this->screeningDocument->assessment;
                if ($assessment) {
                    if (isset($extractedData['weight'])) {
                        $assessment->weight = $extractedData['weight'];
                    }
                    if (isset($extractedData['height'])) {
                        $assessment->height = $extractedData['height'];
                    }
                    $assessment->bmi = $assessment->calculateBmi();
                    $assessment->save();

                    $calculator = resolve(\App\Services\RiskScoreCalculator::class);
                    $riskResult = $calculator->calculate($assessment);

                    $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
                    if ($assessment->ncpRecord) {
                        $assessment->ncpRecord->update(['risk_score' => $riskResult['score']]);
                    }
                }
            }

            // 7. Update screening document status and data

            $this->screeningDocument->update([
                'status' => 'completed',
                'extracted_data' => $extractedData,
                'confidence_score' => $confidence,
            ]);

            // 7. Dispatch completed event
            event(new DocumentExtractionCompleted($this->screeningDocument));

        } catch (Exception $e) {
            Log::error("Document extraction failed: " . $e->getMessage());
            $this->screeningDocument->update(['status' => 'failed']);
            throw $e;
        }
    }
}
