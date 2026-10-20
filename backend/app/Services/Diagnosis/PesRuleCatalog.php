<?php

namespace App\Services\Diagnosis;

class PesRuleCatalog
{
    public const VERSION = '2026-10-20-v2';

    private const ACADEMY_SOURCE = [
        'id' => 'academy_ncp_diagnosis_2026',
        'issuer' => 'Academy of Nutrition and Dietetics',
        'title' => 'Nutrition Diagnosis',
        'version' => 'web edition accessed 2026-10-20',
        'location' => 'Nutrition Diagnosis; Critical Thinking in Nutrition Diagnosis',
        'url' => 'https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-diagnosis',
    ];

    private const WHO_OBESITY_SOURCE = [
        'id' => 'who_obesity_fact_sheet_2025',
        'issuer' => 'World Health Organization',
        'title' => 'Obesity and overweight',
        'version' => 'web edition accessed 2026-10-20',
        'location' => 'Definition of overweight and obesity — Adults',
        'url' => 'https://www.who.int/news-room/fact-sheets/detail/obesity-and-overweight',
    ];

    public function version(): string
    {
        return self::VERSION;
    }

    public function cards(): array
    {
        return [
            ...$this->contextualCards(),
            $this->card(
                'unintended_weight_loss_v1',
                'Unintended Weight Loss',
                ['weight_loss_percentage', 'weight_change_period'],
                ['appetite', 'present_diet'],
                ['weight_loss_percentage:non_positive'],
                self::ACADEMY_SOURCE,
            ),
            $this->card(
                'overweight_obesity_v1',
                'Overweight / Obesity',
                ['bmi', 'age_group'],
                ['weight_kg', 'height_cm'],
                ['age_group:pediatric', 'bmi:below_25'],
                self::WHO_OBESITY_SOURCE,
            ),
            $this->card(
                'swallowing_chewing_difficulty_v1',
                'Swallowing / Chewing Difficulty',
                ['chewing_swallowing_difficulties'],
                ['present_diet', 'appetite'],
                [],
                self::ACADEMY_SOURCE,
            ),
            $this->card(
                'altered_gi_function_v1',
                'Altered GI Function',
                ['gi_symptoms'],
                ['present_diet', 'food_intolerance'],
                [],
                self::ACADEMY_SOURCE,
            ),
            $this->card(
                'food_medication_interaction_v1',
                'Food-Medication Interaction',
                ['nutrient_drug_interaction', 'medication_count'],
                ['present_diet'],
                ['medication_count:zero'],
                self::ACADEMY_SOURCE,
            ),
            $this->card(
                'predicted_suboptimal_intake_v1',
                'Predicted Suboptimal Intake',
                ['energy_intake_status'],
                ['appetite', 'present_diet'],
                ['energy_intake_status:no_change'],
                self::ACADEMY_SOURCE,
            ),
        ];
    }

    private function contextualCards(): array
    {
        $cards = [];
        foreach (['Energy', 'Protein', 'Carbohydrates', 'Fat', 'Fluid', 'Fiber', 'Oral'] as $nutrient) {
            foreach (['Inadequate', 'Excessive'] as $direction) {
                $card = $this->card(strtolower($direction.'_'.$nutrient).'_v2', "{$direction} {$nutrient} Intake", ['dietary_intake'], ['appetite', 'energy_intake_status', 'present_diet', 'dietary_restrictions', 'social_history', 'knowledge_notes', 'lifestyle'], [], self::ACADEMY_SOURCE);
                $card['domain'] = 'NI';
                $cards[] = $card;
            }
        }
        $intakeOther = $this->card('intake_other_v2', 'Other', ['dietary_intake'], ['present_diet', 'appetite', 'energy_intake_status', 'dietary_restrictions', 'supplements', 'social_history', 'knowledge_notes'], [], self::ACADEMY_SOURCE);
        $intakeOther['domain'] = 'NI';
        $cards[] = $intakeOther;
        foreach ([
            'knowledge_deficit_v2' => 'Food and Nutrition Knowledge Deficit',
            'food_beliefs_v2' => 'Harmful Beliefs / Attitudes about Food',
            'readiness_v2' => 'Not Ready for Diet / Lifestyle Change',
            'self_monitoring_v2' => 'Self-Monitoring Deficit',
            'adherence_v2' => 'Limited Adherence to Nutrition-Related Recommendations',
            'food_choices_v2' => 'Undesirable Food Choices',
            'physical_inactivity_v2' => 'Physical Inactivity',
            'food_insecurity_v2' => 'Food Insecurity',
            'behavioral_other_v2' => 'Other',
        ] as $id => $problem) {
            $card = $this->card($id, $problem, ['knowledge_notes', 'lifestyle', 'social_history', 'dietary_intake'], ['dietary_restrictions', 'physical_activity_level', 'functional_assessment', 'present_diet'], [], self::ACADEMY_SOURCE);
            $card['domain'] = 'NB';
            $cards[] = $card;
        }
        $cards[] = $this->card('altered_labs_v2', 'Altered Nutrition-Related Laboratory Values', ['laboratory_flags'], ['laboratory_values', 'medical_history', 'dietary_intake', 'supplements', 'nutrient_drug_interaction'], [], self::ACADEMY_SOURCE);
        $cards[] = $this->card('clinical_other_v2', 'Other', ['physical_assessment', 'medical_history', 'body_composition', 'food_intolerance'], ['dietary_intake', 'present_diet', 'appetite', 'weight_change_direction', 'weight_change_percentage', 'weight_change_period', 'edema_present', 'pregnancy_lactation_status', 'nutritional_status', 'muac_mm', 'ibw_percentage'], [], self::ACADEMY_SOURCE);

        return $cards;
    }

    private function card(
        string $id,
        string $problemKey,
        array $requiredEvidence,
        array $corroboratingEvidence,
        array $disqualifiers,
        array $source,
    ): array {
        return [
            'id' => $id,
            'domain' => 'NC',
            'problem_key' => $problemKey,
            'required_evidence' => $requiredEvidence,
            'corroborating_evidence' => $corroboratingEvidence,
            'disqualifiers' => $disqualifiers,
            'source' => $source,
        ];
    }
}
