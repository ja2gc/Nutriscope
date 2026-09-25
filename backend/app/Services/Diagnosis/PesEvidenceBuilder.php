<?php

namespace App\Services\Diagnosis;

use App\Models\NcpRecord;
use Illuminate\Support\Str;

class PesEvidenceBuilder
{
    public function build(NcpRecord $ncpRecord): array
    {
        $ncpRecord->loadMissing(['patient', 'assessment', 'diagnoses:id,ncp_record_id,problem']);
        $assessment = $ncpRecord->assessment;

        if ($assessment === null) {
            return ['existing_problem_keys' => $this->existingProblems($ncpRecord)];
        }

        $period = null;
        if (
            $assessment->weight_change_period_value !== null
            && in_array($assessment->weight_change_period_unit, ['weeks', 'months'], true)
        ) {
            $period = [
                'value' => (int) $assessment->weight_change_period_value,
                'unit' => $assessment->weight_change_period_unit,
            ];
        }

        $giSymptoms = array_filter([
            'constipation' => $this->bounded($assessment->constipation),
            'diarrhea' => $this->bounded($assessment->diarrhea_notes),
        ]);

        return array_filter([
            'age_group' => $ncpRecord->patient?->age_group_category,
            'weight_kg' => $assessment->weight !== null ? (float) $assessment->weight : null,
            'height_cm' => $assessment->height !== null ? (float) $assessment->height : null,
            'bmi' => $assessment->bmi !== null ? (float) $assessment->bmi : null,
            'weight_loss_percentage' => $assessment->weight_loss_percentage !== null
                ? (float) $assessment->weight_loss_percentage
                : null,
            'weight_change_period' => $period,
            'appetite' => $assessment->appetite_changes,
            'present_diet' => $this->bounded($assessment->present_diet),
            'energy_intake_status' => $assessment->energy_intake_status,
            'chewing_swallowing_difficulties' => $this->bounded($assessment->chewing_swallowing_difficulties),
            'gi_symptoms' => $giSymptoms === [] ? null : $giSymptoms,
            'food_intolerance' => $this->bounded($assessment->food_intolerance),
            'nutrient_drug_interaction' => $this->bounded($assessment->nutrient_drug_interaction),
            'medication_count' => is_array($assessment->medications) ? count($assessment->medications) : 0,
            'rnd_summary' => $this->bounded($assessment->rnd_summary, 280),
            'existing_problem_keys' => $this->existingProblems($ncpRecord),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    private function existingProblems(NcpRecord $ncpRecord): array
    {
        return $ncpRecord->diagnoses
            ->pluck('problem')
            ->filter()
            ->map(fn (string $problem): string => $this->bounded($problem, 255) ?? '')
            ->filter()
            ->unique(fn (string $problem): string => mb_strtolower($problem))
            ->sort()
            ->values()
            ->all();
    }

    private function bounded(?string $value, int $length = 240): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) preg_replace('/[\p{C}\s]+/u', ' ', $value));

        return $normalized === '' ? null : Str::limit($normalized, $length, '');
    }
}
