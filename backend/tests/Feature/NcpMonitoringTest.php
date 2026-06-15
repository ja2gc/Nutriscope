<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NcpMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name'      => 'RND',
            'email'     => 'rnd' . uniqid() . '@example.com',
            'password'  => Hash::make('password'),
            'role'      => 'RND',
            'is_active' => true,
        ]);
    }

    private function patient(): Patient
    {
        return Patient::forceCreate([
            'name'           => 'Test Patient',
            'dob'            => '1990-01-01',
            'sex'            => 'Male',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function ncpRecord(Patient $patient, User $rnd): NcpRecord
    {
        $ncp = NcpRecord::forceCreate([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type'        => 'new',
            'status'      => 'active',
        ]);

        // Monitoring is follow-up only — give the record a care plan (intervention)
        // so it represents a patient already past the first encounter.
        Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'energy_kcal' => 1800.0]);

        return $ncp;
    }

    // ──────────────────────────────────────────────────
    // Monitoring & Evaluation
    // ──────────────────────────────────────────────────

    public function test_rnd_can_log_monitoring_entry(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/monitorings", [
                'weight'       => 72.0,
                'intake_notes' => 'good adherence',
                'symptoms'     => 'Patient improving',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ncp_record_id', $ncp->id)
            ->assertJsonPath('data.weight', '72.00');

        $this->assertDatabaseHas('monitorings', [
            'ncp_record_id' => $ncp->id,
            'weight'        => 72.0,
        ]);
    }

    public function test_monitoring_blocked_on_first_encounter_without_care_plan(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        // Bare record with NO intervention (first encounter, plan not done yet).
        $ncp     = NcpRecord::forceCreate([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type'        => 'new',
            'status'      => 'draft',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/monitorings", ['weight' => 70.0])
            ->assertStatus(422);

        $this->assertDatabaseMissing('monitorings', ['ncp_record_id' => $ncp->id]);
    }

    public function test_rnd_can_list_monitoring_entries(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes'  => 'fair',
        ]);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes'  => 'poor',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/monitorings");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_monitoring_weight_validation(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/monitorings", [
                'weight' => 'invalid-weight',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_rnd_can_update_monitoring_entry(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $monitoring = Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
            'intake_notes'  => 'fair',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/monitorings/{$monitoring->id}", [
                'intake_notes' => 'good',
                'symptoms'     => 'Progress noted',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.intake_notes', 'good');
    }

    public function test_rnd_can_delete_monitoring_entry(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $monitoring = Monitoring::forceCreate([
            'ncp_record_id' => $ncp->id,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->id}/monitorings/{$monitoring->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('monitorings', ['id' => $monitoring->id]);
    }

    public function test_monitoring_entries_are_scoped_to_ncp_record(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp1    = $this->ncpRecord($patient, $rnd);
        $ncp2    = $this->ncpRecord($patient, $rnd);

        Monitoring::forceCreate([
            'ncp_record_id' => $ncp2->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp1->id}/monitorings");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
