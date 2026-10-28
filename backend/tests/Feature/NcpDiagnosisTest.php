<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalCompletenessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NcpDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_problem_wording_survives_editing_other_components_with_current_choices(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $diagnosis = Diagnosis::forceCreate(['ncp_record_id' => $ncp->id, 'domain' => 'NC', 'problem' => 'Overweight / Obesity',
            'etiology' => 'Recorded cause', 'signs_symptoms' => 'BMI 27', 'pes_statement' => 'Original saved wording']);
        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
            'builder_data' => ['problem_override' => 'Overweight / Obesity', 'problem_checks' => ['Overweight/obesity'],
                'etiology_other' => 'Updated recorded cause', 'sign_other' => 'BMI 27'],
        ])->assertOk()->assertJsonPath('data.problem', 'Overweight / Obesity')->assertJsonPath('data.etiology', 'Updated recorded cause');
    }

    public function test_explicit_no_diagnosis_completes_diagnosis_without_fabricated_pes(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", ['domain' => 'NO', 'problem' => 'No nutrition diagnosis at this time'])
            ->assertCreated()->assertJsonPath('data.etiology', '')->assertJsonPath('data.signs_symptoms', '')
            ->assertJsonPath('data.pes_statement', 'No nutrition diagnosis at this time')->assertJsonPath('data.ai_generated', false);
        $this->assertTrue(app(ClinicalCompletenessService::class)->diagnosisComplete($ncp->fresh()));
    }

    public function test_composed_problem_keeps_the_supplied_term_and_specific_nutrient(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
            'domain' => 'NI', 'problem' => 'Inadequate vitamin intake: D', 'etiology' => 'Poor appetite', 'signs_symptoms' => 'Recorded intake below needs',
        ])->assertCreated()->assertJsonPath('data.problem', 'Inadequate vitamin intake: D')
            ->assertJsonPath('data.pes_statement', 'Inadequate vitamin intake: D related to Poor appetite as evidenced by Recorded intake below needs');
    }

    public function test_composed_intake_selections_are_persisted_with_the_existing_builder(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $builder = ['problem_override' => 'Increased nutrient needs: Protein', 'ni_pattern' => 'needs', 'ni_direction' => 'Inadequate',
            'ni_nutrient' => 'Protein', 'ni_group' => '', 'ni_detail' => '', 'etiology_other' => 'Recorded cause', 'sign_other' => 'Recorded findings'];
        $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", ['domain' => 'NI', 'problem' => $builder['problem_override'],
            'etiology' => 'Recorded cause', 'signs_symptoms' => 'Recorded findings', 'builder_data' => $builder])
            ->assertCreated()->assertJsonPath('data.problem', 'Increased nutrient needs: Protein')->assertJsonPath('data.builder_data.ni_pattern', 'needs');
    }

    public function test_no_diagnosis_cannot_coexist_with_actual_diagnoses(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $this->actingAs($rnd, 'sanctum');
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/diagnoses";
        $this->postJson($url, ['domain' => 'NI', 'problem' => 'Inadequate Oral Intake', 'etiology' => 'Poor appetite', 'signs_symptoms' => 'Half of meals consumed'])->assertCreated();
        $this->postJson($url, ['domain' => 'NO', 'problem' => 'No nutrition diagnosis at this time'])->assertUnprocessable();
        $this->assertSame(1, $ncp->diagnoses()->count());
    }

    public function test_saved_no_diagnosis_blocks_adding_generating_and_domain_changes_until_deleted(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $this->actingAs($rnd, 'sanctum');
        Http::preventStrayRequests();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/diagnoses";
        $id = $this->postJson($url, ['domain' => 'NO', 'problem' => 'No nutrition diagnosis at this time'])->assertCreated()->json('data.id');
        $actual = ['domain' => 'NI', 'problem' => 'Inadequate oral intake', 'etiology' => 'Poor appetite', 'signs_symptoms' => 'Half of meals consumed'];
        $warning = "Delete the saved 'No nutrition diagnosis at this time' entry before adding or generating a diagnosis.";
        $this->postJson($url, $actual)->assertUnprocessable()->assertJsonPath('errors.domain.0', $warning);
        $this->postJson($url.'/ai-suggest')->assertUnprocessable()->assertJsonPath('message', $warning);
        $this->patchJson($url.'/'.$id, $actual)->assertUnprocessable()->assertJsonPath('errors.domain.0', $warning);
        $this->getJson($url.'/ai-suggest')->assertOk()->assertJsonPath('meta.blocked_by_no_diagnosis', true)->assertJsonPath('meta.disabled_reason', $warning);
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
        $this->deleteJson($url.'/'.$id)->assertNoContent();
        $this->postJson($url, $actual)->assertCreated();
    }

    public function test_editing_a_saved_legacy_bmi_choice_preserves_its_original_wording(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $sign = 'BMI ≥ 25 (overweight / obesity)';
        $builder = ['problem_checks' => ['Overweight / Obesity'], 'etiology_checks' => [], 'etiology_other' => 'Documented cause', 'sign_checks' => [$sign], 'sign_other' => ''];
        $diagnosis = Diagnosis::forceCreate(['ncp_record_id' => $ncp->id, 'domain' => 'NC', 'problem' => 'Overweight / Obesity', 'etiology' => 'Documented cause', 'signs_symptoms' => $sign, 'pes_statement' => Diagnosis::buildPes('Overweight / Obesity', 'Documented cause', $sign), 'builder_data' => $builder]);
        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", ['domain' => 'NC', 'builder_data' => $builder])
            ->assertOk()->assertJsonPath('data.signs_symptoms', $sign);
        $this->assertSame([$sign], $diagnosis->fresh()->builder_data['sign_checks']);
    }

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

    private function assessment(NcpRecord $ncp): Assessment
    {
        return Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70.0,
            'height' => 170.0,
        ]);
    }

    // ──────────────────────────────────────────────────
    // Diagnoses
    // ──────────────────────────────────────────────────

    public function test_diagnosis_requires_assessment_first(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd); // no assessment yet

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
                'domain' => 'NI',
                'problem' => 'Inadequate oral food intake',
                'etiology' => 'anorexia',
                'signs_symptoms' => 'low intake',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('diagnoses', ['ncp_record_id' => $ncp->id]);
    }

    public function test_rnd_can_add_diagnosis_to_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
                'domain' => 'NI',
                'problem' => 'Inadequate oral food intake',
                'etiology' => 'anorexia',
                'signs_symptoms' => 'Reported intake <50% of estimated requirements',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.domain', 'NI')
            ->assertJsonPath('data.ncp_record_id', $ncp->id);

        $this->assertDatabaseHas('diagnoses', [
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
        ]);
    }

    public function test_diagnosis_pes_statement_is_auto_generated(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
                'domain' => 'NC',
                'problem' => 'Overweight',
                'etiology' => 'excessive energy intake',
                'signs_symptoms' => 'BMI > 25',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('diagnoses', [
            'pes_statement' => 'Overweight related to excessive energy intake as evidenced by BMI > 25',
        ]);
    }

    public function test_diagnosis_rejects_unknown_domains(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
                'domain' => 'INVALID',
                'problem' => 'Some problem',
                'etiology' => 'some cause',
                'signs_symptoms' => 'some signs',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_rnd_can_list_diagnoses_for_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Problem 1',
            'etiology' => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement' => 'PES 1',
        ]);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NB',
            'problem' => 'Problem 2',
            'etiology' => 'Cause 2',
            'signs_symptoms' => 'Signs 2',
            'pes_statement' => 'PES 2',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rnd_can_update_diagnosis(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Old problem',
            'etiology' => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement' => 'Old PES',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
                'extra_notes' => 'Updated notes',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.extra_notes', 'Updated notes');
    }

    public function test_ai_generated_diagnosis_can_be_edited_like_manual_diagnosis(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Inadequate energy intake',
            'label' => 'Inadequate energy intake',
            'etiology' => 'poor appetite',
            'signs_symptoms' => 'weight loss',
            'pes_statement' => 'Inadequate energy intake related to poor appetite as evidenced by weight loss',
            'ai_generated' => true,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
                'domain' => 'NC',
                'problem' => 'Unintended weight loss',
                'etiology' => 'reduced oral intake',
                'signs_symptoms' => '8% weight loss in 1 month',
                'extra_notes' => 'Edited after RND review.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.domain', 'NC')
            ->assertJsonPath('data.problem', 'Unintended weight loss')
            ->assertJsonPath('data.label', 'Unintended weight loss')
            ->assertJsonPath('data.etiology', 'reduced oral intake')
            ->assertJsonPath('data.signs_symptoms', '8% weight loss in 1 month')
            ->assertJsonPath('data.pes_statement', 'Unintended weight loss related to reduced oral intake as evidenced by 8% weight loss in 1 month')
            ->assertJsonPath('data.extra_notes', 'Edited after RND review.')
            ->assertJsonPath('data.ai_generated', true);

        $this->assertDatabaseHas('diagnoses', [
            'id' => $diagnosis->id,
            'domain' => 'NC',
            'problem' => 'Unintended weight loss',
            'label' => 'Unintended weight loss',
            'ai_generated' => true,
        ]);
    }

    public function test_update_persists_manual_pes_override(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'poor appetite',
            'signs_symptoms' => 'weight loss', 'pes_statement' => 'auto',
        ]);

        $override = 'Inadequate oral intake related to chemotherapy-induced nausea as evidenced by 8% weight loss';

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
                'pes_statement' => $override,
            ])
            ->assertOk()
            ->assertJsonPath('data.pes_statement', $override);

        $this->assertDatabaseHas('diagnoses', [
            'id' => $diagnosis->id, 'pes_statement' => $override,
        ]);
    }

    public function test_update_rejects_blanked_pes_component(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'poor appetite',
            'signs_symptoms' => 'weight loss', 'pes_statement' => 'auto',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
                'problem' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem']);
    }

    public function test_cannot_delete_last_diagnosis_from_active_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $ncp->update(['status' => 'active']);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'cause',
            'signs_symptoms' => 'signs', 'pes_statement' => 'PES',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('diagnoses', ['id' => $diagnosis->id]);
    }

    public function test_can_delete_non_last_diagnosis_from_active_ncp(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $ncp->update(['status' => 'active']);

        $keep = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Keep', 'etiology' => 'c', 'signs_symptoms' => 's', 'pes_statement' => 'P',
        ]);
        $drop = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NC',
            'problem' => 'Drop', 'etiology' => 'c', 'signs_symptoms' => 's', 'pes_statement' => 'P',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$drop->uuid}")
            ->assertNoContent();

        $this->assertModelMissing($drop);
        $this->assertDatabaseHas('diagnoses', ['id' => $keep->id]);
    }

    public function test_rnd_can_delete_diagnosis(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NC',
            'problem' => 'Problem',
            'etiology' => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement' => 'PES',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('diagnoses', ['id' => $diagnosis->id]);
    }

    public function test_manual_creation_cannot_claim_ai_provenance_without_an_issued_draft(): void
    {
        $rnd = $this->rnd();
        $patient = $this->patient();
        $ncp = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
                'domain' => 'NI',
                'problem' => 'Inadequate oral food intake',
                'etiology' => 'anorexia',
                'signs_symptoms' => 'low intake',
                'ai_generated' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ai_generated', false);
    }

    public function test_diagnosis_belongs_to_correct_ncp_record(): void
    {
        $rnd = $this->rnd();
        $patient1 = $this->patient();
        $patient2 = $this->patient();
        $ncp1 = $this->ncpRecord($patient1, $rnd);
        $ncp2 = $this->ncpRecord($patient2, $rnd);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp2->id,
            'domain' => 'NB',
            'problem' => 'Other patient diagnosis',
            'etiology' => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement' => 'PES',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp1->uuid}/diagnoses");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_diagnosis_rejects_oversized_pes_fields_and_notes(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/diagnoses";
        $base = [
            'domain' => 'NI',
            'problem' => 'Inadequate oral food intake',
            'etiology' => 'Reduced appetite',
            'signs_symptoms' => 'Low intake',
        ];

        foreach ([
            [['etiology' => str_repeat('x', 401)], 'etiology'],
            [['signs_symptoms' => str_repeat('x', 401)], 'signs_symptoms'],
            [['pes_statement' => str_repeat('x', 501)], 'pes_statement'],
            [['extra_notes' => str_repeat('x', 126)], 'extra_notes'],
        ] as [$override, $field]) {
            $this->actingAs($rnd, 'sanctum')
                ->postJson($url, array_merge($base, $override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_diagnosis_update_rejects_oversized_fields(): void
    {
        $rnd = $this->rnd();
        $ncp = $this->ncpRecord($this->patient(), $rnd);
        $this->assessment($ncp);
        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Inadequate intake',
            'etiology' => 'Reduced appetite',
            'signs_symptoms' => 'Low intake',
            'pes_statement' => 'PES',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses/{$diagnosis->uuid}", [
                'extra_notes' => str_repeat('x', 126),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('extra_notes');
    }
}
