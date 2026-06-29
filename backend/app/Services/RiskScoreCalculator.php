<?php

namespace App\Services;

use App\Models\Assessment;

class RiskScoreCalculator
{
    public const FACTOR_POINTS = [
        'screening_criteria' => 1.0,
        'ibw_limit' => 1.0,
        'unintentional_weight_loss' => 2.0,
        'mechanical_digestive_problem' => 1.0,
        'low_albumin' => 1.0,
        'significant_lab_result' => 1.0,
        'others' => 1.0,
    ];

    public function __construct(private LabFlagService $labFlags) {}

    public function calculate(Assessment $assessment): array
    {
        $score = 0.0;
        $checkedFactors = [];

        // 1. Screening criteria for potential nutritional risk = 1 point
        $patient = $assessment->ncpRecord?->patient;
        if ($patient && $patient->screening_type) {
            $score += 1.0;
            $checkedFactors[] = 'screening_criteria';
        }

        // 2. Less than 85% or greater than 130% ideal body weight = 1 point
        if (!is_null($assessment->ibw_percentage) && ($assessment->ibw_percentage < 85.0 || $assessment->ibw_percentage > 130.0)) {
            $score += 1.0;
            $checkedFactors[] = 'ibw_limit';
        }

        // 3. Unintentional weight loss = 2 points
        $hasWeightLoss = false;
        if (!is_null($assessment->weight_loss_percentage) && $assessment->weight_loss_percentage > 0.0) {
            $hasWeights = !is_null($assessment->usual_weight) && !is_null($assessment->weight);
            $hasWeightLoss = $hasWeights
                ? (float) $assessment->usual_weight > (float) $assessment->weight
                : true;
        }

        if ($hasWeightLoss) {
            $score += 2.0;
            $checkedFactors[] = 'unintentional_weight_loss';
        }

        // 4. Mechanical / digestive problem = 1 point
        $hasMechanicalProblem = !empty($assessment->chewing_swallowing_difficulties) ||
                                !empty($assessment->constipation) ||
                                !empty($assessment->diarrhea_notes) ||
                                !empty($assessment->food_intolerance);
        if ($hasMechanicalProblem) {
            $score += 1.0;
            $checkedFactors[] = 'mechanical_digestive_problem';
        }

        // 5. Low albumin = 1 point
        $biochemical = $assessment->biochemicalData;
        if ($biochemical && !is_null($biochemical->albumin) && $biochemical->albumin < 3.5) {
            $score += 1.0;
            $checkedFactors[] = 'low_albumin';
        }

        // 6. Significant lab result = 1 point
        // Glucose/potassium/BUN use clinically-significant screening thresholds (looser than the
        // normal-range flags so only clearly abnormal values score). Creatinine's upper bound is
        // sex-aware and sourced from LabFlagService (M 1.2 / F 0.9) — never hardcoded here, so the
        // female threshold can't drift from the diagnosis/monitoring flagging logic.
        $hasSignificantLab = false;
        if ($biochemical) {
            $sex = $assessment->ncpRecord?->patient?->sex;
            $creatinineHigh = $this->labFlags->ranges($sex)['creatinine']['high'] ?? 1.2;

            if ((!is_null($biochemical->glucose) && ($biochemical->glucose > 125.0 || $biochemical->glucose < 70.0)) ||
                (!is_null($biochemical->potassium) && ($biochemical->potassium > 5.0 || $biochemical->potassium < 3.5)) ||
                (!is_null($biochemical->creatinine) && $creatinineHigh !== null && $biochemical->creatinine > $creatinineHigh) ||
                (!is_null($biochemical->bun) && $biochemical->bun > 20.0)) {
                $hasSignificantLab = true;
            }
        }
        if ($hasSignificantLab) {
            $score += 1.0;
            $checkedFactors[] = 'significant_lab_result';
        }

        // 7. Other/s = 1 point
        $hasOthers = !empty($assessment->nutrient_drug_interaction) || !empty($assessment->lifestyle);
        if ($hasOthers) {
            $score += 1.0;
            $checkedFactors[] = 'others';
        }

        // Score thresholds:
        //   Total = 1 → Low Risk → Nutritional Status: Normal
        //   Total = 2 or 3 → Moderate → Nutritional Status: Moderate Malnutrition
        //   Total > 3 → High Risk → Nutritional Status: Severe Malnutrition
        $nutritionalStatus = self::nutritionalStatusForScore($score);

        return [
            'score' => $score,
            'nutritional_status' => $nutritionalStatus,
            'checked_factors' => $checkedFactors,
        ];
    }

    /**
     * @param array<int, string> $factors
     */
    public static function scoreFactors(array $factors): float
    {
        return array_reduce(
            array_unique($factors),
            fn (float $score, string $factor): float => $score + (self::FACTOR_POINTS[$factor] ?? 0.0),
            0.0,
        );
    }

    public static function nutritionalStatusForScore(float $score): string
    {
        if ($score === 2.0 || $score === 3.0) {
            return 'Moderate Malnutrition';
        }

        if ($score > 3.0) {
            return 'Severe Malnutrition';
        }

        return 'Normal';
    }
}
