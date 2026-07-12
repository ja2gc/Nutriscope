<?php

namespace App\Jobs;

use App\Services\ClinicalDocumentStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreQuarantinedClinicalFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120, 600];

    /** @param array{original:string, quarantine:string} $move */
    public function __construct(public readonly array $move) {}

    public function handle(ClinicalDocumentStorage $storage): void
    {
        $storage->restore($this->move);
    }
}
