<?php

namespace App\Services\Audit;

use App\Enums\AuditCategory;
use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class ClinicalAttributionService
{
    public function __construct(private readonly AuditEventPresenter $presenter) {}

    /** @param Collection<int, Patient> $patients */
    public function decoratePatients(Collection $patients): void
    {
        $records = $patients->flatMap(fn (Patient $patient) => $patient->ncpRecords)->values();
        $this->decorateNcpRecords($records);

        $activities = $this->latestActivities('root_patient_id', $patients->modelKeys());
        foreach ($patients as $patient) {
            $latestRecord = $patient->ncpRecords->first();
            $patient->setAttribute('latest_ncp_created_by', $latestRecord?->getAttribute('created_by'));
            $patient->setAttribute(
                'last_clinical_action',
                $this->attribution($activities->get((int) $patient->getKey())),
            );
        }
    }

    /** @param Collection<int, NcpRecord> $records */
    public function decorateNcpRecords(Collection $records): void
    {
        $activities = $this->latestActivities('ncp_record_id', $records->pluck('id')->all());
        $creators = User::withTrashed()
            ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role')
            ->whereIn('id', $records->pluck('rnd_user_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach ($records as $record) {
            $creator = $creators->get($record->rnd_user_id);
            $record->setAttribute('created_by', $creator instanceof User ? [
                'id' => $creator->uuid,
                'kind' => 'user',
                'name' => $creator->display_name,
                'role' => $creator->role,
            ] : null);
            $record->setAttribute(
                'last_clinical_action',
                $this->attribution($activities->get((int) $record->getKey())),
            );
        }
    }

    /** @param list<int|string> $keys
     * @return Collection<int, AuditActivity>
     */
    private function latestActivities(string $groupColumn, array $keys): Collection
    {
        if ($keys === []) {
            return new Collection;
        }

        $latestIds = AuditActivity::query()
            ->auditOnly()
            ->forCategory(AuditCategory::Clinical)
            ->whereIn($groupColumn, $keys)
            ->selectRaw("MAX(id) AS id, {$groupColumn}")
            ->groupBy($groupColumn)
            ->pluck('id');

        return AuditActivity::query()
            ->whereIn('id', $latestIds)
            ->with(['causer' => function (MorphTo $relation): void {
                $relation->constrain([
                    User::class => fn (Builder $query): Builder => $query
                        ->withTrashed()
                        ->select('id', 'uuid', 'name', 'first_name', 'last_name', 'role'),
                ]);
            }])
            ->get()
            ->keyBy(fn (AuditActivity $activity): int => (int) $activity->getAttribute($groupColumn));
    }

    /** @return array{actor: array{id: ?string, kind: string, name: string, role: ?string}|null, occurred_at: string}|null */
    private function attribution(?AuditActivity $activity): ?array
    {
        if ($activity === null) {
            return null;
        }

        $event = $this->presenter->present($activity);

        return ['actor' => $event->actor, 'occurred_at' => $event->occurredAt];
    }
}
