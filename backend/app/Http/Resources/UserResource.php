<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'contact_number' => $this->contact_number,
            'profile_photo' => $this->profile_photo,
            'role'      => $this->role,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
