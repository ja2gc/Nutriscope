<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\ScreeningDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScreeningDocumentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_can_approve_screening_document_and_sync_assessment_fields(): void
    {
        $rnd = User::forceCreate([
            'name' => 'Test RND',
            'email' => 'rnd-screening@example.com',
            'password' => Hash::make('password'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $patient = Patient::forceCreate([
            'name' => 'Jane Doe',
            'dob' => '1994-05-05',
            'sex' => 'Female',
            'screening_type' => 'adult',
            'admission_date' => '2026-06-01',
            'status' => 'Active',
        ]);

        $ncpRecord = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);

        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncpRecord->id,
            'dietary_intake' => null,
            'appetite_changes' => null,
            'dietary_restrictions' => null,
            'supplements' => null,
            'knowledge_notes' => null,
            'weight' => null,
            'height' => null,
            'bmi' => null,
            'body_composition' => null,
            'medical_history' => null,
            'social_history' => null,
            'lifestyle' => null,
            'allergies' => [],
            'food_dislikes' => [],
            'medications' => [],
            'rnd_summary' => null,
            'usual_weight' => null,
            'nutritional_status' => null,
            'weight_loss_percentage' => null,
            'weight_loss_period' => null,
            'functional_assessment' => null,
            'energy_intake_status' => null,
            'ibw_percentage' => null,
            'present_diet' => null,
            'physical_assessment' => null,
            'chewing_swallowing_difficulties' => null,
            'constipation' => null,
            'diarrhea_notes' => null,
            'food_intolerance' => null,
            'nutrient_drug_interaction' => null,
            'dietary_intake_method' => null,
            'dietary_record_file' => null,
        ]);

        $screeningDocument = ScreeningDocument::forceCreate([
            'patient_id' => $patient->id,
            'assessment_id' => $assessment->id,
            'type' => 'adult',
            'file_path' => 'documents/screening/adult.pdf',
            'extracted_data' => [
                'patient_name' => 'Jane Doe',
                'height' => '165',
                'weight' => '52.4',
            ],
            'mapped_fields' => [
                'patient_name' => 'Jane Doe',
                'age' => '32',
                'sex' => 'Female',
                'address' => 'Ward 3',
                'height' => '165',
                'weight' => '52.4',
                'clinical_conditions' => [
                    'Admission to ICU',
                ],
                'intake_weight_history' => [
                    'Unintentional weight loss in the past 3 months',
                ],
                'diagnosis' => 'Poor intake',
                'diet_prescription' => 'Regular diet',
                'referral_type' => 'Per Orem',
                'referred_by' => 'Dr. Santos',
                'referral_datetime' => '2026-06-04 08:30',
            ],
            'status' => 'completed',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->patchJson(
            "/api/rnd/screening-documents/{$screeningDocument->id}/approve",
            [
                'mapped_fields' => $screeningDocument->mapped_fields,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.mapped_fields.weight', '52.4');

        $this->assertDatabaseHas('screening_documents', [
            'id' => $screeningDocument->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'height' => 165.0,
            'weight' => 52.4,
            'nutritional_status' => 'Normal',
        ]);

        $this->assertDatabaseHas('ncp_records', [
            'id' => $ncpRecord->id,
            'risk_score' => 1.0,
        ]);
    }
}
