<?php

namespace App\Support\Reports;

use App\Models\Intervention;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class InterventionPlanLabelOrdinals
{
    /**
     * @param  EloquentCollection<int, Intervention>  $plans
     * @return array<int, int>
     */
    public static function sameDayOrdinals(EloquentCollection $plans): array
    {
        $sameDayGroups = $plans
            ->filter(static fn (Intervention $plan): bool => $plan->created_at !== null)
            ->groupBy(static fn (Intervention $plan): string => $plan->ncp_record_id.'|'.$plan->created_at
                ->copy()->timezone(config('nutriscope-reports.timezone'))->toDateString())
            ->filter(static fn (EloquentCollection $sameDayPlans): bool => $sameDayPlans->count() > 1)
            ->all();
        $ordinals = [];

        foreach ($sameDayGroups as $sameDayPlans) {
            $orderedPlans = $sameDayPlans
                ->sort(static fn (Intervention $left, Intervention $right): int => [
                    $left->created_at?->getTimestamp() ?? 0,
                    $left->getKey(),
                ] <=> [
                    $right->created_at?->getTimestamp() ?? 0,
                    $right->getKey(),
                ])
                ->values();

            foreach ($orderedPlans as $index => $plan) {
                $ordinals[(int) $plan->getKey()] = $index + 1;
            }
        }

        return $ordinals;
    }
}
