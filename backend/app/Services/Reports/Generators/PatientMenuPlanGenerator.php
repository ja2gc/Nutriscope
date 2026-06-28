<?php

namespace App\Services\Reports\Generators;

use App\Models\MealPlan;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;

/**
 * Patient Menu Plan — a patient's ADIME meal plan rendered as a Mon→Sun calendar PDF
 * (meals down the side, days across). Reads the persisted meal plan; no recompute.
 */
class PatientMenuPlanGenerator implements ReportGenerator
{
    private const WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private const MEALS = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner'];

    /** Raw meal_plan_days.meal_type → the display label used as the grid key. */
    private const MEAL_TYPE_LABELS = [
        'breakfast' => 'Breakfast',
        'am_snack'  => 'AM Snack',
        'lunch'     => 'Lunch',
        'pm_snack'  => 'PM Snack',
        'dinner'    => 'Dinner',
    ];

    public function type(): string
    {
        return 'patient_menu_plan';
    }

    public function view(): string
    {
        return 'reports.patient-menu-plan';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        // Require an explicit meal_plan_id so the rendered plan is reproducible.
        // patient_id alone would pick "latest" and drift over time.
        if (empty($params['meal_plan_id'])) {
            throw new \InvalidArgumentException('Patient menu plan requires an explicit meal_plan_id.');
        }

        $plan = MealPlan::with(['patient', 'days.items.foodItem', 'days.items.recipe.ingredients.foodItem'])
            ->whereKey($params['meal_plan_id'])
            ->firstOrFail();

        $grid = [];
        foreach (self::MEALS as $meal) {
            foreach (self::WEEK as $day) {
                $grid[$meal][$day] = [];
            }
        }

        foreach ($plan->days as $day) {
            // meal_type is stored raw ('breakfast'); the grid is keyed by display
            // labels ('Breakfast'). Map before lookup or every item is dropped.
            $label = self::MEAL_TYPE_LABELS[$day->meal_type] ?? $day->meal_type;
            foreach ($day->items as $item) {
                // USDA (fdc_id) items have no foodItem/recipe relation — their name
                // lives only in the persisted nutrient snapshot. Fall back to it so
                // USDA foods are not silently dropped from the printed menu. (MP-06)
                $name = $item->foodItem?->name
                    ?? $item->recipe?->name
                    ?? ($item->nutrient_snapshot['name'] ?? null);
                if ($name && isset($grid[$label][$day->day_of_week])) {
                    $grid[$label][$day->day_of_week][] = [
                        'name'     => $name,
                        'quantity' => $item->quantity,
                        'unit'     => $item->unit,
                    ];
                }
            }
        }

        // Collect unique recipes used in the plan with their ingredients and prep notes.
        $recipeDetails = [];
        foreach ($plan->days as $day) {
            foreach ($day->items as $item) {
                if ($item->recipe && ! array_key_exists($item->recipe->id, $recipeDetails)) {
                    $ings = [];
                    foreach ($item->recipe->ingredients as $ing) {
                        $ings[] = [
                            'name'     => $ing->foodItem?->name ?? '—',
                            'quantity' => $ing->quantity,
                            'unit'     => $ing->unit,
                        ];
                    }
                    $recipeDetails[$item->recipe->id] = [
                        'name'        => $item->recipe->name,
                        'servings'    => $item->recipe->servings,
                        'prep_notes'  => $item->recipe->prep_notes,
                        'ingredients' => $ings,
                    ];
                }
            }
        }

        return [
            'plan'           => $plan,
            'patient'        => $plan->patient,
            'meals'          => self::MEALS,
            'days'           => self::WEEK,
            'grid'           => $grid,
            'recipe_details' => array_values($recipeDetails),
        ];
    }
}
