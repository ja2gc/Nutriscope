<?php

namespace App\Contracts;

interface EnvironmentSwitcher
{
    public function available(): bool;

    /** @param array<string,mixed> $candidate
     * @return array{successful:bool,message:string}
     */
    public function switch(array $candidate): array;

    /** @param array<string,mixed> $candidate */
    public function finalize(array $candidate): void;

    /** @return array{successful:bool,message:string} */
    public function rollback(string $safetySnapshotUuid): array;
}
