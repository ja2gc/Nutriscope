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
}
