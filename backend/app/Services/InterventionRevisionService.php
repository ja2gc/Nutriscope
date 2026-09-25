<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\InterventionRevision;
use App\Models\Monitoring;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionRevisionService
{
    private const WORKFLOW_MESSAGE = 'Record prescription changes through a Monitoring follow-up.';

    /** @var list<string> */
    private const SNAPSHOT_FIELDS = [
        'goal_type', 'disease_stage', 'displayed_nutrients', 'energy_kcal',
        'protein_g', 'carbs_g', 'fat_g', 'fluid_ml', 'micronutrient_limits',
        'education_notes', 'counseling_goals', 'barriers', 'strategies',
        'session_type', 'next_followup_date',
    ];

    /** @return array<string, mixed> */
    public function snapshotFields(Intervention $intervention): array
    {
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $value = $intervention->getAttribute($field);
            $snapshot[$field] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }

        return $snapshot;
    }

    public function createInitial(Intervention $intervention, User $actor): InterventionRevision
    {
        return DB::transaction(function () use ($intervention, $actor): InterventionRevision {
            $locked = Intervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            $existing = $locked->revisions()->where('version', 1)->first();
            if ($existing) {
                return $existing;
            }

            return $locked->revisions()->create([
                'version' => 1,
                'effective_at' => $locked->created_at ?? now(),
                'reason' => 'Initial intervention',
                'actor_user_id' => $actor->getKey(),
                'source' => 'initial',
                'snapshot' => $this->snapshotFields($locked),
            ]);
        });
    }

    public function updateInitialBeforeMonitoring(
        Intervention $intervention,
        array $attributes,
        User $actor,
    ): InterventionRevision {
        return DB::transaction(function () use ($intervention, $attributes, $actor): InterventionRevision {
            $locked = Intervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            if ($locked->ncpRecord()->firstOrFail()->monitorings()->exists()) {
                throw ValidationException::withMessages(['intervention' => [self::WORKFLOW_MESSAGE]]);
            }

            $revision = $locked->revisions()->lockForUpdate()->where('version', 1)->first()
                ?? $this->createInitial($locked, $actor);

            $locked->applyRevisionAttributes($attributes);

            $revision->forceFill([
                'reason' => 'Initial intervention updated',
                'actor_user_id' => $actor->getKey(),
                'snapshot' => $this->snapshotFields($locked),
            ])->save();

            return $revision->refresh();
        });
    }

    public function reviseFromMonitoring(
        Intervention $intervention,
        Monitoring $monitoring,
        array $attributes,
        User $actor,
        string $reason,
    ): InterventionRevision {
        return DB::transaction(function () use ($intervention, $monitoring, $attributes, $actor, $reason): InterventionRevision {
            $locked = Intervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            if ($monitoring->ncp_record_id !== $locked->ncp_record_id) {
                throw ValidationException::withMessages([
                    'monitoring' => ['The monitoring record does not belong to this intervention.'],
                ]);
            }

            $nextVersion = ((int) $locked->revisions()->lockForUpdate()->max('version')) + 1;
            $locked->applyRevisionAttributes($attributes);

            $revision = $locked->revisions()->create([
                'monitoring_id' => $monitoring->getKey(),
                'version' => $nextVersion,
                'effective_at' => $monitoring->created_at ?? now(),
                'reason' => $reason,
                'actor_user_id' => $actor->getKey(),
                'source' => 'monitoring',
                'snapshot' => $this->snapshotFields($locked),
            ]);

            $monitoring->forceFill(['intervention_revision_id' => $revision->getKey()])->save();

            return $revision;
        });
    }

    public function activeFor(Intervention $intervention): InterventionRevision
    {
        return DB::transaction(function () use ($intervention): InterventionRevision {
            $locked = Intervention::query()->lockForUpdate()->findOrFail($intervention->getKey());
            $revision = $locked->revisions()->orderByDesc('version')->first();
            if ($revision) {
                return $revision;
            }

            $actorId = $locked->ncpRecord()->value('rnd_user_id');

            return $locked->revisions()->create([
                'version' => 1,
                'effective_at' => $locked->created_at ?? now(),
                'reason' => 'Legacy intervention baseline',
                'actor_user_id' => $actorId,
                'source' => 'legacy_baseline',
                'snapshot' => $this->snapshotFields($locked),
            ]);
        });
    }
}
