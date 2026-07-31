<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RestoreQuarantinedPurchaseOrderAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $quarantine,
        public readonly string $original,
    ) {}

    public function handle(): void
    {
        if (! str_starts_with($this->quarantine, 'po-attachments-quarantine/')
            || ! str_starts_with($this->original, 'po-attachments/')
            || str_contains($this->quarantine.$this->original, '..')
            || str_contains($this->quarantine.$this->original, '\\')) {
            throw new RuntimeException('Invalid purchase-order attachment restoration path.');
        }

        $disk = Storage::disk(config('filesystems.uploads'));
        if ($disk->exists($this->quarantine)
            && ! $disk->move($this->quarantine, $this->original)) {
            throw new RuntimeException('Failed to restore a quarantined purchase-order attachment.');
        }
    }
}
