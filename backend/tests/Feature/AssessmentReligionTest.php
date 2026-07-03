<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssessmentReligionTest extends TestCase
{
    use RefreshDatabase;

    private function setup_assessment(): array
    {
        $rnd = User::forceCreate([
            'name' => 'RND', 'email' => 'rnd' . uniqid() . '@test.com',
            'password' => Hash::make('pw'), 'role' => 'RND', 'is_active' => true,
        ]);
        $patient = Patient::forceCreate([
            'name' => 'Patient', 'dob' => '1990-01-01',
            'sex' => 'Male', 'admission_date' => now()->toDateString(),
        ]);
        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id, 'rnd_user_id' => $rnd->id,
            'type' => 'new', 'status' => 'draft',
        ]);
        $assessment = Assessment::forceCreate([
            'ncp_record_id' => $ncp->id,
            'weight' => 70,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
        ]);
        return [$rnd, $ncp, $assessment];
    }

    public function test_assessment_saves_religion(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'religion' => 'Roman Catholic',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assessments', [
            'id'       => $assessment->id,
            'religion' => 'Roman Catholic',
        ]);
    }

    public function test_religion_is_optional(): void
    {
        [$rnd, $ncp, $assessment] = $this->setup_assessment();

        $response = $this->actingAs($rnd, 'sanctum')
            ->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
                'weight' => 65.0,
            ]);

        $response->assertStatus(200);
        $this->assertNull($assessment->fresh()->religion);
    }
}
