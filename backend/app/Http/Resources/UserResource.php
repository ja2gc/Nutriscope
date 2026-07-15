<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'name' => $this->display_name,
            'email' => $this->email,
            'recovery_email' => $this->recovery_email,
            'recovery_email_verified' => $this->recovery_email_verified_at !== null,
            'contact_number' => $this->contact_number,
            'profile_photo' => $this->profile_photo,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
