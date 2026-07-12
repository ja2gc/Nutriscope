<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreInterventionRequest;
use App\Http\Requests\RND\UpdateInterventionRequest;
use App\Http\Resources\InterventionResource;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\ClinicalCompletenessService;
use App\Services\LabFlagService;
use App\Services\NutritionPrescriptionService;
use App\Services\RecommendService;
use App\Support\InterventionGoalCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InterventionController extends Controller
{
    public function __construct(
        private ClinicalCompletenessService $completeness,
        private LabFlagService $labFlags,
        private AuditPolicy $auditPolicy,
    ) {}

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
        $this->authorizeNcp($ncpRecord);
        $goalType = $request->input('goal_type') ?? $ncpRecord->intervention?->goal_type;
        $stage = $request->input('disease_stage') ?? $ncpRecord->intervention?->disease_stage;

        if (! $goalType) {
            return response()->json(['message' => 'goal_type is required.'], 422);
        }

        if (! InterventionGoalCatalog::stageIsValid($goalType, $stage)) {
            return response()->json([
                'message' => 'Invalid intervention goal or disease stage.',
                'calculation_status' => 'invalid_goal_stage',
                'errors' => [
                    'goal_type' => ['The selected intervention goal is invalid.'],
                    'disease_stage' => ['The selected disease stage is invalid for the intervention goal.'],
                ],
            ], 422);
        }

        $assessment = $ncpRecord->assessment()->first();
        $patient = $ncpRecord->patient;

        $missingFields = [];
        if (! $assessment || $assessment->weight === null) {
            $missingFields[] = 'weight';
        }
        if (! $assessment || $assessment->height === null) {
            $missingFields[] = 'height';
        }
        if ($assessment?->edema_present && $assessment->dry_weight_kg === null) {
            $missingFields[] = 'dry_weight_kg';
        }
        if ($this->requiresActivityFactor($goalType, $stage)
            && (! $assessment || $assessment->physical_activity_level === null || $assessment->physical_activity_level === '')
        ) {
            $missingFields[] = 'physical_activity_level';
        }

        if ($missingFields !== []) {
            return response()->json([
                'message' => 'Assessment fields required before autofill: '.implode(', ', $missingFields).'.',
                'missing_fields' => $missingFields,
                'calculation_status' => 'incomplete',
            ], 422);
        }

        $missingPatientFields = [];
        if (! $patient || ! $patient->dob) {
            $missingPatientFields[] = 'dob';
        }
        if (! $patient || ! $patient->sex) {
            $missingPatientFields[] = 'sex';
        }
        if ($missingPatientFields !== []) {
            return response()->json([
                'message' => 'Patient fields required before autofill: '.implode(', ', $missingPatientFields).'.',
                'missing_fields' => $missingPatientFields,
                'calculation_status' => 'incomplete',
            ], 422);
        }

        $age = (int) Carbon::parse($patient->dob)->age;

        // Phase 5.2: use normalised PAL key so any UI spelling maps to ACTIVITY_FACTORS
        $activityKey = $assessment->normalizedActivityLevel();
        $activityFactor = NutritionPrescriptionService::ACTIVITY_FACTORS[$activityKey] ?? 1.2;

        // Phase 5.3: pass pregnancy/lactation status when present (gate inside service)
        $calculationWeight = $assessment->edema_present
            ? (float) $assessment->dry_weight_kg
            : (float) $assessment->weight;

        $metrics = [
            'weightKg' => $calculationWeight,
            'heightCm' => (float) $assessment->height,
            'ageYears' => $age,
            'sex' => $patient->sex,
            'isAdult' => $age >= 18,
            'activityFactor' => $activityFactor,
        ];

        $pregnancyStatus = $assessment->pregnancy_lactation_status;
        if ($pregnancyStatus && $pregnancyStatus !== 'none') {
            $metrics['pregnancyLactationStatus'] = $pregnancyStatus;
        }

        $rx = $svc->autofill($goalType, $stage, $metrics);

        $warnings = $this->safetyWarnings($goalType, $stage, $assessment, $patient?->sex);
        $rx['calculation_status'] = $warnings === [] ? 'ok' : 'warning';
        if ($warnings !== []) {
            $rx['safety_warnings'] = $warnings;
        }

        return response()->json(['data' => $rx]);
    }

    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function store(StoreInterventionRequest $request, NcpRecord $ncpRecord)
    {
        $this->authorizeNcp($ncpRecord);
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

        return $this->audited(function () use ($data, $ncpRecord) {
            $intervention = new Intervention($data);
            $intervention->ncp_record_id = $ncpRecord->id;
            $intervention->save();

            $this->refreshActivation($ncpRecord);

            return (new InterventionResource($intervention))->response()->setStatusCode(201);
        });
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function show(NcpRecord $ncpRecord): JsonResponse
    {
        $this->authorizeNcp($ncpRecord);
        $intervention = $ncpRecord->intervention()->first();

        if (! $intervention) {
            return response()->json(['data' => null]);
        }

        return (new InterventionResource($intervention))->response();
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/intervention
     */
    public function update(UpdateInterventionRequest $request, NcpRecord $ncpRecord): InterventionResource
    {
        $this->authorizeNcp($ncpRecord);
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $data = $request->validated();

        return $this->audited(function () use ($intervention, $data, $ncpRecord) {
            $intervention->fill($data);
            $intervention->save();

            $this->refreshActivation($ncpRecord);

            return new InterventionResource($intervention);
        });
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
        $this->authorizeNcp($ncpRecord);
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $conditions = $this->mapGoalTypeToConditions($intervention->goal_type ?? '');
        $stages = $intervention->disease_stage ? [$intervention->disease_stage] : null;

        $assessment = $ncpRecord->assessment()->with('biochemicalData')->first();
        $labValues = $assessment?->biochemicalData?->toArray() ?? [];
        $labFlags = $this->labFlags->flag($labValues, $ncpRecord->patient?->sex);

        $result = app(RecommendService::class)
            ->getRecommendations($conditions, $stages, $labFlags);

        return response()->json(['data' => $result]);
    }

    private function authorizeNcp(NcpRecord $ncpRecord): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
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

    private function requiresActivityFactor(string $goalType, ?string $stage): bool
    {
        return match ($goalType) {
            'diabetic_control', 'cardiac_diet', 'weight_loss', 'custom' => true,
            'weight_gain' => $stage !== 'severe',
            default => false,
        };
    }

    /**
     * Labs are optional, but if present they must surface risk-relevant warnings.
     *
     * @return array<int,array{key:string,severity:string,message:string}>
     */
    private function safetyWarnings(string $goalType, ?string $stage, $assessment, ?string $sex): array
    {
        if (! in_array($goalType, ['malnutrition', 'weight_gain'], true) || $stage !== 'severe') {
            return [];
        }

        $assessment->loadMissing('biochemicalData');
        $flags = $this->labFlags->flag($assessment->biochemicalData?->toArray() ?? [], $sex);
        $messages = [
            'potassium' => 'Potassium is out of range. Refeeding protocols require daily potassium monitoring for the first 72 hours.',
            'phosphate' => 'Phosphate is out of range. Low phosphate increases refeeding syndrome risk; monitor daily for the first 72 hours.',
            'magnesium' => 'Magnesium is out of range. Refeeding protocols require daily magnesium monitoring for the first 72 hours.',
            'calcium' => 'Calcium is out of range. Review hydration, renal status, medications, and supplementation before advancing feeding.',
        ];

        $warnings = [];
        foreach (['potassium', 'phosphate', 'magnesium', 'calcium'] as $key) {
            if (! isset($flags[$key])) {
                continue;
            }

            $warnings[] = [
                'key' => strtolower($flags[$key]['status']).'_'.$key,
                'severity' => 'warning',
                'message' => $messages[$key],
            ];
        }

        return $warnings;
    }
}
