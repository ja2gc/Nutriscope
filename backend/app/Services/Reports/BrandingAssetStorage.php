<?php

namespace App\Services\Reports;

use App\Jobs\ProcessReportFileOperation;
use App\Models\ReportFileOperation;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BrandingAssetStorage
{
    private const ACQUISITION_GRACE_MINUTES = 5;

    private const ROOT = 'branding/';

    private const QUARANTINE_ROOT = 'branding-quarantine/';

    /** @return array{path:string,intent:ReportFileOperation} */
    public function store(UploadedFile $file): array
    {
        $extension = strtolower($file->guessExtension() ?: 'bin');
        $path = self::ROOT.Str::uuid().'.'.$extension;
        try {
            $stored = $file->storeAs('branding', basename($path), config('filesystems.uploads'));
            if ($stored !== $path) {
                throw new RuntimeException('Branding asset storage failed.');
            }
        } catch (\Throwable $exception) {
            $this->scheduleOriginalCleanup($path, dispatch: true);
            throw $exception;
        }

        return ['path' => $path, 'intent' => $this->scheduleOriginalCleanup($path, dispatch: false)];
    }

    /** @return array{original:string,quarantine:string}|null */
    public function quarantine(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }
        $this->assertPath($path, self::ROOT);
        if (! $this->disk()->exists($path)) {
            return null;
        }
        $move = ['original' => $path, 'quarantine' => self::QUARANTINE_ROOT.Str::uuid().'.asset'];
        $this->intent('restore', $move);
        if (! $this->disk()->move($path, $move['quarantine'])) {
            throw new RuntimeException('Failed to quarantine branding asset.');
        }

        return $move;
    }

    public function releaseNew(ReportFileOperation $intent): void
    {
        $intent->delete();
    }

    public function retry(ReportFileOperation $intent): void
    {
        $intent = ReportFileOperation::query()->findOrFail($intent->getKey());
        $this->finalize($intent, 'quarantine_delete');
        $this->dispatch($intent);
    }

    /** @param array{original:string,quarantine:string} $move */
    public function deleteAfterCommit(array $move): void
    {
        $intent = $this->intent('delete', $move);
        $this->finalize($intent, 'delete');
        $this->dispatch($intent);
    }

    /** @param array{original:string,quarantine:string} $move */
    public function restoreDurably(array $move): void
    {
        try {
            $this->restore($move);
            ReportFileOperation::query()->where('quarantine_path', $move['quarantine'])->delete();
        } catch (\Throwable $exception) {
            report($exception);
            $intent = $this->intent('restore', $move);
            $this->finalize($intent, 'restore');
            $this->dispatch($intent);
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    public function restore(array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        if ($this->disk()->exists($move['quarantine']) && ! $this->disk()->move($move['quarantine'], $move['original'])) {
            throw new RuntimeException('Failed to restore branding asset.');
        }
    }

    public function purge(string $path): void
    {
        $this->assertPath($path, self::QUARANTINE_ROOT);
        if ($this->disk()->exists($path) && ! $this->disk()->delete($path)) {
            throw new RuntimeException('Failed to purge branding quarantine.');
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    public function quarantineAndPurge(array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        $disk = $this->disk();
        if (! $disk->exists($move['quarantine']) && $disk->exists($move['original']) && ! $disk->move($move['original'], $move['quarantine'])) {
            throw new RuntimeException('Failed to quarantine uncertain branding asset.');
        }
        $this->purge($move['quarantine']);
    }

    private function scheduleOriginalCleanup(string $path, bool $dispatch): ReportFileOperation
    {
        $move = ['original' => $path, 'quarantine' => self::QUARANTINE_ROOT.Str::uuid().'.asset'];
        $intent = $this->intent('quarantine_delete', $move);
        if ($dispatch) {
            $this->finalize($intent, 'quarantine_delete');
            $this->dispatch($intent);
        }

        return $intent;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.uploads'));
    }

    /** @param array{original:string,quarantine:string} $move */
    private function intent(string $operation, array $move): ReportFileOperation
    {
        return ReportFileOperation::query()->firstOrCreate(['quarantine_path' => $move['quarantine']], [
            'asset_scope' => 'branding',
            'operation' => $operation,
            'phase' => ReportFileOperation::PHASE_ACQUISITION,
            'available_at' => now()->addMinutes(self::ACQUISITION_GRACE_MINUTES),
            'original_path' => $move['original'],
        ]);
    }

    private function finalize(ReportFileOperation $intent, string $operation): void
    {
        $intent->update([
            'operation' => $operation,
            'phase' => ReportFileOperation::PHASE_FINALIZED,
            'available_at' => now(),
        ]);
    }

    private function dispatch(ReportFileOperation $intent): void
    {
        try {
            ProcessReportFileOperation::dispatch($intent->id)->afterCommit();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function assertPath(string $path, string $root): void
    {
        if (! str_starts_with($path, $root) || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Branding asset path outside safe root.');
        }
    }
}
