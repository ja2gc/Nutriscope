<?php

namespace App\Services;

use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;

class MealPlanService
{
    // Approximate % of daily energy per meal slot
    private const SLOT_DISTRIBUTION = [
        'breakfast' => 0.25,
        'am_snack'  => 0.10,
        'lunch'     => 0.30,
        'pm_snack'  => 0.10,
        'dinner'    => 0.25,
    ];

    public function generate(NcpRecord $ncpRecord, string $weekStartDate, array $conditions = [], array $allergens = []): array|MealPlan
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();

        // Auto-pull allergens from the assessment if not explicitly passed
        if (empty($allergens)) {
            $assessmentAllergens = $ncpRecord->assessment?->allergies ?? [];
            $allergens = is_array($assessmentAllergens) ? $assessmentAllergens : [];
        }

        // Load all recipes with their ingredients' allergens
        $recipes = Recipe::with('ingredients.foodItem')->get()->filter(function ($recipe) use ($allergens) {
            if (empty($allergens)) return true;
            // Exclude recipe if any ingredient's food item has a matching allergen
            foreach ($recipe->ingredients as $ing) {
                $foodAllergens = $ing->foodItem?->allergens ?? [];
                if (!is_array($foodAllergens)) $foodAllergens = [];
                foreach ($allergens as $patientAllergen) {
                    if (in_array(strtolower($patientAllergen), array_map('strtolower', $foodAllergens))) {
                        return false;
                    }
                }
            }
            return true;
        })->values();

        if ($recipes->count() < 5) {
            return ['insufficient_recipes' => true, 'count' => $recipes->count()];
        }

        // Daily targets
        $dailyKcal    = max((float) ($intervention->energy_kcal ?? 2000), 1);
        $dailyProtein = (float) ($intervention->protein_g ?? 70);
        $dailyCarbs   = (float) ($intervention->carbs_g   ?? 250);
        $dailyFat     = (float) ($intervention->fat_g     ?? 60);

        // Target macro ratios (fraction of total kcal): protein 4kcal/g, carbs 4kcal/g, fat 9kcal/g
        $targetProteinRatio = ($dailyProtein * 4)  / $dailyKcal;
        $targetCarbsRatio   = ($dailyCarbs   * 4)  / $dailyKcal;
        $targetFatRatio     = ($dailyFat     * 9)  / $dailyKcal;

        $mealPlan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $weekStartDate,
            'generation_type' => 'auto',
            'status'          => 'draft',
        ]);

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $mealTypes  = array_keys(self::SLOT_DISTRIBUTION);
        $now        = now();

        // Bulk-insert days
        $dayRows = [];
        foreach ($daysOfWeek as $day) {
            foreach ($mealTypes as $mealType) {
                $dayRows[] = ['meal_plan_id' => $mealPlan->id, 'day_of_week' => $day, 'meal_type' => $mealType, 'flagged' => false];
            }
        }
        MealPlanDay::insert($dayRows);

        $days = MealPlanDay::where('meal_plan_id', $mealPlan->id)->orderBy('id')->get();

        // Shuffle recipe pool per day for variety
        $itemRows = [];
        foreach ($daysOfWeek as $dayIndex => $dayName) {
            // Different shuffle seed per day
            $dayPool = $recipes->shuffle()->values();

            $daySlots = $days->where('day_of_week', $dayName)->sortBy('id')->values();
            $usedThisDay = [];

            foreach ($daySlots as $slotIndex => $dayRecord) {
                $mealType   = $dayRecord->meal_type;
                $slotPct    = self::SLOT_DISTRIBUTION[$mealType] ?? 0.20;
                $targetKcal = $dailyKcal * $slotPct;

                // Score recipes by macro-ratio distance from prescription targets
                // (ignoring absolute kcal — quantity scaling handles that)
                $best      = null;
                $bestScore = PHP_INT_MAX;
                foreach ($dayPool as $r) {
                    if (in_array($r->id, $usedThisDay)) continue;
                    $rKcal = max((float) $r->total_calories, 1);
                    $rProteinRatio = ((float) $r->total_protein * 4) / $rKcal;
                    $rCarbsRatio   = ((float) $r->total_carbs   * 4) / $rKcal;
                    $rFatRatio     = ((float) $r->total_fat     * 9) / $rKcal;

                    // Euclidean distance in macro-ratio space (lower = better match)
                    $score = sqrt(
                        pow($rProteinRatio - $targetProteinRatio, 2) +
                        pow($rCarbsRatio   - $targetCarbsRatio,   2) +
                        pow($rFatRatio     - $targetFatRatio,     2)
                    );
                    if ($score < $bestScore) { $bestScore = $score; $best = $r; }
                }
                // Fallback: allow repeats if pool exhausted
                if (!$best) $best = $dayPool[$slotIndex % $dayPool->count()];
                $usedThisDay[] = $best->id;

                // Scale quantity to hit slot calorie target (clamped to ±50% of 1 serving)
                $recipeKcal = max((float) $best->total_calories, 1);
                $quantity   = round(min(max($targetKcal / $recipeKcal, 0.5), 2.0), 2);

                $itemRows[] = [
                    'meal_plan_day_id'  => $dayRecord->id,
                    'recipe_id'         => $best->id,
                    'quantity'          => $quantity,
                    'unit'              => 'serving',
                    'nutrient_snapshot' => json_encode([
                        'name'           => $best->name,
                        'calories'       => (float) $best->total_calories,
                        'protein'        => (float) $best->total_protein,
                        'carbs'          => (float) $best->total_carbs,
                        'fat'            => (float) $best->total_fat,
                        'micronutrients' => $best->micronutrients ?? [],
                        'serving_size'   => (float) ($best->servings ?? 1),
                        'serving_unit'   => 'serving',
                    ]),
                    'ai_suggested' => false,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }
        MealPlanItem::insert($itemRows);

        return $mealPlan->load('days');
    }
}
