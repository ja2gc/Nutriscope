<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionPlanService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(
        NcpRecord $ncpRecord,
        array $attributes,
        User $actor,
        ?Monitoring $sourceMonitoring = null,
    ): Intervention {
        $this->validateOwnership($ncpRecord, $actor, $sourceMonitoring);
        $this->auditLogger->assertAvailable();

        return DB::transaction(function () use ($ncpRecord, $attributes, $sourceMonitoring): Intervention {
            $lockedNcpRecord = NcpRecord::query()
                ->whereKey($ncpRecord->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $intervention = new Intervention($attributes);
            $intervention->ncp_record_id = $lockedNcpRecord->getKey();
            $intervention->source_monitoring_id = $sourceMonitoring?->getKey();
            $intervention->save();

            return $intervention;
        }, 3);
    }

    private function validateOwnership(
        NcpRecord $ncpRecord,
        User $actor,
        ?Monitoring $sourceMonitoring,
    ): void {
        if ($ncpRecord->rnd_user_id !== $actor->getKey()) {
            throw ValidationException::withMessages([
                'ncp_record' => ['The NCP record does not belong to this RND.'],
            ]);
        }

        if ($sourceMonitoring !== null && $sourceMonitoring->ncp_record_id !== $ncpRecord->getKey()) {
            throw ValidationException::withMessages([
                'source_monitoring' => ['The monitoring record does not belong to this NCP record.'],
            ]);
        }
    }
}
