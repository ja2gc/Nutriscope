<?php

namespace App\Services\Reports\Generators;

use App\Models\MealPlan;
use App\Models\MealPlanItem;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use App\Support\ReportPaper;
use App\Support\UnitConverter;

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

        // Require an explicit meal_plan_id so the rendered plan is reproducible.
        // patient_id alone would pick "latest" and drift over time.
        if (empty($params['meal_plan_id'])) {
            throw new \InvalidArgumentException('Patient menu plan requires an explicit meal_plan_id.');
        }

        $identifier = $params['meal_plan_id'];
        $plan = MealPlan::with([
            'patient', 'revision', 'intervention.revisions', 'intervention.ncpRecord.assessment',
            'days.items.foodItem', 'days.items.recipe.ingredients.foodItem',
        ])
            ->when(
                is_int($identifier) || ctype_digit((string) $identifier),
                fn ($query) => $query->whereKey((int) $identifier),
                fn ($query) => $query->where('uuid', (string) $identifier),
            )
            ->firstOrFail();

        $grid = [];
        foreach (self::MEALS as $meal) {
            foreach (self::WEEK as $day) {
                $grid[$meal][$day] = [];
            }
        }

        $portionDetails = [];
        $portionIds = [];
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
                    $portion = $this->portionFor($item, $name);
                    $portionKey = hash('sha256', json_encode($portion, JSON_THROW_ON_ERROR));
                    if (! isset($portionIds[$portionKey])) {
                        $portionIds[$portionKey] = 'P'.(count($portionDetails) + 1);
                        $portionDetails[] = [
                            'id' => $portionIds[$portionKey],
                            ...$portion,
                        ];
                    }
                    $grid[$label][$day->day_of_week][] = [
                        'name' => $name,
                        'portion_id' => $portionIds[$portionKey],
                    ];
                }
            }
        }

        $revision = $plan->revision
            ?? $plan->intervention->revisions->firstWhere('source', 'legacy_baseline')
            ?? $plan->intervention->revisions->firstWhere('version', 1);
        $snapshot = $revision?->snapshot ?? $this->currentSnapshot($plan);
        $maternalStatus = $plan->intervention->ncpRecord?->assessment?->pregnancy_lactation_status;
        $meals = array_values(array_filter(
            self::MEALS,
            fn (string $meal): bool => ! in_array($meal, ['AM Snack', 'PM Snack'], true)
                || collect($grid[$meal])->contains(fn (array $items): bool => $items !== []),
        ));

        return [
            'plan' => $plan,
            'patient' => $plan->patient,
            'meals' => $meals,
            'days' => self::WEEK,
            'grid' => $grid,
            'portion_details' => $portionDetails,
            'portion_pages' => $this->portionPages($portionDetails),
            'revision' => $revision ? [
                'id' => $revision->uuid,
                'version' => $revision->version,
                'effective_at' => $revision->effective_at,
            ] : null,
            'prescription' => [
                'energy_kcal' => (float) ($snapshot['energy_kcal'] ?? 0),
                'protein_g' => (float) ($snapshot['protein_g'] ?? 0),
                'carbs_g' => (float) ($snapshot['carbs_g'] ?? 0),
                'fat_g' => (float) ($snapshot['fat_g'] ?? 0),
                'fluid_ml' => (float) ($snapshot['fluid_ml'] ?? 0),
                'micronutrient_limits' => $snapshot['micronutrient_limits'] ?? [],
            ],
            'patient_guidance' => [
                'education' => $snapshot['education_notes'] ?? null,
                'counseling' => $snapshot['counseling_goals'] ?? null,
                'strategies' => $snapshot['strategies'] ?? null,
            ],
            'maternal_note' => $this->maternalNote($maternalStatus),
        ];
    }

    /** @return array{dish:string,foods:array<int,array{food:string,household_measure:?string,metric_amount:float,metric_unit:string}>} */
    private function portionFor(MealPlanItem $item, string $name): array
    {
        if ($item->recipe) {
            $foods = [];
            $scale = $this->recipeIngredientScale($item);
            foreach ($item->recipe->ingredients as $ingredient) {
                $amount = (float) $ingredient->quantity * $scale;
                $metric = $this->metricAmount(
                    $amount,
                    (string) $ingredient->unit,
                    (float) ($ingredient->foodItem?->serving_size ?? 0),
                    (string) ($ingredient->foodItem?->serving_unit ?? ''),
                );
                if ($metric !== null) {
                    $foods[] = [
                        'food' => $ingredient->foodItem?->name ?? 'Ingredient',
                        'household_measure' => $this->householdMeasure($amount, (string) $ingredient->unit),
                        ...$metric,
                    ];
                }
            }

            if ($foods !== []) {
                return ['dish' => $name, 'foods' => $foods];
            }
        }

        $snapshot = $item->nutrient_snapshot ?? [];
        $metric = $this->metricAmount(
            (float) $item->quantity,
            (string) $item->unit,
            (float) ($snapshot['serving_size'] ?? $item->foodItem?->serving_size ?? 0),
            (string) ($snapshot['serving_unit'] ?? $item->foodItem?->serving_unit ?? ''),
        ) ?? ['metric_amount' => round((float) $item->quantity, 1), 'metric_unit' => 'g'];

        return [
            'dish' => $name,
            'foods' => [[
                'food' => $item->foodItem?->name ?? $name,
                'household_measure' => $this->householdMeasure((float) $item->quantity, (string) $item->unit),
                ...$metric,
            ]],
        ];
    }

    private function recipeIngredientScale(MealPlanItem $item): float
    {
        $snapshot = $item->nutrient_snapshot ?? [];
        $referenceAmount = (float) ($snapshot['serving_size'] ?? 0);
        $itemUnit = UnitConverter::normalize((string) $item->unit);
        $referenceUnit = UnitConverter::normalize((string) ($snapshot['serving_unit'] ?? ''));
        $compatibleUnits = $itemUnit === $referenceUnit
            || (UnitConverter::isMass($itemUnit) && UnitConverter::isMass($referenceUnit))
            || (UnitConverter::isVolume($itemUnit) && UnitConverter::isVolume($referenceUnit));

        if ($referenceAmount > 0 && $compatibleUnits) {
            return (float) $item->quantity / $referenceAmount;
        }

        return (float) $item->quantity / max((float) ($item->recipe->servings ?? 1), 1);
    }

    /** @return array{metric_amount:float,metric_unit:string}|null */
    private function metricAmount(float $amount, string $unit, float $fallbackSize, string $fallbackUnit): ?array
    {
        if (UnitConverter::isMass($unit)) {
            return ['metric_amount' => round(UnitConverter::convert($amount, $unit, 'g'), 1), 'metric_unit' => 'g'];
        }
        if (UnitConverter::isVolume($unit)) {
            return ['metric_amount' => round(UnitConverter::convert($amount, $unit, 'ml'), 1), 'metric_unit' => 'mL'];
        }
        if ($fallbackSize > 0 && UnitConverter::isMass($fallbackUnit)) {
            return ['metric_amount' => round(UnitConverter::convert($amount * $fallbackSize, $fallbackUnit, 'g'), 1), 'metric_unit' => 'g'];
        }
        if ($fallbackSize > 0 && UnitConverter::isVolume($fallbackUnit)) {
            return ['metric_amount' => round(UnitConverter::convert($amount * $fallbackSize, $fallbackUnit, 'ml'), 1), 'metric_unit' => 'mL'];
        }

        return null;
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
    private function currentSnapshot(MealPlan $plan): array
    {
        return collect([
            'energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml',
            'micronutrient_limits', 'education_notes', 'counseling_goals', 'strategies',
        ])->mapWithKeys(fn (string $field): array => [$field => $plan->intervention->getAttribute($field)])->all();
    }

    /** @param array<int,array<string,mixed>> $portionDetails */
    private function portionPages(array $portionDetails): array
    {
        if (count($portionDetails) <= 21) {
            return [$portionDetails];
        }

        return [
            array_slice($portionDetails, 0, 18),
            ...array_chunk(array_slice($portionDetails, 18), 21),
        ];
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
