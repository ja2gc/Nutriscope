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
            'rnd_user_id'      => $this->rnd_user_id,
            'shopping_list_id' => $this->shopping_list_id,
            'supplier_id'      => $this->supplier_id,
            'supplier'         => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id, 'name' => $this->supplier->name, 'category' => $this->supplier->category,
            ] : null),
            'po_number'        => $this->po_number,
            'or_number'        => $this->or_number,
            'order_date'       => $this->order_date?->toDateString(),
            'received_date'    => $this->received_date?->toDateString(),
            'total_amount'     => $this->total_amount,
            'actual_budget_per_head_per_day' => $this->actual_budget_per_head_per_day,
            'status'           => $this->status,
            'lifecycle_status' => $this->lifecycle_status,
            'converted_at'     => $this->converted_at?->toISOString(),
            'completed_at'     => $this->completed_at?->toISOString(),
            'archived_at'      => $this->archived_at?->toISOString(),
            'notes'            => $this->notes,
            'items'            => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'          => $i->id,
                'vendor_group_id' => $i->vendor_group_id,
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
            'vendor_groups'    => $this->whenLoaded('vendorGroups', fn () => $this->vendorGroups->map(fn ($g) => [
                'id' => $g->id,
                'supplier_id' => $g->supplier_id,
                'supplier' => $g->relationLoaded('supplier') && $g->supplier ? [
                    'id' => $g->supplier->id,
                    'name' => $g->supplier->name,
                    'category' => $g->supplier->category,
                ] : null,
                'or_number' => $g->or_number,
                'status' => $g->status,
                'total_amount' => $g->total_amount,
                'received_at' => $g->received_at?->toISOString(),
                'stocked_at' => $g->stocked_at?->toISOString(),
                'items' => $g->relationLoaded('items') ? $g->items->map(fn ($i) => [
                    'id' => $i->id,
                    'fs_item_id' => $i->fs_item_id,
                    'description' => $i->description,
                    'qty' => $i->qty,
                    'unit' => $i->unit,
                    'unit_price' => $i->unit_price,
                    'total_value' => $i->total_value,
                    'purchase_qty' => $i->purchase_qty,
                    'purchase_unit' => $i->purchase_unit,
                    'purchase_price' => $i->purchase_price,
                ])->values() : null,
                'attachments' => $g->relationLoaded('attachments') ? $g->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'path' => $a->path,
                    'caption' => $a->caption,
                ])->values() : null,
            ])->values()),
            'attachments'      => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id, 'vendor_group_id' => $a->vendor_group_id, 'type' => $a->type, 'path' => $a->path, 'caption' => $a->caption,
            ])),
            'ppa'              => $this->whenLoaded('programProjectActivity', fn () => $this->programProjectActivity ? [
                'id' => $this->programProjectActivity->id,
                'activity' => $this->programProjectActivity->activity,
                'menu_snapshot' => $this->programProjectActivity->menu_snapshot,
                'target_date_range' => $this->programProjectActivity->target_date_range,
                'estimated_total_cost' => $this->programProjectActivity->estimated_total_cost,
                'estimated_output_patients' => $this->programProjectActivity->estimated_output_patients,
                'actual_total_cost' => $this->programProjectActivity->actual_total_cost,
                'actual_output_patients' => $this->programProjectActivity->actual_output_patients,
                'execution_frozen_at' => $this->programProjectActivity->execution_frozen_at?->toISOString(),
            ] : null),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
