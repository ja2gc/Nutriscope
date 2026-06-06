<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreInterventionRequest;
use App\Http\Requests\RND\UpdateInterventionRequest;
use App\Http\Resources\InterventionResource;
use App\Models\Intervention;
use App\Models\NcpRecord;
use Illuminate\Http\JsonResponse;

class InterventionController extends Controller
{
    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function store(StoreInterventionRequest $request, NcpRecord $ncpRecord)
    {
        if ($ncpRecord->intervention()->exists()) {
            return response()->json(['message' => 'Intervention already exists for this NCP record.'], 409);
        }

        $data = $request->validated();
        $intervention = new Intervention($data);
        $intervention->ncp_record_id = $ncpRecord->id;
        $intervention->save();

        return (new InterventionResource($intervention))->response()->setStatusCode(201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function show(NcpRecord $ncpRecord): InterventionResource
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();
        return new InterventionResource($intervention);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function update(UpdateInterventionRequest $request, NcpRecord $ncpRecord): InterventionResource
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $data = $request->validated();
        $intervention->fill($data);
        $intervention->save();

        return new InterventionResource($intervention);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/intervention/recommendations
     * Auto-derives clinical rule conditions from the intervention's goal_type.
     */
    public function recommendations(NcpRecord $ncpRecord): JsonResponse
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $conditions = $this->mapGoalTypeToConditions($intervention->goal_type ?? '');
        $stages     = $intervention->disease_stage ? [$intervention->disease_stage] : null;

        $result = app(\App\Services\RecommendService::class)
            ->getRecommendations($conditions, $stages);

        return response()->json(['data' => $result]);
    }

    private function mapGoalTypeToConditions(string $goalType): array
    {
        return match ($goalType) {
            'renal_diet'        => ['CKD', 'Renal disease'],
            'diabetic_control'  => ['DM', 'High glucose'],
            'cardiac_diet'      => ['Cardiac', 'Hypertension'],
            'weight_gain'       => ['Malnutrition'],
            'high_protein'      => ['Low albumin', 'Malnutrition'],
            'liver_disease'     => ['Liver disease'],
            'malnutrition'      => ['Malnutrition'],
            default             => [],
        };
    }
}
