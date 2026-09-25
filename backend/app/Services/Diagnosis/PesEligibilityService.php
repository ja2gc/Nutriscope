<?php

namespace App\Services\Diagnosis;

class PesEligibilityService
{
    public function __construct(private PesRuleCatalog $catalog) {}

    public function eligible(array $evidence, array $dismissedCandidateIds = []): array
    {
        $existing = array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            $evidence['existing_problem_keys'] ?? [],
        );
        $dismissed = array_fill_keys($dismissedCandidateIds, true);
        $eligible = [];

        foreach ($this->catalog->cards() as $card) {
            if (isset($dismissed[$card['id']])) {
                continue;
            }
            if (in_array(mb_strtolower($card['problem_key']), $existing, true)) {
                continue;
            }
            if (! $this->matches($card['id'], $evidence)) {
                continue;
            }

            $allowedKeys = array_merge($card['required_evidence'], $card['corroborating_evidence']);
            $candidateEvidence = array_intersect_key($evidence, array_fill_keys($allowedKeys, true));
            $eligible[] = [
                'candidate_id' => $card['id'],
                'domain' => 'NC',
                'problem_key' => $card['problem_key'],
                'evidence' => $candidateEvidence,
                'source' => $card['source'],
            ];

            if (count($eligible) === 3) {
                break;
            }
        }

        return $eligible;
    }

    private function matches(string $ruleId, array $evidence): bool
    {
        return match ($ruleId) {
            'unintended_weight_loss_v1' => $this->positiveNumber($evidence['weight_loss_percentage'] ?? null)
                && $this->validPeriod($evidence['weight_change_period'] ?? null),
            'overweight_obesity_v1' => is_numeric($evidence['bmi'] ?? null)
                && (float) $evidence['bmi'] >= 25
                && in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
                && $this->positiveNumber($evidence['weight_kg'] ?? null)
                && $this->positiveNumber($evidence['height_cm'] ?? null),
            'swallowing_chewing_difficulty_v1' => $this->positiveFinding(
                $evidence['chewing_swallowing_difficulties'] ?? null,
                ['difficulty', 'problem'],
            ),
            'altered_gi_function_v1' => is_array($evidence['gi_symptoms'] ?? null)
                && array_filter(
                    $evidence['gi_symptoms'],
                    fn (mixed $value, string $key): bool => $this->positiveFinding($value, [$key]),
                    ARRAY_FILTER_USE_BOTH,
                ) !== [],
            'food_medication_interaction_v1' => $this->positiveFinding(
                $evidence['nutrient_drug_interaction'] ?? null,
                ['interaction'],
            )
                && (int) ($evidence['medication_count'] ?? 0) > 0,
            'predicted_suboptimal_intake_v1' => in_array(
                $evidence['energy_intake_status'] ?? null,
                ['Mostly liquids', 'Sub-optimal', 'Starvation', 'Poor intake prior to admission'],
                true,
            ) && (
                in_array($evidence['appetite'] ?? null, ['decreased', 'absent'], true)
                || $this->hasText($evidence['present_diet'] ?? null)
            ),
            default => false,
        };
    }

    private function positiveNumber(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }

    private function validPeriod(mixed $period): bool
    {
        return is_array($period)
            && filter_var($period['value'] ?? null, FILTER_VALIDATE_INT) !== false
            && (int) $period['value'] > 0
            && in_array($period['unit'] ?? null, ['weeks', 'months'], true);
    }

    private function hasText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function positiveFinding(mixed $value, array $findingTerms): bool
    {
        if (! $this->hasText($value)) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['none', 'n/a', 'na', 'not applicable', 'normal', 'resolved'], true)) {
            return false;
        }

        foreach ($findingTerms as $term) {
            if (preg_match('/^(?:no|den(?:y|ies)|without)\b.*\b'.preg_quote($term, '/').'\b/i', $normalized)) {
                return false;
            }
        }

        return true;
    }
}
