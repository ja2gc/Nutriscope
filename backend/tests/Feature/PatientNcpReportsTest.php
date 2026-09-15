<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientNcpReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rnd_lists_one_patients_combined_ncp_reports_newest_first_with_public_ids(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $olderNcp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'created_at' => '2026-05-01 08:00:00',
        ]);
        $newerNcp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'created_at' => '2026-07-01 08:00:00',
        ]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $olderNcp->id]);
        $plan = MealPlan::factory()->create([
            'patient_id' => $patient->id,
            'intervention_id' => $intervention->id,
            'created_at' => '2026-06-01 08:00:00',
        ]);
        $otherNcp = NcpRecord::factory()->create([
            'patient_id' => $otherPatient->id,
            'rnd_user_id' => $rnd->id,
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/reports/patients/{$patient->uuid}/instances?per_page=2")
            ->assertOk()
            ->assertJsonPath('patient.id', $patient->uuid)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.type', 'ncp_summary')
            ->assertJsonPath('data.0.params.ncp_record_id', $newerNcp->uuid)
            ->assertJsonPath('data.1.type', 'patient_menu_plan')
            ->assertJsonPath('data.1.params.meal_plan_id', $plan->uuid);

        $this->assertStringNotContainsString($otherNcp->uuid, $response->getContent());
    }

    public function test_fss_cannot_open_patient_ncp_reports_feed(): void
    {
        $fss = User::factory()->create(['role' => 'FSS']);
        $patient = Patient::factory()->create();

        $this->actingAs($fss, 'sanctum')
            ->getJson("/api/rnd/reports/patients/{$patient->uuid}/instances")
            ->assertForbidden();
    }
}
