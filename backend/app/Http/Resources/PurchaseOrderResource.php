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
            'po_number'        => $this->po_number,
            'order_date'       => $this->order_date?->toDateString(),
            'total_amount'     => $this->total_amount,
            'status'           => $this->status,
            'receipt_image'    => $this->receipt_image,
            'notes'            => $this->notes,
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
