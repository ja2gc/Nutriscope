<?php

namespace App\Services\Diagnosis;

class PesRuleCatalog
{
    public const VERSION = '2026-09-21-v1';

    private const ACADEMY_SOURCE = [
        'id' => 'academy_ncp_diagnosis_2026',
        'issuer' => 'Academy of Nutrition and Dietetics',
        'title' => 'Nutrition Diagnosis',
        'version' => 'web edition accessed 2026-09-25',
        'location' => 'Nutrition Diagnosis; Critical Thinking in Nutrition Diagnosis',
        'url' => 'https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-diagnosis',
    ];

    private const WHO_OBESITY_SOURCE = [
        'id' => 'who_obesity_fact_sheet_2025',
        'issuer' => 'World Health Organization',
        'title' => 'Obesity and overweight',
        'version' => '8 December 2025',
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
            'problem_key' => $problemKey,
            'required_evidence' => $requiredEvidence,
            'corroborating_evidence' => $corroboratingEvidence,
            'disqualifiers' => $disqualifiers,
            'source' => $source,
        ];
    }
}
