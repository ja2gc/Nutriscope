<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total      = (float) $this->items->sum(fn ($i) => (float) $i->total);
        $population = (int) ($this->estimate_population ?? 0);
        $days       = ($this->period_start && $this->period_end)
            ? (int) $this->period_start->diffInDays($this->period_end) + 1
            : (int) ($this->days_span ?? 0);

        // Estimated budget per head per day: total span cost ÷ (days × population).
        // Never blank once estimate_population is set — all inputs are present.
        $estimatedBudgetPerHeadPerDay = ($population > 0 && $days > 0)
            ? round($total / ($population * $days), 2)
            : null;

        return [
            'id'            => $this->uuid,
            'rnd_user_id'   => $this->rnd_user_id,
            'name'          => $this->name,
            'list_date'     => $this->list_date?->toDateString(),
            'list_type'     => $this->list_type,
            'procurement_track' => $this->procurement_track,
            'status'        => $this->status,
            'coverage_status' => $this->coverage_status,
            'uncovered_dates' => $this->uncovered_dates ?? [],
            'period_start'  => $this->period_start?->toDateString(),
            'period_end'    => $this->period_end?->toDateString(),
            'days_span'     => $this->days_span,
            'total_served_population' => $this->total_served_population,
            'estimate_population' => $this->estimate_population,
            'estimate_population_updated_at' => $this->estimate_population_updated_at?->toISOString(),
            'estimated_total' => round($total, 2),
            'estimated_budget_per_head_per_day' => $estimatedBudgetPerHeadPerDay,
            'items'         => $this->items->map(fn ($item) => [
                'id'              => $item->uuid,
                // fs_item_id stays the raw FK — it isn't consumed for routing/matching by
                // any client. supplier_id, however, is matched against uuid-valued <option>s
                // in the procurement vendor dropdown, so it must be the public uuid.
                'fs_item_id'      => $item->fs_item_id,
                'ingredient_name' => $item->ingredient_name,
                'item_type'       => $item->relationLoaded('fsItem') && $item->fsItem ? $item->fsItem->kind : 'ingredient',
                'qty'             => $item->qty,
                'unit'            => $item->unit,
                'supplier_id'     => $item->relationLoaded('supplier') && $item->supplier ? $item->supplier->uuid : null,
                'unit_price'      => $item->unit_price,
                'total'           => $item->total,
                'vendor_locked'   => $item->vendor_locked_at !== null,
                'purchase_qty'    => $item->purchase_qty,
                'purchase_unit'   => $item->purchase_unit,
                'purchase_price'  => $item->purchase_price,
            ]),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
