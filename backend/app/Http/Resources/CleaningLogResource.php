<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CleaningLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'fss_user_id' => $this->fss_user_id,
            'item_name'   => $this->item_name,
            'category'    => $this->category,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'cleaned_at'  => $this->cleaned_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
