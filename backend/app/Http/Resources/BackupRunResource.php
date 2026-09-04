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
            'categories' => $this->categories(),
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
            'recovery' => $this->whenLoaded('latestRecoveryRequest', fn (): ?array => $this->latestRecoveryRequest === null ? null : [
                'id' => $this->latestRecoveryRequest->uuid,
                'state' => $this->latestRecoveryRequest->state->value,
                'requested_at' => $this->latestRecoveryRequest->requested_at?->toIso8601String(),
                'resolved_at' => $this->latestRecoveryRequest->resolved_at?->toIso8601String(),
                'safety_snapshot_expires_at' => $this->latestRecoveryRequest->safety_snapshot_expires_at?->toIso8601String(),
                'failure_message' => $this->latestRecoveryRequest->failure_message,
                'can_cancel' => in_array($this->latestRecoveryRequest->state->value, ['requested', 'preparing', 'checking', 'ready'], true),
            ]),
            'actions' => [
                'can_delete' => $this->state === BackupState::Failed
                    || ($this->state === BackupState::Completed && ! $isLatest && ! $hasRecoveryRequest),
                'can_purge' => $this->state === BackupState::RecentlyDeleted && ! $hasRecoveryRequest,
                'can_keep' => $this->state === BackupState::RecentlyDeleted
                    && $this->recoverable_until?->isFuture() === true,
                'can_request_recovery' => $this->state === BackupState::Completed && $this->manifest !== null && ! $hasRecoveryRequest,
            ],
        ];
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        if (in_array($this->source->value, ['manual', 'safety'], true)) {
            return [$this->source->value];
        }

        if ($this->resource->relationLoaded('schedulePeriods')) {
            $categories = $this->schedulePeriods
                ->pluck('category.value')
                ->unique()
                ->sort()
                ->values()
                ->all();
            if ($categories !== []) {
                return $categories;
            }
        }

        return $this->retention_tier === null ? [] : [$this->retention_tier->value];
    }
}
