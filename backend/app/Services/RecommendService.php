<?php

namespace App\Services;

use App\Models\ClinicalRule;

class RecommendService
{
    /**
     * Get food recommendations, avoidances, and limits based on clinical conditions.
     *
     * @param  array  $conditions  e.g. ['CKD', 'DM']
     * @param  array|null  $stages  optional stage filters
     * @param  array<string,array{value:float,status:string}>  $labFlags
     * @return array{recommend: array, avoid: array, limits: array}
     */
    public function getRecommendations(array $conditions, ?array $stages = null, array $labFlags = [], array $prescriptionLimits = []): array
    {
        $rules = ClinicalRule::forConditions($conditions, $stages);

        $recommend = [];
        $avoid = [];
        $limits = [];

        foreach ($rules as $rule) {
            $entry = [
                'tag' => $rule->nutrient_or_food_tag,
                'condition' => $rule->condition,
                'reason' => $rule->reason,
            ];

            if ($rule->rule_type === 'recommend') {
                $recommend[] = $entry;
            } elseif ($rule->rule_type === 'avoid') {
                $avoid[] = $entry;
            } elseif ($rule->rule_type === 'limit') {
                $confirmed = $prescriptionLimits[$rule->nutrient_or_food_tag] ?? [];
                $limits[] = array_merge($entry, [
                    'threshold' => $confirmed['max'] ?? $confirmed['target'] ?? null,
                    'unit' => $confirmed['unit'] ?? $rule->unit,
                ]);
            }
        }

        foreach ($labFlags as $key => $flag) {
            $status = $flag['status'] ?? null;
            if ($key === 'potassium' && $status === 'HIGH') {
                $limits[] = [
                    'tag' => 'potassium',
                    'condition' => 'lab_refinement',
                    'reason' => 'Potassium is above reference range; limit high-potassium foods until clinically reviewed.',
                    'threshold' => null,
                    'unit' => 'mg',
                ];
            } elseif ($key === 'potassium' && $status === 'LOW') {
                $recommend[] = [
                    'tag' => 'potassium',
                    'condition' => 'lab_refinement',
                    'reason' => 'Potassium is below reference range; review replacement needs and potassium-containing foods with the care team.',
                ];
            } elseif ($key === 'phosphate' && $status === 'HIGH') {
                $limits[] = [
                    'tag' => 'phosphate',
                    'condition' => 'lab_refinement',
                    'reason' => 'Phosphate is above reference range; review phosphorus additives and renal status.',
                    'threshold' => null,
                    'unit' => 'mg',
                ];
            } elseif ($key === 'phosphate' && $status === 'LOW') {
                $recommend[] = [
                    'tag' => 'phosphate',
                    'condition' => 'lab_refinement',
                    'reason' => 'Phosphate is below reference range; review replacement and nutrition progression clinically.',
                ];
            } elseif ($key === 'calcium' && $status === 'HIGH') {
                $limits[] = [
                    'tag' => 'calcium',
                    'condition' => 'lab_refinement',
                    'reason' => 'Calcium is above reference range; avoid unnecessary calcium supplementation until reviewed.',
                    'threshold' => null,
                    'unit' => 'mg',
                ];
            } elseif ($key === 'magnesium' && $status === 'LOW') {
                $recommend[] = [
                    'tag' => 'magnesium',
                    'condition' => 'lab_refinement',
                    'reason' => 'Magnesium is below reference range; review monitoring and replacement clinically.',
                ];
            }
        }

        return compact('recommend', 'avoid', 'limits');
    }
}
