<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\MonitoringSummaryRequest;
use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use App\Http\Resources\MonitoringResource;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\AIService;
use App\Services\FoodRecallService;
use App\Services\InterventionCalculationContextService;
use App\Services\MonitoringPlanService;
use App\Services\MonitoringSummaryService;
use App\Services\NcpAppointmentWorkflow;
use App\Services\NotificationLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly AuditPolicy $auditPolicy,
        private readonly NcpAppointmentWorkflow $appointments,
        private readonly FoodRecallService $foodRecalls,
    ) {}

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

    public function aiReviewBudget(Request $request, NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $data = $request->validate(['draft_id' => ['required', 'uuid']]);
        $key = $this->summaryEntryKey($ncpRecord, $data['draft_id']);

        return response()->json(['data' => ['entry_key' => $key, 'remaining' => max(0, 3 - Cache::get('monitoring-summary:'.$key, 0))]]);
    }

    /** Scope each draft's allowance to its clinician, cycle, and current visit history. */
    private function summaryEntryKey(NcpRecord $ncpRecord, string $draftId): string
    {
        $latest = $ncpRecord->monitorings()->latest('id')->first();

        return hash('sha256', auth()->id().':'.$ncpRecord->id.':'.json_encode($latest?->getAttributes() ?? 'initial').':'.$draftId);
    }

    public function aiReview(MonitoringSummaryRequest $request, NcpRecord $ncpRecord, MonitoringPlanService $planSvc, AIService $ai): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        abort_unless($ncpRecord->intervention()->exists(), 422, 'Save an intervention before monitoring.');
        if ($request->filled('monitoring_id')) {
            $editing = $ncpRecord->monitorings()->where('uuid', $request->validated('monitoring_id'))->firstOrFail();
            abort_unless($editing->canMutate(), 409, 'Only the latest saved monitoring entry in an active cycle can be edited.');
        }
        $key = $this->summaryEntryKey($ncpRecord, $request->validated('draft_id'));
        abort_unless(hash_equals($key, $request->validated('entry_key')), 409, 'The visit history changed. Reload the form before generating.');
        $rlKey = 'ai-review:'.(auth()->id() ?? 'guest');
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            return response()->json(['message' => 'Too many generation requests. Try again shortly.'], 429);
        }
        RateLimiter::hit($rlKey, 60);
        $cacheKey = 'monitoring-summary:'.$key;
        $response = Cache::lock($cacheKey.':lock', 60)->get(function () use ($key, $cacheKey, $request, $ncpRecord, $planSvc, $ai) {
            $used = Cache::get($cacheKey, 0);
            if ($used >= 3) {
                return response()->json(['message' => 'All three generations for this entry have been used. You can write or edit the summary.', 'remaining' => 0], 429);
            }
            $narrative = $ai->narrateMonitoring($planSvc->draftComparison($ncpRecord, $request->validated()));
            if ($narrative === null) {
                return response()->json(['message' => 'Summary generation is unavailable. Try again or write the summary manually.'], 503);
            }
            abort_unless(hash_equals($key, $this->summaryEntryKey($ncpRecord, $request->validated('draft_id'))), 409, 'The visit history changed during generation. Reload the form.');
            Cache::forever($cacheKey, $used + 1);

            return response()->json(['data' => ['narrative' => $narrative, 'remaining' => 2 - $used]]);
        });

        return $response ?: response()->json(['message' => 'A summary is already being generated for this entry.'], 429);
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
            ->orderByDesc('observed_at')
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
        if (array_key_exists('food_recall', $data)) {
            $data['food_recall'] = $this->foodRecalls->normalize($data['food_recall']);
        }
        $followUpAt = $data['next_monitoring_at'] ?? null;
        $followUpPurpose = $data['next_monitoring_purpose'] ?? null;
        unset($data['next_monitoring_at'], $data['next_monitoring_purpose']);
        if ($followUpAt !== null) {
            $data['next_monitoring_date'] = CarbonImmutable::parse($followUpAt)->toDateString();
        }
        $data['bmi'] = $this->calculateBmi($data['weight'], $data['height']);

        return $this->audited(function () use ($data, $ncpRecord, $notificationLifecycle, $request, $followUpAt, $followUpPurpose) {
            $ncpRecord->newQuery()->whereKey($ncpRecord->id)->lockForUpdate()->firstOrFail();
            $monitoring = new Monitoring($data);
            $monitoring->ncp_record_id = $ncpRecord->id;
            $monitoring->save();
            $notificationLifecycle->resolveFollowUp($ncpRecord, $monitoring->created_at);
            if ($followUpAt !== null && $followUpPurpose !== null) {
                $scheduledAt = CarbonImmutable::parse($followUpAt);
                $this->appointments->create($request->user(), $ncpRecord->patient, [
                    'source' => 'scheduled',
                    'purpose' => $followUpPurpose,
                    'scheduled_at' => $scheduledAt->toDateTimeString(),
                ]);
            }
            $freshNcp = $ncpRecord->fresh(['monitorings']);
            $this->appointments->recordClinicalWork($request->user(), $freshNcp, 'monitoring');

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
        if (array_key_exists('food_recall', $data)) {
            $data['food_recall'] = $this->foodRecalls->normalize($data['food_recall'], $monitoring->food_recall);
        }
        unset($data['next_monitoring_at'], $data['next_monitoring_purpose']);
        $data['bmi'] = $this->calculateBmi($data['weight'], $data['height']);

        return $this->audited(function () use ($monitoring, $data, $ncpRecord, $request) {
            $lockedNcp = $ncpRecord->newQuery()->whereKey($ncpRecord->id)->lockForUpdate()->firstOrFail();
            $monitoring->setRelation('ncpRecord', $lockedNcp);
            abort_unless($monitoring->canMutate(), 409, 'Only the latest saved monitoring entry in an active cycle can be edited.');
            $monitoring->fill($data);
            $monitoring->save();
            $freshNcp = $ncpRecord->fresh(['monitorings']);
            $this->appointments->recordClinicalWork($request->user(), $freshNcp, 'monitoring');

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

        return $this->audited(function () use ($monitoring, $ncpRecord) {
            $lockedNcp = $ncpRecord->newQuery()->whereKey($ncpRecord->id)->lockForUpdate()->firstOrFail();
            $monitoring->setRelation('ncpRecord', $lockedNcp);
            abort_unless($monitoring->canMutate(), 409, 'Only the latest saved monitoring entry in an active cycle can be deleted.');
            $monitoring->delete();

            return response()->noContent();
        });
    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
    }

    public function context(
        NcpRecord $ncpRecord,
        InterventionCalculationContextService $context,
    ): JsonResponse {
        $this->authorizeNcp($ncpRecord);

        return response()->json(['data' => $context->prefillForMonitoring($ncpRecord)]);
    }

    private function calculateBmi(float|int|string $weight, float|int|string $height): float
    {
        $heightMeters = (float) $height / 100;

        return round((float) $weight / ($heightMeters * $heightMeters), 2);
    }
}
