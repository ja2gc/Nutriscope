<?php

namespace Tests\Feature;

use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcpAppointmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_can_schedule_a_patient_appointment_with_written_purpose(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();

        $response = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            [
                'source' => 'scheduled',
                'purpose' => 'Review diagnosis and begin intervention',
                'scheduled_at' => '2026-09-15 10:00:00',
                'ncp_record_id' => $ncp->uuid,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->uuid)
            ->assertJsonPath('data.ncp_record_id', $ncp->uuid)
            ->assertJsonPath('data.source', 'scheduled')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.purpose', 'Review diagnosis and begin intervention');

        $this->assertDatabaseHas('ncp_appointments', [
            'patient_id' => $patient->id,
            'ncp_record_id' => $ncp->id,
            'rnd_user_id' => $rnd->id,
            'source' => 'scheduled',
            'status' => 'scheduled',
            'purpose' => 'Review diagnosis and begin intervention',
        ]);
    }

    public function test_walk_in_starts_immediately_and_is_returned_as_the_active_visit(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();

        $created = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            [
                'source' => 'walk_in',
                'purpose' => 'Unscheduled nutrition consultation',
                'ncp_record_id' => $ncp->uuid,
            ],
        );

        $created->assertCreated()
            ->assertJsonPath('data.source', 'walk_in')
            ->assertJsonPath('data.status', 'in_progress');

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/ncp-appointments/active')
            ->assertOk()
            ->assertJsonPath('data.id', $created->json('data.id'))
            ->assertJsonPath('data.patient.display_name', $patient->display_name);
    }

    public function test_rnd_user_cannot_have_two_active_visits(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $otherPatient = Patient::factory()->create();
        $otherNcp = NcpRecord::factory()->create([
            'patient_id' => $otherPatient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'First visit', 'ncp_record_id' => $ncp->uuid],
        )->assertCreated();

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$otherPatient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'Second visit', 'ncp_record_id' => $otherNcp->uuid],
        )->assertStatus(409)
            ->assertJsonPath('message', 'Finish or resolve the active visit before starting another one.');
    }

    public function test_scheduled_visit_requires_an_explicit_start(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();

        $created = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            [
                'source' => 'scheduled',
                'purpose' => 'Continue assessment',
                'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
                'ncp_record_id' => $ncp->uuid,
            ],
        );

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/ncp-appointments/active')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$created->json('data.id')}",
            ['action' => 'start'],
        )->assertOk()->assertJsonPath('data.status', 'in_progress');
    }

    public function test_cancel_and_no_show_are_distinct_and_cancel_requires_a_reason(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $cancelled = $this->schedule($rnd, $patient, $ncp, 'Cancellation case');
        $noShow = $this->schedule($rnd, $patient, $ncp, 'No-show case');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$cancelled}",
            ['action' => 'cancel'],
        )->assertUnprocessable()->assertJsonValidationErrors('reason_code');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$cancelled}",
            ['action' => 'cancel', 'reason_code' => 'patient_requested'],
        )->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$noShow}",
            ['action' => 'no_show'],
        )->assertOk()->assertJsonPath('data.status', 'no_show');
    }

    public function test_reschedule_preserves_old_record_and_creates_a_linked_scheduled_record(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $original = $this->schedule($rnd, $patient, $ncp, 'Monitoring follow-up');

        $response = $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$original}",
            [
                'action' => 'reschedule',
                'scheduled_at' => '2026-09-29 14:00:00',
                'purpose' => 'Monitoring follow-up after laboratory results',
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.original.status', 'rescheduled')
            ->assertJsonPath('data.replacement.status', 'scheduled')
            ->assertJsonPath('data.replacement.purpose', 'Monitoring follow-up after laboratory results');

        $this->assertDatabaseHas('ncp_appointments', ['uuid' => $original, 'status' => 'rescheduled']);
        $this->assertDatabaseCount('ncp_appointments', 2);
    }

    public function test_empty_mistaken_start_can_be_discarded(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();

        $walkIn = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'Mistaken start', 'ncp_record_id' => $ncp->uuid],
        )->json('data.id');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$walkIn}",
            ['action' => 'discard'],
        )->assertNoContent();

        $this->assertDatabaseMissing('ncp_appointments', ['uuid' => $walkIn]);
    }

    private function clinicalContext(): array
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);

        return [$rnd, $patient, $ncp];
    }

    private function schedule(User $rnd, Patient $patient, NcpRecord $ncp, string $purpose): string
    {
        return (string) $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            [
                'source' => 'scheduled',
                'purpose' => $purpose,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ncp_record_id' => $ncp->uuid,
            ],
        )->assertCreated()->json('data.id');
    }
}
