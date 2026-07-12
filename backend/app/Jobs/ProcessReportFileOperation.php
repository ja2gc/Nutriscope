<?php

namespace App\Jobs;

use App\Models\ReportFileOperation;
use App\Services\Reports\BrandingAssetStorage;
use App\Services\Reports\ReportArchiveStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessReportFileOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $operationId) {}

    public function handle(ReportArchiveStorage $reports, BrandingAssetStorage $branding): void
    {
        $operation = ReportFileOperation::query()->find($this->operationId);
        if ($operation === null) {
            return;
        }
        if (! in_array($operation->phase, [ReportFileOperation::PHASE_FINALIZED, ReportFileOperation::PHASE_ACQUISITION], true)
            || $operation->available_at->isFuture()) {
            return;
        }

        try {
            $storage = $operation->asset_scope === 'branding' ? $branding : $reports;
            $move = ['original' => $operation->original_path, 'quarantine' => $operation->quarantine_path];
            match ($operation->operation) {
                'restore' => $storage->restore($move),
                'delete' => $storage->purge($operation->quarantine_path),
                'quarantine_delete' => $storage->quarantineAndPurge($move),
                default => throw new \RuntimeException('Unknown report file operation.'),
            };
            $operation->delete();
        } catch (\Throwable $exception) {
            $operation->update(['attempts' => $operation->attempts + 1, 'last_error_code' => $exception::class]);
            throw $exception;
        }
    }
}
