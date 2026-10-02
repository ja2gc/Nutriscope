<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\StoredObjectStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('reports:purge-demographic-census-pdfs {--execute : Permanently delete listed Census report rows and PDF files}')]
#[Description('List legacy Demographic Census PDFs; delete only with --execute')]
class PurgeDemographicCensusPdfs extends Command
{
    public function handle(StoredObjectStorage $storage): int
    {
        $reports = Report::query()->where('type', 'demographic_census')->with('officialFile')->orderBy('id')->get();
        $this->info('Census PDF report records: '.$reports->count());
        foreach ($reports as $report) {
            $this->line($report->uuid);
            if (! $this->safeReportPath($report->cache_path, 'report_cache')
                || ! $this->safeReportPath($report->file_path, 'public')
                || ! $this->safeOfficialFile($report)) {
                $this->error('Unsafe or shared file reference on Census report '.$report->uuid.'. No files deleted.');

                return self::FAILURE;
            }
        }

        if (! $this->option('execute')) {
            $this->comment('Dry run only. No records or files changed.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($reports as $report) {
            try {
                $this->deletePath($report->cache_path, 'report_cache');
                $this->deletePath($report->file_path, 'public');
                if ($report->officialFile !== null) {
                    $storage->delete($report->officialFile);
                }
                $report->delete();
                $deleted++;
            } catch (Throwable $exception) {
                report($exception);
                $this->error('Cleanup stopped at Census report '.$report->uuid.'. Deleted '.$deleted.' records; rerun after fixing storage.');

                return self::FAILURE;
            }
        }

        $this->info('Deleted Census PDF report records: '.$deleted);

        return self::SUCCESS;
    }

    private function safeReportPath(?string $path, string $disk): bool
    {
        if ($path === null) {
            return true;
        }
        if (! str_starts_with($path, 'reports/') || ! str_ends_with($path, '.pdf')
            || str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        return ! Report::query()->where('type', '!=', 'demographic_census')->where($disk === 'public' ? 'file_path' : 'cache_path', $path)->exists();
    }

    private function safeOfficialFile(Report $report): bool
    {
        $object = $report->officialFile;
        if ($object === null) {
            return true;
        }

        return $object->purpose === 'report'
            && $object->mime_type === 'application/pdf'
            && str_starts_with($object->object_key, 'report/')
            && str_ends_with($object->object_key, '.pdf')
            && ! str_contains($object->object_key, '..')
            && ! str_contains($object->object_key, '\\')
            && ! Report::query()->where('type', '!=', 'demographic_census')
                ->where('official_file_stored_object_id', $object->id)->exists();
    }

    private function deletePath(?string $path, string $disk): void
    {
        if ($path === null) {
            return;
        }
        $storage = Storage::disk($disk);
        if ($storage->exists($path) && ! $storage->delete($path)) {
            throw new \RuntimeException('Census PDF file could not be deleted.');
        }
    }
}
