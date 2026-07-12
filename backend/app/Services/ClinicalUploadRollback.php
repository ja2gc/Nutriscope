<?php

namespace App\Services;

use App\Jobs\CleanupFailedClinicalUpload;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ClinicalUploadRollback
{
    public function __construct(
        private readonly ClinicalDocumentStorage $documentStorage,
        private readonly Dispatcher $dispatcher,
    ) {}

    public function cleanup(string $storedPath): void
    {
        $failures = [];

        try {
            Storage::delete($storedPath);
        } catch (Throwable $exception) {
            $failures[] = $exception;
        }

        try {
            $exists = Storage::exists($storedPath);
        } catch (Throwable $exception) {
            $failures[] = $exception;
            $exists = true;
        }

        if (! $exists) {
            $this->reportFailures($failures);

            return;
        }

        try {
            $this->dispatcher->dispatch(new CleanupFailedClinicalUpload($storedPath));
            $this->reportFailures($failures);

            return;
        } catch (Throwable $exception) {
            $failures[] = $exception;
        }

        try {
            $this->documentStorage->deleteIfPresent($storedPath);
        } catch (Throwable $exception) {
            $failures[] = $exception;
        }

        $this->reportFailures($failures);
    }

    /** @param array<int, Throwable> $failures */
    private function reportFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        report(new RuntimeException(
            sprintf('Clinical upload rollback encountered %d cleanup failure(s).', count($failures)),
            previous: $failures[0],
        ));
    }
}
