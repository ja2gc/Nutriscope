<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentSaveTest extends TestCase
{
    use RefreshDatabase;

    private function rnd(): User
    {
        return User::forceCreate([
            'name'      => 'RND User',
            'email'     => 'rnd' . uniqid() . '@example.com',
            'password'  => Hash::make('password'),
            'role'      => 'RND',
            'is_active' => true,
        ]);
    }

    private function setup_assessment(): array
    {
        $rnd = $this->rnd();
        $patient = Patient::forceCreate([
            'name'           => 'Test Patient',
            'dob'            => '1990-01-01',
            'sex'            => 'Male',
            'admission_date' => now()->toDateString(),
        ]);
        $ncp = NcpRecord::forceCreate([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type'        => 'new',
            'status'      => 'draft',
        ]);
        $assessment = Assessment::forceCreate(['ncp_record_id' => $ncp->id]);
        return [$rnd, $ncp, $assessment];
    }

    public function test_assessment_saves_physical_activity_level(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/assessment", [
                'physical_activity_level' => 'light',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id'                     => $assessment->id,
            'physical_activity_level' => 'light',
        ]);
    }

    public function test_assessment_saves_muac_mm(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/assessment", [
                'muac_mm' => 285.0,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id'      => $assessment->id,
            'muac_mm' => 285.0,
        ]);
    }

    public function test_assessment_saves_waist_and_hip_cm(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/assessment", [
                'waist_cm' => 88.5,
                'hip_cm'   => 96.0,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id'       => $assessment->id,
            'waist_cm' => 88.5,
            'hip_cm'   => 96.0,
        ]);
    }

    public function test_all_new_columns_are_nullable(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->id}/assessment", [
                'weight' => 70.0,
            ]);

        $response->assertStatus(200);
        $fresh = $assessment->fresh();
        $this->assertNull($fresh->physical_activity_level);
        $this->assertNull($fresh->muac_mm);
        $this->assertNull($fresh->waist_cm);
        $this->assertNull($fresh->hip_cm);
    }
}
