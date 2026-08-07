<?php

namespace App\Console\Commands;

use App\Actions\Reports\PrepareSavedReport;
use App\Models\PurchaseOrderAttachment;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\ClinicalDocumentStorage;
use App\Services\StoredObjectStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigratePrivateStoredObjects extends Command
{
    protected $signature = 'storage:migrate-private-objects {--limit=100}';

    protected $description = 'Move legacy sensitive files into private storage without changing ownership';

    public function handle(StoredObjectStorage $objects, ClinicalDocumentStorage $clinical, PrepareSavedReport $reports): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $failed = false;
        User::query()->whereNull('profile_photo_stored_object_id')->whereNotNull('profile_photo')->limit($limit)->each(function (User $user) use ($objects, &$failed): void {
            try {
                if (! preg_match('/^data:(image\/(?:png|jpeg|webp));base64,(.+)$/sD', $user->profile_photo, $match)) {
                    throw new \RuntimeException('Legacy profile image is invalid.');
                }
                $bytes = base64_decode($match[2], true);
                if (! is_string($bytes)) {
                    throw new \RuntimeException('Legacy profile image is invalid.');
                }
                $object = $objects->storeBytes($bytes, $match[1], '', 'profile', 'profile-photo');
                $user->update(['profile_photo_stored_object_id' => $object->id, 'profile_photo' => null]);
            } catch (\Throwable $exception) {
                $failed = true;
                report($exception);
            }
        });

        ScreeningDocument::query()->whereNull('stored_object_id')->whereNotNull('file_path')->limit($limit)->each(function (ScreeningDocument $document) use ($objects, $clinical, &$failed): void {
            try {
                $path = $clinical->resolve($document->file_path);
                $bytes = file_get_contents($path);
                $mime = $this->detectedMime($bytes);
                $object = $objects->storeBytes($bytes, $mime, '', 'clinical', $document->original_name);
                DB::transaction(fn () => $document->update(['stored_object_id' => $object->id, 'file_path' => null]));
                $clinical->deleteIfPresent($path);
            } catch (\Throwable $exception) {
                $failed = true;
                report($exception);
            }
        });

        PurchaseOrderAttachment::query()->whereNull('stored_object_id')->whereNotNull('path')->limit($limit)->each(function (PurchaseOrderAttachment $attachment) use ($objects, &$failed): void {
            try {
                $disk = Storage::disk(config('filesystems.uploads'));
                $bytes = $disk->get($attachment->path);
                $mime = $this->detectedMime($bytes);
                $object = $objects->storeBytes($bytes, $mime, '', 'purchase_order', basename($attachment->path));
                $oldPath = $attachment->path;
                DB::transaction(fn () => $attachment->update(['stored_object_id' => $object->id, 'path' => null]));
                $disk->delete($oldPath);
            } catch (\Throwable $exception) {
                $failed = true;
                report($exception);
            }
        });

        $branding = ReportBranding::singleton();
        foreach (['left', 'right'] as $side) {
            $pathField = "logo_{$side}_path";
            $idField = "logo_{$side}_stored_object_id";
            if ($branding->{$idField} || ! $branding->{$pathField}) {
                continue;
            }
            try {
                $disk = Storage::disk('public');
                $bytes = $disk->get($branding->{$pathField});
                $mime = $this->detectedMime($bytes);
                $object = $objects->storeBytes($bytes, $mime, '', 'branding', basename($branding->{$pathField}));
                $oldPath = $branding->{$pathField};
                $branding->update([$idField => $object->id, $pathField => null]);
                $disk->delete($oldPath);
            } catch (\Throwable $exception) {
                $failed = true;
                report($exception);
            }
        }

        Report::query()->whereNotNull('file_path')->limit($limit)->each(function (Report $report) use ($reports, &$failed): void {
            try {
                $public = Storage::disk('public');
                $legacy = $public->get($report->file_path);
                if (! str_starts_with($legacy, '%PDF-')) {
                    throw new \RuntimeException('Legacy report PDF is invalid.');
                }
                $quarantine = 'legacy-report-quarantine/'.$report->uuid.'.pdf';
                Storage::disk('private_uploads')->put($quarantine, $legacy, ['visibility' => 'private']);
                $prepared = $reports->execute($report->user, $report->type, $report->parameters ?? [], $report);
                $preparedBytes = Storage::disk('report_cache')->get($prepared->cache_path);
                if (! str_starts_with($preparedBytes, '%PDF-')) {
                    throw new \RuntimeException('Prepared report PDF is invalid.');
                }
                $oldPath = $report->file_path;
                $report->update(['file_path' => null]);
                $public->delete($oldPath);
                Storage::disk('private_uploads')->delete($quarantine);
            } catch (\Throwable $exception) {
                $failed = true;
                report($exception);
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function detectedMime(string $bytes): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }
}
