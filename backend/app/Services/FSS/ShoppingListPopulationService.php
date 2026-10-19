<?php

namespace App\Services\FSS;

use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\ShoppingList;
use App\Models\Supplier;
use App\Services\MenuCycleCostService;
use App\Support\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShoppingListPopulationService
{
    public function planRange(Carbon|string $startDate, Carbon|string $endDate, ?int $population = null): array
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $acc = [];
        $uncovered = [];
        $missingByDate = [];
        $cycleCache = [];
        $headDays = 0;
        $missingPopulation = [];

        for ($d = $cursor->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                $uncovered[] = $date;
                $missingByDate[$date] = 'no menu planned for this date';

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

            if ($population === null && $plannedDays->contains(fn ($day) => $day->estimate_population === null)) {
                $missingPopulation[] = $date;

                continue;
            }

            $entries = MenuCycleCostService::entriesForDays($plannedDays);
            if ($population !== null) {
                $entries = array_map(fn (array $entry): array => array_merge($entry, [
                    'estimate_population' => $population,
                    'servings_override' => null,
                ]), $entries);
            }
            $headDays += $population ?? (int) $plannedDays->first()->estimate_population;

            foreach (MenuCycleCostService::aggregate($entries)['ingredient_usage'] as $usage) {
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
            'items' => $this->purchaseRows($acc, $headDays),
            'uncovered_dates' => array_values(array_unique($uncovered)),
            'missing_population_dates' => $missingPopulation,
            'missing_items_by_date' => $missingByDate,
        ];
    }

    public function cascadePopulation(ShoppingList $list, int $population): void
    {
        if (! $list->period_start || ! $list->period_end || $list->isSupplies()) {
            return;
        }

        DB::transaction(function () use ($list, $population) {
            $previous = (int) $list->estimate_population;
            if ($previous > 0 && $previous !== $population) {
                $ratio = $population / $previous;
                foreach ($list->items()->where('source', 'generated')->get() as $item) {
                    $purchaseQty = round((float) ($item->purchase_qty ?? $item->qty) * $ratio, 6);
                    if ($item->quantity_mode === 'rounded' && UnitConverter::isCompatible((string) $item->unit, (string) ($item->purchase_unit ?? $item->unit))) {
                        $purchaseQty = round(UnitConverter::convert((float) ($item->scaled_quantity ?? $item->qty) * $ratio, (string) $item->unit, (string) ($item->purchase_unit ?? $item->unit)), 6);
                    }
                    $item->update([
                        'qty' => round((float) $item->qty * $ratio, 6),
                        'scaled_quantity' => round((float) ($item->scaled_quantity ?? $item->qty) * $ratio, 6),
                        'purchase_qty' => $purchaseQty,
                        'total' => round($purchaseQty * (float) ($item->purchase_price ?? $item->unit_price), 2),
                    ]);
                }
            }
            $list->update([
                'estimate_population' => $population,
                'estimate_population_updated_at' => now(),
            ]);

            $this->cascadeMenuDays($list->period_start, $list->period_end, $population);

            if ($previous < 1) {
                $this->syncItems($list->fresh());
            }
        });
    }

    public function cascadeMenuDays(Carbon|string $startDate, Carbon|string $endDate, int $population): void
    {
        $cursor = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        for ($d = $cursor->copy(); $d->lte($end); $d->addDay()) {
            $cycle = MenuCycle::coveringDate($d);
            if (! $cycle) {
                continue;
            }

            MenuCycleDay::query()
                ->where('menu_cycle_id', $cycle->id)
                ->where('day_of_week', $d->format('l'))
                ->where('po_snapshot_locked', false)
                ->where(fn ($query) => $query->whereNotNull('recipe_id')->orWhereNotNull('fs_item_id'))
                ->update([
                    'estimate_population' => $population,
                    'estimate_population_updated_at' => now(),
                    'servings_override' => null,
                ]);
        }
    }

    public function recalculateDraftListsForCycle(MenuCycle $cycle): void
    {
        if (! $cycle->week_start_date) {
            return;
        }

        $start = $cycle->week_start_date->copy()->startOfDay();
        $end = $start->copy()->addDays(6);

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

        $plan = $this->planRange($list->period_start, $list->period_end, $list->estimate_population ?: null);
        $missing = array_values(array_unique([...$plan['uncovered_dates'], ...$plan['missing_population_dates']]));
        $list->update(['coverage_status' => $missing === [] ? 'full' : 'partial', 'uncovered_dates' => $missing]);
        if ($missing !== []) {
            return;
        }
        $seen = [];

        foreach ($plan['items'] as $row) {
            $seen[] = $row['fs_item_id'];
            $item = $list->items()
                ->where('source', 'generated')
                ->where('fs_item_id', $row['fs_item_id'])
                ->first();
            $attrs = [
                'ingredient_name' => $row['ingredient_name'],
                'qty' => $row['qty'],
                'unit' => $row['unit'],
                'supplier_id' => $item?->vendorLocked() ? $item->supplier_id : $row['supplier_id'],
                'unit_price' => $row['unit_price'],
                'total' => $row['total'],
                'purchase_qty' => $row['purchase_qty'],
                'purchase_unit' => $row['purchase_unit'],
                'purchase_price' => $row['purchase_price'],
                'baseline_servings' => $row['baseline_servings'],
                'baseline_quantity' => $row['baseline_quantity'],
                'scaled_quantity' => $row['scaled_quantity'],
                'scaled_unit' => $row['scaled_unit'],
            ];

            if ($item) {
                $purchaseUnit = (string) ($item->purchase_unit ?: $item->unit);
                if (UnitConverter::isCompatible((string) $row['unit'], $purchaseUnit)) {
                    $purchaseQty = UnitConverter::convert((float) $row['qty'], (string) $row['unit'], $purchaseUnit);
                    $mode = $item->quantity_mode ?: 'calculated';
                    $attrs['purchase_qty'] = $mode === 'manual'
                        ? $item->purchase_qty
                        : round($purchaseQty, 6);
                    $attrs['purchase_unit'] = $item->purchase_unit;
                    $attrs['purchase_price'] = $item->purchase_price;
                    $attrs['total'] = round((float) $attrs['purchase_qty'] * (float) ($item->purchase_price ?? $item->unit_price), 2);
                }
                $attrs['supplier_id'] = $item->supplier_id;
            }

            $item
                ? $item->update($attrs)
                : $list->items()->create(['fs_item_id' => $row['fs_item_id'], 'source' => 'generated', 'generation_baseline' => $this->generationBaseline($row, (int) $list->estimate_population)] + $attrs);
        }

        $list->items()
            ->where('source', 'generated')
            ->whereNotNull('fs_item_id')
            ->whereNotIn('fs_item_id', $seen)
            ->delete();
    }

    public function generationBaseline(array $row, int $population): array
    {
        return array_intersect_key($row, array_flip([
            'qty', 'unit', 'unit_price', 'purchase_qty', 'purchase_unit', 'purchase_price', 'scaled_quantity', 'scaled_unit',
        ])) + [
            'estimate_population' => $population,
            'supplier_id' => isset($row['supplier_id']) ? Supplier::find($row['supplier_id'])?->uuid : null,
        ];
    }

    private function purchaseRows(array $acc, int $targetPopulation): array
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
            if ($fs && ! $fs->include_in_generated_lists) {
                continue;
            }
            $basePerPurchase = $fs ? $fs->basePerPurchase() : 0.0;
            $baselineQuantity = $targetPopulation > 0 ? round($net / $targetPopulation, 6) : round($net, 6);

            if ($fs && $basePerPurchase > 0 && $basePerPurchase !== 1.0 && UnitConverter::isCompatible((string) $fs->base_unit, (string) $fs->purchase_unit)) {
                $packs = round($net / $basePerPurchase, 6);
                $purchasePrice = (float) $fs->purchase_price;
                $rows[] = [
                    'fs_item_id' => $id,
                    'ingredient_name' => $row['name'],
                    'qty' => round($net, 6),
                    'unit' => $fs->base_unit ?? $row['unit'],
                    'supplier_id' => $fs->default_supplier_id,
                    'unit_price' => round($purchasePrice / $basePerPurchase, 6),
                    'total' => round($packs * $purchasePrice, 2),
                    'purchase_qty' => $packs,
                    'purchase_unit' => $fs->purchase_unit,
                    'purchase_price' => round($purchasePrice, 6),
                    'baseline_servings' => 1,
                    'baseline_quantity' => $baselineQuantity,
                    'scaled_quantity' => round($net, 6),
                    'scaled_unit' => $fs->base_unit ?? $row['unit'],
                ];

                continue;
            }

            $unitPrice = $row['qty'] > 0 ? round($row['total'] / $row['qty'], 6) : 0;
            $rows[] = [
                'fs_item_id' => $id,
                'ingredient_name' => $row['name'],
                'qty' => round($net, 6),
                'unit' => $row['unit'],
                'supplier_id' => $fs?->default_supplier_id,
                'unit_price' => $unitPrice,
                'total' => round($net * $unitPrice, 2),
                'purchase_qty' => round($net, 6),
                'purchase_unit' => $fs?->base_unit ?? $row['unit'],
                'purchase_price' => $unitPrice,
                'baseline_servings' => 1,
                'baseline_quantity' => $baselineQuantity,
                'scaled_quantity' => round($net, 6),
                'scaled_unit' => $row['unit'],
            ];
        }

        return $rows;
    }
}
