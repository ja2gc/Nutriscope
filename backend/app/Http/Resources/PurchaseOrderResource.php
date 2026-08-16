<?php

namespace App\Http\Resources;

use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $servedPopulationProgress = null;
        if ($this->relationLoaded('shoppingList') && $this->shoppingList && $this->procurement_track === 'food') {
            $servedPopulationProgress = app(PurchaseOrderLifecycleService::class)
                ->servedPopulationProgress($this->shoppingList);
        }

        return [
            'id' => $this->uuid,
            'rnd_user_id' => $this->rnd_user_id,
            'shopping_list_id' => $this->whenLoaded('shoppingList', fn () => $this->shoppingList?->uuid, $this->shopping_list_id),
            'shopping_list' => $this->whenLoaded('shoppingList', fn () => $this->shoppingList ? [
                'id' => $this->shoppingList->uuid,
                'name' => $this->shoppingList->name,
            ] : null),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->uuid, 'name' => $this->supplier->name, 'category' => $this->supplier->category,
            ] : null),
            'po_number' => $this->po_number,
            'or_number' => $this->or_number,
            'order_date' => $this->order_date?->toDateString(),
            'received_date' => $this->received_date?->toDateString(),
            'total_amount' => $this->total_amount,
            'actual_budget_per_head_per_day' => $this->actual_budget_per_head_per_day,
            'served_population_progress' => $servedPopulationProgress,
            'status' => $this->status,
            'lifecycle_status' => $this->lifecycle_status,
            'procurement_track' => $this->procurement_track,
            'structural_locked' => $this->structural_locked_at !== null,
            'structural_locked_at' => $this->structural_locked_at?->toISOString(),
            'final_locked' => $this->final_locked_at !== null,
            'final_locked_at' => $this->final_locked_at?->toISOString(),
            'converted_at' => $this->converted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'vendor_group_id' => $i->vendor_group_id,
                'fs_item_id' => $i->fs_item_id,
                'description' => $i->description,
                'qty' => $i->qty,
                'unit' => $i->unit,
                'unit_price' => $i->unit_price,
                'total_value' => $i->total_value,
                'purchase_qty' => $i->purchase_qty,
                'purchase_unit' => $i->purchase_unit,
                'purchase_price' => $i->purchase_price,
                'actual_qty' => number_format((float) ($i->actual_qty ?? $i->purchase_qty ?? $i->qty), 3, '.', ''),
                'actual_unit' => $i->purchase_unit ?? $i->unit,
                'actual_unit_price' => number_format((float) ($i->actual_unit_price ?? $i->purchase_price ?? $i->unit_price), 2, '.', ''),
                'actual_total' => round(
                    (float) ($i->actual_qty ?? $i->purchase_qty ?? $i->qty)
                    * (float) ($i->actual_unit_price ?? $i->purchase_price ?? $i->unit_price),
                    2,
                ),
                'actual_values_confirmed' => $i->actual_qty !== null && $i->actual_unit_price !== null,
            ])),
            'vendor_groups' => $this->whenLoaded('vendorGroups', fn () => $this->vendorGroups->map(fn ($g) => [
                'id' => $g->uuid,
                'supplier_id' => $g->supplier_id,
                'supplier' => $g->relationLoaded('supplier') && $g->supplier ? [
                    'id' => $g->supplier->uuid,
                    'name' => $g->supplier->name,
                    'category' => $g->supplier->category,
                ] : null,
                'or_number' => $g->or_number,
                'or_number_display' => $g->or_number ?: 'Not provided',
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
                    'actual_qty' => number_format((float) ($i->actual_qty ?? $i->purchase_qty ?? $i->qty), 3, '.', ''),
                    'actual_unit' => $i->purchase_unit ?? $i->unit,
                    'actual_unit_price' => number_format((float) ($i->actual_unit_price ?? $i->purchase_price ?? $i->unit_price), 2, '.', ''),
                    'actual_total' => round(
                        (float) ($i->actual_qty ?? $i->purchase_qty ?? $i->qty)
                        * (float) ($i->actual_unit_price ?? $i->purchase_price ?? $i->unit_price),
                        2,
                    ),
                    'actual_values_confirmed' => $i->actual_qty !== null && $i->actual_unit_price !== null,
                ])->values() : null,
                'attachments' => $g->relationLoaded('attachments') ? $g->attachments->map(fn ($a) => [
                    'id' => $a->uuid,
                    'type' => $a->type,
                    'url' => $a->url,
                    'caption' => $a->caption,
                ])->values() : null,
                'evidence_requirements' => [
                    'supplier_assigned' => $g->supplier_id !== null,
                    'actual_values_reviewed' => $g->relationLoaded('items')
                        && $g->items->isNotEmpty()
                        && $g->items->every(fn ($i) => $i->actual_qty !== null && $i->actual_unit_price !== null),
                    'receipt_uploaded' => $g->relationLoaded('attachments')
                        && $g->attachments->where('type', 'receipt')->isNotEmpty(),
                    'proof_uploaded' => $g->relationLoaded('attachments')
                        && $g->attachments->where('type', 'proof')->isNotEmpty(),
                    'can_mark_received' => $g->supplier_id !== null
                        && $g->relationLoaded('items')
                        && $g->items->isNotEmpty()
                        && $g->items->every(fn ($i) => $i->actual_qty !== null && $i->actual_unit_price !== null)
                        && $g->relationLoaded('attachments')
                        && $g->attachments->where('type', 'receipt')->isNotEmpty()
                        && $g->attachments->where('type', 'proof')->isNotEmpty(),
                ],
            ])->values()),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->uuid, 'vendor_group_id' => $a->vendor_group_id, 'type' => $a->type, 'url' => $a->url, 'caption' => $a->caption,
            ])),
            'ppa' => $this->whenLoaded('programProjectActivity', fn () => $this->programProjectActivity ? [
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
            'waiting_on_receipts' => $this->when(
                $this->relationLoaded('vendorGroups'),
                fn () => $this->lifecycle_status === 'open_execution'
                    && $this->vendorGroups->contains(
                        fn ($g) => ! $g->relationLoaded('attachments')
                            || $g->attachments->where('type', 'receipt')->isEmpty()
                    ),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
