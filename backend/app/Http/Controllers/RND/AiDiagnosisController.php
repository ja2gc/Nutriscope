<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\AiSuggestDiagnosisRequest;
use App\Http\Requests\RND\AiApproveDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Services\AIService;

class AiDiagnosisController extends Controller
{
    public function __construct(private AIService $aiService) {}

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
                $labs = $assessment->biochemicalData->toArray();
                $sex = $patient?->sex ?? 'Male';

                $ranges = [
                    'albumin'       => ['low' => 3.5,  'high' => 5.5],
                    'hemoglobin'    => $sex === 'Male' ? ['low' => 13.5, 'high' => 17.5] : ['low' => 12.0, 'high' => 15.5],
                    'hematocrit'    => $sex === 'Male' ? ['low' => 41,   'high' => 53]   : ['low' => 36,   'high' => 46],
                    'glucose'       => ['low' => 70,   'high' => 99],
                    'hba1c'         => ['low' => null, 'high' => 5.6],
                    'bun'           => ['low' => 7,    'high' => 18],
                    'creatinine'    => $sex === 'Male' ? ['low' => 0.7,  'high' => 1.2]  : ['low' => 0.5,  'high' => 0.9],
                    'sodium'        => ['low' => 136,  'high' => 145],
                    'potassium'     => ['low' => 3.5,  'high' => 5.1],
                    'calcium'       => ['low' => 8.7,  'high' => 10.3],
                    'phosphate'     => ['low' => 2.5,  'high' => 4.5],
                    'cholesterol'   => ['low' => null, 'high' => 200],
                    'ldl'           => ['low' => null, 'high' => 100],
                    'hdl'           => $sex === 'Male' ? ['low' => 40,   'high' => null] : ['low' => 50,   'high' => null],
                    'triglycerides' => ['low' => null, 'high' => 150],
                ];

                foreach ($ranges as $key => $range) {
                    $value = $labs[$key] ?? null;
                    if ($value === null) continue;
                    $value = (float) $value;
                    $status = null;
                    if ($range['low'] !== null && $value < $range['low']) $status = 'LOW';
                    if ($range['high'] !== null && $value > $range['high']) $status = 'HIGH';
                    if ($status) {
                        $flagged[$key] = ['value' => $value, 'status' => $status];
                    }
                }
            }

            if (!empty($flagged)) {
                $data['abnormal_labs'] = $flagged;
            }
        }

        $existing = $ncpRecord->diagnoses()->pluck('pes_statement')->all();
        if (!empty($existing)) {
            $data['existing_diagnoses'] = $existing;
        }

        $suggestions = $this->aiService->suggestDiagnoses($data);

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

        $diagnosis = Diagnosis::create([
            'ncp_record_id' => $ncpRecord->id,
            'domain'        => $data['domain'],
            'problem'       => $data['label'],
            'label'         => $data['label'],
            'etiology'      => $data['etiology'],
            'signs_symptoms'=> $data['signs'],
            'pes_statement' => Diagnosis::buildPes($data['label'], $data['etiology'], $data['signs']),
            'ai_generated'  => true,
        ]);

        return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
    }
}
