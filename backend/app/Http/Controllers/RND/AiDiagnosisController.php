<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\AiSuggestDiagnosisRequest;
use App\Http\Requests\RND\AiApproveDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Services\AIService;
use App\Services\LabFlagService;

class AiDiagnosisController extends Controller
{
    public function __construct(private AIService $aiService, private LabFlagService $labFlags) {}

    /**
     * GET AI-suggested diagnoses for an NCP record.
     */
    public function aiSuggest(AiSuggestDiagnosisRequest $request, NcpRecord $ncpRecord): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        // Enrich from DB — client only sends conditions[] + ibw_percentage.
        // All clinical data needed for valid G-NCP PES comes from the server side.
        $patient    = $ncpRecord->patient;
        $assessment = $ncpRecord->assessment()->with('biochemicalData')->first();

        if ($patient) {
            $data['patient_age'] = $patient->age;
            $data['patient_sex'] = $patient->sex;
        }

        if ($assessment) {
            $clinical = array_filter([
                'nutritional_status'      => $assessment->nutritional_status,
                'weight_kg'               => $assessment->weight !== null ? (float) $assessment->weight : null,
                'height_cm'               => $assessment->height !== null ? (float) $assessment->height : null,
                'bmi'                     => $assessment->bmi !== null ? (float) $assessment->bmi : null,
                'weight_loss_percentage'  => $assessment->weight_loss_percentage,
                'weight_loss_period'      => $assessment->weight_loss_period,
                'edema_present'           => $assessment->edema_present,
                'stress_factor'           => $assessment->stress_factor,
                'physical_activity_level' => $assessment->normalizedActivityLevel(),
                'energy_intake_status'    => $assessment->energy_intake_status,
                'present_diet'            => $assessment->present_diet,
                'appetite_changes'        => $assessment->appetite_changes,
                'dietary_restrictions'    => $assessment->dietary_restrictions,
                'medications'             => $assessment->medications,
                'allergies'               => $assessment->allergies,
                'food_intolerance'        => $assessment->food_intolerance,
                'chewing_swallowing'      => $assessment->chewing_swallowing_difficulties,
                'functional_assessment'   => $assessment->functional_assessment,
            ], fn($v) => $v !== null && $v !== '' && $v !== []);

            $data += $clinical;

            $flagged = [];
            if ($assessment?->biochemicalData) {
                $flagged = $this->labFlags->flag(
                    $assessment->biochemicalData->toArray(),
                    $patient?->sex ?? 'Male'
                );
            }

            if (!empty($flagged)) {
                $data['abnormal_labs'] = $flagged;
            }
        }

        $existing = $ncpRecord->diagnoses()->pluck('pes_statement')->all();
        if (!empty($existing)) {
            $data['existing_diagnoses'] = $existing;
        }

        try {
            $suggestions = $this->aiService->suggestDiagnoses($data);
        } catch (\App\Exceptions\TokenLimitExceededException $e) {
            throw $e; // renders as 429
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $suggestions]);
    }

    /**
     * Store an AI-approved diagnosis to the database.
     */
    public function aiApprove(AiApproveDiagnosisRequest $request, NcpRecord $ncpRecord): \Illuminate\Http\JsonResponse
    {
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

        $diagnosis = Diagnosis::create([
            'ncp_record_id' => $ncpRecord->id,
            'domain'        => $data['domain'],
            'problem'       => $problem,
            'label'         => $problem,
            'etiology'      => $etiology,
            'signs_symptoms'=> $signs,
            'pes_statement' => Diagnosis::buildPes($problem, $etiology, $signs),
            'ai_generated'  => true,
        ]);

        return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
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
