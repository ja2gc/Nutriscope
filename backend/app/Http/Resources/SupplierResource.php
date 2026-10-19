<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'category' => $this->category,
            'contact' => $this->contact,
            'additional_contacts' => $this->additional_contacts ?? [],
            'preferred_contact_id' => $this->preferred_contact_id,
            'address' => $this->address,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
