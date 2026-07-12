<?php

namespace App\Policies;

use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;

class AuditPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'Admin';
    }

    public function viewClinical(User $user): bool
    {
        return $user->role === 'Admin';
    }

    public function viewSecurity(User $user): bool
    {
        return $user->role === 'Admin';
    }

    public function export(User $user): bool
    {
        return $user->role === 'Admin';
    }

    public function viewTrail(User $user, object $subject): bool
    {
        return match (true) {
            $subject instanceof Patient => $this->viewPatientTrail($user, $subject),
            $subject instanceof NcpRecord => $this->viewNcpTrail($user, $subject),
            $subject instanceof Report => $this->viewReportTrail($user, $subject),
            default => $user->role === 'Admin',
        };
    }

    public function viewReportTrail(User $user, Report $report): bool
    {
        return match ($user->role) {
            'Admin' => in_array($report->type, Report::ADMIN_ALLOWED_TYPES, true),
            'RND' => $report->user_id === $user->id || $report->type === 'accomplishment_report',
            default => false,
        };
    }

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
