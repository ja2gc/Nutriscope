<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecoveryRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'backup_id' => $this->backupRun->uuid,
            'incident_type' => $this->incident_type->value,
            'state' => $this->state->value,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'safety_snapshot_expires_at' => $this->safety_snapshot_expires_at?->toIso8601String(),
            'failure_message' => $this->failure_message,
            'can_cancel' => in_array($this->state->value, ['requested', 'preparing', 'checking', 'ready'], true),
        ];
    }
}
