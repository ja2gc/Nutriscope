<?php

namespace App\Services\Diagnosis;

class PesEligibilityService
{
    public function __construct(private PesRuleCatalog $catalog) {}

    public function eligible(array $evidence, array $dismissedCandidateIds = []): array
    {
        $existing = $this->canonicalProblems($evidence['existing_problem_keys'] ?? []);
        $eligible = [];
        foreach ($this->catalog->cards() as $card) {
            if (in_array($card['id'], $dismissedCandidateIds, true) || in_array($this->canonicalProblem($card['problem_key']), $existing, true)) {
                continue;
            }
            if (! $this->matches($card['id'], $evidence)) {
                continue;
            }
            $keys = array_unique([...$card['required_evidence'], ...$card['corroborating_evidence'], 'age_group', 'sex', 'edema_present', 'pregnancy_lactation_status', 'weight_change_direction', 'weight_change_percentage', 'usual_weight_kg', 'weight_kg', 'weight_change_period']);
            $candidateEvidence = array_intersect_key($evidence, array_fill_keys($keys, true));
            $eligible[] = ['candidate_id' => $card['id'], 'domain' => $card['domain'], 'problem_key' => $card['problem_key'], 'evidence' => $candidateEvidence, 'source' => $card['source']];
        }

        return $eligible;
    }

    public function canonicalProblem(string $problem): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($problem)));

        return match (true) {
            preg_match('/\b(unintended|unintentional) weight loss\b/', $key) === 1 => 'unintended weight loss',
            preg_match('/\b(malnutrition|undernutrition)\b/', $key) === 1 => 'malnutrition undernutrition',
            preg_match('/\b(overweight|obesity|obese)\b/', $key) === 1 => 'overweight obesity',
            preg_match('/\b(inadequate|insufficient|suboptimal|sub optimal).*\b(oral|food|energy).*\bintake\b/', $key) === 1 => 'inadequate energy oral intake',
            preg_match('/\b(food|nutrition).*\bknowledge.*\b(deficit|lack)\b|\black of.*nutrition knowledge\b/', $key) === 1 => 'nutrition knowledge deficit',
            preg_match('/\b(limited|inadequate|poor) (food and )?nutrition knowledge\b/', $key) === 1 => 'nutrition knowledge deficit',
            preg_match('/\baltered (gi|gastrointestinal) function\b/', $key) === 1 => 'altered gastrointestinal function',
            preg_match('/\bdysphagia\b|\b(swallowing|chewing|mastication).*\bdifficulty\b/', $key) === 1 => 'swallowing chewing difficulty',
            preg_match('/\bfood insecurity\b|\blimited access to food\b/', $key) === 1 => 'food insecurity',
            default => $key,
        };
    }

    public function canonicalProblems(array $problems): array
    {
        $parts = [];
        foreach ($problems as $problem) {
            foreach (explode(';', $problem) as $part) {
                $parts[] = $this->canonicalProblem(trim($part));
            }
        }

        return array_values(array_unique($parts));
    }

    public function hasConcern(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item): bool => $this->hasConcern($item));
        }
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        $text = mb_strtolower(trim((string) preg_replace('/^[\s•*-]+/u', '', $value)));
        if (preg_match('/^[+-]?\d+(?:\.\d+)?\s*(?:%|kg|g|mg|cm|mm|ml|l|kcal)?$/', $text)) {
            return false;
        }
        if (preg_match('/^(no|none|n\/a|na|normal|resolved|adequate|sufficient|regular|no change|independent|ambulatory|\[redacted\])\.?$/', $text)) {
            return false;
        }
        if (preg_match('/^(understands|demonstrates understanding|(?:normal|adequate|sufficient)\b|no (?:(?:current|known|diagnosed|documented) )?(?:concerns|barriers|symptoms|signs|evidence|difficulty|dysphagia|constipation|diarrhea|vomiting|edema|muscle wasting|chronic illness|disease|medical conditions)|(?:patient )?denies\b|(?:possible|suspected|historical|resolved|family history of)\b)[^;]*$/', $text) && ! preg_match('/\b(but|however|except)\b/', $text)) {
            return false;
        }

        return true;
    }

    private function matches(string $id, array $evidence): bool
    {
        $text = fn (string $key): bool => $this->hasConcern($evidence[$key] ?? null);
        if (str_starts_with($id, 'inadequate_') || str_starts_with($id, 'excessive_') || $id === 'intake_other_v2') {
            return $text('dietary_intake');
        }

        return match ($id) {
            'unintended_weight_loss_v1' => ($evidence['weight_change_direction'] ?? 'unknown') === 'loss'
                && ($evidence['weight_loss_percentage'] ?? 0) > 0 && ! empty($evidence['weight_change_period']),
            'overweight_obesity_v1' => ($evidence['bmi'] ?? 0) >= 25 && in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
                && ($evidence['pregnancy_lactation_status'] ?? 'none') === 'none' && ! ($evidence['edema_present'] ?? false),
            'swallowing_chewing_difficulty_v1' => $text('chewing_swallowing_difficulties'),
            'altered_gi_function_v1' => $text('gi_symptoms'),
            'food_medication_interaction_v1' => $text('nutrient_drug_interaction') && ($evidence['medication_count'] ?? 0) > 0,
            'predicted_suboptimal_intake_v1' => in_array($evidence['energy_intake_status'] ?? null, ['Mostly liquids', 'Sub-optimal', 'Starvation', 'Poor intake prior to admission'], true)
                && (in_array($evidence['appetite'] ?? null, ['decreased', 'absent'], true) || $text('dietary_intake')),
            'altered_labs_v2' => ! empty($evidence['laboratory_flags']) && in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true) && ($evidence['sex'] ?? 'unknown') !== 'unknown',
            'clinical_other_v2' => $text('physical_assessment') || $text('medical_history') || $text('body_composition') || $text('food_intolerance')
                || (in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true) && ($evidence['bmi'] ?? 0) > 0 && $evidence['bmi'] < (($evidence['age_70_plus'] ?? false) ? 20 : 18.5)),
            default => str_ends_with($id, '_v2') && ($text('knowledge_notes') || $text('social_history') || $text('lifestyle') || $text('dietary_intake')),
        };
    }
}
