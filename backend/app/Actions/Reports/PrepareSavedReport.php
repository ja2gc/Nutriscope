<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\Reports\ReportService;
use Illuminate\Support\Facades\Storage;

class PrepareSavedReport
{
    public function __construct(private readonly ReportService $reports) {}

    public function execute(User $actor, string $type, array $parameters, ?Report $existing = null): Report
    {
        ksort($parameters);
        $identity = hash('sha256', $actor->role.'|'.$type.'|'.json_encode($parameters, JSON_THROW_ON_ERROR));
        $template = ReportTemplate::query()->where('type', $type)->first();
        $report = $existing ?? Report::query()->where('archive_identity', $identity)->first();
        $created = $report === null;
        if ($report === null) {
            $report = Report::query()->create([
                'user_id' => $actor->id,
                'title' => $template?->name ?? $type,
                'type' => $type,
                'archive_identity' => $identity,
                'parameters' => $parameters,
                'status' => 'completed',
                'template_version' => hash('sha256', (string) ($template?->updated_at?->toJSON() ?? 'default')),
                'appearance_version' => 'v1',
                'snapshot' => [
                    'branding' => ReportBranding::singleton()->only([
                        'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                        'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
                    ]),
                    'signatories' => $this->reports->signatoriesFor(new Report(['type' => $type, 'parameters' => $parameters])),
                    'params' => $parameters,
                ],
            ]);
        }

        try {
            $bytes = $this->reports->buildPdf($report)['bytes'];
        } catch (\Throwable $exception) {
            if ($created) {
                $report->delete();
            }
            throw $exception;
        }
        $hash = hash('sha256', $bytes);
        $path = "reports/{$report->uuid}/{$hash}.pdf";
        $disk = Storage::disk('report_cache');
        if (! $disk->exists($path) && ! $disk->put($path, $bytes, ['visibility' => 'private'])) {
            throw new \RuntimeException('Prepared report storage failed.');
        }

        $changed = $report->content_hash !== $hash;
        $oldPath = $report->cache_path;
        $report->forceFill([
            'source_fingerprint' => $hash,
            'content_hash' => $hash,
            'cache_path' => $path,
            'cache_expires_at' => now()->addDay(),
            'generated_at' => now(),
            'expires_at' => now()->addDay(),
            'file_path' => null,
        ]);
        if (! $changed) {
            $report->timestamps = false;
        }
        $report->save();
        $report->timestamps = true;
        if ($oldPath && $oldPath !== $path) {
            $disk->delete($oldPath);
        }

        return $report->fresh('user:id,uuid,name,first_name,last_name');
    }
}
