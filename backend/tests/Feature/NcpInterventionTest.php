<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClinicalRule;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\NcpAppointmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NcpInterventionTest extends TestCase
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
        return NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);
    }

    private function diagnosis(NcpRecord $ncp): Diagnosis
    {
        return Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Inadequate intake',
            'etiology' => 'cause',
            'signs_symptoms' => 'signs',
            'pes_statement' => 'PES',
        ]);
    }

    // ──────────────────────────────────────────────────
    // Interventions
    // ──────────────────────────────────────────────────

    public function test_intervention_requires_diagnosis_first(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd); // no diagnosis yet

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'energy_kcal' => 1800.0,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('interventions', ['ncp_record_id' => $ncp->id]);
    }

    public function test_intervention_rejects_prescription_values_above_supported_ranges(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/interventions";

        foreach ([
            [['energy_kcal' => 10000.01], 'energy_kcal'],
            [['fluid_ml' => 10000.01], 'fluid_ml'],
            [['protein_g' => 1000.01], 'protein_g'],
        ] as [$input, $field]) {
            $this->actingAs($rnd, 'sanctum')
                ->postJson($url, $input)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_autofill_returns_authoritative_prescription(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient(); // Male
        $ncp = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 80.0,
            'height' => 170.0,
            'physical_activity_level' => 'sedentary',
        ]);

        // renal_diet/stage_1 is flat-rate (age-independent): matches frozen golden case A.
        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'renal_diet',
                'disease_stage' => 'stage_1',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.energy_kcal', 2400)
            ->assertJsonPath('data.protein_g', 53)
            ->assertJsonPath('data.fat_g', 67)
            ->assertJsonPath('data.carbs_g', 396)
            ->assertJsonPath('data.fluid_ml', 2600)
            ->assertJsonPath('data.sodium_max_mg', 2000);
    }

    public function test_autofill_uses_latest_monitoring_context_and_reports_source(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 80,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'edema_present' => false,
            'pregnancy_lactation_status' => 'none',
            'allergies' => [],
            'food_dislikes' => [],
        ]);
        $monitoring = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            'observed_at' => '2026-09-25',
            'weight' => 60,
            'height' => 160,
            'physical_activity_level' => 'moderate',
        ]);
        $assessmentBefore = $ncp->assessment->refresh()->getAttributes();

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'renal_diet',
                'disease_stage' => 'stage_1',
            ])
            ->assertOk()
            ->assertJsonPath('data.energy_kcal', 1800)
            ->assertJsonPath('data.source_monitoring_id', $monitoring->uuid)
            ->assertJsonPath('data.source_monitoring_date', '2026-09-25');

        $this->assertSame($assessmentBefore, $ncp->assessment->fresh()->getAttributes());
    }

    public function test_autofill_requires_assessment_weight_height(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'renal_diet', 'disease_stage' => 'stage_1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('missing_fields', ['weight', 'height']);
    }

    public function test_autofill_rejects_unknown_goal_type(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'bad_goal',
                'disease_stage' => 'stage_1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid intervention goal or disease stage.');
    }

    public function test_autofill_requires_activity_level_for_tee_based_goals(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70.0,
            'height' => 170.0,
            'physical_activity_level' => null,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'weight_loss',
                'disease_stage' => 'class_1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('missing_fields', ['physical_activity_level']);
    }

    public function test_autofill_requires_trimester_confirmation_for_legacy_pregnancy(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 60.0,
            'height' => 160.0,
            'physical_activity_level' => 'sedentary',
            'pregnancy_lactation_status' => 'pregnant_unspecified',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'diabetic_control',
                'disease_stage' => 'stage_1',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('calculation_status', 'maternal_status_confirmation_required')
            ->assertJsonPath('missing_fields', ['pregnancy_lactation_status'])
            ->assertJsonPath('message', 'Confirm the pregnancy trimester before automatic maternal targets are available.');
    }

    public function test_autofill_returns_authoritative_maternal_trace(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $patient->update(['sex' => 'Female']);
        $ncp = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 60.0,
            'height' => 160.0,
            'physical_activity_level' => 'sedentary',
            'pregnancy_lactation_status' => 'pregnant_t2',
            'stress_factor' => 2.0,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'cardiac_diet',
                'disease_stage' => 'severe',
            ])
            ->assertOk()
            ->assertJsonPath('data.maternal_modifier.modifier.energy_kcal', 300)
            ->assertJsonPath('data.maternal_modifier.modifier.protein_g', 27)
            ->assertJsonPath('data.maternal_modifier.final.fluid_ml', 1500)
            ->assertJsonPath('data.maternal_modifier.source_key', 'FNRI_PDRI_2015_REV_2018_SUMMARY_TABLES');
    }

    public function test_autofill_returns_severe_nutrition_lab_warnings_for_low_electrolytes(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 48.0,
            'height' => 174.0,
            'physical_activity_level' => 'moderate',
        ]);
        $assessment->biochemicalData()->create([
            'potassium' => 3.1,
            'phosphate' => 2.1,
            'calcium' => 13.0,
            'hemoglobin' => 10.0,
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/autofill", [
                'goal_type' => 'malnutrition',
                'disease_stage' => 'severe',
            ])
            ->assertOk()
            ->assertJsonPath('data.calculation_status', 'warning')
            ->assertJsonFragment(['key' => 'low_potassium'])
            ->assertJsonFragment(['key' => 'low_phosphate'])
            ->assertJsonFragment(['key' => 'high_calcium']);
    }

    public function test_rnd_can_create_intervention(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'custom',
                'energy_kcal' => 1800.0,
                'protein_g' => 70.0,
                'carbs_g' => 250.0,
                'fat_g' => 55.0,
                'fluid_ml' => 2000.0,
                'session_type' => 'individual',
                'next_followup_date' => now()->addDays(7)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', Intervention::where('ncp_record_id', $ncp->id)->firstOrFail()->uuid)
            ->assertJsonMissingPath('data.ncp_record_id')
            ->assertJsonPath('data.energy_kcal', '1800.00')
            ->assertJsonPath('data.session_type', null)
            ->assertJsonPath('data.next_followup_date', null);

        $this->assertDatabaseHas('interventions', [
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800.0,
            'session_type' => null,
            'next_followup_date' => null,
        ]);
    }

    public function test_intervention_rejects_unknown_goal_type(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'bad_goal',
                'disease_stage' => 'stage_1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['goal_type']);
    }

    public function test_intervention_rejects_stage_that_does_not_belong_to_goal(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'weight_gain',
                'disease_stage' => 'stage_1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disease_stage']);
    }

    public function test_empty_intervention_does_not_activate_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0, 'height' => 170.0]);
        $this->diagnosis($ncp);

        // Creating an intervention with no prescription must NOT flip the NCP active.
        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [])
            ->assertStatus(201);

        $this->assertSame('draft', $ncp->fresh()->status);
    }

    public function test_show_returns_null_when_intervention_missing(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/latest")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_completing_prescription_activates_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0, 'height' => 170.0]);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [])
            ->assertStatus(201);
        $this->assertSame('draft', $ncp->fresh()->status);

        // A complete new plan supersedes the earlier incomplete saved plan.
        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'renal_diet',
                'disease_stage' => 'stage_1',
                'energy_kcal' => 1800.0,
                'protein_g' => 70.0,
                'carbs_g' => 250.0,
                'fat_g' => 55.0,
            ])
            ->assertCreated();

        $this->assertSame('active', $ncp->fresh()->status);
    }

    public function test_intervention_has_no_encounter_location_field(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1600.0,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/latest");

        $response->assertOk()
            ->assertJsonMissingPath('data.encounter_location');
    }

    public function test_intervention_validates_numeric_nutrient_fields(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'energy_kcal' => 'not-a-number',
                'protein_g' => -10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['energy_kcal', 'protein_g']);
    }

    public function test_intervention_limits_each_guidance_field(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'education_notes' => str_repeat('a', 1201),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['education_notes']);
    }

    public function test_intervention_limits_combined_guidance_for_print_layout(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'education_notes' => str_repeat('a', 600),
                'counseling_goals' => str_repeat('b', 600),
                'barriers' => str_repeat('c', 600),
                'strategies' => str_repeat('d', 401),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['education_notes']);
    }

    public function test_intervention_is_within_target_10_percent(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $intervention = Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800.0,
        ]);

        // 1800 ±10% = 1620-1980, actual 1850 is within range
        $this->assertTrue($intervention->isWithinTarget('energy', 1850.0));
        // actual 2000 is outside range
        $this->assertFalse($intervention->isWithinTarget('energy', 2000.0));
    }

    public function test_new_intervention_plan_can_follow_a_saved_plan(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $first = Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'energy_kcal' => 1800.0]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'custom',
                'energy_kcal' => 2000.0,
            ]);

        $response->assertCreated();
        $this->assertDatabaseCount('interventions', 2);
        $this->assertNotSame($first->uuid, $response->json('data.id'));
    }

    public function test_micronutrient_limits_stored_as_json(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'micronutrient_limits' => ['sodium' => 2000, 'potassium' => 4700],
            ]);

        $intervention = Intervention::where('ncp_record_id', $ncp->id)->firstOrFail();
        $this->assertIsArray($intervention->micronutrient_limits);
        $this->assertEquals(2000, $intervention->micronutrient_limits['sodium']);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_dated_plan_collection_is_paginated_newest_first_without_revision_language(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $monitoring = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            'created_at' => '2026-09-20 08:00:00',
        ]);
        $oldest = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'created_at' => '2026-09-01 08:00:00',
        ]);
        $middle = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'source_monitoring_id' => $monitoring->id,
            'created_at' => '2026-09-20 09:00:00',
        ]);
        $newest = Intervention::factory()->create([
            'ncp_record_id' => $ncp->id,
            'source_monitoring_id' => $monitoring->id,
            'created_at' => '2026-09-20 09:00:00',
        ]);
        MealPlan::factory()->create([
            'intervention_id' => $middle->id,
            'patient_id' => $patient->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions?per_page=2");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newest->uuid)
            ->assertJsonPath('data.1.id', $middle->uuid)
            ->assertJsonPath('data.1.has_meal_plan', true)
            ->assertJsonPath('data.1.source_monitoring_id', $monitoring->uuid)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.revision');
        $this->assertTrue(Str::isUuid($response->json('data.0.id')));
        $this->assertNotSame($oldest->uuid, $response->json('data.0.id'));
    }

    public function test_dated_plan_detail_is_scoped_to_parent_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $plan = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $otherNcp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/{$plan->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $plan->uuid)
            ->assertJsonMissingPath('data.ncp_record_id')
            ->assertJsonMissingPath('data.revision');

        $this->getJson("/api/rnd/ncp-records/{$otherNcp->uuid}/interventions/{$plan->uuid}")
            ->assertNotFound();

        $foreignPatient = $this->patient();
        $foreignNcp = $this->ncpRecord($foreignPatient, $rnd);
        $foreignPlan = Intervention::factory()->create(['ncp_record_id' => $foreignNcp->id]);

        $this->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/{$foreignPlan->uuid}")
            ->assertNotFound();
    }

    public function test_plural_store_creates_new_complete_plan_from_latest_monitoring(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);
        $older = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            'observed_at' => '2026-09-20',
            'created_at' => '2026-09-22 08:00:00',
        ]);
        $latest = Monitoring::factory()->create([
            'ncp_record_id' => $ncp->id,
            'observed_at' => '2026-09-21',
            'created_at' => '2026-09-21 08:00:00',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'custom',
                'energy_kcal' => 1900,
                'protein_g' => 75,
                'carbs_g' => 250,
                'fat_g' => 60,
                'fluid_ml' => 2000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.source_monitoring_id', $latest->uuid)
            ->assertJsonPath('data.energy_kcal', '1900.00')
            ->assertJsonMissingPath('data.revision');
        $this->assertNotSame($older->id, Intervention::latest('id')->firstOrFail()->source_monitoring_id);
        $this->assertDatabaseCount('intervention_revisions', 0);
    }

    public function test_plural_store_rolls_back_plan_when_follow_up_workflow_fails(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->diagnosis($ncp);
        $this->mock(NcpAppointmentWorkflow::class, function ($workflow): void {
            $workflow->shouldReceive('recordClinicalWork')
                ->once()
                ->andThrow(new \RuntimeException('Follow-up workflow unavailable.'));
        });

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions", [
                'goal_type' => 'custom',
                'energy_kcal' => 1900,
                'protein_g' => 75,
                'carbs_g' => 250,
                'fat_g' => 60,
            ])
            ->assertServerError();

        $this->assertDatabaseMissing('interventions', ['ncp_record_id' => $ncp->id]);
    }

    // ──────────────────────────────────────────────────
    // Recommendations endpoint
    // ──────────────────────────────────────────────────

    public function test_recommendations_returns_recommend_avoid_for_renal_diet(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'renal_diet',
            'disease_stage' => 'stage_4',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/recommendations");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['recommend', 'avoid', 'limits'],
            ]);
    }

    public function test_recommendations_resolve_real_conditions_per_goal_type(): void
    {
        $rnd = $this->rnd();

        ClinicalRule::insert([
            ['condition' => 'CKD',          'stage' => 'all', 'nutrient_or_food_tag' => 'potassium',     'rule_type' => 'limit',     'threshold' => 2000, 'unit' => 'mg', 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'hypertension', 'stage' => 'all', 'nutrient_or_food_tag' => 'sodium',        'rule_type' => 'limit',     'threshold' => 1500, 'unit' => 'mg', 'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'dyslipidemia', 'stage' => 'all', 'nutrient_or_food_tag' => 'saturated_fat', 'rule_type' => 'limit',     'threshold' => 7,    'unit' => '%',  'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'malnutrition', 'stage' => 'all', 'nutrient_or_food_tag' => 'protein',       'rule_type' => 'recommend', 'threshold' => 0,    'unit' => '',   'reason' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $recs = function (string $goalType) use ($rnd) {
            $ncp = $this->ncpRecord($this->patient(), $rnd);
            Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'goal_type' => $goalType, 'disease_stage' => 'all']);

            return $this->actingAs($rnd, 'sanctum')
                ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/recommendations");
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
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'custom',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/recommendations");

        $response->assertOk()
            ->assertJsonPath('data.recommend', [])
            ->assertJsonPath('data.avoid', []);
    }

    public function test_recommendations_include_lab_refinements_for_abnormal_potassium(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);
        $assessment->biochemicalData()->create(['potassium' => 5.8]);

        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'custom',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/recommendations");

        $response->assertOk();
        $this->assertContains('potassium', array_column($response->json('data.limits'), 'tag'));
    }

    public function test_recommendations_returns_404_when_no_intervention(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/interventions/recommendations")
            ->assertNotFound();
    }
}
