<?php

namespace App\Actions\Reports;

use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\StoredObjectStorage;
use Illuminate\Support\Facades\Storage;

class PrepareSavedReport
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly StoredObjectStorage $storedObjects,
    ) {}

    public function execute(User $actor, string $type, array $parameters, ?Report $existing = null, bool $freeze = true): Report
    {
        ksort($parameters);
        $identity = hash('sha256', $actor->role.'|'.$type.'|'.json_encode($parameters, JSON_THROW_ON_ERROR));
        $template = ReportTemplate::query()->where('type', $type)->first();
        $report = $existing ?? Report::query()->where('archive_identity', $identity)->first();
        $created = $report === null;
        if ($report?->official_file_stored_object_id !== null) {
            return $report->fresh(['user:id,uuid,name,first_name,last_name', 'officialFile']);
        }
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

        $officialFile = null;
        try {
            $bytes = $this->reports->buildPdf($report)['bytes'];
            if ($freeze) {
                $officialFile = $this->storedObjects->storeBytes(
                    $bytes,
                    'application/pdf',
                    'pdf',
                    'report',
                    str($report->title)->slug().'.pdf',
                );
            }
        } catch (\Throwable $exception) {
            if ($created) {
                $report->delete();
            }
            throw $exception;
        }
        $hash = hash('sha256', $bytes);
        $path = "reports/{$report->uuid}/{$hash}.pdf";
        $disk = Storage::disk('report_cache');
        try {
            if (! $disk->exists($path) && ! $disk->put($path, $bytes, ['visibility' => 'private'])) {
                throw new \RuntimeException('Prepared report storage failed.');
            }
        } catch (\Throwable $exception) {
            if ($officialFile !== null) {
                $this->storedObjects->deleteOrQueue($officialFile);
            }
            if ($created) {
                $report->delete();
            }
            throw $exception;
        }

        $changed = $report->content_hash !== $hash;
        $oldPath = $report->cache_path;
        try {
            $report->forceFill([
                'source_fingerprint' => $hash,
                'content_hash' => $hash,
                'official_file_stored_object_id' => $officialFile?->id,
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
        } catch (\Throwable $exception) {
            $report->timestamps = true;
            $disk->delete($path);
            if ($officialFile !== null) {
                $this->storedObjects->deleteOrQueue($officialFile);
            }
            if ($created) {
                $report->delete();
            }
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path) {
            $disk->delete($oldPath);
        }

        return $report->fresh(['user:id,uuid,name,first_name,last_name', 'officialFile']);
    }
}
