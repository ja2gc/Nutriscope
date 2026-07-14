<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NcpAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name' => 'RND User',
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
        return NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Assessment
    // ──────────────────────────────────────────────────

    public function test_rnd_can_create_assessment_for_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 70.5,
                'usual_weight' => 72.0,
                'height' => 170.0,
                'physical_activity_level' => 'light',
                'dietary_intake' => 'Normal diet',
                'allergies' => ['peanuts', 'shellfish'],
                'medications' => [],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ncp_record_id', $ncp->id)
            ->assertJsonPath('data.weight', '70.50')
            ->assertJsonPath('data.bmi', '24.39');

        $this->assertDatabaseHas('assessments', [
            'ncp_record_id' => $ncp->id,
            'weight' => 70.5,
        ]);
    }

    public function test_assessment_bmi_is_calculated_automatically(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 60.0,
                'usual_weight' => 61.0,
                'height' => 160.0,
                'physical_activity_level' => 'sedentary',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bmi', '23.44');
    }

    public function test_assessment_validates_required_fields(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 'not-a-number',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_assessment_rejects_implausible_weight(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        // 700 kg is a classic digit-transposition typo (intended 70). Left
        // unbounded it flows through the flat kcal/kg engine into an absurd Rx.
        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 700.0,
                'usual_weight' => 72.0,
                'height' => 170.0,
                'physical_activity_level' => 'light',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['weight']);
    }

    public function test_assessment_rejects_implausible_height(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        // 1650 cm is a typo (intended 165); it inflates IBW and BMR into an
        // absurd energy/protein prescription.
        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 65.0,
                'usual_weight' => 65.0,
                'height' => 1650.0,
                'physical_activity_level' => 'light',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['height']);
    }

    public function test_assessment_accepts_plausible_boundary_anthropometrics(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        // Upper plausibility bounds must still save (never reject a real patient).
        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 400.0,
                'usual_weight' => 400.0,
                'height' => 250.0,
                'physical_activity_level' => 'sedentary',
            ]);

        $response->assertStatus(201);
    }

    public function test_assessment_requires_prescription_calculation_inputs_on_create(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'dietary_intake' => 'Normal diet',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'weight',
                'usual_weight',
                'height',
                'physical_activity_level',
            ]);
    }

    public function test_rnd_can_get_assessment_for_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 80.0,
            'usual_weight' => 82.0,
            'height' => 175.0,
            'physical_activity_level' => 'sedentary',
            'bmi' => 26.12,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment");

        $response->assertOk()
            ->assertJsonPath('data.ncp_record_id', $ncp->id);
    }

    public function test_rnd_can_update_assessment(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 80.0,
            'usual_weight' => 82.0,
            'height' => 175.0,
            'physical_activity_level' => 'sedentary',
            'bmi' => 26.12,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 78.0,
                'height' => 175.0,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.weight', '78.00')
            ->assertJsonPath('data.bmi', '25.47');

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'weight' => 78.0]);
    }

    public function test_assessment_stores_allergies_as_json(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 65.0,
                'usual_weight' => 66.0,
                'height' => 160.0,
                'physical_activity_level' => 'sedentary',
                'allergies' => ['gluten', 'dairy'],
            ]);

        $assessment = Assessment::where('ncp_record_id', $ncp->id)->firstOrFail();
        $this->assertIsArray($assessment->allergies);
        $this->assertContains('gluten', $assessment->allergies);
    }

    public function test_guest_cannot_access_assessment(): void
    {
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $this->rnd());

        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment")
            ->assertStatus(401);
    }

    public function test_duplicate_assessment_returns_conflict(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 75.0,
                'usual_weight' => 76.0,
                'height' => 170.0,
                'physical_activity_level' => 'sedentary',
            ]);

        $response->assertStatus(409);
    }
}
