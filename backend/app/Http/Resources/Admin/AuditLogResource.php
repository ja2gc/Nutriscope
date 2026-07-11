<?php

namespace App\Http\Resources\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Enums\AuditSeverity;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'action' => $this->event ?? AuditAction::Updated->value,
            'category' => $this->valueOrDefault($this->category, AuditCategory::Operations),
            'domain' => $this->valueOrDefault($this->domain, AuditDomain::System),
            'severity' => $this->valueOrDefault($this->severity, AuditSeverity::Info),
            'outcome' => $this->valueOrDefault($this->outcome, AuditOutcome::Success),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'id' => $this->causer->uuid,
                'name' => $this->causer->name,
                'email' => $this->causer->email,
                'role' => $this->causer->role,
            ] : null),
            'properties' => $this->properties,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function valueOrDefault(mixed $value, BackedEnum $default): string
    {
        return $value instanceof BackedEnum ? $value->value : ($value ?? $default->value);
    }
}
