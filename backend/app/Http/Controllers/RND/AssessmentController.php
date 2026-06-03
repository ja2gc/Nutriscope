<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Http\Requests\RND\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\ScreeningDocument;
use App\Models\OcrDocument;
use App\Jobs\ProcessDocumentExtraction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function store(StoreAssessmentRequest $request, NcpRecord $ncpRecord)
    {
        if ($ncpRecord->assessment()->exists()) {
            return response()->json(['message' => 'Assessment already exists for this NCP record.'], 409);
        }

        $data = $request->validated();
        $assessment = new Assessment($data);
        $assessment->ncp_record_id = $ncpRecord->id;
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        // Calculate and save risk score
        $calculator = resolve(\App\Services\RiskScoreCalculator::class);
        $riskResult = $calculator->calculate($assessment);

        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
        $ncpRecord->update(['risk_score' => $riskResult['score']]);

        return (new AssessmentResource($assessment->fresh()))->response()->setStatusCode(201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function show(NcpRecord $ncpRecord): AssessmentResource
    {
        $assessment = $ncpRecord->assessment()->firstOrFail();
        return new AssessmentResource($assessment);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function update(UpdateAssessmentRequest $request, NcpRecord $ncpRecord): AssessmentResource
    {
        $assessment = $ncpRecord->assessment()->firstOrFail();
        
        $data = $request->validated();
        $assessment->fill($data);
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        // Calculate and save risk score
        $calculator = resolve(\App\Services\RiskScoreCalculator::class);
        $riskResult = $calculator->calculate($assessment);

        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
        $ncpRecord->update(['risk_score' => $riskResult['score']]);

        return new AssessmentResource($assessment->fresh());
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/upload-screening
     */
    public function uploadScreening(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        $assessment = $ncpRecord->assessment()->firstOrCreate([
            'ncp_record_id' => $ncpRecord->id,
        ]);

        $path = $request->file('file')->store('documents/screening');
        $absolutePath = storage_path('app/' . $path);

        $screeningDocument = ScreeningDocument::create([
            'patient_id' => $ncpRecord->patient_id,
            'assessment_id' => $assessment->id,
            'type' => $ncpRecord->patient->screening_type === 'pediatric' ? 'pediatric' : 'adult',
            'file_path' => $absolutePath,
            'status' => 'pending',
            'reviewed_by' => $request->user()->id,
        ]);

        ProcessDocumentExtraction::dispatch($screeningDocument);

        return response()->json([
            'message' => 'Screening document uploaded successfully and extraction queued.',
            'data' => $screeningDocument,
        ], 202);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/upload-labs
     */
    public function uploadLabs(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
        ]);

        $assessment = $ncpRecord->assessment()->firstOrCreate([
            'ncp_record_id' => $ncpRecord->id,
        ]);

        $path = $request->file('file')->store('documents/labs');
        $absolutePath = storage_path('app/' . $path);

        $ocrDocument = OcrDocument::create([
            'user_id' => $request->user()->id,
            'assessment_id' => $assessment->id,
            'file_path' => $absolutePath,
            'document_type' => 'lab',
            'status' => 'pending',
        ]);

        ProcessDocumentExtraction::dispatch($ocrDocument, 'lab_result');

        return response()->json([
            'message' => 'Lab document uploaded successfully and extraction queued.',
            'data' => $ocrDocument,
        ], 202);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/screening-document
     */
    public function showScreeningDocument(NcpRecord $ncpRecord): JsonResponse
    {
        $assessment = $ncpRecord->assessment;
        if (!$assessment) {
            return response()->json(['data' => null]);
        }

        $doc = ScreeningDocument::where('assessment_id', $assessment->id)->latest()->first();
        return response()->json(['data' => $doc]);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/ocr-documents
     */
    public function showOcrDocuments(NcpRecord $ncpRecord): JsonResponse
    {
        $assessment = $ncpRecord->assessment;
        if (!$assessment) {
            return response()->json(['data' => []]);
        }

        $docs = OcrDocument::where('assessment_id', $assessment->id)->get();
        return response()->json(['data' => $docs]);
    }
}

