<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InterventionPlanMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ncp_exposes_all_plans_and_resolves_latest_with_stable_tie_break(): void
    {
        $ncpRecord = NcpRecord::factory()->create();
        $createdAt = now()->startOfSecond();
        $first = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'created_at' => $createdAt,
        ]);
        $second = Intervention::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'created_at' => $createdAt,
        ]);

        $this->assertSame([$first->id, $second->id], $ncpRecord->interventions()->oldest('id')->pluck('id')->all());
        $this->assertTrue($ncpRecord->latestIntervention->is($second));
    }

    public function test_intervention_uses_public_uuid_and_has_one_menu_plan(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create(['patient_id' => $patient->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);
        $menuPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertTrue(Str::isUuid($intervention->uuid));
        $this->assertSame('uuid', $intervention->getRouteKeyName());
        $this->assertTrue($intervention->mealPlan->is($menuPlan));
    }

    public function test_saved_intervention_clinical_fields_are_immutable(): void
    {
        $intervention = Intervention::factory()->create(['energy_kcal' => 1800]);

        try {
            $intervention->update(['energy_kcal' => 1900]);
            $this->fail('Expected a saved Intervention Plan to reject clinical mutation.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Saved Intervention Plans are immutable. Create a new plan instead.',
                $exception->errors()['intervention'][0],
            );
        }

        $this->assertSame('1800.00', $intervention->fresh()->energy_kcal);
    }

    public function test_database_rejects_a_second_menu_plan_for_one_intervention(): void
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create(['patient_id' => $patient->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);
        MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);

        $this->expectException(QueryException::class);
        MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_legacy_revisions_and_extra_menus_become_idempotent_complete_plans(): void
    {
        $constraintMigration = require database_path('migrations/2026_09_28_214255_enforce_one_menu_plan_per_intervention.php');
        $dataMigration = require database_path('migrations/2026_09_28_214249_migrate_intervention_revisions_to_complete_plans.php');
        $constraintMigration->down();

        $patient = Patient::factory()->create();
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
        ]);
        $monitoring = $ncpRecord->monitorings()->create(['weight' => 61]);
        $initialAt = '2026-01-05 09:00:00';
        $changedAt = '2026-02-10 09:00:00';

        $legacyInterventionId = DB::table('interventions')->insertGetId([
            'uuid' => null,
            'ncp_record_id' => $ncpRecord->id,
            'source_monitoring_id' => null,
            'goal_type' => 'custom',
            'energy_kcal' => 2100,
            'protein_g' => 80,
            'carbs_g' => 280,
            'fat_g' => 70,
            'created_at' => $initialAt,
            'updated_at' => $changedAt,
        ]);

        $initialSnapshot = $this->snapshot(1800, 70);
        $changedSnapshot = $this->snapshot(2100, 80);
        $initialRevisionId = $this->insertRevision(
            $legacyInterventionId,
            $rnd->id,
            null,
            1,
            $initialAt,
            $initialSnapshot,
        );
        $changedRevisionId = $this->insertRevision(
            $legacyInterventionId,
            $rnd->id,
            $monitoring->id,
            2,
            $changedAt,
            $changedSnapshot,
        );
        $this->insertRevision(
            $legacyInterventionId,
            $rnd->id,
            $monitoring->id,
            3,
            '2026-02-15 09:00:00',
            $changedSnapshot,
        );

        $firstMenu = MealPlan::factory()->create([
            'intervention_id' => $legacyInterventionId,
            'intervention_revision_id' => $initialRevisionId,
            'patient_id' => $patient->id,
            'created_at' => '2026-01-06 09:00:00',
        ]);
        $secondMenu = MealPlan::factory()->create([
            'intervention_id' => $legacyInterventionId,
            'intervention_revision_id' => $changedRevisionId,
            'patient_id' => $patient->id,
            'created_at' => '2026-02-11 09:00:00',
        ]);
        $thirdMenu = MealPlan::factory()->create([
            'intervention_id' => $legacyInterventionId,
            'intervention_revision_id' => $changedRevisionId,
            'patient_id' => $patient->id,
            'created_at' => '2026-02-20 09:00:00',
        ]);

        $constraintRestored = false;

        try {
            $dataMigration->up();

            $plans = Intervention::query()
                ->where('ncp_record_id', $ncpRecord->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $this->assertCount(3, $plans);
            $this->assertSame($legacyInterventionId, $plans[0]->id);
            $this->assertSame('1800.00', $plans[0]->energy_kcal);
            $this->assertSame($initialAt, $plans[0]->created_at->format('Y-m-d H:i:s'));
            $this->assertNull($plans[0]->source_monitoring_id);
            $this->assertSame('2100.00', $plans[1]->energy_kcal);
            $this->assertSame($changedAt, $plans[1]->created_at->format('Y-m-d H:i:s'));
            $this->assertSame($monitoring->id, $plans[1]->source_monitoring_id);
            $this->assertSame('2100.00', $plans[2]->energy_kcal);
            $this->assertSame('2026-02-20 09:00:00', $plans[2]->created_at->format('Y-m-d H:i:s'));
            $this->assertSame($monitoring->id, $plans[2]->source_monitoring_id);
            $this->assertTrue($plans->every(fn (Intervention $plan): bool => Str::isUuid($plan->uuid)));
            $this->assertSame(3, MealPlan::query()->whereIn('id', [$firstMenu->id, $secondMenu->id, $thirdMenu->id])->distinct()->count('intervention_id'));

            $dataMigration->up();
            $this->assertSame(3, Intervention::query()->where('ncp_record_id', $ncpRecord->id)->count());

            $constraintMigration->up();
            $constraintRestored = true;
        } finally {
            if (! $constraintRestored) {
                $menuIdsToKeep = MealPlan::query()
                    ->whereIn('id', [$firstMenu->id, $secondMenu->id, $thirdMenu->id])
                    ->orderBy('id')
                    ->pluck('id')
                    ->take(1);
                MealPlan::query()
                    ->whereIn('id', [$firstMenu->id, $secondMenu->id, $thirdMenu->id])
                    ->whereNotIn('id', $menuIdsToKeep)
                    ->delete();
                DB::table('interventions')->whereNull('uuid')->update(['uuid' => (string) Str::uuid()]);
                $constraintMigration->up();
            }
        }
    }

    public function test_partial_legacy_links_preserve_explicit_nulls_and_leave_no_menu_orphans(): void
    {
        $constraintMigration = require database_path('migrations/2026_09_28_214255_enforce_one_menu_plan_per_intervention.php');
        $dataMigration = require database_path('migrations/2026_09_28_214249_migrate_intervention_revisions_to_complete_plans.php');
        $constraintMigration->down();
        $patient = Patient::factory()->create();
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
        ]);
        $constraintRestored = false;

        try {
            $unversionedId = DB::table('interventions')->insertGetId([
                'uuid' => null,
                'ncp_record_id' => $ncpRecord->id,
                'energy_kcal' => 1750,
                'protein_g' => 68,
                'created_at' => '2026-01-01 08:00:00',
                'updated_at' => '2026-01-01 08:00:00',
            ]);
            $unversionedMenus = MealPlan::factory(2)->create([
                'intervention_id' => $unversionedId,
                'intervention_revision_id' => null,
                'patient_id' => $patient->id,
            ]);

            $partialId = DB::table('interventions')->insertGetId([
                'uuid' => null,
                'ncp_record_id' => $ncpRecord->id,
                'energy_kcal' => 2200,
                'protein_g' => 999,
                'carbs_g' => 999,
                'created_at' => '2026-02-01 08:00:00',
                'updated_at' => '2026-02-01 08:00:00',
            ]);
            $revisionId = $this->insertRevision(
                $partialId,
                $rnd->id,
                null,
                1,
                '2026-02-01 08:00:00',
                ['goal_type' => 'custom', 'energy_kcal' => 1650],
            );
            $partiallyLinkedMenu = MealPlan::factory()->create([
                'intervention_id' => $partialId,
                'intervention_revision_id' => null,
                'patient_id' => $patient->id,
            ]);

            $dataMigration->up();

            $partialPlan = Intervention::query()->findOrFail($partialId);
            $this->assertSame('1650.00', $partialPlan->energy_kcal);
            $this->assertNull($partialPlan->protein_g);
            $this->assertNull($partialPlan->carbs_g);
            $this->assertSame($partialId, $partiallyLinkedMenu->fresh()->intervention_id);
            $this->assertSame(3, MealPlan::query()
                ->whereIn('id', $unversionedMenus->pluck('id')->push($partiallyLinkedMenu->id))
                ->distinct()
                ->count('intervention_id'));
            $this->assertFalse(MealPlan::query()
                ->whereIn('id', $unversionedMenus->pluck('id')->push($partiallyLinkedMenu->id))
                ->whereDoesntHave('intervention')
                ->exists());
            $this->assertTrue(Intervention::query()
                ->where('ncp_record_id', $ncpRecord->id)
                ->get()
                ->every(fn (Intervention $plan): bool => Str::isUuid($plan->uuid)));

            $dataMigration->up();
            $this->assertSame(3, Intervention::query()->where('ncp_record_id', $ncpRecord->id)->count());

            $constraintMigration->up();
            $constraintRestored = true;
            $this->assertDatabaseHas('intervention_revisions', ['id' => $revisionId]);
        } finally {
            if (! $constraintRestored) {
                DB::table('interventions')->whereNull('uuid')->update(['uuid' => (string) Str::uuid()]);
                $duplicateOwners = MealPlan::query()
                    ->select('intervention_id')
                    ->groupBy('intervention_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->pluck('intervention_id');
                foreach ($duplicateOwners as $ownerId) {
                    MealPlan::query()->where('intervention_id', $ownerId)->oldest('id')->skip(1)->delete();
                }
                $constraintMigration->up();
            }
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(int $energyKcal, int $proteinGrams): array
    {
        return [
            'goal_type' => 'custom',
            'disease_stage' => null,
            'displayed_nutrients' => ['energy', 'protein'],
            'energy_kcal' => $energyKcal,
            'protein_g' => $proteinGrams,
            'carbs_g' => 240,
            'fat_g' => 60,
            'fluid_ml' => 1800,
            'micronutrient_limits' => [],
            'education_notes' => null,
            'counseling_goals' => null,
            'barriers' => null,
            'strategies' => null,
            'session_type' => 'follow-up',
            'next_followup_date' => null,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function insertRevision(
        int $interventionId,
        int $actorId,
        ?int $monitoringId,
        int $version,
        string $effectiveAt,
        array $snapshot,
    ): int {
        return DB::table('intervention_revisions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'intervention_id' => $interventionId,
            'monitoring_id' => $monitoringId,
            'version' => $version,
            'effective_at' => $effectiveAt,
            'reason' => 'Legacy revision',
            'actor_user_id' => $actorId,
            'source' => $version === 1 ? 'initial' : 'monitoring_revision',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'created_at' => $effectiveAt,
            'updated_at' => $effectiveAt,
        ]);
    }
}
