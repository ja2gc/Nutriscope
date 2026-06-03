<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'food_item_id'             => $this->food_item_id,
            'food_item'                => $this->whenLoaded('foodItem', fn() => [
                'id'   => $this->foodItem->id,
                'name' => $this->foodItem->name,
            ]),
            'quantity_in_stock'        => $this->quantity_in_stock,
            'unit'                     => $this->unit,
            'expiry_date'              => $this->expiry_date?->toDateString(),
            'usage_rate'               => $this->usage_rate,
            'minimum_stock_threshold'  => $this->minimum_stock_threshold,
            'notes'                    => $this->notes,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
