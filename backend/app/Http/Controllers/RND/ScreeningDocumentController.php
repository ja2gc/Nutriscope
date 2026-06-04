<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScreeningDocumentResource;
use App\Models\ScreeningDocument;
use App\Services\RiskScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreeningDocumentController extends Controller
{
    public function show(ScreeningDocument $screeningDocument): ScreeningDocumentResource
    {
        return new ScreeningDocumentResource($screeningDocument);
    }

    public function approve(
        Request $request,
        ScreeningDocument $screeningDocument,
        RiskScoreCalculator $riskScoreCalculator
    ): JsonResponse {
        $validated = $request->validate([
            'mapped_fields' => ['required', 'array'],
        ]);

        $mappedFields = $validated['mapped_fields'];
        $screeningDocument->mapped_fields = $mappedFields;
        $screeningDocument->status = 'completed';
        $screeningDocument->reviewed_by = $request->user()?->id;
        $screeningDocument->reviewed_at = now();
        $screeningDocument->save();

        $assessment = $screeningDocument->assessment;
        if ($assessment) {
            if (is_null($assessment->height) && isset($mappedFields['height'])) {
                $assessment->height = $this->normalizeDecimal($mappedFields['height']);
            }

            if (is_null($assessment->weight) && isset($mappedFields['weight'])) {
                $assessment->weight = $this->normalizeDecimal($mappedFields['weight']);
            }

            $assessment->bmi = $assessment->calculateBmi();
            $assessment->save();

            $riskResult = $riskScoreCalculator->calculate($assessment->fresh(['ncpRecord.patient', 'biochemicalData']));
            $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
            $assessment->ncpRecord?->update(['risk_score' => $riskResult['score']]);
        }

        return response()->json([
            'data' => new ScreeningDocumentResource($screeningDocument->fresh()),
        ]);
    }

    public function file(ScreeningDocument $screeningDocument): BinaryFileResponse
    {
        $absolutePath = $screeningDocument->file_path;

        // Support both absolute paths stored in DB and relative paths
        if (!file_exists($absolutePath)) {
            $absolutePath = storage_path('app/' . ltrim($absolutePath, '/\\'));
        }

        abort_unless(file_exists($absolutePath), 404, 'File not found.');

        return response()->file($absolutePath);
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
