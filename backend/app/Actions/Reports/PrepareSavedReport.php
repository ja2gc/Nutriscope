<?php

namespace App\Actions\Reports;

use App\Models\Intervention;
use App\Models\MealPlan;
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
        if ($type === 'demographic_census') {
            throw new \InvalidArgumentException('Demographic Census no longer creates filed PDF reports.');
        }
        ksort($parameters);
        $identity = hash('sha256', $actor->role.'|'.$type.'|'.json_encode($parameters, JSON_THROW_ON_ERROR));
        $template = ReportTemplate::query()->where('type', $type)->first();
        $title = $this->titleFor($type, $parameters, $template?->name ?? $type);
        $report = $existing ?? Report::query()->where('archive_identity', $identity)->first();
        $created = $report === null;
        if ($report?->status === 'archived') {
            return $report->fresh(['user:id,uuid,name,first_name,last_name', 'officialFile']);
        }
        $snapshot = [
            'branding' => ReportBranding::singleton()->only([
                'hospital_name', 'address', 'accreditation', 'service_name', 'province', 'lgu',
                'logo_left_path', 'logo_right_path', 'logo_left_stored_object_id', 'logo_right_stored_object_id',
            ]),
            'signatories' => $this->reports->signatoriesFor(new Report(['type' => $type, 'parameters' => $parameters])),
            'params' => $parameters,
        ];
        $templateVersion = hash('sha256', (string) ($template?->updated_at?->toJSON() ?? 'default'));
        if ($report === null) {
            $report = Report::query()->create([
                'user_id' => $actor->id,
                'title' => $title,
                'type' => $type,
                'archive_identity' => $identity,
                'parameters' => $parameters,
                'status' => 'completed',
                'template_version' => $templateVersion,
                'appearance_version' => 'v1',
                'snapshot' => $snapshot,
            ]);
        } else {
            $report->forceFill([
                'title' => $title,
                'parameters' => $parameters,
                'snapshot' => $snapshot,
                'template_version' => $templateVersion,
            ]);
        }

        $oldOfficialFile = $report->officialFile;
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
        $wroteCache = false;
        $cacheExisted = false;
        try {
            $cacheExisted = $disk->exists($path);
            if (! $cacheExisted) {
                if (! $disk->put($path, $bytes, ['visibility' => 'private'])) {
                    throw new \RuntimeException('Prepared report storage failed.');
                }
                $wroteCache = true;
            }
        } catch (\Throwable $exception) {
            if (! $cacheExisted) {
                $disk->delete($path);
            }
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
            if ($wroteCache && $path !== $oldPath) {
                $disk->delete($path);
            }
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
        if ($oldOfficialFile !== null && $oldOfficialFile->id !== $officialFile?->id) {
            $this->storedObjects->deleteOrQueue($oldOfficialFile);
        }

        return $report->fresh(['user:id,uuid,name,first_name,last_name', 'officialFile']);
    }

    private function titleFor(string $type, array $parameters, string $fallback): string
    {
        if ($type !== 'patient_menu_plan') {
            return $fallback;
        }

        $planIdentifier = $parameters['intervention_plan_id'] ?? null;
        $plan = $planIdentifier === null ? null : Intervention::query()
            ->when(
                is_int($planIdentifier) || ctype_digit((string) $planIdentifier),
                fn ($query) => $query->whereKey((int) $planIdentifier),
                fn ($query) => $query->where('uuid', (string) $planIdentifier),
            )
            ->first();

        if ($plan === null && isset($parameters['meal_plan_id'])) {
            $mealPlanIdentifier = $parameters['meal_plan_id'];
            $plan = MealPlan::query()
                ->with('intervention')
                ->when(
                    is_int($mealPlanIdentifier) || ctype_digit((string) $mealPlanIdentifier),
                    fn ($query) => $query->whereKey((int) $mealPlanIdentifier),
                    fn ($query) => $query->where('uuid', (string) $mealPlanIdentifier),
                )
                ->first()?->intervention;
        }

        return $plan?->created_at
            ? 'Nutrition Intervention Plan — '.$plan->created_at->format('M j, Y')
            : $fallback;
    }
}
