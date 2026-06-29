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
use App\Services\RiskScoreCalculator;
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
        $biochemicalData = $data['biochemical_data'] ?? null;
        $manualOverride = (bool) ($data['risk_score_manual_override'] ?? false);
        $manualFactors = $data['risk_score_manual_factors'] ?? [];
        unset($data['biochemical_data'], $data['risk_score_manual_override'], $data['risk_score_manual_factors']);

        $assessment = new Assessment($data);
        $assessment->ncp_record_id = $ncpRecord->id;
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        if ($biochemicalData) {
            $assessment->biochemicalData()->create($biochemicalData);
            $assessment->load('biochemicalData');
        }

        $this->saveRiskScore($ncpRecord, $assessment, $manualOverride, $manualFactors);

        return (new AssessmentResource($assessment->fresh()->load('biochemicalData')))->response()->setStatusCode(201);
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
        $biochemicalData = $data['biochemical_data'] ?? null;
        $manualOverride = array_key_exists('risk_score_manual_override', $data)
            ? (bool) $data['risk_score_manual_override']
            : (bool) $ncpRecord->risk_score_manual_override;
        $manualFactors = array_key_exists('risk_score_manual_factors', $data)
            ? $data['risk_score_manual_factors']
            : ($ncpRecord->risk_score_manual_factors ?? []);
        unset($data['biochemical_data'], $data['risk_score_manual_override'], $data['risk_score_manual_factors']);

        $assessment->fill($data);
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        if ($biochemicalData !== null) {
            $assessment->biochemicalData()->updateOrCreate([], $biochemicalData);
            $assessment->load('biochemicalData');
        }

        $this->saveRiskScore($ncpRecord, $assessment, $manualOverride, $manualFactors);

        return new AssessmentResource($assessment->fresh()->load('biochemicalData'));
    }

    /**
     * @param array<int, string> $manualFactors
     */
    private function saveRiskScore(NcpRecord $ncpRecord, Assessment $assessment, bool $manualOverride, array $manualFactors): void
    {
        $calculator = resolve(RiskScoreCalculator::class);
        $riskResult = $calculator->calculate($assessment);

        if ($manualOverride) {
            $factors = array_values(array_unique($manualFactors));
            $score = RiskScoreCalculator::scoreFactors($factors);
            $nutritionalStatus = RiskScoreCalculator::nutritionalStatusForScore($score);

            $assessment->update(['nutritional_status' => $nutritionalStatus]);
            $ncpRecord->update([
                'risk_score' => $score,
                'risk_score_manual_override' => true,
                'risk_score_manual_factors' => $factors,
            ]);

            return;
        }

        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
        $ncpRecord->update([
            'risk_score' => $riskResult['score'],
            'risk_score_manual_override' => false,
            'risk_score_manual_factors' => null,
        ]);
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

        $file = $request->file('file');
        // Store the disk-relative path (portable) — readers resolve it to an absolute
        // path at access time. Storing an absolute path breaks if the app root moves (A8).
        $path = $file->store('documents/ncp');

        // AS-02: link the document to the NCP cycle directly. Do NOT create an
        // assessment row — uploading a file must not satisfy the Assessment gate.
        // If an assessment already exists, keep the legacy link populated too.
        $document = ScreeningDocument::create([
            'patient_id'    => $ncpRecord->patient_id,
            'ncp_record_id' => $ncpRecord->id,
            'assessment_id' => $ncpRecord->assessment?->id,
            'type'          => $validated['type'] ?? null,
            'file_path'     => $path,
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
        // Scope by cycle. Include legacy rows that only carry the assessment link
        // (pre-backfill) so nothing disappears for older records.
        $assessmentId = $ncpRecord->assessment?->id;

        $type = request()->query('type');

        $docs = ScreeningDocument::query()
            ->where(function ($q) use ($ncpRecord, $assessmentId) {
                $q->where('ncp_record_id', $ncpRecord->id);
                if ($assessmentId) {
                    $q->orWhere('assessment_id', $assessmentId);
                }
            })
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest()
            ->get();

        return ScreeningDocumentResource::collection($docs)->response();
    }
}
