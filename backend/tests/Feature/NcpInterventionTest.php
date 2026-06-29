<?php

namespace Tests\Feature;

use App\Models\Assessment;
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

    private function diagnosis(NcpRecord $ncp): \App\Models\Diagnosis
    {
        return \App\Models\Diagnosis::forceCreate([
            'ncp_record_id'  => $ncp->id,
            'domain'         => 'NI',
            'problem'        => 'Inadequate intake',
            'etiology'       => 'cause',
            'signs_symptoms' => 'signs',
            'pes_statement'  => 'PES',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Interventions
    // ──────────────────────────────────────────────────

    public function test_intervention_requires_diagnosis_first(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd); // no diagnosis yet

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'energy_kcal' => 1800.0,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('interventions', ['ncp_record_id' => $ncp->id]);
    }

    public function test_autofill_returns_authoritative_prescription(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient(); // Male
        $ncp     = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate([
            'ncp_record_id'           => $ncp->id,
            'weight'                  => 80.0,
            'height'                  => 170.0,
            'physical_activity_level' => 'sedentary',
        ]);

        // renal_diet/stage_1 is flat-rate (age-independent): matches frozen golden case A.
        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention/autofill", [
                'goal_type'     => 'renal_diet',
                'disease_stage' => 'stage_1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.energy_kcal', 2400)
            ->assertJsonPath('data.protein_g', 53)
            ->assertJsonPath('data.fat_g', 67)
            ->assertJsonPath('data.carbs_g', 396)
            ->assertJsonPath('data.fluid_ml', 2600)
            ->assertJsonPath('data.sodium_max_mg', 2300);
    }

    public function test_autofill_requires_assessment_weight_height(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention/autofill", [
                'goal_type' => 'renal_diet', 'disease_stage' => 'stage_1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('missing_fields', ['weight', 'height']);
    }

    public function test_rnd_can_create_intervention(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

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

    public function test_empty_intervention_does_not_activate_ncp(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0, 'height' => 170.0]);
        $this->diagnosis($ncp);

        // Creating an intervention with no prescription must NOT flip the NCP active.
        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [])
            ->assertStatus(201);

        $this->assertSame('draft', $ncp->fresh()->status);
    }

    public function test_completing_prescription_activates_ncp(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0, 'height' => 170.0]);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [])
            ->assertStatus(201);
        $this->assertSame('draft', $ncp->fresh()->status);

        // Filling the prescription via update completes the initial ADI → active.
        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'goal_type'   => 'renal_diet',
                'energy_kcal' => 1800.0,
                'protein_g'   => 70.0,
                'carbs_g'     => 250.0,
                'fat_g'       => 55.0,
            ])
            ->assertOk();

        $this->assertSame('active', $ncp->fresh()->status);
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
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
                'micronutrient_limits' => ['sodium' => 2000, 'potassium' => 4700],
            ]);

        $intervention = Intervention::where('ncp_record_id', $ncp->id)->firstOrFail();
        $this->assertIsArray($intervention->micronutrient_limits);
        $this->assertEquals(2000, $intervention->micronutrient_limits['sodium']);
    }

    // ──────────────────────────────────────────────────
    // Recommendations endpoint
    // ──────────────────────────────────────────────────

    public function test_recommendations_returns_recommend_avoid_for_renal_diet(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type'     => 'renal_diet',
            'disease_stage' => 'stage_4',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['recommend', 'avoid', 'limits'],
            ]);
    }

    public function test_recommendations_resolve_real_conditions_per_goal_type(): void
    {
        $rnd = $this->rnd();

        \App\Models\ClinicalRule::insert([
            ['condition' => 'CKD',          'stage' => 'all', 'nutrient_or_food_tag' => 'potassium',     'rule_type' => 'limit',     'threshold' => 2000, 'unit' => 'mg', 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium',        'rule_type' => 'limit',     'threshold' => 1500, 'unit' => 'mg', 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit',     'threshold' => 7,    'unit' => '%',  'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein',       'rule_type' => 'recommend', 'threshold' => 0,    'unit' => '',   'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $recs = function (string $goalType) use ($rnd) {
            $ncp = $this->ncpRecord($this->patient(), $rnd);
            Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'goal_type' => $goalType, 'disease_stage' => 'all']);
            return $this->actingAs($rnd, 'sanctum')
                ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");
        };

        // renal_diet -> CKD
        $this->assertContains('potassium', array_column($recs('renal_diet')->json('data.limits'), 'tag'));

        // cardiac_diet -> hypertension + dyslipidemia (both conditions resolved)
        $cardiacTags = array_column($recs('cardiac_diet')->json('data.limits'), 'tag');
        $this->assertContains('sodium', $cardiacTags);
        $this->assertContains('saturated_fat', $cardiacTags);

        // malnutrition -> malnutrition (previously broken: 'Malnutrition' vs 'malnutrition')
        $this->assertContains('protein', array_column($recs('malnutrition')->json('data.recommend'), 'tag'));
    }

    public function test_recommendations_returns_empty_for_custom_goal(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type'     => 'custom',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");

        $response->assertOk()
            ->assertJsonPath('data.recommend', [])
            ->assertJsonPath('data.avoid', []);
    }

    public function test_recommendations_returns_404_when_no_intervention(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations")
            ->assertNotFound();
    }
}
