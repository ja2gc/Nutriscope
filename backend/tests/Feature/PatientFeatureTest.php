<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
