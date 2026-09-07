<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\TransitionNcpRecordRequest;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\ClinicalCompletenessService;
use Illuminate\Http\JsonResponse;

class NcpRecordController extends Controller
{
    public function __construct(
        private readonly AuditPolicy $auditPolicy,
        private readonly ClinicalCompletenessService $completeness,
    ) {}

    public function transition(TransitionNcpRecordRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        abort_unless(in_array($ncpRecord->status, ['draft', 'active'], true), 422, 'Only the current cycle can be changed.');

        if ($request->validated('action') === 'complete' && ! $this->completeness->initialAdiComplete($ncpRecord)) {
            return response()->json([
                'message' => 'Complete Assessment, Diagnosis, and Intervention before protecting this cycle.',
            ], 422);
        }

        $data = $request->validated('action') === 'complete'
            ? ['status' => 'completed', 'discontinuation_reason_code' => null]
            : ['status' => 'discontinued', 'discontinuation_reason_code' => $request->validated('reason_code')];
        $this->audited(fn () => $ncpRecord->update($data));

        return response()->json(['data' => [
            'id' => $ncpRecord->uuid,
            'status' => $ncpRecord->status,
        ]]);
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}
     * Blocked when the record has gone through Assessment → Diagnosis → Intervention.
     */
    public function destroy(NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
        $isOfficial = $this->completeness->initialAdiComplete($ncpRecord);

        if ($isOfficial) {
            return response()->json([
                'message' => 'This NCP record has completed clinical data (Assessment through Intervention) and cannot be deleted.',
            ], 422);
        }

        $this->audited(fn () => $ncpRecord->delete());

        return response()->json(null, 204);
    }
}
