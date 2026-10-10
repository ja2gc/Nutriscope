<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationLifecycleService;
use Carbon\CarbonImmutable;
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
        Assessment::factory()->create([
            'ncp_record_id' => $ncp->id,
            'weight' => 70,
            'height' => 170,
            'edema_present' => false,
            'physical_activity_level' => 'light',
            'pregnancy_lactation_status' => 'none',
            'allergies' => ['shellfish'],
            'dietary_restrictions' => 'Low sodium',
            'food_dislikes' => ['liver'],
        ]);
        Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'custom',
            'disease_stage' => null,
            'energy_kcal' => 1800,
        ]);

        return $ncp;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'observed_at' => now()->toDateString(),
            'visit_type' => 'scheduled_follow_up',
            'weight' => 72,
            'height' => 170,
            'edema_present' => false,
            'dry_weight_kg' => null,
            'physical_activity_level' => 'moderate',
            'pregnancy_lactation_status' => 'none',
            'allergies' => ['shellfish'],
            'dietary_restrictions' => 'Low sodium',
            'food_dislikes' => ['liver'],
            'lab_values' => [
                'glucose' => 105,
                'energy_kcal' => 1750,
                'protein_g' => 72,
            ],
            'intake_notes' => 'Good adherence',
            'symptoms' => 'No nausea',
            'goal_achievement' => [
                'compliance' => 'mostly_compliant',
                'gi_tolerance' => 'tolerating',
                'continuation_decision' => 'continue',
            ],
            'clinical_summary' => 'Progressing toward goals.',
            'next_monitoring_date' => now()->addWeeks(2)->toDateString(),
            ...$overrides,
        ];
    }

    public function test_context_prefills_first_visit_from_assessment(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/context")
            ->assertOk()
            ->assertJsonPath('data.source_type', 'assessment')
            ->assertJsonPath('data.source_monitoring_id', null)
            ->assertJsonPath('data.weight', '70.00')
            ->assertJsonPath('data.height', '170.00')
            ->assertJsonPath('data.allergies.0', 'shellfish')
            ->assertJsonPath('data.sex', 'Male');
    }

    public function test_store_persists_complete_effective_snapshot_and_derives_bmi(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [
                ...$this->payload(),
                'bmi' => 99,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.weight', '72.00')
            ->assertJsonPath('data.height', '170.00')
            ->assertJsonPath('data.bmi', '24.91')
            ->assertJsonPath('data.visit_type', 'scheduled_follow_up')
            ->assertJsonPath('data.goal_achievement.continuation_decision', 'continue')
            ->assertJsonMissingPath('data.ncp_record_id')
            ->assertJsonMissingPath('data.intervention_revision');
        $this->assertDatabaseCount('intervention_revisions', 0);
        $this->assertSame('24.91', Monitoring::sole()->bmi);
    }

    public function test_store_requires_complete_calculation_snapshot(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'observed_at', 'visit_type', 'weight', 'height', 'edema_present',
                'physical_activity_level', 'pregnancy_lactation_status', 'allergies',
                'dietary_restrictions', 'food_dislikes',
            ]);
    }

    public function test_edema_requires_dry_weight_and_future_observation_is_rejected(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $this->payload([
                'observed_at' => CarbonImmutable::now('Asia/Manila')->addDay()->toDateString(),
                'edema_present' => true,
                'dry_weight_kg' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['observed_at', 'dry_weight_kg']);
    }

    public function test_visit_on_current_manila_date_is_accepted_before_utc_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 16:30:00', 'UTC'));
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $this->payload([
                'observed_at' => '2026-10-05',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.observed_at', '2026-10-05');
    }

    public function test_nested_intervention_revision_is_rejected_without_partial_write(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $this->payload([
                'intervention_revision' => ['reason' => 'Retired workflow'],
                'intervention_revision_id' => 123,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['intervention_revision', 'intervention_revision_id']);

        $this->assertDatabaseCount('monitorings', 0);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_update_replaces_complete_snapshot_and_recalculates_bmi(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $monitoring = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            ...$this->payload(),
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson(
                "/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}",
                $this->payload(['weight' => 64, 'height' => 160]),
            )
            ->assertOk()
            ->assertJsonPath('data.bmi', '25.00');

        $this->assertSame('25.00', $monitoring->fresh()->bmi);
    }

    public function test_failure_after_save_rolls_back_complete_snapshot(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $notificationLifecycle = Mockery::mock(NotificationLifecycleService::class);
        $notificationLifecycle->shouldReceive('resolveFollowUp')->once()->andThrow(new \RuntimeException('notification failure'));
        $this->app->instance(NotificationLifecycleService::class, $notificationLifecycle);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($rnd, 'sanctum')
                ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $this->payload());
            $this->fail('Expected notification failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('notification failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_monitoring_is_blocked_before_first_intervention_plan(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_list_update_and_delete_are_scoped_to_parent_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $other = $this->ncpRecord($patient, $rnd);
        $monitoring = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            ...$this->payload(),
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->patchJson(
            "/api/rnd/ncp-records/{$other->uuid}/monitorings/{$monitoring->uuid}",
            $this->payload(),
        )->assertNotFound();
        $this->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoring->uuid}")
            ->assertNoContent();
        $this->assertModelMissing($monitoring);
    }

    public function test_monitoring_rejects_oversized_notes_summaries_and_out_of_range_labs(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/monitorings";

        foreach ([
            [['intake_notes' => str_repeat('x', 401)], 'intake_notes'],
            [['symptoms' => str_repeat('x', 401)], 'symptoms'],
            [['clinical_summary' => str_repeat('x', 3001)], 'clinical_summary'],
            [['lab_values' => ['glucose' => 2001]], 'lab_values.glucose'],
        ] as [$override, $field]) {
            $this->actingAs($rnd, 'sanctum')
                ->postJson($url, $this->payload($override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }
}
