<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'fss_user_id'  => $this->fss_user_id,
            'name'         => $this->name,
            'list_date'    => $this->list_date?->toDateString(),
            'list_type'    => $this->list_type,
            'status'       => $this->status,
            'period_start' => $this->period_start?->toDateString(),
            'period_end'   => $this->period_end?->toDateString(),
            'items'        => $this->items->map(fn($item) => [
                'id'              => $item->id,
                'food_item_id'    => $item->food_item_id,
                'ingredient_name' => $item->ingredient_name,
                'qty'             => $item->qty,
                'unit'            => $item->unit,
                'unit_price'      => $item->unit_price,
                'total'           => $item->total,
            ]),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
