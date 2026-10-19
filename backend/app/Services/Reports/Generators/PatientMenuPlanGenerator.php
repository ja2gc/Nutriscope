<?php

namespace App\Services\Reports\Generators;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use App\Support\ReportPaper;
use App\Support\UnitConverter;
use Illuminate\Validation\ValidationException;

/**
 * Nutrition Intervention Plan — internal patient_menu_plan generator for a Mon→Sun ADIME menu PDF.
 * (meals down the side, days across). Reads the persisted meal plan; no recompute.
 */
class PatientMenuPlanGenerator implements ReportGenerator
{
    private const WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const MEALS = ['Breakfast', 'AM Snack', 'Lunch', 'PM Snack', 'Dinner'];

    /** Raw meal_plan_days.meal_type → the display label used as the grid key. */
    private const MEAL_TYPE_LABELS = [
        'breakfast' => 'Breakfast',
        'am_snack' => 'AM Snack',
        'lunch' => 'Lunch',
        'pm_snack' => 'PM Snack',
        'dinner' => 'Dinner',
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
        return [ReportPaper::LONG_BOND, 'landscape'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        [$interventionPlan, $mealPlan] = $this->resolvePlan($params);

        $grid = [];
        foreach (self::MEALS as $meal) {
            foreach (self::WEEK as $day) {
                $grid[$meal][$day] = [];
            }
        }

        $portionDetails = [];
        $portionIds = [];
        $portionGroupIndexes = [];
        $fluidByDay = array_fill_keys(self::WEEK, 0.0);
        foreach ($mealPlan->days as $day) {
            // meal_type is stored raw ('breakfast'); the grid is keyed by display
            // labels ('Breakfast'). Map before lookup or every item is dropped.
            $label = self::MEAL_TYPE_LABELS[$day->meal_type] ?? $day->meal_type;
            foreach ($day->items as $item) {
                $itemSnapshot = $item->nutrient_snapshot ?? [];
                $servingSize = (float) ($itemSnapshot['serving_size'] ?? 0);
                if ($servingSize > 0 && isset($fluidByDay[$day->day_of_week])) {
                    $fluidByDay[$day->day_of_week] += (float) ($itemSnapshot['water_g'] ?? 0)
                        * ((float) $item->quantity / $servingSize);
                }
                // USDA (fdc_id) items have no foodItem/recipe relation — their name
                // lives only in the persisted nutrient snapshot. Fall back to it so
                // USDA foods are not silently dropped from the printed menu. (MP-06)
                $name = ($itemSnapshot['name'] ?? null)
                    ?? $item->foodItem?->name
                    ?? $item->recipe?->name
                    ?? ($item->nutrient_snapshot['name'] ?? null);
                if ($name && isset($grid[$label][$day->day_of_week])) {
                    $portion = $this->portionFor($item, $name);
                    $sourceKey = $item->recipe_id ? 'recipe:'.$item->recipe_id
                        : ($item->food_item_id ? 'food:'.$item->food_item_id : ($item->fdc_id ? 'usda:'.$item->fdc_id : 'item:'.$item->id));
                    $portionKey = hash('sha256', json_encode([$sourceKey, $portion], JSON_THROW_ON_ERROR));
                    if (! isset($portionIds[$portionKey])) {
                        $portionIds[$portionKey] = 'P'.(count($portionIds) + 1);
                        $dishKey = hash('sha256', json_encode([
                            $sourceKey, $portion['dish'], $portion['recipe_yield'] ?? null,
                            $itemSnapshot['recipe_original_ingredients'] ?? null,
                            array_map(fn (array $food): array => array_intersect_key($food, array_flip(['food', 'recipe_quantity', 'recipe_unit'])), $portion['foods']),
                        ], JSON_THROW_ON_ERROR));
                        if (! isset($portionGroupIndexes[$dishKey])) {
                            $portionGroupIndexes[$dishKey] = count($portionDetails);
                            $portionDetails[] = [
                                'id' => $portionIds[$portionKey],
                                'dish' => $portion['dish'],
                                'foods' => $portion['foods'],
                                ...array_intersect_key($portion, array_flip(['recipe_yield'])),
                                'variants' => [],
                            ];
                        }
                        $portionDetails[$portionGroupIndexes[$dishKey]]['variants'][] = [
                            'id' => $portionIds[$portionKey],
                            'foods' => $portion['foods'],
                            ...array_intersect_key($portion, array_flip(['recipe_yield', 'patient_servings'])),
                        ];
                    }
                    $grid[$label][$day->day_of_week][] = [
                        'name' => $name,
                        'portion_id' => $portionIds[$portionKey],
                    ];
                }
            }
        }

        $snapshot = $this->currentSnapshot($interventionPlan);
        $dailyFoodFluid = round(array_sum($fluidByDay) / count(self::WEEK));
        $fluidTarget = (float) ($snapshot['fluid_ml'] ?? 0);
        $maternalStatus = $interventionPlan->sourceMonitoring?->pregnancy_lactation_status
            ?? $interventionPlan->ncpRecord?->assessment?->pregnancy_lactation_status;
        $meals = array_values(array_filter(
            self::MEALS,
            fn (string $meal): bool => ! in_array($meal, ['AM Snack', 'PM Snack'], true)
                || collect($grid[$meal])->contains(fn (array $items): bool => $items !== []),
        ));

        return [
            'intervention_plan' => $interventionPlan,
            'meal_plan' => $mealPlan,
            'patient' => $interventionPlan->ncpRecord?->patient,
            'meals' => $meals,
            'days' => self::WEEK,
            'grid' => $grid,
            'portion_details' => $portionDetails,
            'portion_pages' => $this->portionPages($portionDetails),
            'prescription' => [
                'energy_kcal' => (float) ($snapshot['energy_kcal'] ?? 0),
                'protein_g' => (float) ($snapshot['protein_g'] ?? 0),
                'carbs_g' => (float) ($snapshot['carbs_g'] ?? 0),
                'fat_g' => (float) ($snapshot['fat_g'] ?? 0),
                'fluid_ml' => (float) ($snapshot['fluid_ml'] ?? 0),
                'micronutrient_limits' => $snapshot['micronutrient_limits'] ?? [],
                'nutrient_operators' => $snapshot['nutrient_operators'] ?? [],
                'weight_target' => $snapshot['weight_target'] ?? [],
            ],
            'fluid_balance' => [
                'required_fluid_ml' => $fluidTarget,
                'food_fluid_ml' => $dailyFoodFluid,
                'remaining_ml' => max(0, round($fluidTarget - $dailyFoodFluid)),
            ],
            'patient_guidance' => [
                'education' => $snapshot['education_notes'] ?? null,
                'counseling' => $snapshot['counseling_goals'] ?? null,
                'barriers' => $snapshot['barriers'] ?? null,
                'strategies' => $snapshot['strategies'] ?? null,
            ],
            'maternal_note' => $this->maternalNote($maternalStatus),
        ];
    }

    /** @return array{dish:string,foods:array<int,array{food:string,household_measure:?string,metric_amount:float,metric_unit:string}>} */
    private function portionFor(MealPlanItem $item, string $name): array
    {
        $snapshot = $item->nutrient_snapshot ?? [];
        if ($item->recipe_id !== null || ! empty($snapshot['recipe_ingredients'])) {
            $yield = (float) ($snapshot['recipe_yield'] ?? 0);
            if ($item->unit !== 'serving' || $yield <= 0 || empty($snapshot['recipe_original_ingredients'])
                || empty($snapshot['recipe_ingredients']) || ($snapshot['recipe_snapshot_source'] ?? '') === 'current_recipe_fallback') {
                throw ValidationException::withMessages([
                    'meal_plan' => 'Repair this menu\'s saved recipe details before preparing a new report.',
                ]);
            }
            $foods = [];
            foreach ($snapshot['recipe_ingredients'] as $index => $ingredient) {
                $original = $snapshot['recipe_original_ingredients'][$index] ?? null;
                $foods[] = [
                    'food' => $ingredient['name'], 'household_measure' => null,
                    'whole_quantity' => $original === null ? null : (float) $original['quantity'],
                    'whole_unit' => $original['unit'] ?? $ingredient['unit'],
                    'recipe_quantity' => (float) $ingredient['quantity'], 'recipe_unit' => $ingredient['unit'],
                    'metric_amount' => (float) $ingredient['quantity'] * (float) $item->quantity / $yield,
                    'metric_unit' => $ingredient['unit'],
                ];
            }

            return [
                'dish' => $name, 'foods' => $foods, 'recipe_yield' => $yield,
                'patient_servings' => (float) $item->quantity,
            ];
        }

        return [
            'dish' => $name,
            'foods' => [[
                'food' => $snapshot['name'] ?? $item->foodItem?->name ?? $name,
                'household_measure' => $this->householdMeasure((float) $item->quantity, (string) $item->unit),
                ...$this->metricAmount((float) $item->quantity, (string) $item->unit),
            ]],
        ];
    }

    /** @return array{metric_amount:float,metric_unit:string} */
    private function metricAmount(float $amount, string $unit): array
    {
        if (UnitConverter::isMass($unit)) {
            return ['metric_amount' => UnitConverter::convert($amount, $unit, 'g'), 'metric_unit' => 'g'];
        }
        if (UnitConverter::isVolume($unit)) {
            return ['metric_amount' => UnitConverter::convert($amount, $unit, 'ml'), 'metric_unit' => 'mL'];
        }

        return ['metric_amount' => $amount, 'metric_unit' => $unit];
    }

    private function householdMeasure(float $amount, string $unit): ?string
    {
        $normalized = UnitConverter::normalize($unit);
        if (! in_array($normalized, ['cup', 'tbsp', 'tablespoon', 'tsp', 'teaspoon'], true)) {
            return null;
        }

        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.').' '.$unit;
    }

    /** @return array<string,mixed> */
    private function currentSnapshot(Intervention $plan): array
    {
        return collect([
            'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
            'micronutrient_limits', 'nutrient_operators', 'weight_target', 'education_notes', 'counseling_goals', 'strategies',
            'barriers',
        ])->mapWithKeys(fn (string $field): array => [$field => $plan->getAttribute($field)])->all();
    }

    /** @param array<string,mixed> $params @return array{Intervention,MealPlan} */
    private function resolvePlan(array $params): array
    {
        $relations = [
            'ncpRecord.patient', 'ncpRecord.assessment', 'sourceMonitoring',
            'mealPlan.days.items.foodItem', 'mealPlan.days.items.recipe.ingredients.foodItem',
        ];
        $planIdentifier = $params['intervention_plan_id'] ?? null;

        if ($planIdentifier !== null) {
            $plan = Intervention::with($relations)
                ->when(
                    is_int($planIdentifier) || ctype_digit((string) $planIdentifier),
                    fn ($query) => $query->whereKey((int) $planIdentifier),
                    fn ($query) => $query->where('uuid', (string) $planIdentifier),
                )
                ->firstOrFail();
            if ($plan->mealPlan === null) {
                throw new \InvalidArgumentException('Save a menu plan before preparing this report.');
            }

            return [$plan, $plan->mealPlan];
        }

        $mealPlanIdentifier = $params['meal_plan_id'] ?? null;
        if ($mealPlanIdentifier === null) {
            throw new \InvalidArgumentException('Nutrition Intervention Plan requires an intervention_plan_id.');
        }

        $mealPlan = MealPlan::with([
            'intervention.ncpRecord.patient', 'intervention.ncpRecord.assessment',
            'intervention.sourceMonitoring', 'days.items.foodItem',
            'days.items.recipe.ingredients.foodItem',
        ])->when(
            is_int($mealPlanIdentifier) || ctype_digit((string) $mealPlanIdentifier),
            fn ($query) => $query->whereKey((int) $mealPlanIdentifier),
            fn ($query) => $query->where('uuid', (string) $mealPlanIdentifier),
        )->firstOrFail();

        return [$mealPlan->intervention, $mealPlan];
    }

    /** @param array<int,array<string,mixed>> $portionDetails */
    private function portionPages(array $portionDetails): array
    {
        return [$portionDetails];
    }

    private function maternalNote(?string $status): ?string
    {
        return match ($status) {
            'pregnant_t1' => 'Final targets include the confirmed first trimester maternal adjustment.',
            'pregnant_t2' => 'Final targets include the confirmed second trimester maternal adjustment.',
            'pregnant_t3' => 'Final targets include the confirmed third trimester maternal adjustment.',
            'lactating' => 'Final targets include the confirmed lactation adjustment.',
            default => null,
        };
    }
}
