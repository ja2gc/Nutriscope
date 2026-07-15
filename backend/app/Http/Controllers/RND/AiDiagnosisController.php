<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Exceptions\TokenLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RND\AiApproveDiagnosisRequest;
use App\Http\Requests\RND\AiSuggestDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Policies\AuditPolicy;
use App\Services\AIService;
use App\Services\Audit\AuditLogger;
use App\Services\LabFlagService;
use Illuminate\Http\JsonResponse;

class AiDiagnosisController extends Controller
{
    public function __construct(
        private AIService $aiService,
        private LabFlagService $labFlags,
        private AuditPolicy $auditPolicy,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * GET AI-suggested diagnoses for an NCP record.
     */
    public function aiSuggest(AiSuggestDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        $data = $request->validated();

        // Enrich from DB — client only sends conditions[] + ibw_percentage.
        // All clinical data needed for valid G-NCP PES comes from the server side.
        $patient = $ncpRecord->patient;
        $assessment = $ncpRecord->assessment()->with('biochemicalData')->first();

        if ($patient) {
            $data['patient_age'] = $patient->age;
            $data['patient_sex'] = $patient->sex;
        }

        if ($assessment) {
            $clinical = array_filter([
                'nutritional_status' => $assessment->nutritional_status,
                'weight_kg' => $assessment->weight !== null ? (float) $assessment->weight : null,
                'height_cm' => $assessment->height !== null ? (float) $assessment->height : null,
                'bmi' => $assessment->bmi !== null ? (float) $assessment->bmi : null,
                'weight_loss_percentage' => $assessment->weight_loss_percentage,
                'weight_loss_period' => $assessment->weight_loss_period,
                'edema_present' => $assessment->edema_present,
                'stress_factor' => $assessment->stress_factor,
                'physical_activity_level' => $assessment->normalizedActivityLevel(),
                'energy_intake_status' => $assessment->energy_intake_status,
                'present_diet' => $assessment->present_diet,
                'appetite_changes' => $assessment->appetite_changes,
                'dietary_restrictions' => $assessment->dietary_restrictions,
                'medications' => $assessment->medications,
                'allergies' => $assessment->allergies,
                'food_intolerance' => $assessment->food_intolerance,
                'chewing_swallowing' => $assessment->chewing_swallowing_difficulties,
                'functional_assessment' => $assessment->functional_assessment,
            ], fn ($v) => $v !== null && $v !== '' && $v !== []);

            $data += $clinical;

            $flagged = [];
            if ($assessment?->biochemicalData) {
                $flagged = $this->labFlags->flag(
                    $assessment->biochemicalData->toArray(),
                    $patient?->sex ?? 'Male'
                );
            }

            if (! empty($flagged)) {
                $data['abnormal_labs'] = $flagged;
            }
        }

        $existing = $ncpRecord->diagnoses()->pluck('pes_statement')->all();
        if (! empty($existing)) {
            $data['existing_diagnoses'] = $existing;
        }

        try {
            $suggestions = $this->aiService->suggestDiagnoses($data);
        } catch (TokenLimitExceededException $e) {
            throw $e; // renders as 429
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $this->auditLogger->record(
            AuditAction::Generated,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $ncpRecord,
            details: ['status' => 200],
        );

        return response()->json(['data' => $suggestions]);
    }

    /**
     * Store an AI-approved diagnosis to the database.
     */
    public function aiApprove(AiApproveDiagnosisRequest $request, NcpRecord $ncpRecord): JsonResponse
    {
        abort_unless($this->auditPolicy->viewNcpTrail($request->user(), $ncpRecord), 403);
        // ADIME step order: the assessment must precede the diagnosis (same gate as the
        // manual create path, so AI-approve can't bypass it).
        if (! $ncpRecord->assessment()->exists()) {
            return response()->json([
                'message' => 'Record the nutrition assessment before adding a diagnosis.',
            ], 422);
        }

        $data = $request->validated();
        $problem = $this->cleanPesComponent($data['label']);
        $etiology = $this->cleanPesComponent($data['etiology'], 'related to');
        $signs = $this->cleanPesComponent($data['signs'], 'as evidenced by');

        return $this->audited(function () use ($ncpRecord, $data, $problem, $etiology, $signs) {
            $diagnosis = $this->auditLogger->withoutModelEvents(fn (): Diagnosis => Diagnosis::create([
                'ncp_record_id' => $ncpRecord->id,
                'domain' => $data['domain'],
                'problem' => $problem,
                'label' => $problem,
                'etiology' => $etiology,
                'signs_symptoms' => $signs,
                'pes_statement' => Diagnosis::buildPes($problem, $etiology, $signs),
                'ai_generated' => true,
            ]));
            $this->auditLogger->record(
                AuditAction::Approved,
                AuditCategory::Clinical,
                AuditDomain::Ncp,
                subject: $diagnosis,
                context: $ncpRecord,
                details: ['status' => 201, 'fields' => ['domain', 'label', 'etiology', 'signs_symptoms']],
            );

            return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
        });
    }

    private function cleanPesComponent(string $value, ?string $prefix = null): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if ($prefix === null) {
            return $value;
        }

        return trim((string) preg_replace('/^'.preg_quote($prefix, '/').'\s+/i', '', $value));
    }
}
