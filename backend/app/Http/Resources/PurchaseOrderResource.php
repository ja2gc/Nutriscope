<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'fss_user_id'      => $this->fss_user_id,
            'shopping_list_id' => $this->shopping_list_id,
            'supplier_id'      => $this->supplier_id,
            'supplier'         => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id, 'name' => $this->supplier->name, 'category' => $this->supplier->category,
            ] : null),
            'po_number'        => $this->po_number,
            'or_number'        => $this->or_number,
            'order_date'       => $this->order_date?->toDateString(),
            'total_amount'     => $this->total_amount,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'items'            => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'          => $i->id,
                'fs_item_id'  => $i->fs_item_id,
                'description' => $i->description,
                'qty'         => $i->qty,
                'unit'        => $i->unit,
                'unit_price'  => $i->unit_price,
                'total_value' => $i->total_value,
                'purchase_qty'    => $i->purchase_qty,
                'purchase_unit'   => $i->purchase_unit,
                'purchase_price'  => $i->purchase_price,
            ])),
            'attachments'      => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'path' => $a->path, 'caption' => $a->caption,
            ])),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
