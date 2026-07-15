<?php

namespace App\Services\Audit\Revisions;

use App\Data\AuditRevisionSnapshot;
use App\Models\AuditActivity;
use App\Models\AuditRevision;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AuditRevisionWriter
{
    public function __construct(private readonly AuditRevisionRegistry $registry) {}

    public function write(
        AuditActivity $activity,
        ?AuditRevisionSnapshot $before,
        ?AuditRevisionSnapshot $after,
    ): AuditRevision {
        $connection = DB::connection($activity->getConnectionName());
        if ($connection->transactionLevel() < 1) {
            throw new RuntimeException('Audit revisions must be written inside the business transaction.');
        }
        if ($before === null && $after === null) {
            throw new InvalidArgumentException('Audit revision requires a before or after snapshot.');
        }

        $snapshot = $after ?? $before;
        if ($before !== null) {
            $this->registry->assertSnapshot($before);
        }
        if ($after !== null) {
            $this->registry->assertSnapshot($after);
        }
        if (($before !== null && $after !== null)
            && ($before->serializer !== $after->serializer
                || $before->subjectType !== $after->subjectType
                || strtolower($before->subjectPublicId) !== strtolower($after->subjectPublicId)
                || $before->schemaVersion !== $after->schemaVersion)) {
            throw new InvalidArgumentException('Audit revision snapshots must describe the same record.');
        }

        return AuditRevision::create([
            'activity_id' => $activity->id,
            'module' => $activity->module,
            'domain' => $activity->domain,
            'subject_type' => $snapshot->subjectType,
            'subject_public_id' => $snapshot->subjectPublicId,
            'action' => $activity->event,
            'schema_version' => $snapshot->schemaVersion,
            'before' => $before?->payload,
            'after' => $after?->payload,
            'occurred_at' => $activity->created_at,
        ]);
    }
}
