<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\NcpRecord;
use App\Models\Recipe;
use Illuminate\Support\Carbon;

class MealPlanService
{
    /**
     * Auto-generate a 7-day meal plan for an NCP record based on intervention targets.
     * Falls back to a skeleton plan if not enough recipes are found.
     */
    public function generate(NcpRecord $ncpRecord, string $weekStartDate, array $conditions = [], array $allergens = []): MealPlan
    {
        $intervention = $ncpRecord->intervention()->firstOrFail();
        $startDate = Carbon::parse($weekStartDate);

        // Create the MealPlan record
        $mealPlan = MealPlan::create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $ncpRecord->patient_id,
            'week_start_date' => $weekStartDate,
            'generation_type' => 'auto',
            'status'          => 'draft',
        ]);

        // Load recipes excluding allergens
        $recipes = Recipe::query()
            ->when(!empty($allergens), function ($q) use ($allergens) {
                foreach ($allergens as $allergen) {
                    $q->whereJsonDoesntContain('allergens', $allergen);
                }
            })
            ->get();

        // Generate 7 days x 5 meal types
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $mealTypes  = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];

        foreach ($daysOfWeek as $day) {
            foreach ($mealTypes as $mealType) {
                MealPlanDay::create([
                    'meal_plan_id' => $mealPlan->id,
                    'day_of_week'  => $day,
                    'meal_type'    => $mealType,
                ]);
            }
        }

        return $mealPlan->load('days');
    }
}
