<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Models\NcpRecord;
use Illuminate\Http\JsonResponse;

class NcpRecordController extends Controller
{
    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}
     * Blocked when the record has gone through Assessment → Diagnosis → Intervention.
     */
    public function destroy(NcpRecord $ncpRecord): JsonResponse
    {
        $isOfficial = $ncpRecord->assessment()->exists()
            && $ncpRecord->diagnoses()->exists()
            && $ncpRecord->intervention()->exists();

        if ($isOfficial) {
            return response()->json([
                'message' => 'This NCP record has completed clinical data (Assessment through Intervention) and cannot be deleted.',
            ], 422);
        }

        $ncpRecord->delete();

        return response()->json(null, 204);
    }
}
