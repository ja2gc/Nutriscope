<?php

namespace App\Services\FSS;

use App\Jobs\DeleteQuarantinedPurchaseOrderAttachment;
use App\Jobs\RestoreQuarantinedPurchaseOrderAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PurchaseOrderAttachmentStorage
{
    private const ROOT = 'po-attachments/';

    private const QUARANTINE_ROOT = 'po-attachments-quarantine/';

    public function store(UploadedFile $file): string
    {
        $path = $file->store('po-attachments', 'public');
        $this->assertPath($path, self::ROOT);

        return $path;
    }

    /** @param array<int, string> $paths */
    public function deleteUploads(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $move = $this->quarantine($path);
                if ($move !== null) {
                    $this->deleteAfterCommit($move);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    /** @return array{original: string, quarantine: string}|null */
    public function quarantine(string $path): ?array
    {
        $this->assertPath($path, self::ROOT);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $quarantine = self::QUARANTINE_ROOT.Str::uuid().($extension === '' ? '' : '.'.$extension);
        if (! Storage::disk('public')->move($path, $quarantine)) {
            throw new RuntimeException('Failed to quarantine the purchase-order attachment.');
        }

        return ['original' => $path, 'quarantine' => $quarantine];
    }

    /** @param array<int, string> $paths
     * @return array<int, array{original: string, quarantine: string}>
     */
    public function quarantineMany(array $paths): array
    {
        $moves = [];
        try {
            foreach ($paths as $path) {
                $move = $this->quarantine($path);
                if ($move !== null) {
                    $moves[] = $move;
                }
            }
        } catch (\Throwable $exception) {
            $this->restoreMany($moves);

            throw $exception;
        }

        return $moves;
    }

    /** @param array{original: string, quarantine: string} $move */
    public function restore(array $move): void
    {
        $this->assertPath($move['original'], self::ROOT);
        $this->assertPath($move['quarantine'], self::QUARANTINE_ROOT);
        if (Storage::disk('public')->exists($move['quarantine'])
            && ! Storage::disk('public')->move($move['quarantine'], $move['original'])) {
            throw new RuntimeException('Failed to restore the purchase-order attachment.');
        }
    }

    /** @param array<int, array{original: string, quarantine: string}> $moves */
    public function restoreMany(array $moves): void
    {
        foreach (array_reverse($moves) as $move) {
            try {
                $this->restore($move);
            } catch (\Throwable $exception) {
                report($exception);
                try {
                    RestoreQuarantinedPurchaseOrderAttachment::dispatch($move['quarantine'], $move['original']);
                } catch (\Throwable $dispatchException) {
                    report($dispatchException);
                }
            }
        }
    }

    /** @param array{original: string, quarantine: string} $move */
    public function deleteAfterCommit(array $move): void
    {
        try {
            DeleteQuarantinedPurchaseOrderAttachment::dispatch($move['quarantine']);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<int, array{original: string, quarantine: string}> $moves */
    public function deleteManyAfterCommit(array $moves): void
    {
        foreach ($moves as $move) {
            $this->deleteAfterCommit($move);
        }
    }

    private function assertPath(string $path, string $root): void
    {
        if (! str_starts_with($path, $root) || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new RuntimeException('Purchase-order attachment path is outside its safe root.');
        }
    }
}
