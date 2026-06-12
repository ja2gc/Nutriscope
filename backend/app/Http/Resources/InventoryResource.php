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
            'item_type'                => $this->item_type,
            'fs_item_id'               => $this->fs_item_id,
            'recipe_id'                => $this->recipe_id,
            'fs_item'                  => $this->whenLoaded('fsItem', fn () => [
                'id'        => $this->fsItem->id,
                'name'      => $this->fsItem->name,
                'kind'      => $this->fsItem->kind,
                'category'  => $this->fsItem->category,
                'base_unit' => $this->fsItem->base_unit,
                'unit_cost' => $this->fsItem->unit_cost,
            ]),
            'recipe'                   => $this->whenLoaded('recipe', fn () => [
                'id'       => $this->recipe->id,
                'name'     => $this->recipe->name,
                'category' => $this->recipe->category,
                'cost'     => $this->recipe->cost,
                'servings' => $this->recipe->servings,
            ]),
            'quantity_in_stock'        => $this->quantity_in_stock,
            'unit'                     => $this->unit,
            'expiry_date'              => $this->expiry_date?->toDateString(),
            'usage_rate'               => $this->usage_rate,
            'minimum_stock_threshold'  => $this->minimum_stock_threshold,
            'unit_price'               => $this->unit_price,
            'notes'                    => $this->notes,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
