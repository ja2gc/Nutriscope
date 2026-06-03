<?php

namespace App\Services;

use App\Models\Assessment;

class RiskScoreCalculator
{
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
        if (!is_null($assessment->weight_loss_percentage) && $assessment->weight_loss_percentage > 0.0) {
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
        $hasSignificantLab = false;
        if ($biochemical) {
            if ((!is_null($biochemical->glucose) && ($biochemical->glucose > 125.0 || $biochemical->glucose < 70.0)) ||
                (!is_null($biochemical->potassium) && ($biochemical->potassium > 5.0 || $biochemical->potassium < 3.5)) ||
                (!is_null($biochemical->creatinine) && $biochemical->creatinine > 1.2) ||
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
        $nutritionalStatus = 'Normal';
        if ($score === 2.0 || $score === 3.0) {
            $nutritionalStatus = 'Moderate Malnutrition';
        } elseif ($score > 3.0) {
            $nutritionalStatus = 'Severe Malnutrition';
        }

        return [
            'score' => $score,
            'nutritional_status' => $nutritionalStatus,
            'checked_factors' => $checkedFactors,
        ];
    }
}
