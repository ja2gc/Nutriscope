<?php

namespace App\Services\Reports;

use App\Jobs\ProcessReportFileOperation;
use App\Models\ReportFileOperation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ReportArchiveStorage
{
    private const ACQUISITION_GRACE_MINUTES = 5;

    private const ROOT = 'reports/';

    private const QUARANTINE_ROOT = 'reports-quarantine/';

    /** @return array{original:string,quarantine:string}|null */
    public function quarantine(?string $path): ?array
    {
        if ($path === null) {
            return null;
        }
        $this->assertPath($path, self::ROOT);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }
        $quarantine = self::QUARANTINE_ROOT.Str::uuid().'.pdf';
        $move = ['original' => $path, 'quarantine' => $quarantine];
        $this->intent('restore', $move);
        if (! Storage::disk('public')->move($path, $quarantine)) {
            throw new RuntimeException('Failed to quarantine report archive.');
        }

        return $move;
    }

    /** @param array{original:string,quarantine:string} $move */
    public function restore(array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        if (Storage::disk('public')->exists($move['quarantine']) && ! Storage::disk('public')->move($move['quarantine'], $move['original'])) {
            throw new RuntimeException('Failed to restore report archive.');
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    public function restoreDurably(array $move): void
    {
        try {
            $this->restore($move);
            ReportFileOperation::query()->where('quarantine_path', $move['quarantine'])->delete();
        } catch (\Throwable $e) {
            report($e);
            $this->schedule('restore', $move);
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    public function deleteAfterCommit(array $move): void
    {
        $intent = $this->intent('delete', $move);
        $this->finalize($intent, 'delete');
        $this->dispatch($intent);
    }

    public function cleanupGenerated(?string $path): void
    {
        if ($path !== null) {
            $this->scheduleOriginalCleanup($path);
        }
    }

    public function scheduleOriginalCleanup(string $path): void
    {
        $this->assertPath($path, self::ROOT);
        $this->schedule('quarantine_delete', [
            'original' => $path,
            'quarantine' => self::QUARANTINE_ROOT.Str::uuid().'.pdf',
        ]);
    }

    public function purge(string $path): void
    {
        $this->assertPath($path, self::QUARANTINE_ROOT);
        if (Storage::disk('public')->exists($path) && ! Storage::disk('public')->delete($path)) {
            throw new RuntimeException('Failed to purge report quarantine.');
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    public function quarantineAndPurge(array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        $disk = Storage::disk('public');
        if (! $disk->exists($move['quarantine']) && $disk->exists($move['original'])
            && ! $disk->move($move['original'], $move['quarantine'])) {
            throw new RuntimeException('Failed to quarantine uncertain report archive.');
        }
        $this->purge($move['quarantine']);
    }

    private function assertPath(string $path, string $root): void
    {
        if (! str_starts_with($path, $root) || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Report archive path outside safe root.');
        }
    }

    /** @param array{original:string,quarantine:string} $move */
    private function schedule(string $operation, array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        $intent = $this->intent($operation, $move);
        $this->finalize($intent, $operation);
        $this->dispatch($intent);
    }

    /** @param array{original:string,quarantine:string} $move */
    private function intent(string $operation, array $move): ReportFileOperation
    {
        return ReportFileOperation::query()->firstOrCreate([
            'quarantine_path' => $move['quarantine'],
        ], [
            'asset_scope' => 'report',
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
}
