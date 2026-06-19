<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Http\Requests\RND\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\ScreeningDocumentResource;
use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\ScreeningDocument;
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
        $assessment = $ncpRecord->assessment()->with('biochemicalData')->firstOrFail();
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
     * POST /api/rnd/ncp-records/{ncpRecord}/attachments
     *
     * Plain supporting-document upload linked to this NCP cycle (rnd.md §3.1).
     * No OCR/extraction — file storage only.
     */
    public function uploadAttachment(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240',
            'type' => 'nullable|string|max:50',
        ]);

        $assessment = $ncpRecord->assessment()->firstOrCreate([
            'ncp_record_id' => $ncpRecord->id,
        ]);

        $file = $request->file('file');
        // Store the disk-relative path (portable) — readers resolve it to an absolute
        // path at access time. Storing an absolute path breaks if the app root moves (A8).
        $path = $file->store('documents/ncp');

        $document = ScreeningDocument::create([
            'patient_id' => $ncpRecord->patient_id,
            'assessment_id' => $assessment->id,
            'type' => $validated['type'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return (new ScreeningDocumentResource($document))->response()->setStatusCode(201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/attachments
     *
     * Attachments scoped to this NCP cycle — never mixes across a patient's cycles.
     */
    public function listAttachments(NcpRecord $ncpRecord): JsonResponse
    {
        $assessment = $ncpRecord->assessment;
        $docs = $assessment
            ? $assessment->screeningDocuments()->latest()->get()
            : collect();

        return ScreeningDocumentResource::collection($docs)->response();
    }
}

