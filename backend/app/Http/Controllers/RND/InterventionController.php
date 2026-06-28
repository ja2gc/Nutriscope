<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreInterventionRequest;
use App\Http\Requests\RND\UpdateInterventionRequest;
use App\Http\Resources\InterventionResource;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Services\ClinicalCompletenessService;
use App\Services\NutritionPrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
    public function __construct(private ClinicalCompletenessService $completeness) {}

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention/autofill
     *
     * AUTHORITATIVE prescription calculation. Derives patient metrics from the
     * assessment + patient record and returns the spec-correct prescription.
     * The frontend mirror is for live preview only; persisted values should come
     * from here. Body: { goal_type, disease_stage? }.
     */
    public function autofill(Request $request, NcpRecord $ncpRecord, NutritionPrescriptionService $svc): JsonResponse
    {
        $goalType = $request->input('goal_type') ?? $ncpRecord->intervention?->goal_type;
        $stage    = $request->input('disease_stage') ?? $ncpRecord->intervention?->disease_stage;

        if (! $goalType) {
            return response()->json(['message' => 'goal_type is required.'], 422);
        }

        $assessment = $ncpRecord->assessment()->first();
        $patient    = $ncpRecord->patient;

        if (! $assessment || $assessment->weight === null || $assessment->height === null) {
            return response()->json([
                'message' => 'Assessment with weight and height is required before autofill.',
            ], 422);
        }
        if (! $patient || ! $patient->dob || ! $patient->sex) {
            return response()->json(['message' => 'Patient date of birth and sex are required.'], 422);
        }

        $age = (int) \Illuminate\Support\Carbon::parse($patient->dob)->age;

        // Phase 5.2: use normalised PAL key so any UI spelling maps to ACTIVITY_FACTORS
        $activityKey    = $assessment->normalizedActivityLevel();
        $activityFactor = NutritionPrescriptionService::ACTIVITY_FACTORS[$activityKey] ?? 1.2;

        // Phase 5.3: pass pregnancy/lactation status when present (gate inside service)
        $metrics = [
            'weightKg'       => (float) $assessment->weight,
            'heightCm'       => (float) $assessment->height,
            'ageYears'       => $age,
            'sex'            => $patient->sex,
            'isAdult'        => $age >= 18,
            'activityFactor' => $activityFactor,
        ];

        $pregnancyStatus = $assessment->pregnancy_lactation_status;
        if ($pregnancyStatus && $pregnancyStatus !== 'none') {
            $metrics['pregnancyLactationStatus'] = $pregnancyStatus;
        }

        // edema: flag in response for FE warning; no formula change (weight unreliable)
        $rx = $svc->autofill($goalType, $stage, $metrics);

        if ($assessment->edema_present) {
            $rx['edema_warning'] = 'Weight may be unreliable due to edema. Verify anthropometrics before confirming prescription.';
        }

        return response()->json(['data' => $rx]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function store(StoreInterventionRequest $request, NcpRecord $ncpRecord)
    {
        if ($ncpRecord->intervention()->exists()) {
            return response()->json(['message' => 'Intervention already exists for this NCP record.'], 409);
        }

        // ADIME step order: at least one diagnosis must precede the intervention.
        if (! $ncpRecord->diagnoses()->exists()) {
            return response()->json([
                'message' => 'Add at least one diagnosis before recording the intervention.',
            ], 422);
        }

        $data = $request->validated();
        $intervention = new Intervention($data);
        $intervention->ncp_record_id = $ncpRecord->id;
        $intervention->save();

        $this->refreshActivation($ncpRecord);

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

        $this->refreshActivation($ncpRecord);

        return new InterventionResource($intervention);
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

    /**
     * Translate an intervention goal_type to the clinical_rules condition(s) whose
     * food rules apply. Data-driven via config/clinical.php (condition strings must
     * match clinical_rules.condition) — not hardcoded here. Unknown goal_types and
     * purely calculation-based goals resolve to no conditions.
     */
    private function mapGoalTypeToConditions(string $goalType): array
    {
        return config("clinical.goal_type_conditions.{$goalType}", []);
    }
}
