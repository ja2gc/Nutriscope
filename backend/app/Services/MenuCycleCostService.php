<?php

namespace App\Services;

use App\Models\MenuCycle;
use App\Support\RecipeScaler;
use App\Support\UnitConverter;

/**
 * Costing engine for menu cycles. The single source of truth for:
 *   - the planner's live cost / cost-per-head summary,
 *   - the procurement suggested shopping list (ingredient_usage),
 *   - the budget's "planned" figure.
 *
 * `aggregate()` is a pure function over plain arrays (no DB) so it is exhaustively
 * unit-tested; `forCycle()` is the thin DB loader that feeds it.
 */
class MenuCycleCostService
{
    /**
     * @param array<int,array{day_of_week?:string,meal_type?:string,servings_override?:int|null,recipe:array{servings?:int,ingredients?:array<int,array{fs_item_id:int,name:string,quantity:float,unit?:string,base_unit?:string,unit_cost:float}>}}> $entries
     */
    public static function aggregate(array $entries, int $population): array
    {
        $days  = [];
        $usage = [];
        $total = 0.0;

        foreach ($entries as $entry) {
            $target    = $entry['servings_override'] ?? $population;
            $entryCost = 0.0;

            if ($recipe = $entry['recipe'] ?? null) {
                $factor = RecipeScaler::factor((int) ($recipe['servings'] ?? 1), (int) $target);

                foreach ($recipe['ingredients'] ?? [] as $ing) {
                    $qty  = (float) $ing['quantity'];
                    $from = (string) ($ing['unit'] ?? '');
                    $base = (string) ($ing['base_unit'] ?? '');

                    if ($from !== '' && $base !== ''
                        && UnitConverter::normalize($from) !== UnitConverter::normalize($base)
                        && UnitConverter::isKnown($from) && UnitConverter::isKnown($base)) {
                        $qty = UnitConverter::convert($qty, $from, $base);
                    }

                    $scaledQty = $qty * $factor;
                    $lineCost  = $scaledQty * (float) $ing['unit_cost'];
                    $entryCost += $lineCost;

                    $id = (int) $ing['fs_item_id'];
                    if (! isset($usage[$id])) {
                        $usage[$id] = [
                            'fs_item_id' => $id,
                            'name'       => $ing['name'],
                            'unit'       => $base !== '' ? $base : $from,
                            'quantity'   => 0.0,
                            'cost'       => 0.0,
                        ];
                    }
                    $usage[$id]['quantity'] += $scaledQty;
                    $usage[$id]['cost']     += $lineCost;
                }
            } elseif ($item = $entry['item'] ?? null) {
                // Ready-to-serve catalog item (e.g. a Yakult / banana snack): one
                // serving per head (× per-head quantity), no recipe scaling.
                $perHead = (float) ($item['quantity'] ?? 1);
                $qty     = $perHead * (int) $target;
                $lineCost = $qty * (float) $item['unit_cost'];
                $entryCost += $lineCost;

                $id = (int) $item['fs_item_id'];
                if (! isset($usage[$id])) {
                    $usage[$id] = [
                        'fs_item_id' => $id,
                        'name'       => $item['name'],
                        'unit'       => (string) ($item['unit'] ?? ''),
                        'quantity'   => 0.0,
                        'cost'       => 0.0,
                    ];
                }
                $usage[$id]['quantity'] += $qty;
                $usage[$id]['cost']     += $lineCost;
            } else {
                continue;
            }

            $day = $entry['day_of_week'] ?? 'Unknown';
            $days[$day] ??= ['cost' => 0.0];
            $days[$day]['cost'] += $entryCost;
            $total += $entryCost;
        }

        foreach ($days as &$d) {
            $d['cost']          = round($d['cost'], 2);
            $d['cost_per_head'] = $population > 0 ? round($d['cost'] / $population, 2) : 0.0;
        }
        unset($d);

        foreach ($usage as &$u) {
            $u['quantity'] = round($u['quantity'], 2);
            $u['cost']     = round($u['cost'], 2);
        }
        unset($u);

        return [
            'population'       => $population,
            'total_cost'      => round($total, 2),
            'cost_per_head'   => $population > 0 ? round($total / $population, 2) : 0.0,
            'days'            => $days,
            'ingredient_usage' => array_values($usage),
        ];
    }

    /**
     * Load a persisted menu cycle into the shape aggregate() expects, then cost it.
     * Population defaults to the cycle's own; pass an override for "what-if" / spans.
     */
    public static function forCycle(MenuCycle $cycle, ?int $population = null): array
    {
        $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');

        return self::aggregate(self::entriesForDays($cycle->days), $population ?? (int) $cycle->population);
    }

    /**
     * Map a collection of MenuCycleDay models into the plain-array entries that
     * aggregate() consumes. Shared by the planner (forCycle), consumption
     * (ConsumptionService), and procurement so their math can never diverge.
     * Requires days.recipe.ingredients.fsItem and days.fsItem to be loaded.
     *
     * @param  \Illuminate\Support\Collection $days
     */
    public static function entriesForDays($days): array
    {
        return $days
            ->filter(fn ($day) => $day->recipe !== null || $day->fsItem !== null)
            ->map(function ($day) {
                $base = [
                    'day_of_week'       => $day->day_of_week,
                    'meal_type'         => $day->meal_type,
                    'servings_override' => $day->servings_override,
                ];

                if ($day->recipe !== null) {
                    return $base + [
                        'recipe' => [
                            'servings'    => (int) $day->recipe->servings,
                            'ingredients' => $day->recipe->ingredients
                                ->filter(fn ($ing) => $ing->fsItem !== null)
                                ->map(fn ($ing) => [
                                    'fs_item_id' => $ing->fs_item_id,
                                    'name'       => $ing->fsItem->name,
                                    'quantity'   => (float) $ing->quantity,
                                    'unit'       => $ing->unit,
                                    'base_unit'  => $ing->fsItem->base_unit,
                                    'unit_cost'  => $ing->fsItem->unit_cost,
                                ])->values()->all(),
                        ],
                    ];
                }

                return $base + [
                    'item' => [
                        'fs_item_id' => $day->fs_item_id,
                        'name'       => $day->fsItem->name,
                        'unit'       => $day->fsItem->base_unit,
                        'unit_cost'  => $day->fsItem->unit_cost,
                        'quantity'   => (float) ($day->quantity ?: 1),
                    ],
                ];
            })->values()->all();
    }

    /** Required base-unit ingredient usage for a subset of days at a target headcount. */
    public static function usageForDays($days, int $target): array
    {
        return self::aggregate(self::entriesForDays($days), $target)['ingredient_usage'];
    }
}
