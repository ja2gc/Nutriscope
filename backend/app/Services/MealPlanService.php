<?php

namespace App\Services;

use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;

class MealPlanService
{
    public function generate(NcpRecord $ncpRecord, string $weekStartDate, array $conditions = [], array $allergens = []): array|MealPlan
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        $recipes = Recipe::query()
            ->when(!empty($allergens), function ($q) use ($allergens) {
                foreach ($allergens as $allergen) {
                    $q->whereJsonDoesntContain('allergens', $allergen);
                }
            })
            ->get();

        if ($recipes->count() < 5) {
            return ['insufficient_recipes' => true, 'count' => $recipes->count()];
        }

        $mealPlan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $weekStartDate,
            'generation_type' => 'auto',
            'status'          => 'draft',
        ]);

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $mealTypes  = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];

        $pool     = $recipes->shuffle()->values();
        $poolSize = $pool->count();
        $now      = now();

        // Bulk-insert all 35 day rows (no timestamps on meal_plan_days)
        $dayRows = [];
        foreach ($daysOfWeek as $day) {
            foreach ($mealTypes as $mealType) {
                $dayRows[] = [
                    'meal_plan_id' => $mealPlan->id,
                    'day_of_week'  => $day,
                    'meal_type'    => $mealType,
                    'flagged'      => false,
                ];
            }
        }
        MealPlanDay::insert($dayRows);

        // Load inserted days to get their IDs
        $days = MealPlanDay::where('meal_plan_id', $mealPlan->id)
            ->orderBy('id')
            ->get();

        // Bulk-insert all 35 item rows at once
        $itemRows  = [];
        $slotIndex = 0;
        foreach ($days as $day) {
            $recipe = $pool[$slotIndex % $poolSize];
            $slotIndex++;
            $itemRows[] = [
                'meal_plan_day_id'  => $day->id,
                'recipe_id'         => $recipe->id,
                'quantity'          => 1,
                'unit'              => 'serving',
                'nutrient_snapshot' => json_encode([
                    'name'           => $recipe->name,
                    'calories'       => (float) $recipe->total_calories,
                    'protein'        => (float) $recipe->total_protein,
                    'carbs'          => (float) $recipe->total_carbs,
                    'fat'            => (float) $recipe->total_fat,
                    'micronutrients' => $recipe->micronutrients ?? [],
                    'serving_size'   => (float) ($recipe->servings ?? 1),
                    'serving_unit'   => 'serving',
                ]),
                'ai_suggested' => false,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        MealPlanItem::insert($itemRows);

        return $mealPlan->load('days');
    }
}
