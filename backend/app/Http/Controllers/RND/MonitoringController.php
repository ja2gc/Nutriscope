<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use App\Http\Resources\MonitoringResource;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\AIService;
use App\Services\MonitoringPlanService;
use App\Services\MonitoringSummaryService;
use App\Services\NotificationLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;

class MonitoringController extends Controller
{
    public function __construct(private readonly AuditPolicy $auditPolicy) {}

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/monitorings/summary
     *
     * Phase 6.2 — rule-based, ZERO-TOKEN last-vs-current delta summary.
     */
    public function summary(NcpRecord $ncpRecord, MonitoringSummaryService $svc): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);

        return response()->json(['data' => $svc->summarize($ncpRecord)]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/monitorings/ai-review
     *
     * Phase 6.3 — optional Haiku narrative over the compact delta object. Cached
     * per visit-pair (re-used while the compared values are unchanged) and
     * rate-limited so cost stays negligible.
     */
    public function aiReview(NcpRecord $ncpRecord, MonitoringPlanService $planSvc, AIService $ai): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $plan = $planSvc->build($ncpRecord);

        $hasFollowup = collect($plan['visits'])->contains(fn ($v) => ($v['type'] ?? null) === 'monitoring');
        if (! $hasFollowup) {
            return response()->json(['message' => 'No follow-up visits to review yet.'], 422);
        }

        // Focused payload — tracked indicators' trajectory only (nothing else).
        $payload = [
            'pes_statements' => $plan['pes_statements'],
            'goal_type' => $plan['goal_type'],
            'nutritional_status' => $plan['nutritional_status'],
            'visit_count' => count($plan['visits']),
            'indicators' => array_map(fn ($i) => [
                'label' => $i['label'],
                'unit' => $i['unit'],
                'category' => $i['category'],
                'target' => $i['target'],
                'reference' => $i['reference'],
                'latest_status' => $i['latest_status'],
                'trajectory' => array_map(fn ($s) => ['visit' => $s['visit'], 'value' => $s['value']], $i['series']),
            ], $plan['indicators']),
        ];

        $latest = $ncpRecord->monitorings()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        $signature = md5(json_encode($payload['indicators']));

        // Cache hit — same trajectory already narrated.
        if ($latest && $latest->ai_review && $latest->ai_review_key === $signature) {
            return response()->json(['data' => ['narrative' => $latest->ai_review, 'cached' => true]]);
        }

        // Rate-limit: 5 AI reviews per user per minute (per-user, not per-NCP —
        // keying on the record let a user bypass the cap by switching NCPs).
        $rlKey = 'ai-review:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            return response()->json(['message' => 'Too many AI reviews. Try again shortly.'], 429);
        }
        RateLimiter::hit($rlKey, 60);

        $narrative = $ai->narrateMonitoring($payload);
        if ($narrative === null) {
            return response()->json(['message' => 'AI review is temporarily unavailable. The rule-based summary is still shown.'], 503);
        }

        if ($latest) {
            $this->audited(fn () => $latest->update(['ai_review' => $narrative, 'ai_review_key' => $signature]));
        }

        return response()->json(['data' => ['narrative' => $narrative, 'cached' => false]]);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/monitoring-plan
     *
     * Patient-specific tracked-indicator set (flagged labs + prescription + goal +
     * PES-implied + calculated anthropometrics), seeded from the assessment ("Visit 1").
     * Single source of truth for the visit form, trend charts, and progress tracker.
     */
    public function plan(NcpRecord $ncpRecord, MonitoringPlanService $svc): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);

        return response()->json(['data' => $svc->build($ncpRecord)]);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/monitorings
     */
    public function index(PaginatedRequest $request, NcpRecord $ncpRecord): AnonymousResourceCollection
    {
        $this->authorizeNcp($ncpRecord);
        $monitorings = $ncpRecord->monitorings()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return MonitoringResource::collection($monitorings);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/monitorings
     */
    public function store(
        StoreMonitoringRequest $request,
        NcpRecord $ncpRecord,
        NotificationLifecycleService $notificationLifecycle,
    ) {
        $this->authorizeNcp($ncpRecord);
        // Monitoring & Evaluation is a FOLLOW-UP activity: the initial encounter
        // produces the care plan (assessment → diagnosis → intervention). Block
        // monitoring on the first encounter, before that plan exists.
        if (! $ncpRecord->intervention()->exists()) {
            return response()->json([
                'message' => 'Monitoring is for follow-up visits. Complete the assessment, diagnosis, and intervention first.',
            ], 422);
        }

        $data = $request->validated();

        return $this->audited(function () use ($data, $ncpRecord, $notificationLifecycle) {
            $monitoring = new Monitoring($data);
            $monitoring->ncp_record_id = $ncpRecord->id;
            $monitoring->save();
            $notificationLifecycle->resolveFollowUp($ncpRecord, $monitoring->created_at);

            return (new MonitoringResource($monitoring))->response()->setStatusCode(201);
        });
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}
     */
    public function update(UpdateMonitoringRequest $request, NcpRecord $ncpRecord, Monitoring $monitoring): MonitoringResource
    {
        $this->authorizeNcp($ncpRecord);
        if ($monitoring->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $data = $request->validated();

        return $this->audited(function () use ($monitoring, $data) {
            $monitoring->fill($data);
            $monitoring->save();

            return new MonitoringResource($monitoring);
        });
    }

    /**
     * DELETE /api/rnd/ncp-records/{ncpRecord}/monitorings/{monitoring}
     */
    public function destroy(NcpRecord $ncpRecord, Monitoring $monitoring)
    {
        $this->authorizeNcp($ncpRecord);
        if ($monitoring->ncp_record_id !== $ncpRecord->id) {
            abort(404);
        }

        $this->audited(fn () => $monitoring->delete());

        return response()->noContent();
    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
    }
}
