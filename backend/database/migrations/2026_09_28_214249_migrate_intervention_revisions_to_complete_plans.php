<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SNAPSHOT_FIELDS = [
        'goal_type',
        'disease_stage',
        'displayed_nutrients',
        'energy_kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fluid_ml',
        'micronutrient_limits',
        'education_notes',
        'counseling_goals',
        'barriers',
        'strategies',
        'session_type',
        'next_followup_date',
    ];

    private const JSON_FIELDS = [
        'displayed_nutrients',
        'micronutrient_limits',
    ];

    public function up(): void
    {
        DB::table('interventions')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function ($interventions): void {
                foreach ($interventions as $intervention) {
                    DB::transaction(function () use ($intervention): void {
                        $lockedIntervention = DB::table('interventions')
                            ->where('id', $intervention->id)
                            ->whereNull('uuid')
                            ->lockForUpdate()
                            ->first();

                        if ($lockedIntervention === null) {
                            return;
                        }

                        $planByRevision = $this->convertRevisions($lockedIntervention);
                        $this->separateMenus($lockedIntervention->id, $planByRevision);
                    }, 3);
                }
            });
    }

    public function down(): void
    {
        // Historical complete-plan conversion is intentionally forward-only.
    }

    /** @return array<int, int> */
    private function convertRevisions(object $intervention): array
    {
        $revisions = DB::table('intervention_revisions')
            ->where('intervention_id', $intervention->id)
            ->orderBy('version')
            ->orderBy('id')
            ->get();

        if ($revisions->isEmpty()) {
            DB::table('interventions')->where('id', $intervention->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);

            return [];
        }

        $planByHash = [];
        $planByRevision = [];

        foreach ($revisions as $index => $revision) {
            $snapshot = $this->normalizeSnapshot($revision->snapshot);
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

            if (isset($planByHash[$hash])) {
                $planByRevision[$revision->id] = $planByHash[$hash];

                continue;
            }

            $effectiveAt = $revision->effective_at ?? $revision->created_at ?? $intervention->created_at;
            $attributes = $this->databaseAttributes($snapshot) + [
                'uuid' => (string) Str::uuid(),
                'ncp_record_id' => $intervention->ncp_record_id,
                'source_monitoring_id' => $revision->monitoring_id,
                'updated_at' => $effectiveAt,
            ];

            if ($index === 0) {
                DB::table('interventions')->where('id', $intervention->id)->update($attributes);
                $planId = $intervention->id;
            } else {
                $planId = DB::table('interventions')->insertGetId($attributes + [
                    'created_at' => $effectiveAt,
                ]);
            }

            $planByHash[$hash] = $planId;
            $planByRevision[$revision->id] = $planId;
        }

        return $planByRevision;
    }

    /** @param array<int, int> $planByRevision */
    private function separateMenus(int $legacyInterventionId, array $planByRevision): void
    {
        $fallbackPlanId = $planByRevision === []
            ? $legacyInterventionId
            : Arr::last($planByRevision);
        $occupiedPlanIds = [];
        $mealPlans = DB::table('meal_plans')
            ->where('intervention_id', $legacyInterventionId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($mealPlans as $mealPlan) {
            $targetPlanId = $mealPlan->intervention_revision_id !== null
                ? ($planByRevision[$mealPlan->intervention_revision_id] ?? $fallbackPlanId)
                : $fallbackPlanId;

            if (isset($occupiedPlanIds[$targetPlanId])) {
                $targetPlanId = $this->clonePlanForMenu($targetPlanId, $mealPlan->created_at);
            }

            DB::table('meal_plans')->where('id', $mealPlan->id)->update([
                'intervention_id' => $targetPlanId,
            ]);
            $occupiedPlanIds[$targetPlanId] = true;
        }
    }

    private function clonePlanForMenu(int $sourcePlanId, mixed $createdAt): int
    {
        $sourcePlan = DB::table('interventions')->where('id', $sourcePlanId)->first();
        $snapshot = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $sourcePlan->{$field};
        }

        return DB::table('interventions')->insertGetId($this->databaseAttributes($snapshot) + [
            'uuid' => (string) Str::uuid(),
            'ncp_record_id' => $sourcePlan->ncp_record_id,
            'source_monitoring_id' => $sourcePlan->source_monitoring_id,
            'created_at' => $createdAt ?? $sourcePlan->created_at,
            'updated_at' => $createdAt ?? $sourcePlan->updated_at,
        ]);
    }

    /** @return array<string, mixed> */
    private function normalizeSnapshot(mixed $encodedSnapshot): array
    {
        $decodedSnapshot = is_array($encodedSnapshot)
            ? $encodedSnapshot
            : json_decode((string) $encodedSnapshot, true, flags: JSON_THROW_ON_ERROR);
        $snapshot = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $decodedSnapshot[$field] ?? null;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function databaseAttributes(array $snapshot): array
    {
        foreach (self::JSON_FIELDS as $field) {
            $snapshot[$field] = $snapshot[$field] === null
                ? null
                : (is_string($snapshot[$field]) ? $snapshot[$field] : json_encode($snapshot[$field], JSON_THROW_ON_ERROR));
        }

        return Arr::only($snapshot, self::SNAPSHOT_FIELDS);
    }
};
