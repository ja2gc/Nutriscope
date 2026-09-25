<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class NcpMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name' => 'RND',
            'email' => 'rnd'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'RND',
            'is_active' => true,
        ]);
    }

    private function patient(): Patient
    {
        return Patient::forceCreate([
            'name' => 'Test Patient',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function ncpRecord(Patient $patient, User $rnd): NcpRecord
    {
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'active',
        ]);

        // Monitoring is follow-up only — give the record a care plan (intervention)
        // so it represents a patient already past the first encounter.
        Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'energy_kcal' => 1800.0]);

        return $ncp;
    }

    private function completeRevisionSnapshot(array $overrides = []): array
    {
        return [
            'goal_type' => 'custom',
            'disease_stage' => null,
            'displayed_nutrients' => ['energy', 'protein', 'carbs', 'fat'],
            'energy_kcal' => 2000,
            'protein_g' => 80,
            'carbs_g' => 260,
            'fat_g' => 65,
            'fluid_ml' => 2100,
            'micronutrient_limits' => [],
            'education_notes' => 'Updated education',
            'counseling_goals' => 'Updated counseling goal',
            'barriers' => 'Shift schedule',
            'strategies' => 'Prepare meals ahead',
            'session_type' => 'follow-up',
            'next_followup_date' => '2026-10-15',
            ...$overrides,
        ];
    }

    // ──────────────────────────────────────────────────
    // Monitoring & Evaluation
    // ──────────────────────────────────────────────────

    public function test_rnd_can_log_monitoring_entry(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'weight' => 72.0,
                'intake_notes' => 'good adherence',
                'symptoms' => 'Patient improving',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ncp_record_id', $ncp->id)
            ->assertJsonPath('data.weight', '72.00');

        $this->assertDatabaseHas('monitorings', [
            'ncp_record_id' => $ncp->id,
            'weight' => 72.0,
        ]);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_monitoring_revision_requires_effective_date_reason_and_complete_snapshot(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'weight' => 72,
                'intervention_revision' => [
                    'snapshot' => $this->completeRevisionSnapshot(['energy_kcal' => null]),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'intervention_revision.effective_date',
                'intervention_revision.reason',
                'intervention_revision.snapshot.energy_kcal',
            ]);

        $this->assertDatabaseMissing('monitorings', ['ncp_record_id' => $ncp->id]);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_monitoring_can_atomically_create_revision_and_update_current_intervention(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'weight' => 72,
                'intervention_revision' => [
                    'effective_date' => '2026-09-25',
                    'reason' => 'Increased needs after reassessment',
                    'snapshot' => $this->completeRevisionSnapshot(),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.intervention_revision.version', 2)
            ->assertJsonPath('data.intervention_revision.reason', 'Increased needs after reassessment')
            ->assertJsonPath('data.intervention_revision.effective_at', '2026-09-25T00:00:00.000000Z');

        $monitoring = Monitoring::where('ncp_record_id', $ncp->id)->firstOrFail();
        $this->assertNotNull($monitoring->intervention_revision_id);
        $this->assertSame('2000.00', $ncp->intervention->fresh()->energy_kcal);
        $this->assertDatabaseHas('intervention_revisions', [
            'intervention_id' => $ncp->intervention->id,
            'monitoring_id' => $monitoring->id,
            'version' => 2,
            'source' => 'monitoring_revision',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings")
            ->assertOk()
            ->assertJsonPath('data.0.intervention_revision.version', 2)
            ->assertJsonPath('data.0.intervention_revision.reason', 'Increased needs after reassessment');
    }

    public function test_failure_after_revision_rolls_back_monitoring_current_row_and_history(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $notificationLifecycle = Mockery::mock(NotificationLifecycleService::class);
        $notificationLifecycle->shouldReceive('resolveFollowUp')->once()->andThrow(new \RuntimeException('notification failure'));
        $this->app->instance(NotificationLifecycleService::class, $notificationLifecycle);

        try {
            $this->actingAs($rnd, 'sanctum')
                ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                    'weight' => 72,
                    'intervention_revision' => [
                        'effective_date' => '2026-09-25',
                        'reason' => 'Must roll back',
                        'snapshot' => $this->completeRevisionSnapshot(),
                    ],
                ]);
        } catch (\RuntimeException $exception) {
            $this->assertSame('notification failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('monitorings', ['ncp_record_id' => $ncp->id]);
        $this->assertDatabaseCount('intervention_revisions', 0);
        $this->assertSame('1800.00', $ncp->intervention->fresh()->energy_kcal);
    }

    public function test_existing_monitoring_can_receive_only_one_revision(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $monitoring = Monitoring::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70]);
        $payload = [
            'intervention_revision' => [
                'effective_date' => '2026-09-25',
                'reason' => 'Follow-up revision',
                'snapshot' => $this->completeRevisionSnapshot(),
            ],
        ];

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}", $payload)
            ->assertOk()
            ->assertJsonPath('data.intervention_revision.version', 2);

        $payload['intervention_revision']['reason'] = 'Duplicate revision';
        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('intervention_revision');

        $this->assertSame(2, $ncp->intervention->revisions()->count());
        $this->assertDatabaseMissing('intervention_revisions', ['reason' => 'Duplicate revision']);
    }

    public function test_monitoring_does_not_accept_legacy_next_visit_date(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'weight' => 72.0,
                'next_monitoring_date' => now()->addWeek()->toDateString(),
            ]);

        $response->assertCreated()->assertJsonPath('data.next_monitoring_date', null);
        $this->assertDatabaseHas('monitorings', [
            'ncp_record_id' => $ncp->id,
            'next_monitoring_date' => null,
        ]);
    }

    public function test_monitoring_blocked_on_first_encounter_without_care_plan(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        // Bare record with NO intervention (first encounter, plan not done yet).
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", ['weight' => 70.0])
            ->assertStatus(422);

        $this->assertDatabaseMissing('monitorings', ['ncp_record_id' => $ncp->id]);
    }

    public function test_rnd_can_list_monitoring_entries(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes' => 'fair',
        ]);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes' => 'poor',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_monitoring_weight_validation(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'weight' => 'invalid-weight',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_monitoring_rejects_non_numeric_electrolyte_labs(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                'lab_values' => ['phosphate' => 'not-a-number'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lab_values.phosphate']);
    }

    public function test_rnd_can_update_monitoring_entry(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $monitoring = Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes' => 'fair',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}", [
                'intake_notes' => 'good',
                'symptoms' => 'Progress noted',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.intake_notes', 'good');
    }

    public function test_monitoring_update_rejects_non_numeric_electrolyte_labs(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $monitoring = Monitoring::forceCreate(['ncp_record_id' => $ncp->id]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}", [
                'lab_values' => ['magnesium' => 'not-a-number'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lab_values.magnesium']);
    }

    public function test_rnd_can_delete_monitoring_entry(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $monitoring = Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('monitorings', ['id' => $monitoring->id]);
    }

    public function test_monitoring_entries_are_scoped_to_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp1 = $this->ncpRecord($patient, $rnd);
        $ncp2 = $this->ncpRecord($patient, $rnd);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp2->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp1->uuid}/monitorings");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
