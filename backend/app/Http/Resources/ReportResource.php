<?php

namespace App\Http\Resources;

use App\Models\ReportArchiveSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'user_id' => $this->user_id,
            'created_by' => $this->whenLoaded('user', fn (): ?array => $this->user === null ? null : [
                'id' => $this->user->uuid,
                'name' => $this->user->display_name,
            ]),
            'title' => $this->title,
            'type' => $this->type,
            'filters' => $this->filters,
            'parameters' => $this->parameters,
            'snapshot' => $this->snapshot,
            'file_path' => $this->official_file_stored_object_id || $this->cache_path ? 'prepared' : null,
            'prepared' => $this->official_file_stored_object_id !== null
                || (filled($this->cache_path) && $this->cache_expires_at?->isFuture() === true),
            'template_version' => $this->template_version,
            'appearance_version' => $this->appearance_version,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'report_covered_until' => $this->report_covered_until?->toDateString(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'retention_expires_at' => $this->status === 'archived' && $this->retentionEnabled($request)
                ? $this->retention_expires_at?->toIso8601String()
                : null,
        ];
    }

    private function retentionEnabled(Request $request): bool
    {
        if (! $request->attributes->has('report_archive_retention_enabled')) {
            $request->attributes->set('report_archive_retention_enabled', ReportArchiveSetting::enabled());
        }

        return $request->attributes->get('report_archive_retention_enabled');
    }
}
