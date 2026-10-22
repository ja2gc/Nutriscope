<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\RND\AutofillInterventionRequest;
use App\Http\Requests\RND\StoreInterventionRequest;
use App\Http\Resources\InterventionPlanSummaryResource;
use App\Http\Resources\InterventionResource;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\ClinicalCompletenessService;
use App\Services\InterventionCalculationContextService;
use App\Services\InterventionPlanService;
use App\Services\LabFlagService;
use App\Services\NcpAppointmentWorkflow;
use App\Support\InterventionGuidance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InterventionController extends Controller
{
    public function __construct(
        private ClinicalCompletenessService $completeness,
        private AuditPolicy $auditPolicy,
        private NcpAppointmentWorkflow $appointments,
        private InterventionPlanService $plans,
    ) {}

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/interventions/autofill
     *
     * Derives a current calculation and provenance from saved measurements.
     * Guidance-only requests never change numeric targets.
     */
    public function autofill(
        AutofillInterventionRequest $request,
        NcpRecord $ncpRecord,
        InterventionCalculationContextService $contextService,
    ): JsonResponse {
        $this->authorizeNcp($ncpRecord);
        $attributes = $request->validated();
        if ($request->boolean('guidance_only')) {
            $context = $contextService->for($ncpRecord);

            return response()->json(['data' => ['guidance' => InterventionGuidance::for(
                $attributes['goal_type'], $attributes['disease_stage'] ?? null, [
                    ...($attributes['calculation_options'] ?? []),
                    'energy_adjustment_mode' => $attributes['energy_adjustment_mode'] ?? 'none',
                    'energy_adjustment_kcal' => ($attributes['energy_adjustment_mode'] ?? 'none') === 'none' ? 0 : ($attributes['energy_adjustment_kcal'] ?? 0),
                    'fluid_mode' => $attributes['fluid_mode'] ?? null,
                    'pediatric' => ($context['age_years'] ?? 18) < 18,
                    'age_months' => $context['age_months'],
                    'maternal' => ! in_array($context['pregnancy_lactation_status'], [null, 'none'], true),
                ],
            )]]);
        }
        $rx = $contextService->calculate($ncpRecord, $attributes);
        if (! empty($attributes['calculation_options']['refeeding_risk'])) {
            $context = $contextService->for($ncpRecord);
            $flags = app(LabFlagService::class)->flag($context['lab_values'], $context['sex']);
            foreach (['potassium', 'phosphate', 'magnesium', 'calcium'] as $electrolyte) {
                if (isset($flags[$electrolyte])) {
                    $rx['safety_warnings'][] = ['key' => strtolower($flags[$electrolyte]['status']).'_'.$electrolyte,
                        'severity' => 'warning', 'message' => ucfirst($electrolyte).' is out of range. Review intake and clinical monitoring with the care team.'];
                }
            }
            if (! empty($rx['safety_warnings'])) {
                $rx['calculation_status'] = 'warning';
            }
        }
        $snapshot = $rx['calculation_snapshot'];
        $rx['guidance'] = InterventionGuidance::for($attributes['goal_type'], $attributes['disease_stage'] ?? null, [
            ...($attributes['calculation_options'] ?? []),
            'energy_adjustment_mode' => $attributes['energy_adjustment_mode'] ?? 'none',
            'energy_adjustment_kcal' => $snapshot['energy_adjustment_kcal'],
            'fluid_mode' => $rx['fluid_mode'],
            'pediatric' => ($snapshot['inputs']['ageYears'] ?? 18) < 18,
            'age_months' => $snapshot['inputs']['ageMonths'] ?? (($snapshot['inputs']['ageYears'] ?? 18) * 12),
            'maternal' => ($snapshot['inputs']['pregnancyLactationStatus'] ?? 'none') !== 'none',
        ]);

        return response()->json(['data' => $rx]);
    }

    public function index(PaginatedRequest $request, NcpRecord $ncpRecord): AnonymousResourceCollection
    {
        $this->authorizeNcp($ncpRecord);
        $plans = $ncpRecord->interventions()
            ->with(['sourceMonitoring', 'mealPlan'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return InterventionPlanSummaryResource::collection($plans);
    }

    public function latest(NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $plan = $ncpRecord->latestIntervention()->with(['sourceMonitoring', 'mealPlan'])->first();

        return $plan === null
            ? response()->json(['data' => null])
            : (new InterventionResource($plan))->response();
    }

    public function showPlan(NcpRecord $ncpRecord, Intervention $intervention): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        abort_unless($intervention->ncp_record_id === $ncpRecord->id, 404);

        return (new InterventionResource($intervention->load(['sourceMonitoring', 'mealPlan'])))->response();
    }

    public function storePlan(StoreInterventionRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        if (! $ncpRecord->diagnoses()->exists()) {
            return response()->json([
                'message' => 'Add at least one diagnosis before recording the intervention.',
            ], 422);
        }

        return $this->audited(function () use ($ncpRecord, $request): JsonResponse {
            $sourceMonitoring = $ncpRecord->monitorings()
                ->orderByDesc('observed_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();
            $intervention = $this->plans->create(
                $ncpRecord,
                $request->validated(),
                $request->user(),
                $sourceMonitoring,
            );
            $this->refreshActivation($ncpRecord);
            $this->appointments->recordClinicalWork($request->user(), $ncpRecord->fresh(), 'intervention');

            return (new InterventionResource($intervention->load(['sourceMonitoring', 'mealPlan'])))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function updatePlan(StoreInterventionRequest $request, NcpRecord $ncpRecord, Intervention $intervention): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        abort_unless($intervention->ncp_record_id === $ncpRecord->id, 404);

        return $this->audited(function () use ($request, $ncpRecord, $intervention): JsonResponse {
            NcpRecord::whereKey($ncpRecord->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $ncpRecord->latestIntervention()->value('id') === $intervention->id, 403, 'Historical plans are view only.');
            $intervention->update($this->plans->prepareAttributes($ncpRecord, $request->validated(), $intervention));
            if ($intervention->mealPlan) {
                $intervention->mealPlan->update(['needs_rescaling' => true, 'scaled_at' => null]);
            }
            $this->refreshActivation($ncpRecord);
            $this->appointments->recordClinicalWork($request->user(), $ncpRecord->fresh(), 'intervention');

            return (new InterventionResource($intervention->load(['sourceMonitoring', 'mealPlan'])))->response();
        });
    }

    public function destroyPlan(NcpRecord $ncpRecord, Intervention $intervention): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        abort_unless($intervention->ncp_record_id === $ncpRecord->id, 404);
        $this->audited(function () use ($ncpRecord, $intervention): void {
            NcpRecord::whereKey($ncpRecord->id)->lockForUpdate()->firstOrFail();
            $intervention->mealPlans()->get()->each->delete();
            $intervention->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Activate the NCP only once the initial ADI is clinically COMPLETE (not just
     * present). An empty intervention row no longer flips the record to active —
     * that was the "false active NCP" risk (SL-02 / AD-01). Re-evaluated whenever
     * the intervention is created or edited.
     */
    private function refreshActivation(NcpRecord $ncpRecord): void
    {
        if ($ncpRecord->type === 'new'
            && $ncpRecord->status === 'draft'
            && $this->completeness->initialAdiComplete($ncpRecord->fresh())
        ) {
            $ncpRecord->update(['status' => 'active']);
        }
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/interventions/recommendations
     * Auto-derives clinical rule conditions from the intervention's goal_type.
     */
    public function recommendations(NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $intervention = $ncpRecord->latestIntervention()->firstOrFail();
        $guidance = InterventionGuidance::for($intervention->goal_type ?? 'custom', $intervention->disease_stage, $intervention->calculation_options ?? []);
        $limits = [];
        foreach ($intervention->micronutrient_limits ?? [] as $tag => $limit) {
            foreach (['target', 'min', 'max'] as $operator) {
                if (isset($limit[$operator])) {
                    $limits[] = ['tag' => $tag, 'condition' => $intervention->goal_type,
                        'reason' => 'Confirmed individual prescription', 'threshold' => $limit[$operator],
                        'unit' => $limit['unit'] ?? '', 'operator' => $operator];
                }
            }
        }

        return response()->json(['data' => [
            'recommend' => [['tag' => 'food_pattern', 'condition' => $intervention->goal_type, 'reason' => $guidance['foods']['choose']]],
            'avoid' => [['tag' => 'individual_limits', 'condition' => $intervention->goal_type, 'reason' => $guidance['foods']['limit']]],
            'limits' => $limits,
            'foods' => $guidance['foods'],
            'lab_review' => app(LabFlagService::class)->flag(
                app(InterventionCalculationContextService::class)->for($ncpRecord)['lab_values'],
                $ncpRecord->patient?->sex,
            ),
        ]]);

    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
    }
}
