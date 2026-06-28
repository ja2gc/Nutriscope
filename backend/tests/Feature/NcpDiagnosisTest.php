<?php

namespace Tests\Feature;

use App\Models\ClinicalRule;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NcpDiagnosisTest extends TestCase
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

    private function assessment(NcpRecord $ncp): \App\Models\Assessment
    {
        return \App\Models\Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight'        => 70.0,
            'height'        => 170.0,
        ]);
    }

    // ──────────────────────────────────────────────────
    // Diagnoses
    // ──────────────────────────────────────────────────

    public function test_diagnosis_requires_assessment_first(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd); // no assessment yet

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses", [
                'domain'         => 'NI',
                'problem'        => 'Inadequate oral food intake',
                'etiology'       => 'anorexia',
                'signs_symptoms' => 'low intake',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('diagnoses', ['ncp_record_id' => $ncp->id]);
    }

    public function test_rnd_can_add_diagnosis_to_ncp_record(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses", [
                'domain'        => 'NI',
                'problem'       => 'Inadequate oral food intake',
                'etiology'      => 'anorexia',
                'signs_symptoms' => 'Reported intake <50% of estimated requirements',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.domain', 'NI')
            ->assertJsonPath('data.ncp_record_id', $ncp->id);

        $this->assertDatabaseHas('diagnoses', [
            'ncp_record_id' => $ncp->id,
            'domain'        => 'NI',
        ]);
    }

    public function test_diagnosis_pes_statement_is_auto_generated(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses", [
                'domain'         => 'NC',
                'problem'        => 'Overweight',
                'etiology'       => 'excessive energy intake',
                'signs_symptoms' => 'BMI > 25',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('diagnoses', [
            'pes_statement' => 'Overweight related to excessive energy intake as evidenced by BMI > 25',
        ]);
    }

    public function test_diagnosis_domain_must_be_ni_nc_or_nb(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses", [
                'domain'         => 'NO',
                'problem'        => 'Some problem',
                'etiology'       => 'some cause',
                'signs_symptoms' => 'some signs',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['domain']);
    }

    public function test_rnd_can_list_diagnoses_for_ncp_record(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain'        => 'NI',
            'problem'        => 'Problem 1',
            'etiology'       => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement'  => 'PES 1',
        ]);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain'        => 'NB',
            'problem'        => 'Problem 2',
            'etiology'       => 'Cause 2',
            'signs_symptoms' => 'Signs 2',
            'pes_statement'  => 'PES 2',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_rnd_can_update_diagnosis(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain'        => 'NI',
            'problem'        => 'Old problem',
            'etiology'       => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement'  => 'Old PES',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}", [
                'extra_notes' => 'Updated notes',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.extra_notes', 'Updated notes');
    }

    public function test_ai_generated_diagnosis_can_be_edited_like_manual_diagnosis(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

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
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}", [
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
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'poor appetite',
            'signs_symptoms' => 'weight loss', 'pes_statement' => 'auto',
        ]);

        $override = 'Inadequate oral intake related to chemotherapy-induced nausea as evidenced by 8% weight loss';

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}", [
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
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'poor appetite',
            'signs_symptoms' => 'weight loss', 'pes_statement' => 'auto',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}", [
                'problem' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['problem']);
    }

    public function test_cannot_delete_last_diagnosis_from_active_ncp(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        $ncp->update(['status' => 'active']);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id, 'domain' => 'NI',
            'problem' => 'Inadequate intake', 'etiology' => 'cause',
            'signs_symptoms' => 'signs', 'pes_statement' => 'PES',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('diagnoses', ['id' => $diagnosis->id]);
    }

    public function test_can_delete_non_last_diagnosis_from_active_ncp(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
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
            ->deleteJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$drop->id}")
            ->assertNoContent();

        $this->assertModelMissing($drop);
        $this->assertDatabaseHas('diagnoses', ['id' => $keep->id]);
    }

    public function test_rnd_can_delete_diagnosis(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);

        $diagnosis = Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'domain'        => 'NC',
            'problem'        => 'Problem',
            'etiology'       => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement'  => 'PES',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses/{$diagnosis->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('diagnoses', ['id' => $diagnosis->id]);
    }

    public function test_clinical_rules_drive_ai_flag_on_diagnosis(): void
    {
        $rnd     = $this->rnd();
        $patient = $this->patient();
        $ncp     = $this->ncpRecord($patient, $rnd);
        $this->assessment($ncp);

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->id}/diagnoses", [
                'domain'         => 'NI',
                'problem'        => 'Inadequate oral food intake',
                'etiology'       => 'anorexia',
                'signs_symptoms' => 'low intake',
                'ai_generated'   => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ai_generated', true);
    }

    public function test_diagnosis_belongs_to_correct_ncp_record(): void
    {
        $rnd      = $this->rnd();
        $patient1 = $this->patient();
        $patient2 = $this->patient();
        $ncp1     = $this->ncpRecord($patient1, $rnd);
        $ncp2     = $this->ncpRecord($patient2, $rnd);

        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp2->id,
            'domain'        => 'NB',
            'problem'        => 'Other patient diagnosis',
            'etiology'       => 'Cause',
            'signs_symptoms' => 'Signs',
            'pes_statement'  => 'PES',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp1->id}/diagnoses");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
