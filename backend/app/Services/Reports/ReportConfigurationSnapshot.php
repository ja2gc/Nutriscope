<?php

namespace App\Services\Reports;

use App\Models\ReportBranding;
use App\Models\ReportTemplate;

class ReportConfigurationSnapshot
{
    /** @param list<string> $types */
    public function capture(array $types): array
    {
        $saved = ReportTemplate::query()->whereIn('type', $types)->get()
            ->mapWithKeys(fn (ReportTemplate $template): array => [$template->type => [
                'name' => $template->name,
                'signatories' => $template->signatories ?? [],
                'version' => $template->updated_at?->toJSON(),
            ]])->all();
        $templates = [];
        foreach ($types as $type) {
            $templates[$type] = $saved[$type] ?? ['name' => null, 'signatories' => [], 'version' => null];
        }

        return [
            'branding' => ReportBranding::singleton()->only([
                'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
            ]),
            'templates' => $templates,
        ];
    }
}
