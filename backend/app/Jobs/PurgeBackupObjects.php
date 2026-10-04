<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PurgeBackupObjects implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 300;

    /** @param array<string,list<string>> $keysByDisk */
    public function __construct(public readonly array $keysByDisk) {}

    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    public function handle(): void
    {
        foreach ($this->keysByDisk as $diskName => $keys) {
            if ($keys !== [] && ! Storage::disk($diskName)->delete(array_values(array_unique($keys)))) {
                throw new RuntimeException('Backup objects could not be removed.');
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Backup object purge failed after retries.', [
            'disk_count' => count($this->keysByDisk),
        ]);
    }
}
