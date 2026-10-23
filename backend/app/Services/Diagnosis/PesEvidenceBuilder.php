<?php

namespace App\Services\Diagnosis;

use App\Models\NcpRecord;
use App\Services\FoodRecallService;
use App\Services\LabFlagService;
use Illuminate\Support\Str;

class PesEvidenceBuilder
{
    public function build(NcpRecord $ncpRecord): array
    {
        $ncpRecord->loadMissing(['patient', 'assessment.biochemicalData', 'diagnoses']);
        $assessment = $ncpRecord->assessment;
        $existing = $ncpRecord->diagnoses->flatMap(fn ($diagnosis): array => [
            $diagnosis->problem, ...($diagnosis->builder_data['problem_checks'] ?? []),
        ])->filter()->sort()->values()->all();
        if ($assessment === null) {
            return ['existing_problem_keys' => $existing];
        }
        $patient = $ncpRecord->patient;
        $identifiers = array_filter([
            $patient?->name, $patient?->first_name, $patient?->last_name, $patient?->hospital_number,
            $patient?->patient_code, $patient?->address, $patient?->contact, $patient?->physician,
        ], static fn ($value): bool => is_string($value) && mb_strlen($value) >= 3);
        $text = static function (?string $value, int $limit = 600) use ($identifiers): ?string {
            if ($value === null) {
                return null;
            }
            foreach ($identifiers as $identifier) {
                $value = str_ireplace($identifier, '[redacted]', $value);
            }
            $value = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b|(?:\+?\d[\d ()-]{8,}\d)/i', '[redacted]', $value);
            $value = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $value));

            return $value === '' ? null : Str::limit($value, $limit, '');
        };
        $symptom = static fn (?string $answer, ?string $details): ?string => $answer === 'No' ? 'No' : $text($answer === 'Yes' ? $details : $answer);
        $existing = array_values(array_filter(array_map($text, $existing)));
        $age = $patient?->dob && $ncpRecord->created_at ? (int) $patient->dob->diffInYears($ncpRecord->created_at) : null;
        $current = $assessment->weight !== null ? (float) $assessment->weight : null;
        $usual = $assessment->usual_weight !== null ? (float) $assessment->usual_weight : null;
        $hasWeights = $current > 0 && $usual > 0;
        $direction = $hasWeights ? ($current < $usual ? 'loss' : ($current > $usual ? 'gain' : 'stable')) : 'unknown';
        $change = $hasWeights ? round(abs($usual - $current) / $usual * 100, 2) : null;
        $evidence = [
            'age_group' => $age === null ? 'unknown' : ($age < 18 ? 'pediatric' : ($age >= 60 ? 'elderly' : 'adult')),
            'age_70_plus' => $age === null ? null : $age >= 70,
            'sex' => in_array($patient?->sex, ['Male', 'Female'], true) ? $patient->sex : 'unknown',
            'weight_kg' => $current, 'usual_weight_kg' => $usual,
            'height_cm' => $assessment->height !== null ? (float) $assessment->height : null,
            'bmi' => $assessment->calculateBmi(),
            'weight_change_direction' => $direction,
            'weight_change_percentage' => $change,
            'weight_loss_percentage' => $direction === 'loss' ? $change : null,
            'weight_change_period' => $assessment->weight_change_period_value > 0 && in_array($assessment->weight_change_period_unit, ['weeks', 'months'], true)
                ? ['value' => (float) $assessment->weight_change_period_value, 'unit' => $assessment->weight_change_period_unit] : null,
            'appetite' => $text($assessment->appetite_changes),
            'energy_intake_status' => $assessment->energy_intake_status,
            'chewing_swallowing_difficulties' => $symptom($assessment->chewing_swallowing_difficulties, $assessment->chewing_swallowing_details),
            'gi_symptoms' => array_filter([
                'constipation' => $symptom($assessment->constipation, $assessment->constipation_details),
                'diarrhea' => $symptom($assessment->diarrhea_notes, $assessment->diarrhea_details),
            ]),
            'medication_count' => is_array($assessment->medications) ? count($assessment->medications) : 0,
            'edema_present' => $assessment->edema_present,
            'dry_weight_kg' => $assessment->dry_weight_kg,
            'pregnancy_lactation_status' => $assessment->pregnancy_lactation_status,
            'physical_activity_level' => $assessment->physical_activity_level,
            'functional_assessment' => $text($assessment->functional_assessment),
            'nutritional_status' => $assessment->nutritional_status,
            'ibw_percentage' => $assessment->ibw_percentage,
            'muac_mm' => $assessment->muac_mm,
            'waist_cm' => $assessment->waist_cm,
            'hip_cm' => $assessment->hip_cm,
            'existing_problem_keys' => $existing,
            'saved_candidate_ids' => $ncpRecord->diagnoses->map(fn ($diagnosis) => $diagnosis->ai_provenance['candidate_id'] ?? null)
                ->filter(fn ($id) => is_string($id) && ! in_array($id, ['intake_other_v2', 'clinical_other_v2', 'behavioral_other_v2'], true))->unique()->values()->all(),
            'food_recall' => app(FoodRecallService::class)->evidenceSummary($assessment->food_recall),
            'intake_nutrients' => FoodRecallService::intakeEvidence($assessment->intake_nutrients),
        ];
        foreach (['dietary_intake', 'present_diet', 'knowledge_notes', 'lifestyle', 'social_history', 'medical_history', 'physical_assessment', 'body_composition', 'food_intolerance', 'nutrient_drug_interaction', 'dietary_restrictions', 'supplements', 'rnd_summary'] as $field) {
            $evidence[$field] = $text($assessment->{$field});
        }
        $labs = $assessment->biochemicalData;
        if ($labs !== null) {
            $values = array_intersect_key($labs->getAttributes(), app(LabFlagService::class)->ranges($patient?->sex));
            $evidence['laboratory_values'] = array_filter($values, static fn ($value): bool => $value !== null && is_numeric($value));
            if ($age !== null && $age >= 18 && $evidence['sex'] !== 'unknown' && ($assessment->pregnancy_lactation_status ?? 'none') === 'none') {
                $flags = app(LabFlagService::class)->flag($values, $patient?->sex);
                if (! preg_match('/\b(fasting (?:blood )?(?:glucose|sugar)|fbs)\b/i', $assessment->medical_history ?? '')) {
                    unset($flags['glucose']);
                }
                $evidence['laboratory_flags'] = $flags;
            }
        }

        return array_filter($evidence, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
