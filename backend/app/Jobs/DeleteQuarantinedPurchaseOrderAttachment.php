<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteQuarantinedPurchaseOrderAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly string $path) {}

    public function handle(): void
    {
        if (! str_starts_with($this->path, 'po-attachments-quarantine/')
            || str_contains($this->path, '..')
            || str_contains($this->path, '\\')) {
            throw new RuntimeException('Invalid purchase-order attachment quarantine path.');
        }

        if (Storage::disk('public')->exists($this->path) && ! Storage::disk('public')->delete($this->path)) {
            throw new RuntimeException('Failed to delete a quarantined purchase-order attachment.');
        }
    }
}
