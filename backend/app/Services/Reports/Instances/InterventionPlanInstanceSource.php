<?php

namespace App\Services\Reports\Instances;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Services\Reports\Contracts\InstanceSource;
use Illuminate\Database\Eloquent\Builder;

class InterventionPlanInstanceSource implements InstanceSource
{
    public function axis(): string
    {
        return 'entity';
    }

    public function instances(array $filters): array
    {
        return $this->query()
            ->when(! empty($filters['year']), fn (Builder $query) => $query->whereYear('created_at', (int) $filters['year']))
            ->when(! empty($filters['month']), fn (Builder $query) => $query->whereMonth('created_at', (int) $filters['month']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Intervention $plan): array {
                $mealPlan = $plan->mealPlan;
                $date = $plan->created_at?->toDateString();
                $displayDate = $plan->created_at?->format('M j, Y') ?? 'Date unavailable';

                return [
                    'key' => $plan->uuid,
                    'label' => trim(($plan->ncpRecord?->patient?->display_name ?? 'Patient')
                        .' — Nutrition Intervention Plan — '.$displayDate),
                    'params' => ['intervention_plan_id' => $plan->uuid],
                    'date' => $date,
                    'intervention_plan_id' => $plan->uuid,
                    'intervention_plan_date' => $date,
                    'meal_plan_id' => $mealPlan?->uuid,
                    'available' => $mealPlan !== null,
                    'unavailable_reason' => $mealPlan === null
                        ? 'Save a menu plan before preparing this report.'
                        : null,
                ];
            })
            ->all();
    }

    public function hasData(array $params): bool
    {
        if ($identifier = $params['intervention_plan_id'] ?? null) {
            return $this->query()
                ->when(
                    is_int($identifier) || ctype_digit((string) $identifier),
                    fn (Builder $query) => $query->whereKey((int) $identifier),
                    fn (Builder $query) => $query->where('uuid', (string) $identifier),
                )
                ->exists();
        }

        $mealPlanIdentifier = $params['meal_plan_id'] ?? null;

        return $mealPlanIdentifier !== null && MealPlan::query()
            ->when(
                is_int($mealPlanIdentifier) || ctype_digit((string) $mealPlanIdentifier),
                fn (Builder $query) => $query->whereKey((int) $mealPlanIdentifier),
                fn (Builder $query) => $query->where('uuid', (string) $mealPlanIdentifier),
            )
            ->exists();
    }

    private function query(): Builder
    {
        return Intervention::query()->with(['ncpRecord.patient', 'mealPlan']);
    }
}
