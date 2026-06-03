<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\NcpRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PatientFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_can_list_patients()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test1@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);
        
        Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->getJson('/api/rnd/patients');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_rnd_can_create_patient()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test2@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);

        $response = $this->actingAs($rnd, 'sanctum')->postJson('/api/rnd/patients', [
            'name' => 'Jane Doe',
            'dob' => '1995-05-05',
            'sex' => 'Female',
            'admission_date' => '2024-01-01',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Jane Doe');

        $this->assertDatabaseHas('patients', ['name' => 'Jane Doe']);
    }

    public function test_rnd_can_update_patient()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test3@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);
        
        $patient = Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/patients/{$patient->id}", [
            'ward' => 'ICU',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('ward', 'ICU');

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'ward' => 'ICU']);
    }

    public function test_rnd_can_update_patient_screening_workflow_fields()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test3b@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);

        $patient = Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/patients/{$patient->id}", [
            'screening_type' => 'pediatric',
            'hospital_number' => 'HOSP-2026-0001',
            'age_group_category' => 'Adolescent',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('screening_type', 'pediatric')
            ->assertJsonPath('hospital_number', 'HOSP-2026-0001')
            ->assertJsonPath('age_group_category', 'Adolescent');

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'screening_type' => 'pediatric',
            'hospital_number' => 'HOSP-2026-0001',
            'age_group_category' => 'Adolescent',
        ]);
    }

    public function test_rnd_can_start_ncp_cycle()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test4@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);

        $patient = Patient::forceCreate([
            'name' => 'John Doe',
            'dob' => '1990-01-01',
            'sex' => 'Male',
            'admission_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->postJson("/api/rnd/patients/{$patient->id}/ncp-records");

        $response->assertStatus(201)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.rnd_user_id', $rnd->id)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('ncp_records', [
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'draft',
        ]);
    }

    public function test_patient_resource_exposes_system_calculated_risk_score()
    {
        $rnd = User::forceCreate(['name' => 'Test', 'email' => 'test5@example.com', 'password' => Hash::make('pass'), 'role' => 'RND', 'is_active' => true]);

        $patient = Patient::forceCreate([
            'name' => 'Jane Doe',
            'dob' => '1995-05-05',
            'sex' => 'Female',
            'admission_date' => '2024-01-01',
        ]);

        NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'active',
            'risk_score' => 3.5,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')->getJson("/api/rnd/patients/{$patient->id}");

        $response->assertOk()
            ->assertJsonPath('risk_score', '3.50')
            ->assertJsonMissingPath('ai_risk_score');
    }

    public function test_ncp_records_schema_uses_risk_score_column()
    {
        $this->assertTrue(Schema::hasColumn('ncp_records', 'risk_score'));
        $this->assertFalse(Schema::hasColumn('ncp_records', 'ai_risk_score'));
    }
}
