<?php

namespace App\Policies;

use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;

class AuditPolicy
{
    public function viewPatientTrail(User $user, Patient $patient): bool
    {
        return $user->role === 'RND'
            && ($patient->ncpRecords()->where('rnd_user_id', $user->id)->exists()
                || AuditActivity::query()
                    ->auditOnly()
                    ->where('audit_owner_id', $user->id)
                    ->where('root_patient_id', $patient->id)
                    ->exists());
    }

    public function viewNcpTrail(User $user, NcpRecord $ncpRecord): bool
    {
        return $user->role === 'RND' && $ncpRecord->rnd_user_id === $user->id;
    }
}
