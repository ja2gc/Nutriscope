<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use App\Http\Resources\MonitoringResource;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MonitoringController extends Controller
{
    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/monitorings
     */
    public function index(NcpRecord $ncpRecord): AnonymousResourceCollection
    {
        $monitorings = $ncpRecord->monitorings;
        return MonitoringResource::collection($monitorings);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/monitorings
     */
    public function store(StoreMonitoringRequest $request, NcpRecord $ncpRecord)
    {
        $data = $request->validated();
        $monitoring = new Monitoring($data);
        $monitoring->ncp_record_id = $ncpRecord->id;
        $monitoring->save();

        return (new MonitoringResource($monitoring))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}
     */
    public function update(UpdateMonitoringRequest $request, NcpRecord $ncpRecord, Monitoring $monitoring): MonitoringResource
    {
        if ($monitoring->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $data = $request->validated();
        $monitoring->fill($data);
        $monitoring->save();

        return new MonitoringResource($monitoring);
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}
     */
    public function destroy(NcpRecord $ncpRecord, Monitoring $monitoring)
    {
        if ($monitoring->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $monitoring->delete();

        return response()->noContent();
    }
}
