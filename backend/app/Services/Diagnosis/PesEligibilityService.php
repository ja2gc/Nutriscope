<?php

namespace App\Services\Diagnosis;

class PesEligibilityService
{
    public function __construct(private PesRuleCatalog $catalog) {}

    public function eligible(array $evidence, array $dismissedCandidateIds = []): array
    {
        if ($evidence['no_diagnosis_recorded'] ?? false) {
            return [];
        }
        $existing = $this->canonicalProblems($evidence['existing_problem_keys'] ?? []);
        $eligible = [];
        foreach ($this->catalog->cards() as $card) {
            if (in_array($card['id'], $dismissedCandidateIds, true)
                || in_array($card['id'], $evidence['saved_candidate_ids'] ?? [], true)
                || in_array($this->canonicalProblem($card['problem_key']), $existing, true)) {
                continue;
            }
            if (! $this->matches($card['id'], $evidence, $card['problem_key'])) {
                continue;
            }
            $keys = array_unique([...$card['required_evidence'], ...$card['corroborating_evidence'], 'age_group', 'sex', 'edema_present', 'pregnancy_lactation_status', 'weight_change_direction', 'weight_change_percentage', 'weight_change_period']);
            $candidateEvidence = array_intersect_key($evidence, array_fill_keys($keys, true));
            $eligible[] = ['candidate_id' => $card['id'], 'domain' => $card['domain'], 'problem_key' => $card['problem_key'], 'evidence' => $candidateEvidence, 'source' => $card['source']];
        }

        return $eligible;
    }

    public function canonicalProblem(string $problem): string
    {
        $problem = preg_replace('/^(Inadequate|Excessive) vitamin intake:\s*(.+)$/i', '$1 vitamin $2 intake', $problem);
        $problem = preg_replace('/^(Inadequate|Excessive) mineral intake:\s*(.+)$/i', '$1 $2 intake', $problem);
        $key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($problem)));
        $key = preg_replace(['/\b(carbohydrates|carbs)\b/', '/\b(calorie|calories|caloric)\b/', '/\bproteins\b/', '/\bfats\b/', '/\bfibre\b/'],
            ['carbohydrate', 'energy', 'protein', 'fat', 'fiber'], $key);
        $key = preg_replace(['/\bvitamin (?:b1 )?thiamin\b/', '/\bvitamin (?:b2 )?riboflavin\b/', '/\bvitamin (?:b3 )?niacin\b/', '/\bvitamin (?:b5 )?pantothenic acid\b/', '/\bvitamin (?:b7 )?biotin\b/'],
            ['thiamin', 'riboflavin', 'niacin', 'pantothenic acid', 'biotin'], $key);

        return match (true) {
            preg_match('/\b(unintended|unintentional) weight loss\b/', $key) === 1 => 'unintended weight loss',
            preg_match('/\b(malnutrition|undernutrition)\b/', $key) === 1 => 'malnutrition undernutrition',
            preg_match('/\b(overweight|obesity|obese)\b/', $key) === 1 => 'overweight obesity',
            preg_match('/\b(inadequate|insufficient|suboptimal|sub optimal).*\b(oral|food|energy).*\bintake\b/', $key) === 1 => 'inadequate energy oral intake',
            preg_match('/\b(food|nutrition).*\bknowledge.*\b(deficit|lack)\b|\black of.*nutrition knowledge\b/', $key) === 1 => 'nutrition knowledge deficit',
            preg_match('/\b(limited|inadequate|poor) (food and )?nutrition knowledge\b/', $key) === 1 => 'nutrition knowledge deficit',
            preg_match('/\baltered (gi|gastrointestinal) function\b/', $key) === 1 => 'altered gastrointestinal function',
            preg_match('/\bswallowing\b.*\bchewing\b/', $key) === 1 => 'swallowing chewing difficulty',
            preg_match('/\bdysphagia\b|\bswallowing.*\bdifficulty\b/', $key) === 1 => 'swallowing difficulty',
            preg_match('/\b(chewing|masticatory|mastication).*\bdifficulty\b/', $key) === 1 => 'chewing difficulty',
            preg_match('/\b(harmful|unsupported) beliefs\b/', $key) === 1 => 'unsupported food beliefs attitudes',
            preg_match('/\bfood insecurity\b|\blimited access to food\b/', $key) === 1 => 'food insecurity',
            default => $key,
        };
    }

    public function canonicalProblems(array $problems): array
    {
        $parts = [];
        foreach ($problems as $problem) {
            foreach (explode(';', $problem) as $part) {
                $canonical = $this->canonicalProblem(trim($part));
                $parts[] = $canonical;
                if ($canonical === 'swallowing chewing difficulty') {
                    $parts = [...$parts, 'swallowing difficulty', 'chewing difficulty'];
                }
            }
        }

        return array_values(array_unique($parts));
    }

    public function intakeFinding(array $evidence): string
    {
        return implode('; ', array_filter([$evidence['dietary_intake'] ?? '', $evidence['present_diet'] ?? ''], 'is_string'));
    }

    public function hasConcern(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item): bool => $this->hasConcern($item));
        }
        if (! is_string($value) || trim($value) === '') {
            return false;
        }
        $clauses = $this->clauses($value);
        if (count($clauses) > 1) {
            return collect($clauses)->contains(fn ($clause): bool => $this->hasConcern($clause));
        }
        $text = mb_strtolower(trim((string) preg_replace('/^[\s•*-]+/u', '', $value)));
        if (preg_match('/^[+-]?\d+(?:\.\d+)?\s*(?:%|kg|g|mg|cm|mm|ml|l|kcal)?$/', $text)) {
            return false;
        }
        if (preg_match('/^(no|none|n\/a|na|normal|resolved|adequate|sufficient|regular|no change|independent|ambulatory|\[redacted\])\.?$/', $text)) {
            return false;
        }
        if (preg_match('/^(understands|demonstrates understanding|(?:normal|adequate|sufficient)\b|no (?:(?:current|known|diagnosed|documented) )?(?:concerns|barriers|symptoms|signs|evidence|difficulty|dysphagia|constipation|diarrhea|nausea|vomiting|edema|muscle wasting|chronic illness|disease|medical conditions)|(?:patient )?denies\b|(?:possible|suspected|historical|resolved|family history of)\b)[^;]*$/', $text)) {
            return false;
        }

        return true;
    }

    public function clauses(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/(?<=[.!?;])(?:\s+|$)|[;\n]+|,\s+(?=(?:no|normal|adequate|sufficient|denies|patient|reports|persistent|reduced|low|high|inadequate|excessive)\b)|\s+\b(?:but|however|except)\b\s+/iu', $text))));
    }

    /** Intake labels need a documented nutrient and direction, not an unrelated abnormal field. */
    public function supportsIntakeProblem(string $label, string $finding): bool
    {
        $label = preg_replace('/^(Inadequate|Excessive) (vitamin|mineral) intake:\s*(.+)$/i', '$1 $3 Intake', $label);
        if (! preg_match('/^(Inadequate|Excessive) (.+) Intake$/i', $label, $parts)) {
            return false;
        }
        $nutrient = match (strtolower($parts[2])) {
            'energy', 'oral' => 'energy|calori\\w*|kcal|oral|food|meals?|(?:poor|reduced|decreased|inadequate|insufficient|excessive) intake',
            'carbohydrates', 'carbohydrate' => 'carbohydrates?|carbs?',
            'fluid' => 'fluids?|water',
            'fiber' => 'fib(?:er|re)',
            default => preg_quote($parts[2], '/'),
        };
        $direction = strtolower($parts[1]) === 'inadequate'
            ? 'inadequate|insufficient|sub.?optimal|below|less|low|poor|reduced|decreased|half|miss(?:es|ed|ing)|skip\\w*'
            : 'excessive|excess|above|exceed\\w*|high|too much|more than';
        foreach ($this->clauses($finding) as $part) {
            if ($this->hasConcern($part) && ! preg_match('/\b(adequate|sufficient|normal|denies|denied|no|not)\b/i', $part)
                && preg_match('/\b(?:'.$nutrient.')\b/i', $part)) {
                $comparison = preg_match('/([<>]?)\s*(\d+(?:\.\d+)?)\s*%\s*(?:of\s+)?(?:(?:estimated|prescribed|calculated|daily|usual|energy|nutrient|protein|fluid)\s+)*(?:needs|requirements|target|goal)\b/i', $part, $amount);
                $numericDirection = $comparison && (strtolower($parts[1]) === 'inadequate'
                    ? $amount[1] !== '>' && (float) $amount[2] < 100
                    : $amount[1] !== '<' && (float) $amount[2] > 100);
                $sides = preg_split('/\s+\b(?:vs\.?|versus|compared\s+(?:with|to)|against)\s+/i', $part);
                if (count($sides) === 2 && preg_match('/\b(needs|requirements|target|goal)\b/i', $sides[1])
                    && ! preg_match('/[<>]\s*\d|\b(?:at least|at most|more than|less than|over|under|up to)\s+\d/i', $part)
                    && preg_match('/(?<![\d<>.])(\d+(?:\.\d+)?)\s*(kcal|mcg|mg|g|ml|l)(?:\s*\/\s*(?:day|d))?\s*$/i', $sides[0], $actual)
                    && preg_match('/(?<![\d<>.])(\d+(?:\.\d+)?)\s*(kcal|mcg|mg|g|ml|l)\b/i', $sides[1], $target)
                    && strtolower($actual[2]) === strtolower($target[2]) && (float) $target[1] > 0) {
                    $numericDirection = $numericDirection || (strtolower($parts[1]) === 'inadequate'
                        ? (float) $actual[1] < (float) $target[1]
                        : (float) $actual[1] > (float) $target[1]);
                }
                if (preg_match('/\b(?:'.$direction.')\b/i', $part) || $numericDirection) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matches(string $id, array $evidence, string $label): bool
    {
        $text = fn (string $key): bool => $this->hasConcern($evidence[$key] ?? null);
        if (str_starts_with($id, 'inadequate_') || str_starts_with($id, 'excessive_')) {
            return $this->supportsIntakeProblem($label, $this->intakeFinding($evidence));
        }
        if ($id === 'intake_other_v2') {
            return $text('dietary_intake') || $text('present_diet') || $text('physical_assessment') || $text('body_composition');
        }

        return match ($id) {
            'knowledge_deficit_v2' => $text('knowledge_notes'),
            'food_insecurity_v2' => $text('social_history') && preg_match('/\b(food|meals?|groceries)\b/i', $evidence['social_history'] ?? '')
                && preg_match('/\b(food insecur|(?:cannot|unable|struggl\w*|not enough|lack of|limited)\b.{0,35}\b(?:afford|food|meals)|financial\b.{0,30}\bfood|food\b.{0,30}\b(?:money|access|unavailable))/i', $evidence['social_history'] ?? ''),
            'physical_inactivity_v2' => in_array($evidence['physical_activity_level'] ?? '', ['sedentary', 'inactive'], true)
                || ($text('lifestyle') && preg_match('/\b(inactiv\w*|sedentary|no (?:planned )?physical activity|no exercise|does not exercise)\b/i', $evidence['lifestyle'] ?? '')),
            'readiness_v2' => $text('lifestyle') && preg_match('/\b(not ready|unwilling|refus\w*|ambivalent)\b/i', $evidence['lifestyle'] ?? ''),
            'self_monitoring_v2' => $text('knowledge_notes') && preg_match('/\b(monitor|record|log|track)\w*\b/i', $evidence['knowledge_notes'] ?? ''),
            'adherence_v2' => ($text('lifestyle') || $text('dietary_intake')) && preg_match('/\b(adher\w*|complian\w*|prescribed|recommended|agreed plan)\b/i', ($evidence['lifestyle'] ?? '').' '.($evidence['dietary_intake'] ?? '')),
            'food_beliefs_v2' => ($text('knowledge_notes') || $text('lifestyle')) && preg_match('/\b(believ\w*|belief|myth|fear|taboo)\b/i', ($evidence['knowledge_notes'] ?? '').' '.($evidence['lifestyle'] ?? '')),
            'food_choices_v2' => $text('dietary_intake'),
            'unintended_weight_loss_v1' => ($evidence['weight_change_direction'] ?? 'unknown') === 'loss'
                && ($evidence['weight_loss_percentage'] ?? 0) > 0 && ! empty($evidence['weight_change_period']),
            'overweight_obesity_v1' => ($evidence['bmi'] ?? 0) >= 23 && in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true)
                && ($evidence['pregnancy_lactation_status'] ?? 'none') === 'none' && ! ($evidence['edema_present'] ?? false),
            'swallowing_chewing_difficulty_v1' => $text('chewing_swallowing_difficulties'),
            'altered_gi_function_v1' => $text('gi_symptoms'),
            'food_medication_interaction_v1' => $text('nutrient_drug_interaction') && ($evidence['medication_count'] ?? 0) > 0,
            'altered_labs_v2' => ! empty($evidence['laboratory_flags']) && in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true) && ($evidence['sex'] ?? 'unknown') !== 'unknown',
            'clinical_other_v2' => $text('physical_assessment') || $text('medical_history') || $text('body_composition') || $text('food_intolerance')
                || (in_array($evidence['age_group'] ?? null, ['adult', 'elderly'], true) && ($evidence['bmi'] ?? 0) > 0 && $evidence['bmi'] < (($evidence['age_70_plus'] ?? false) ? 20 : 18.5)),
            default => str_ends_with($id, '_v2') && ($text('knowledge_notes') || $text('social_history') || $text('lifestyle') || $text('dietary_intake')),
        };
    }
}
