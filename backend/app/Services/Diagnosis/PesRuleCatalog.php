<?php

namespace App\Services\Diagnosis;

use App\Support\ClinicalAiContext;

class PesRuleCatalog
{
    public const VERSION = '2026-10-29-v15';

    private const ACADEMY_SOURCE = [
        'id' => 'academy_ncp_diagnosis_2026',
        'issuer' => 'Academy of Nutrition and Dietetics',
        'title' => 'Nutrition Diagnosis',
        'version' => 'web edition accessed 2026-10-20',
        'location' => 'Nutrition Diagnosis; Critical Thinking in Nutrition Diagnosis',
        'url' => 'https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-diagnosis',
    ];

    private const ASIA_PACIFIC_SOURCE = [
        'id' => 'asia_pacific_bmi_2026',
        'issuer' => 'World Obesity Federation',
        'title' => 'Obesity Classification',
        'version' => 'web table checked 2026-10-25; Asia-Pacific report (WHO, IASO, IOTF 2000)',
        'location' => 'Asia-Pacific BMI classification table',
        'url' => 'https://www.worldobesity.org/about/about-obesity/obesity-classification',
    ];

    public function version(): string
    {
        return self::VERSION.':'.ClinicalAiContext::version();
    }

    public function cards(): array
    {
        return [
            ...$this->contextualCards(),
            $this->card(
                'unintended_weight_loss_v1',
                'Unintended Weight Loss',
                ['weight_loss_percentage', 'weight_change_period'],
                ['appetite', 'present_diet', 'weight_kg', 'usual_weight_kg'],
                ['weight_loss_percentage:non_positive'],
                self::ACADEMY_SOURCE,
            ),
            $this->card(
                'overweight_obesity_v1',
                'Overweight / Obesity',
                ['bmi', 'age_group'],
                ['bmi_classification'],
                ['age_group:pediatric', 'bmi:below_23'],
                self::ASIA_PACIFIC_SOURCE,
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
        $intakeOther = $this->card('intake_other_v2', 'Other', ['dietary_intake'], ['present_diet', 'appetite', 'energy_intake_status', 'dietary_restrictions', 'supplements', 'social_history', 'knowledge_notes', 'physical_assessment', 'body_composition', 'gi_symptoms', 'chewing_swallowing_difficulties', 'nutrition_support'], [], self::ACADEMY_SOURCE);
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
            $required = match ($id) {
                'knowledge_deficit_v2', 'self_monitoring_v2' => ['knowledge_notes'],
                'food_beliefs_v2' => ['knowledge_notes', 'lifestyle'],
                'readiness_v2' => ['lifestyle'],
                'adherence_v2' => ['lifestyle', 'dietary_intake'],
                'food_choices_v2' => ['dietary_intake'],
                'physical_inactivity_v2' => ['physical_activity_level', 'lifestyle', 'social_history'],
                'food_insecurity_v2' => ['social_history'],
                default => ['knowledge_notes', 'lifestyle', 'social_history', 'dietary_intake'],
            };
            $card = $this->card($id, $problem, $required, ['dietary_restrictions', 'physical_activity_level', 'functional_assessment', 'present_diet'], [], self::ACADEMY_SOURCE);
            $card['domain'] = 'NB';
            $cards[] = $card;
        }
        $cards[] = $this->card('altered_labs_v2', 'Altered Nutrition-Related Laboratory Values', ['laboratory_flags'], ['laboratory_values', 'medical_history', 'dietary_intake', 'supplements', 'nutrient_drug_interaction'], [], self::ACADEMY_SOURCE);
        $cards[] = $this->card('clinical_other_v2', 'Other', ['physical_assessment', 'medical_history', 'body_composition', 'food_intolerance'], ['dietary_intake', 'present_diet', 'appetite', 'weight_change_direction', 'weight_change_percentage', 'weight_change_period', 'edema_present', 'pregnancy_lactation_status', 'bmi_classification', 'muac_mm', 'ibw_percentage'], [], self::ACADEMY_SOURCE);

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
