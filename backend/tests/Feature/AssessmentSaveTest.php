<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Support\PrimaryDiagnosisCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentSaveTest extends TestCase
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

    private function setup_assessment(): array
    {
        $rnd = $this->rnd();
        $patient = Patient::forceCreate([
            'name' => 'Test Patient',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => now()->toDateString(),
            'screening_type' => 'adult',
        ]);
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);
        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'primary_diagnosis_category' => PrimaryDiagnosisCategory::DIABETES,
        ]);

        return [$rnd, $ncp, $assessment];
    }

    private function validStorePayload(array $overrides = []): array
    {
        return array_merge([
            'weight' => 70,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'primary_diagnosis_category' => PrimaryDiagnosisCategory::DIABETES,
        ], $overrides);
    }

    private function setupNcpWithoutAssessment(): array
    {
        $rnd = $this->rnd();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->recycle($patient)->recycle($rnd)->create([
            'rnd_user_id' => $rnd->id,
            'status' => 'draft',
        ]);

        return [$rnd, $ncp];
    }

    public function test_new_assessment_requires_primary_diagnosis_category(): void
    {
        [$rnd, $ncp] = $this->setupNcpWithoutAssessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->validStorePayload([
                'primary_diagnosis_category' => null,
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('primary_diagnosis_category')
            ->assertJsonPath('errors.primary_diagnosis_category.0', 'The nutrition care category field is required.');
        $this->assertFalse($ncp->assessment()->exists());
    }

    public function test_other_category_requires_details_and_persists_one_census_bucket(): void
    {
        [$rnd, $ncp] = $this->setupNcpWithoutAssessment();

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->validStorePayload([
                'primary_diagnosis_category' => PrimaryDiagnosisCategory::OTHER,
                'primary_diagnosis_other' => '   ',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_diagnosis_other');

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->validStorePayload([
                'primary_diagnosis_category' => PrimaryDiagnosisCategory::OTHER,
                'primary_diagnosis_other' => 'Neurologic condition',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.primary_diagnosis_category', PrimaryDiagnosisCategory::OTHER)
            ->assertJsonPath('data.primary_diagnosis_other', 'Neurologic condition');
    }

    public function test_non_other_category_clears_other_details(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();
        $assessment->forceFill([
            'primary_diagnosis_category' => PrimaryDiagnosisCategory::OTHER,
            'primary_diagnosis_other' => 'Old context',
        ])->save();

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'primary_diagnosis_category' => PrimaryDiagnosisCategory::RENAL,
                'primary_diagnosis_other' => 'Must be discarded',
            ])
            ->assertOk();

        $assessment->refresh();
        $this->assertSame(PrimaryDiagnosisCategory::RENAL, $assessment->primary_diagnosis_category);
        $this->assertNull($assessment->primary_diagnosis_other);
    }

    public function test_existing_unclassified_assessment_requires_category_on_later_edit(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();
        $assessment->forceFill(['primary_diagnosis_category' => null])->save();

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", ['weight' => 71])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_diagnosis_category');
    }

    public function test_weight_change_period_requires_complete_positive_structured_pair(): void
    {
        [$rnd, $ncp] = $this->setupNcpWithoutAssessment();
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/assessment";

        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->validStorePayload([
            'weight_change_period_value' => 3,
            'weight_change_period_unit' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('weight_change_period_unit');

        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->validStorePayload([
            'weight_change_period_value' => 0,
            'weight_change_period_unit' => 'weeks',
        ]))->assertUnprocessable()->assertJsonValidationErrors('weight_change_period_value');

        $this->actingAs($rnd, 'sanctum')->postJson($url, $this->validStorePayload([
            'weight_change_period_value' => 3,
            'weight_change_period_unit' => 'days',
        ]))->assertUnprocessable()->assertJsonValidationErrors('weight_change_period_unit');
    }

    public function test_structured_period_and_confirmed_maternal_status_are_returned(): void
    {
        [$rnd, $ncp] = $this->setupNcpWithoutAssessment();

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->validStorePayload([
                'weight_change_period_value' => 1,
                'weight_change_period_unit' => 'month',
                'pregnancy_lactation_status' => 'pregnant_t2',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weight_change_period_unit');

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", $this->validStorePayload([
                'weight_change_period_value' => 1,
                'weight_change_period_unit' => 'months',
                'pregnancy_lactation_status' => 'pregnant_t2',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.weight_change_period', '1 month')
            ->assertJsonPath('data.pregnancy_lactation_status', 'pregnant_t2');
    }

    public function test_stress_factor_is_ignored_by_new_writes(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();
        $assessment->forceFill([
            'primary_diagnosis_category' => PrimaryDiagnosisCategory::DIABETES,
            'stress_factor' => 1.1,
        ])->save();

        $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'stress_factor' => 2.5,
            ])
            ->assertOk();

        $this->assertSame(1.1, $assessment->fresh()->stress_factor);
    }

    public function test_assessment_saves_physical_activity_level(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'physical_activity_level' => 'light',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'physical_activity_level' => 'light',
        ]);
    }

    public function test_assessment_saves_muac_mm(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'muac_mm' => 285.0,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'muac_mm' => 285.0,
        ]);
    }

    public function test_assessment_saves_waist_and_hip_cm(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'waist_cm' => 88.5,
                'hip_cm' => 96.0,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'waist_cm' => 88.5,
            'hip_cm' => 96.0,
        ]);
    }

    public function test_assessment_response_returns_clinical_measurement_fields(): void
    {
        // Regression: AssessmentResource previously omitted these 9 fields, so the edit page
        // re-loaded them blank even though they were saved. The response must echo them back.
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'religion' => 'Roman Catholic',
                'physical_activity_level' => 'sedentary',
                'muac_mm' => 285.5,
                'waist_cm' => 92.5,
                'hip_cm' => 100.5,
                'stress_factor' => 1.2,
                'edema_present' => true,
                'dry_weight_kg' => 68.0,
                'pregnancy_lactation_status' => 'none',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.religion', 'Roman Catholic')
            ->assertJsonPath('data.physical_activity_level', 'sedentary')
            ->assertJsonPath('data.muac_mm', 285.5)
            ->assertJsonPath('data.waist_cm', 92.5)
            ->assertJsonPath('data.hip_cm', 100.5)
            ->assertJsonPath('data.edema_present', true)
            ->assertJsonPath('data.pregnancy_lactation_status', 'none');
    }

    public function test_non_prescription_measurement_columns_are_nullable(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 70.0,
            ]);

        $response->assertStatus(200);
        $fresh = $assessment->fresh();
        $this->assertNull($fresh->muac_mm);
        $this->assertNull($fresh->waist_cm);
        $this->assertNull($fresh->hip_cm);
    }

    public function test_manual_risk_factor_override_updates_ncp_score(): void
    {
        [$rnd, $ncp] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'risk_score_manual_override' => true,
                'risk_score_manual_factors' => [
                    'screening_criteria',
                    'unintentional_weight_loss',
                    'others',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.risk_score', '4.00')
            ->assertJsonPath('data.risk_score_manual_override', true)
            ->assertJsonPath('data.checked_factors', [
                'screening_criteria',
                'unintentional_weight_loss',
                'others',
            ]);

        $this->assertDatabaseHas('ncp_records', [
            'id' => $ncp->id,
            'risk_score' => 4.00,
            'risk_score_manual_override' => true,
        ]);
    }

    public function test_risk_score_can_return_to_automatic_calculation(): void
    {
        [$rnd, $ncp] = $this->setup_assessment();
        $ncp->forceFill([
            'risk_score' => 4.00,
            'risk_score_manual_override' => true,
            'risk_score_manual_factors' => [
                'screening_criteria',
                'unintentional_weight_loss',
                'others',
            ],
        ])->save();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'risk_score_manual_override' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.risk_score', '1.00')
            ->assertJsonPath('data.risk_score_manual_override', false)
            ->assertJsonPath('data.risk_score_manual_factors', null)
            ->assertJsonPath('data.checked_factors', ['screening_criteria']);

        $this->assertDatabaseHas('ncp_records', [
            'id' => $ncp->id,
            'risk_score' => 1.00,
            'risk_score_manual_override' => false,
        ]);
    }
}
