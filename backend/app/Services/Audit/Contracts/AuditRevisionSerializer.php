<?php

namespace App\Services\Audit\Contracts;

use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditRevisionSnapshot;
use Illuminate\Database\Eloquent\Model;

interface AuditRevisionSerializer
{
    public function key(): string;

    /** @return class-string<Model> */
    public function subjectType(): string;

    public function schemaVersion(): int;

    public function capture(Model $subject): AuditRevisionSnapshot;

    /** @param array<string, mixed> $snapshot */
    public function present(array $snapshot): AuditHistorySnapshotDto;
}
