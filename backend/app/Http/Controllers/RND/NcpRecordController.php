<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use Illuminate\Http\JsonResponse;

class NcpRecordController extends Controller
{
    public function __construct(private readonly AuditPolicy $auditPolicy) {}

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}
     * Blocked when the record has gone through Assessment → Diagnosis → Intervention.
     */
    public function destroy(NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
        $isOfficial = $ncpRecord->assessment()->exists()
            && $ncpRecord->diagnoses()->exists()
            && $ncpRecord->intervention()->exists();

        if ($isOfficial) {
            return response()->json([
                'message' => 'This NCP record has completed clinical data (Assessment through Intervention) and cannot be deleted.',
            ], 422);
        }

        $this->audited(fn () => $ncpRecord->delete());

        return response()->json(null, 204);
    }
}
