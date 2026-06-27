<?php

namespace App\Services\FSS;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\ShoppingList;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShoppingListPopulationService
{
    public function planRange(Carbon|string $startDate, Carbon|string $endDate): array
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $acc = [];
        $uncovered = [];
        $missingPopulation = [];
        $missingByDate = [];
        $cycleCache = [];

        for ($d = $cursor->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                $uncovered[] = $date;
                $missingByDate[$date] = 'no menu cycle covers this date';
                continue;
            }

            if (! isset($cycleCache[$cycle->id])) {
                $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');
                $cycleCache[$cycle->id] = $cycle;
            }

            $plannedDays = $cycleCache[$cycle->id]->days
                ->where('day_of_week', $d->format('l'))
                ->filter(fn ($day) => $day->recipe !== null || $day->fsItem !== null);

            if ($plannedDays->isEmpty()) {
                $uncovered[] = $date;
                $missingByDate[$date] = 'no menu items planned for this day';
                continue;
            }

            if ($plannedDays->contains(fn ($day) => (int) ($day->estimate_population ?? 0) <= 0)) {
                $missingPopulation[] = $date;
                $missingByDate[$date] = 'estimated population not set for this day';
                continue;
            }

            foreach (MenuCycleCostService::usageForDays($plannedDays) as $usage) {
                $id = $usage['fs_item_id'];
                $acc[$id] ??= [
                    'name' => $usage['name'],
                    'unit' => $usage['unit'],
                    'qty' => 0.0,
                    'total' => 0.0,
                ];
                $acc[$id]['qty'] += (float) $usage['quantity'];
                $acc[$id]['total'] += (float) $usage['cost'];
            }
        }

        return [
            'items' => $this->purchaseRows($acc),
            'uncovered_dates' => array_values(array_unique($uncovered)),
            'missing_population_dates' => array_values(array_unique($missingPopulation)),
            'missing_items_by_date' => $missingByDate,
        ];
    }

    public function cascadePopulation(ShoppingList $list, int $population): void
    {
        if (! $list->period_start || ! $list->period_end) {
            return;
        }

        DB::transaction(function () use ($list, $population) {
            $writtenAt = now();

            $list->update([
                'estimate_population' => $population,
                'estimate_population_updated_at' => $writtenAt,
            ]);

            $cursor = $list->period_start->copy()->startOfDay();
            $end = $list->period_end->copy()->startOfDay();
            $cycleCache = [];

            for ($d = $cursor; $d->lte($end); $d->addDay()) {
                $cycle = MenuCycle::coveringDate($d);
                if (! $cycle) {
                    continue;
                }

                if (! isset($cycleCache[$cycle->id])) {
                    $cycle->loadMissing('days.recipe', 'days.fsItem');
                    $cycleCache[$cycle->id] = $cycle;
                }

                $cycleCache[$cycle->id]->days
                    ->where('day_of_week', $d->format('l'))
                    ->filter(fn ($day) => $day->recipe !== null || $day->fsItem !== null)
                    ->each(fn ($day) => $day->update([
                        'estimate_population' => $population,
                        'estimate_population_updated_at' => $writtenAt,
                    ]));
            }

            $this->syncItems($list->fresh());
        });
    }

    public function recalculateDraftListsForCycle(MenuCycle $cycle): void
    {
        if (! $cycle->week_start_date) {
            return;
        }

        $start = $cycle->week_start_date->copy()->startOfDay();
        $end = $start->copy()->addDays((int) ($cycle->cycle_days ?: 7) - 1);

        ShoppingList::query()
            ->where('status', 'draft')
            ->where('list_type', 'suggested')
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->get()
            ->each(fn (ShoppingList $list) => $this->syncItems($list));
    }

    public function syncItems(ShoppingList $list): void
    {
        if (! $list->period_start || ! $list->period_end) {
            return;
        }

        $plan = $this->planRange($list->period_start, $list->period_end);
        if ($plan['missing_population_dates'] !== []) {
            throw ValidationException::withMessages([
                'estimate_population' => 'Menu plan dates with assigned items require estimate_population before recalculation.',
            ]);
        }

        foreach ($plan['items'] as $row) {
            $item = $list->items()->where('fs_item_id', $row['fs_item_id'])->first();
            $attrs = [
                'ingredient_name' => $row['ingredient_name'],
                'qty' => $row['qty'],
                'unit' => $row['unit'],
                'supplier_id' => $row['supplier_id'],
                'unit_price' => $row['unit_price'],
                'total' => $row['total'],
                'purchase_qty' => $row['purchase_qty'],
                'purchase_unit' => $row['purchase_unit'],
                'purchase_price' => $row['purchase_price'],
            ];

            $item
                ? $item->update($attrs)
                : $list->items()->create(['fs_item_id' => $row['fs_item_id']] + $attrs);
        }
    }

    private function purchaseRows(array $acc): array
    {
        $ids = array_keys($acc);
        $fsItems = FsItem::whereIn('id', $ids)->get()->keyBy('id');

        $rows = [];
        foreach ($acc as $id => $row) {
            // Food shopping list includes ALL required ingredients for the span —
            // on-hand stock is NOT subtracted (inventory is a reference catalog only).
            $net = (float) $row['qty'];
            if ($net <= 0) {
                continue;
            }

            $fs = $fsItems[$id] ?? null;
            $basePerPurchase = $fs ? $fs->basePerPurchase() : 0.0;

            if ($fs && $basePerPurchase > 0) {
                $packs = (int) ceil($net / $basePerPurchase);
                $baseBought = $packs * $basePerPurchase;
                $purchasePrice = (float) $fs->purchase_price;
                $rows[] = [
                    'fs_item_id' => $id,
                    'ingredient_name' => $row['name'],
                    'qty' => round($baseBought, 2),
                    'unit' => $fs->base_unit ?? $row['unit'],
                    'supplier_id' => $fs->default_supplier_id,
                    'unit_price' => round($purchasePrice / $basePerPurchase, 4),
                    'total' => round($packs * $purchasePrice, 2),
                    'purchase_qty' => $packs,
                    'purchase_unit' => $fs->purchase_unit,
                    'purchase_price' => round($purchasePrice, 2),
                ];
                continue;
            }

            $unitPrice = $row['qty'] > 0 ? round($row['total'] / $row['qty'], 4) : 0;
            $rows[] = [
                'fs_item_id' => $id,
                'ingredient_name' => $row['name'],
                'qty' => round($net, 2),
                'unit' => $row['unit'],
                'supplier_id' => $fs?->default_supplier_id,
                'unit_price' => $unitPrice,
                'total' => round($net * $unitPrice, 2),
                'purchase_qty' => null,
                'purchase_unit' => null,
                'purchase_price' => null,
            ];
        }

        return $rows;
    }
}
