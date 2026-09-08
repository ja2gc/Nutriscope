<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NcpAppointmentWorkflow
{
    private const CLINICAL_SECTIONS = ['assessment', 'diagnosis', 'intervention', 'monitoring'];

    public function __construct(
        private readonly ClinicalCompletenessService $completeness,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationLifecycleService $notifications,
    ) {}

    public function create(User $rnd, Patient $patient, array $data): NcpAppointment
    {
        return DB::transaction(function () use ($rnd, $patient, $data): NcpAppointment {
            User::query()->whereKey($rnd->id)->lockForUpdate()->firstOrFail();
            $ncp = $data['source'] === 'walk_in' ? $this->currentNcp($patient, $data['ncp_record_id'] ?? null) : null;

            if ($data['source'] === 'walk_in') {
                $this->ensureNoActiveVisit($rnd);
            }

            $appointment = $this->auditLogger->withoutModelEvents(fn (): NcpAppointment => NcpAppointment::create([
                'patient_id' => $patient->id,
                'ncp_record_id' => $ncp?->id,
                'rnd_user_id' => $rnd->id,
                'source' => $data['source'],
                'status' => $data['source'] === 'walk_in' ? 'in_progress' : 'scheduled',
                'purpose' => trim($data['purpose']),
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'started_at' => $data['source'] === 'walk_in' ? now() : null,
                'completeness_at_start' => $ncp ? $this->completenessSnapshot($ncp) : null,
            ]));
            $this->auditLogger->recordMutation(
                $data['source'] === 'walk_in' ? AuditAction::VisitStarted : AuditAction::AppointmentScheduled,
                AuditDomain::Ncp,
                $appointment,
                $data['source'] === 'walk_in'
                    ? ['source', 'status', 'patient_id', 'ncp_record_id', 'started_at']
                    : ['source', 'status', 'patient_id', 'scheduled_at'],
            );

            return $appointment;
        });
    }

    public function transition(User $rnd, NcpAppointment $appointment, array $data): NcpAppointment|array|null
    {
        return DB::transaction(function () use ($rnd, $appointment, $data): NcpAppointment|array|null {
            User::query()->whereKey($rnd->id)->lockForUpdate()->firstOrFail();
            $appointment = NcpAppointment::query()->lockForUpdate()->findOrFail($appointment->id);

            return match ($data['action']) {
                'start' => $this->start($rnd, $appointment, $data['ncp_record_id'] ?? null),
                'finish' => $this->finish($appointment, 'completed', AuditAction::VisitCompleted),
                'end_early' => $this->finish($appointment, 'ended_early', AuditAction::VisitEndedEarly, $data['reason_code']),
                'cancel' => $this->resolveScheduled($appointment, 'cancelled', AuditAction::AppointmentCancelled, $data['reason_code']),
                'no_show' => $this->resolveScheduled($appointment, 'no_show', AuditAction::AppointmentNoShow),
                'reschedule' => $this->reschedule($appointment, $data),
                'discard' => $this->discard($appointment),
            };
        });
    }

    public function recordClinicalWork(User $rnd, NcpRecord $ncpRecord, string $section): void
    {
        if (! in_array($section, self::CLINICAL_SECTIONS, true)) {
            return;
        }

        $appointment = NcpAppointment::query()
            ->whereBelongsTo($rnd, 'rnd')
            ->whereBelongsTo($ncpRecord)
            ->where('status', 'in_progress')
            ->first();

        if ($appointment === null) {
            return;
        }

        $workedOn = array_values(array_unique([...($appointment->worked_on ?? []), $section]));
        $completed = array_values(array_diff(
            $this->completenessSnapshot($ncpRecord),
            $appointment->completeness_at_start ?? [],
        ));

        $this->auditLogger->withoutModelEvents(fn () => $appointment->update(['worked_on' => $workedOn, 'newly_completed' => $completed]));
    }

    private function start(User $rnd, NcpAppointment $appointment, ?string $ncpUuid): NcpAppointment
    {
        $this->requireStatus($appointment, ['scheduled']);
        $this->ensureNoActiveVisit($rnd);
        $ncp = $this->currentNcp($appointment->patient, $ncpUuid);
        $this->auditLogger->withoutModelEvents(fn () => $appointment->update([
            'rnd_user_id' => $rnd->id,
            'ncp_record_id' => $ncp->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'completeness_at_start' => $this->completenessSnapshot($ncp),
            'newly_completed' => [],
        ]));
        $this->auditLogger->recordMutation(AuditAction::VisitStarted, AuditDomain::Ncp, $appointment, [
            'status', 'ncp_record_id', 'started_at',
        ]);
        $this->notifications->resolveAppointment($appointment);

        return $appointment->refresh();
    }

    private function finish(NcpAppointment $appointment, string $status, AuditAction $action, ?string $reasonCode = null): NcpAppointment
    {
        $this->requireStatus($appointment, ['in_progress']);
        $ncp = $appointment->ncpRecord()->firstOrFail();
        $newlyCompleted = array_values(array_diff(
            $this->completenessSnapshot($ncp),
            $appointment->completeness_at_start ?? [],
        ));
        $this->auditLogger->withoutModelEvents(fn () => $appointment->update([
            'status' => $status,
            'finished_at' => now(),
            'reason_code' => $reasonCode,
            'newly_completed' => $newlyCompleted,
        ]));
        $this->auditLogger->recordMutation($action, AuditDomain::Ncp, $appointment, [
            'status', 'finished_at', ...($reasonCode !== null ? ['reason_code'] : []), 'newly_completed',
        ]);
        $this->notifications->resolveAppointment($appointment);

        return $appointment->refresh();
    }

    private function resolveScheduled(NcpAppointment $appointment, string $status, AuditAction $action, ?string $reasonCode = null): NcpAppointment
    {
        $this->requireStatus($appointment, ['scheduled']);
        $this->auditLogger->withoutModelEvents(fn () => $appointment->update(['status' => $status, 'finished_at' => now(), 'reason_code' => $reasonCode]));
        $this->auditLogger->recordMutation($action, AuditDomain::Ncp, $appointment, ['status', 'finished_at', ...($reasonCode !== null ? ['reason_code'] : [])]);
        $this->notifications->resolveAppointment($appointment);

        return $appointment->refresh();
    }

    private function reschedule(NcpAppointment $appointment, array $data): array
    {
        $this->requireStatus($appointment, ['scheduled']);
        $this->auditLogger->withoutModelEvents(fn () => $appointment->update(['status' => 'rescheduled', 'finished_at' => now()]));
        $replacement = $this->auditLogger->withoutModelEvents(fn (): NcpAppointment => NcpAppointment::create([
            'patient_id' => $appointment->patient_id,
            'ncp_record_id' => null,
            'rnd_user_id' => $appointment->rnd_user_id,
            'rescheduled_from_id' => $appointment->id,
            'source' => 'scheduled',
            'status' => 'scheduled',
            'purpose' => trim($data['purpose']),
            'scheduled_at' => $data['scheduled_at'],
        ]));
        $this->auditLogger->recordMutation(AuditAction::AppointmentRescheduled, AuditDomain::Ncp, $appointment, ['status', 'finished_at', 'scheduled_at']);
        $this->notifications->resolveAppointment($appointment);

        return ['original' => $appointment->refresh(), 'replacement' => $replacement];
    }

    private function discard(NcpAppointment $appointment): ?NcpAppointment
    {
        $this->requireStatus($appointment, ['in_progress']);
        if (($appointment->worked_on ?? []) !== []) {
            throw ValidationException::withMessages(['action' => 'A visit with saved clinical work cannot be discarded.']);
        }

        if ($appointment->source === 'walk_in') {
            $this->auditLogger->withoutModelEvents(fn () => $appointment->delete());

            return null;
        }

        $this->auditLogger->withoutModelEvents(fn () => $appointment->update([
            'status' => 'scheduled', 'started_at' => null, 'ncp_record_id' => null,
            'completeness_at_start' => null, 'newly_completed' => null,
        ]));
        $this->notifications->reopenAppointment($appointment);

        return $appointment->refresh();
    }

    private function ensureNoActiveVisit(User $rnd): void
    {
        if (NcpAppointment::query()->whereBelongsTo($rnd, 'rnd')->where('status', 'in_progress')->exists()) {
            abort(409, 'Finish or resolve the active visit before starting another one.');
        }
    }

    private function currentNcp(Patient $patient, ?string $uuid): NcpRecord
    {
        $current = $patient->ncpRecords()->whereIn('status', ['draft', 'active'])->get();
        $ncp = $current->count() === 1 ? $current->first() : null;
        if ($ncp === null || ($uuid !== null && $ncp->uuid !== $uuid)) {
            throw ValidationException::withMessages(['ncp_record_id' => 'Select the patient current NCP cycle before starting the visit.']);
        }

        return $ncp;
    }

    /** @return string[] */
    private function completenessSnapshot(NcpRecord $ncp): array
    {
        $ncp->load(['assessment', 'diagnoses', 'intervention', 'monitorings']);

        return array_values(array_filter(self::CLINICAL_SECTIONS, fn (string $section): bool => match ($section) {
            'assessment' => $this->completeness->assessmentComplete($ncp),
            'diagnosis' => $this->completeness->diagnosisComplete($ncp),
            'intervention' => $this->completeness->interventionComplete($ncp),
            'monitoring' => $this->completeness->monitoringComplete($ncp),
        }));
    }

    private function requireStatus(NcpAppointment $appointment, array $allowed): void
    {
        if (! in_array($appointment->status, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'This action is not allowed for the appointment status.']);
        }
    }
}
