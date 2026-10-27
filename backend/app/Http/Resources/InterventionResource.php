<?php

namespace App\Http\Resources;

use App\Support\InterventionGuidance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'nutrition_support' => $this->nutrition_support,
            'formula_items' => $this->formula_items ?? [],
            'goal_type' => $this->goal_type,
            'disease_stage' => $this->disease_stage,
            'energy_adjustment_mode' => $this->energy_adjustment_mode,
            'energy_adjustment_kcal' => $this->energy_adjustment_kcal,
            'fluid_mode' => $this->fluid_mode,
            'calculation_options' => $this->calculation_options,
            'calculation_snapshot' => $this->calculation_snapshot,
            'guidance' => InterventionGuidance::for($this->goal_type ?? 'custom', $this->disease_stage, [
                ...($this->calculation_options ?? []),
                'energy_adjustment_mode' => empty($this->calculation_snapshot['manual_overrides']['energy_kcal']) ? ($this->energy_adjustment_mode ?? 'none') : 'none',
                'energy_adjustment_kcal' => $this->energy_adjustment_kcal ?? 0,
                'fluid_mode' => $this->fluid_mode,
                'pediatric' => ($this->calculation_snapshot['inputs']['ageYears'] ?? 18) < 18,
                'age_months' => $this->calculation_snapshot['inputs']['ageMonths'] ?? (($this->calculation_snapshot['inputs']['ageYears'] ?? 18) * 12),
                'maternal' => ! in_array($this->calculation_snapshot['inputs']['pregnancyLactationStatus'] ?? 'none', [null, 'none'], true),
            ]),
            'displayed_nutrients' => $this->displayed_nutrients,
            'energy_kcal' => $this->energy_kcal,
            'protein_g' => $this->protein_g,
            'carbs_g' => $this->carbs_g,
            'fat_g' => $this->fat_g,
            'fluid_ml' => $this->fluid_ml,
            'micronutrient_limits' => $this->micronutrient_limits,
            'nutrient_operators' => $this->nutrient_operators,
            'weight_target' => $this->weight_target,
            'education_notes' => $this->education_notes,
            'counseling_goals' => $this->counseling_goals,
            'barriers' => $this->barriers,
            'strategies' => $this->strategies,
            'session_type' => $this->session_type,
            'next_followup_date' => $this->next_followup_date?->toDateString(),
            'source_monitoring_id' => $this->sourceMonitoring?->uuid,
            'source_monitoring_date' => $this->sourceMonitoring?->observed_at?->toDateString()
                ?? $this->sourceMonitoring?->created_at?->toDateString(),
            'has_meal_plan' => $this->mealPlan !== null,
            'meal_plan_id' => $this->mealPlan?->uuid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
