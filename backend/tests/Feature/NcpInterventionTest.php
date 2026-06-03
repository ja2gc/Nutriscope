<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NcpInterventionTest extends TestCase
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
        return NcpRecord::forceCreate([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type'        => 'new',
            'status'      => 'draft',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Interventions
    // ──────────────────────────────────────────────────

    public function test_rnd_can_create_intervention(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'goal_type'         => 'Maintenance',
                'energy_kcal'       => 1800.0,
                'protein_g'         => 70.0,
                'carbs_g'           => 250.0,
                'fat_g'             => 55.0,
                'fluid_ml'          => 2000.0,
                'session_type'      => 'individual',
                'next_followup_date' => now()->addDays(7)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ncp_record_id', $ncp->id)
            ->assertJsonPath('data.energy_kcal', '1800.00')
            ->assertJsonPath('data.session_type', 'individual');

        $this->assertDatabaseHas('interventions', [
            'ncp_record_id' => $ncp->id,
            'energy_kcal'   => 1800.0,
        ]);
    }

    public function test_intervention_has_no_encounter_location_field(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'energy_kcal'   => 1600.0,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention");

        $response->assertOk()
            ->assertJsonMissingPath('data.encounter_location');
    }

    public function test_intervention_validates_numeric_nutrient_fields(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'energy_kcal' => 'not-a-number',
                'protein_g'   => -10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['energy_kcal', 'protein_g']);
    }

    public function test_rnd_can_update_intervention(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'energy_kcal'   => 1800.0,
            'protein_g'     => 65.0,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'energy_kcal'   => 2000.0,
                'education_notes' => 'Focus on protein-rich foods',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.energy_kcal', '2000.00')
            ->assertJsonPath('data.education_notes', 'Focus on protein-rich foods');
    }

    public function test_intervention_is_within_target_10_percent(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $intervention = Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'energy_kcal'   => 1800.0,
        ]);

        // 1800 ±10% = 1620-1980, actual 1850 is within range
        $this->assertTrue($intervention->isWithinTarget('energy', 1850.0));
        // actual 2000 is outside range
        $this->assertFalse($intervention->isWithinTarget('energy', 2000.0));
    }

    public function test_duplicate_intervention_returns_conflict(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'energy_kcal' => 1800.0]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'energy_kcal' => 2000.0,
            ]);

        $response->assertStatus(409);
    }

    public function test_micronutrient_limits_stored_as_json(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'micronutrient_limits' => ['sodium' => 2000, 'potassium' => 4700],
            ]);

        $intervention = Intervention::where('ncp_record_id', $ncp->id)->firstOrFail();
        $this->assertIsArray($intervention->micronutrient_limits);
        $this->assertEquals(2000, $intervention->micronutrient_limits['sodium']);
    }
}
