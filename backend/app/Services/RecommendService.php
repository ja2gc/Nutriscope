<?php

namespace App\Services;

use App\Models\ClinicalRule;

class RecommendService
{
    /**
     * Get food recommendations, avoidances, and limits based on clinical conditions.
     *
     * @param array $conditions e.g. ['CKD', 'DM']
     * @param array|null $stages optional stage filters
     * @return array{recommend: array, avoid: array, limits: array}
     */
    public function getRecommendations(array $conditions, ?array $stages = null): array
    {
        $rules = ClinicalRule::forConditions($conditions, $stages);

        $recommend = [];
        $avoid = [];
        $limits = [];

        foreach ($rules as $rule) {
            $entry = [
                'tag'       => $rule->nutrient_or_food_tag,
                'condition' => $rule->condition,
                'reason'    => $rule->reason,
            ];

            if ($rule->rule_type === 'recommend') {
                $recommend[] = $entry;
            } elseif ($rule->rule_type === 'avoid') {
                $avoid[] = $entry;
            } elseif ($rule->rule_type === 'limit') {
                $limits[] = array_merge($entry, [
                    'threshold' => $rule->threshold,
                    'unit'      => $rule->unit,
                ]);
            }
        }

        return compact('recommend', 'avoid', 'limits');
    }
}
