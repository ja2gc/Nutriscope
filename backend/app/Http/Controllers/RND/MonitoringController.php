<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use App\Http\Resources\MonitoringResource;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Services\AIService;
use App\Services\MonitoringSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;

class MonitoringController extends Controller
{
    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/monitorings/summary
     *
     * Phase 6.2 — rule-based, ZERO-TOKEN last-vs-current delta summary.
     */
    public function summary(NcpRecord $ncpRecord, MonitoringSummaryService $svc): JsonResponse
    {
        return response()->json(['data' => $svc->summarize($ncpRecord)]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/monitorings/ai-review
     *
     * Phase 6.3 — optional Haiku narrative over the compact delta object. Cached
     * per visit-pair (re-used while the compared values are unchanged) and
     * rate-limited so cost stays negligible.
     */
    public function aiReview(NcpRecord $ncpRecord, MonitoringSummaryService $svc, AIService $ai): JsonResponse
    {
        $summary = $svc->summarize($ncpRecord);
        if (! ($summary['has_data'] ?? false)) {
            return response()->json(['message' => 'No monitoring visits to review yet.'], 422);
        }

        $latest = $ncpRecord->monitorings()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $signature = md5(json_encode([$summary['changes'], $summary['intake']]));

        // Cache hit — same visit-pair already narrated.
        if ($latest && $latest->ai_review && $latest->ai_review_key === $signature) {
            return response()->json(['data' => ['narrative' => $latest->ai_review, 'cached' => true]]);
        }

        // Rate-limit: 5 AI reviews per user per minute (per-user, not per-NCP —
        // keying on the record let a user bypass the cap by switching NCPs).
        $rlKey = 'ai-review:' . (auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            return response()->json(['message' => 'Too many AI reviews. Try again shortly.'], 429);
        }
        RateLimiter::hit($rlKey, 60);

        $narrative = $ai->narrateMonitoring($summary);
        if ($narrative === null) {
            return response()->json(['message' => 'AI review is temporarily unavailable. The rule-based summary is still shown.'], 503);
        }

        if ($latest) {
            $latest->update(['ai_review' => $narrative, 'ai_review_key' => $signature]);
        }

        return response()->json(['data' => ['narrative' => $narrative, 'cached' => false]]);
    }

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
        // Monitoring & Evaluation is a FOLLOW-UP activity: the initial encounter
        // produces the care plan (assessment → diagnosis → intervention). Block
        // monitoring on the first encounter, before that plan exists.
        if (! $ncpRecord->intervention()->exists()) {
            return response()->json([
                'message' => 'Monitoring is for follow-up visits. Complete the assessment, diagnosis, and intervention first.',
            ], 422);
        }

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
