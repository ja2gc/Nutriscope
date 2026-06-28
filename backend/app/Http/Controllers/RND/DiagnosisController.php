<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreDiagnosisRequest;
use App\Http\Requests\RND\UpdateDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiagnosisController extends Controller
{
    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/diagnoses
     */
    public function index(NcpRecord $ncpRecord): AnonymousResourceCollection
    {
        $diagnoses = $ncpRecord->diagnoses;
        return DiagnosisResource::collection($diagnoses);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/diagnoses
     */
    public function store(StoreDiagnosisRequest $request, NcpRecord $ncpRecord)
    {
        // ADIME step order: the assessment must precede the diagnosis.
        if (! $ncpRecord->assessment()->exists()) {
            return response()->json([
                'message' => 'Record the nutrition assessment before adding a diagnosis.',
            ], 422);
        }

        $data = $request->validated();
        $diagnosis = new Diagnosis($data);
        $diagnosis->ncp_record_id = $ncpRecord->id;
        $diagnosis->pes_statement = $this->resolvePes($data, $diagnosis);
        $diagnosis->save();

        return (new DiagnosisResource($diagnosis))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}
     */
    public function update(UpdateDiagnosisRequest $request, NcpRecord $ncpRecord, Diagnosis $diagnosis): DiagnosisResource
    {
        // Scope check
        if ($diagnosis->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $data = $request->validated();
        $diagnosis->fill($data);
        $diagnosis->pes_statement = $this->resolvePes($data, $diagnosis);
        $diagnosis->save();

        return new DiagnosisResource($diagnosis);
    }

    /**
     * Use the RND's manual PES override when one is supplied (DP-02 — previously
     * the override was silently discarded), otherwise derive the statement from
     * the P-E-S components. Components are guaranteed non-empty by validation.
     */
    private function resolvePes(array $data, Diagnosis $diagnosis): string
    {
        $override = trim((string) ($data['pes_statement'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        return Diagnosis::buildPes(
            $diagnosis->problem,
            $diagnosis->etiology,
            $diagnosis->signs_symptoms
        );
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/diagnoses/{diagnosis}
     */
    public function destroy(NcpRecord $ncpRecord, Diagnosis $diagnosis)
    {
        // Scope check
        if ($diagnosis->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        // DP-04 / DI-02: an active or completed care plan must always retain at
        // least one PES — its intervention/monitoring would otherwise be orphaned.
        $isLast = $ncpRecord->diagnoses()->count() <= 1;
        $locked = in_array($ncpRecord->status, ['active', 'completed'], true);
        if ($isLast && $locked) {
            return response()->json([
                'message' => 'Cannot remove the last diagnosis from an active care plan. Add a replacement diagnosis first, or reopen the workflow.',
            ], 422);
        }

        $diagnosis->delete();

        return response()->noContent();
    }
}
