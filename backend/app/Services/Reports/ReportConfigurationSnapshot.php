<?php

namespace App\Services\Reports;

use App\Models\ReportBranding;
use App\Models\ReportConfigurationRevision;
use App\Models\ReportTemplate;
use Illuminate\Support\Carbon;

class ReportConfigurationSnapshot
{
    /** @param list<string> $types */
    public function capture(array $types, ?string $effectiveDate = null): array
    {
        $cutoff = $effectiveDate === null ? null : Carbon::parse($effectiveDate)->startOfDay();
        $brandingRevision = $this->revision('branding', null, $cutoff);
        $saved = ReportTemplate::query()->whereIn('type', $types)->get()
            ->mapWithKeys(function (ReportTemplate $template) use ($cutoff): array {
                $revision = $this->revision('template', $template->id, $cutoff);
                $snapshot = $revision?->snapshot ?? [];

                return [$template->type => [
                    'name' => $snapshot['name'] ?? $template->name,
                    'signatories' => $snapshot['signatories'] ?? $template->signatories ?? [],
                    'version' => $revision?->uuid ?? $template->updated_at?->toJSON(),
                    'revision_uuid' => $revision?->uuid,
                ]];
            })->all();
        $templates = [];
        foreach ($types as $type) {
            $templates[$type] = $saved[$type] ?? ['name' => null, 'signatories' => [], 'version' => null];
        }

        return [
            'branding' => $brandingRevision?->snapshot ?? ReportBranding::singleton()->only([
                'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
            ]),
            'templates' => $templates,
            'revisions' => [
                'branding' => $brandingRevision?->uuid,
                'templates' => collect($templates)->mapWithKeys(fn (array $template, string $type): array => [
                    $type => $template['revision_uuid'] ?? null,
                ])->all(),
            ],
        ];
    }

    private function revision(string $kind, ?int $templateId, ?Carbon $cutoff): ?ReportConfigurationRevision
    {
        return ReportConfigurationRevision::query()
            ->where('kind', $kind)
            ->when($templateId === null, fn ($query) => $query->whereNull('report_template_id'))
            ->when($templateId !== null, fn ($query) => $query->where('report_template_id', $templateId))
            ->when($cutoff !== null, fn ($query) => $query->where('created_at', '<=', $cutoff))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
