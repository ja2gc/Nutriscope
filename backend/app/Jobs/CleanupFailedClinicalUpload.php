<?php

namespace App\Jobs;

use App\Services\ClinicalDocumentStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupFailedClinicalUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public readonly string $storedPath) {}

    public function handle(ClinicalDocumentStorage $storage): void
    {
        $storage->deleteIfPresent($this->storedPath);
    }
}
