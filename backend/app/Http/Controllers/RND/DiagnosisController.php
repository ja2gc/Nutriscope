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
        
        $diagnosis->pes_statement = Diagnosis::buildPes(
            $diagnosis->problem,
            $diagnosis->etiology,
            $diagnosis->signs_symptoms
        );

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

        $diagnosis->pes_statement = Diagnosis::buildPes(
            $diagnosis->problem,
            $diagnosis->etiology,
            $diagnosis->signs_symptoms
        );

        $diagnosis->save();

        return new DiagnosisResource($diagnosis);
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

        $diagnosis->delete();

        return response()->noContent();
    }
}
