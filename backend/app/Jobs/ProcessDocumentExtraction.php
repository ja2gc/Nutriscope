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

    public $document;
    public $docType;

    public function __construct($document, ?string $docType = null)
    {
        $this->document = $document;
        if ($docType) {
            $this->docType = $docType;
        } else {
            if ($document instanceof ScreeningDocument) {
                $this->docType = $document->type === 'pediatric' ? 'screening_pediatric' : 'screening_adult';
            } else {
                $this->docType = 'lab_result';
            }
        }
    }

    /**
     * Resolve a stored file_path to an absolute filesystem path. Supports both the
     * new disk-relative paths (A8) and any legacy absolute paths already in the DB.
     */
    private function resolveFilePath(string $storedPath): string
    {
        if (file_exists($storedPath)) {
            return $storedPath; // legacy absolute path
        }

        return storage_path('app/' . ltrim($storedPath, '/\\'));
    }

    public function handle(OCRService $ocrService, ExtractionService $extractionService): void
    {
        $startTime = microtime(true);
        $this->document->update(['status' => 'processing']);

        try {
            // 1. Run OCR. file_path is stored disk-relative (A8); resolve to an absolute
            // filesystem path here since OCRService reads the file directly.
            $absolutePath = $this->resolveFilePath($this->document->file_path);
            $rawText = $ocrService->extractText($absolutePath);
            $endTime = microtime(true);
            $processingTimeMs = (int) (($endTime - $startTime) * 1000);

            // 2. Find template matching the document type
            $template = ExtractionTemplate::where('document_type', $this->docType)
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

            $userId = $this->document->reviewed_by ?? null;
            if (!$userId && method_exists($this->document, 'user')) {
                $userId = $this->document->user_id;
            }
            if (!$userId) {
                $userId = \App\Models\User::where('role', 'Admin')->first()?->id
                    ?? \App\Models\User::where('role', 'RND')->first()?->id
                    ?? \App\Models\User::first()?->id
                    ?? 1;
            }

            // 4. For screening docs, detect checked checkboxes.
            //    Primary: text-based (works for any hospital layout).
            //    Fallback: pixel OMR (tuned to B.06/B.07 template).
            if ($this->document instanceof ScreeningDocument) {
                $screeningType = $this->document->type ?? 'adult';

                $checkboxData = $ocrService->parseCheckboxesFromText($rawText, $screeningType);

                if (empty($checkboxData['clinical_conditions']) && empty($checkboxData['intake_weight_history'])) {
                    Log::info("OCR text found no checkboxes; falling back to pixel OMR.");
                    $checkboxData = $ocrService->detectCheckboxes($absolutePath, $screeningType);
                }

                $extractedData = array_merge($extractedData, $checkboxData);
            }

            // 5. Handle ScreeningDocument
            if ($this->document instanceof ScreeningDocument) {
                $ocrDoc = OcrDocument::create([
                    'user_id' => $userId,
                    'assessment_id' => $this->document->assessment_id,
                    'file_path' => $this->document->file_path,
                    'extracted_text' => $rawText,
                    'document_type' => 'screening',
                    'extraction_template_id' => $templateId,
                    'parsed_fields' => $extractedData,
                    'confidence_score' => $confidence,
                    'processing_time_ms' => $processingTimeMs,
                    'status' => 'completed',
                ]);

                // Save to extraction_logs
                \App\Models\ExtractionLog::create([
                    'screening_document_id' => $this->document->id,
                    'ocr_document_id' => $ocrDoc->id,
                    'source_type' => 'screening',
                    'raw_text' => $rawText,
                    'parsed_fields' => $extractedData,
                    'confidence_scores' => ['overall' => $confidence],
                    'errors' => [],
                    'processing_time_ms' => $processingTimeMs,
                ]);

                // Update assessment weight, height, and recalculate BMI
                if ($this->document->assessment_id) {
                    $assessment = $this->document->assessment;
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

                $this->document->update([
                    'status' => 'completed',
                    'extracted_data' => $extractedData,
                    'confidence_score' => $confidence,
                ]);
            } else if ($this->document instanceof OcrDocument) {
                // Handle OcrDocument (for lab results)
                $this->document->update([
                    'extracted_text' => $rawText,
                    'extraction_template_id' => $templateId,
                    'parsed_fields' => $extractedData,
                    'confidence_score' => $confidence,
                    'processing_time_ms' => $processingTimeMs,
                    'status' => 'completed',
                ]);

                // Save to extraction_logs
                \App\Models\ExtractionLog::create([
                    'screening_document_id' => null,
                    'ocr_document_id' => $this->document->id,
                    'source_type' => 'lab',
                    'raw_text' => $rawText,
                    'parsed_fields' => $extractedData,
                    'confidence_scores' => ['overall' => $confidence],
                    'errors' => [],
                    'processing_time_ms' => $processingTimeMs,
                ]);

                // Update biochemical_data values if assessment_id is set
                if ($this->document->assessment_id) {
                    $assessment = $this->document->assessment;
                    if ($assessment) {
                        $bio = $assessment->biochemicalData ?: new \App\Models\BiochemicalData(['assessment_id' => $assessment->id]);
                        foreach ($extractedData as $field => $val) {
                            if (in_array($field, $bio->getFillable())) {
                                $bio->$field = $val;
                            }
                        }
                        $bio->save();
                    }
                }
            }

            // 7. Dispatch completed event
            event(new DocumentExtractionCompleted($this->document));

        } catch (Exception $e) {
            Log::error("Document extraction failed: " . $e->getMessage());
            $this->document->update(['status' => 'failed']);
            throw $e;
        }
    }

}
