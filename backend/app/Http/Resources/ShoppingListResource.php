<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'rnd_user_id'   => $this->rnd_user_id,
            'menu_cycle_id' => $this->menu_cycle_id,
            'name'          => $this->name,
            'list_date'     => $this->list_date?->toDateString(),
            'list_type'     => $this->list_type,
            'status'        => $this->status,
            'period_start'  => $this->period_start?->toDateString(),
            'period_end'    => $this->period_end?->toDateString(),
            'days_span'     => $this->days_span,
            'total_served_population' => $this->total_served_population,
            'items'         => $this->items->map(fn ($item) => [
                'id'              => $item->id,
                'fs_item_id'      => $item->fs_item_id,
                'ingredient_name' => $item->ingredient_name,
                'qty'             => $item->qty,
                'unit'            => $item->unit,
                'supplier_id'     => $item->supplier_id,
                'unit_price'      => $item->unit_price,
                'total'           => $item->total,
                'purchase_qty'    => $item->purchase_qty,
                'purchase_unit'   => $item->purchase_unit,
                'purchase_price'  => $item->purchase_price,
            ]),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
