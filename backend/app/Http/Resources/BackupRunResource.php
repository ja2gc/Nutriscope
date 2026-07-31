<?php

namespace App\Http\Resources;

use App\Enums\BackupState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackupRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isLatest = (bool) ($this->resource->is_latest_verified ?? false);
        $hasRecoveryRequest = (int) ($this->resource->pending_recovery_requests_count ?? 0) > 0;

        return [
            'id' => $this->uuid,
            'state' => $this->state->value,
            'source' => $this->source->value,
            'size_bytes' => $this->bytes,
            'encrypted' => $this->encrypted,
            'retention_tier' => $this->retention_tier?->value,
            'retention_expires_at' => $this->retention_expires_at?->toIso8601String(),
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'recoverable_until' => $this->recoverable_until?->toIso8601String(),
            'failure' => $this->state === BackupState::Failed ? [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
            ] : null,
            'actions' => [
                'can_delete' => $this->state === BackupState::Completed && ! $isLatest && ! $hasRecoveryRequest,
                'can_keep' => $this->state === BackupState::RecentlyDeleted
                    && $this->recoverable_until?->isFuture() === true,
                'can_request_recovery' => $this->state === BackupState::Completed && ! $hasRecoveryRequest,
            ],
        ];
    }
}
