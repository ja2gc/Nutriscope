<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'item_type' => $this->item_type,
            'fs_item_id' => $this->fs_item_id,
            'recipe_id' => $this->recipe_id,
            'fs_item' => $this->whenLoaded('fsItem', fn () => [
                'id' => $this->fsItem->uuid,
                'name' => $this->fsItem->name,
                'kind' => $this->fsItem->kind,
                'category' => $this->fsItem->category,
                'base_unit' => $this->fsItem->base_unit,
                'unit_cost' => $this->fsItem->unit_cost,
                'include_in_generated_lists' => $this->fsItem->include_in_generated_lists,
            ]),
            'recipe' => $this->whenLoaded('recipe', fn () => [
                'id' => $this->recipe->uuid,
                'name' => $this->recipe->name,
                'category' => $this->recipe->category,
                'cost' => $this->recipe->cost,
                'servings' => $this->recipe->servings,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
