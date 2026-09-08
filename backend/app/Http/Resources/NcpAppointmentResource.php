<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NcpAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'patient_id' => $this->patient?->uuid,
            'ncp_record_id' => $this->ncpRecord?->uuid,
            'rescheduled_from_id' => $this->whenLoaded('rescheduledFrom', fn (): ?string => $this->rescheduledFrom?->uuid),
            'source' => $this->source,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'reason_code' => $this->reason_code,
            'worked_on' => $this->worked_on ?? [],
            'newly_completed' => $this->newly_completed ?? [],
            'administered_by' => $this->started_at === null
                ? null
                : $this->whenLoaded('rnd', fn (): array => [
                    'id' => $this->rnd->uuid,
                    'display_name' => $this->rnd->display_name,
                ]),
            'patient' => $this->whenLoaded('patient', fn (): array => [
                'id' => $this->patient->uuid,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
                'display_name' => $this->patient->display_name,
            ]),
        ];
    }
}
