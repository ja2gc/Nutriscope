<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\Diagnosis;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuditFixture;
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
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->uuid)
            ->assertJsonPath('data.ncp_record_id', null)
            ->assertJsonPath('data.source', 'scheduled')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.purpose', 'Review diagnosis and begin intervention');

        $this->assertDatabaseHas('ncp_appointments', [
            'patient_id' => $patient->id,
            'ncp_record_id' => null,
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
            ],
        );

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/ncp-appointments/active')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$created->json('data.id')}",
            ['action' => 'start'],
        )->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.ncp_record_id', $ncp->uuid);
    }

    public function test_walk_in_and_scheduled_start_require_the_single_current_cycle(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $past = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'completed',
        ]);

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'Past cycle attempt', 'ncp_record_id' => $past->uuid],
        )->assertUnprocessable()->assertJsonValidationErrors('ncp_record_id');

        $scheduled = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'scheduled', 'purpose' => 'Needs current cycle', 'scheduled_at' => now()->addDay()],
        )->assertCreated()->json('data.id');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}",
            ['action' => 'start'],
        )->assertUnprocessable()->assertJsonValidationErrors('ncp_record_id');
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
            ->assertJsonPath('data.replacement.rescheduled_from_id', $original)
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

    public function test_empty_scheduled_start_returns_to_scheduled_but_saved_work_blocks_discard(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $scheduled = $this->schedule($rnd, $patient, $ncp, 'Review assessment');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'start'],
        )->assertOk();
        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'discard'],
        )->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'start'],
        )->assertOk();
        NcpAppointment::where('uuid', $scheduled)->update(['worked_on' => ['assessment']]);

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'discard'],
        )->assertUnprocessable();
    }

    public function test_due_notification_remains_actionable_until_the_started_visit_is_finished(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $scheduled = $this->schedule($rnd, $patient, $ncp, 'Due visit');
        $appointment = NcpAppointment::where('uuid', $scheduled)->firstOrFail();
        $notification = Notification::factory()->for($rnd)->create([
            'type' => 'appointment_due',
            'source_module' => 'ncp_appointment',
            'source_id' => $appointment->id,
        ]);

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'start'],
        )->assertOk();
        $this->assertNull($notification->fresh()->resolved_at);

        $this->actingAs($rnd, 'sanctum')->deleteJson(
            "/api/notifications/{$notification->uuid}",
        )->assertUnprocessable();

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'finish'],
        )->assertOk();
        $this->assertNotNull($notification->fresh()->resolved_at);

        $this->actingAs($rnd, 'sanctum')->deleteJson(
            "/api/notifications/{$notification->uuid}",
        )->assertOk();
    }

    public function test_finish_recomputes_newly_completed_against_start_snapshot(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $scheduled = $this->schedule($rnd, $patient, $ncp, 'Diagnosis work');

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'start'],
        )->assertOk();

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'problem' => 'Inadequate energy intake',
            'etiology' => 'reduced appetite',
            'signs_symptoms' => 'intake below estimated needs',
            'pes_statement' => 'Inadequate energy intake related to reduced appetite as evidenced by intake below estimated needs',
        ]);
        NcpAppointment::where('uuid', $scheduled)->update([
            'worked_on' => ['diagnosis'],
            'newly_completed' => ['diagnosis'],
        ]);
        $diagnosis->delete();

        $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'finish'],
        )->assertOk()->assertJsonPath('data.newly_completed', []);
    }

    public function test_another_rnd_cannot_transition_an_appointment(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        $scheduled = $this->schedule($rnd, $patient, $ncp, 'Owner only');

        $this->actingAs(User::factory()->rnd()->create(), 'sanctum')->patchJson(
            "/api/rnd/ncp-appointments/{$scheduled}", ['action' => 'start'],
        )->assertNotFound();
    }

    public function test_patient_history_is_five_per_page(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        NcpAppointment::factory()->count(6)->create([
            'patient_id' => $patient->id,
            'ncp_record_id' => null,
            'rnd_user_id' => $rnd->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/appointments")
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 6);
    }

    public function test_dashboard_queue_uses_scheduled_appointments_and_keeps_overdue_until_resolved(): void
    {
        [$rnd, $patient] = $this->clinicalContext();
        $overdue = NcpAppointment::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->subDay(),
        ]);
        NcpAppointment::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'cancelled',
            'scheduled_at' => now()->subDay(),
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson('/api/rnd/ncp-appointments/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->uuid);
    }

    public function test_patient_appointment_history_filters_upcoming_and_past_and_names_administering_rnd(): void
    {
        [$rnd, $patient, $ncp] = $this->clinicalContext();
        NcpAppointment::factory()->create([
            'patient_id' => $patient->id,
            'ncp_record_id' => null,
            'rnd_user_id' => $rnd->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);
        $past = NcpAppointment::factory()->create([
            'patient_id' => $patient->id,
            'ncp_record_id' => $ncp->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'completed',
            'scheduled_at' => now()->subDay(),
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/appointments?scope=upcoming")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'scheduled')
            ->assertJsonPath('data.0.administered_by', null);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/appointments?scope=past&appointment_id={$past->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $past->uuid)
            ->assertJsonPath('data.0.ncp_record_id', $ncp->uuid)
            ->assertJsonPath('data.0.administered_by.display_name', $rnd->display_name);
    }

    public function test_appointment_actions_emit_one_semantic_sanitized_audit_event_with_actor(): void
    {
        [$rnd, $patient] = $this->clinicalContext();
        AuditFixture::delete(AuditActivity::query());

        $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            [
                'source' => 'scheduled',
                'purpose' => 'Private purpose sentinel',
                'scheduled_at' => now()->addDay(),
            ],
        )->assertCreated();

        $event = AuditActivity::query()->sole();
        $this->assertSame('appointment_scheduled', $event->event);
        $this->assertSame($rnd->id, $event->causer_id);
        $this->assertStringNotContainsString('Private purpose sentinel', $event->properties->toJson());
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
            ],
        )->assertCreated()->json('data.id');
    }
}
