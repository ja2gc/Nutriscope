<?php

namespace App\Services;

use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NcpAppointmentWorkflow
{
    private const CLINICAL_SECTIONS = ['assessment', 'diagnosis', 'intervention', 'monitoring'];

    public function create(User $rnd, Patient $patient, array $data): NcpAppointment
    {
        return DB::transaction(function () use ($rnd, $patient, $data): NcpAppointment {
            User::query()->whereKey($rnd->id)->lockForUpdate()->firstOrFail();
            $ncp = $this->resolveNcp($patient, $data['ncp_record_id'] ?? null);

            if ($data['source'] === 'walk_in') {
                $this->ensureNoActiveVisit($rnd);
            }

            return NcpAppointment::create([
                'patient_id' => $patient->id,
                'ncp_record_id' => $ncp?->id,
                'rnd_user_id' => $rnd->id,
                'source' => $data['source'],
                'status' => $data['source'] === 'walk_in' ? 'in_progress' : 'scheduled',
                'purpose' => trim($data['purpose']),
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'started_at' => $data['source'] === 'walk_in' ? now() : null,
            ]);
        });
    }

    public function transition(User $rnd, NcpAppointment $appointment, array $data): NcpAppointment|array|null
    {
        return DB::transaction(function () use ($rnd, $appointment, $data): NcpAppointment|array|null {
            User::query()->whereKey($rnd->id)->lockForUpdate()->firstOrFail();
            $appointment = NcpAppointment::query()->lockForUpdate()->findOrFail($appointment->id);

            return match ($data['action']) {
                'start' => $this->start($rnd, $appointment, $data['ncp_record_id'] ?? null),
                'finish' => $this->finish($appointment, 'completed'),
                'end_early' => $this->finish($appointment, 'ended_early', $data['reason_code']),
                'cancel' => $this->resolveScheduled($appointment, 'cancelled', $data['reason_code']),
                'no_show' => $this->resolveScheduled($appointment, 'no_show'),
                'reschedule' => $this->reschedule($appointment, $data),
                'discard' => $this->discard($appointment),
            };
        });
    }

    public function recordClinicalWork(User $rnd, NcpRecord $ncpRecord, string $section, bool $newlyCompleted): void
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
        $completed = $newlyCompleted
            ? array_values(array_unique([...($appointment->newly_completed ?? []), $section]))
            : ($appointment->newly_completed ?? []);

        $appointment->update(['worked_on' => $workedOn, 'newly_completed' => $completed]);
    }

    private function start(User $rnd, NcpAppointment $appointment, ?string $ncpUuid): NcpAppointment
    {
        $this->requireStatus($appointment, ['scheduled']);
        $this->ensureNoActiveVisit($rnd);
        $ncp = $ncpUuid !== null ? $this->resolveNcp($appointment->patient, $ncpUuid) : null;
        $appointment->update([
            'rnd_user_id' => $rnd->id,
            'ncp_record_id' => $appointment->ncp_record_id ?? $ncp?->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $appointment->refresh();
    }

    private function finish(NcpAppointment $appointment, string $status, ?string $reasonCode = null): NcpAppointment
    {
        $this->requireStatus($appointment, ['in_progress']);
        $appointment->update(['status' => $status, 'finished_at' => now(), 'reason_code' => $reasonCode]);

        return $appointment->refresh();
    }

    private function resolveScheduled(NcpAppointment $appointment, string $status, ?string $reasonCode = null): NcpAppointment
    {
        $this->requireStatus($appointment, ['scheduled']);
        $appointment->update(['status' => $status, 'finished_at' => now(), 'reason_code' => $reasonCode]);

        return $appointment->refresh();
    }

    private function reschedule(NcpAppointment $appointment, array $data): array
    {
        $this->requireStatus($appointment, ['scheduled']);
        $appointment->update(['status' => 'rescheduled', 'finished_at' => now()]);
        $replacement = NcpAppointment::create([
            'patient_id' => $appointment->patient_id,
            'ncp_record_id' => $appointment->ncp_record_id,
            'rnd_user_id' => $appointment->rnd_user_id,
            'rescheduled_from_id' => $appointment->id,
            'source' => 'scheduled',
            'status' => 'scheduled',
            'purpose' => trim($data['purpose']),
            'scheduled_at' => $data['scheduled_at'],
        ]);

        return ['original' => $appointment->refresh(), 'replacement' => $replacement];
    }

    private function discard(NcpAppointment $appointment): ?NcpAppointment
    {
        $this->requireStatus($appointment, ['in_progress']);
        if (($appointment->worked_on ?? []) !== []) {
            throw ValidationException::withMessages(['action' => 'A visit with saved clinical work cannot be discarded.']);
        }

        if ($appointment->source === 'walk_in') {
            $appointment->delete();

            return null;
        }

        $appointment->update(['status' => 'scheduled', 'started_at' => null]);

        return $appointment->refresh();
    }

    private function ensureNoActiveVisit(User $rnd): void
    {
        if (NcpAppointment::query()->whereBelongsTo($rnd, 'rnd')->where('status', 'in_progress')->exists()) {
            abort(409, 'Finish or resolve the active visit before starting another one.');
        }
    }

    private function resolveNcp(Patient $patient, ?string $uuid): ?NcpRecord
    {
        if ($uuid === null) {
            return null;
        }

        $ncp = $patient->ncpRecords()->where('uuid', $uuid)->first();
        if ($ncp === null) {
            throw ValidationException::withMessages(['ncp_record_id' => 'The selected NCP record does not belong to this patient.']);
        }

        return $ncp;
    }

    private function requireStatus(NcpAppointment $appointment, array $allowed): void
    {
        if (! in_array($appointment->status, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'This action is not allowed for the appointment status.']);
        }
    }
}
