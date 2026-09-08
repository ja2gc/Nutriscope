<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcpVisitAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_visit_can_attribute_assessment_diagnosis_and_intervention_work(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);

        $visitId = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'Complete ADIME work', 'ncp_record_id' => $ncp->uuid],
        )->assertCreated()->json('data.id');

        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/assessment", [
            'weight' => 70,
            'usual_weight' => 70,
            'height' => 170,
            'physical_activity_level' => 'light',
        ])->assertCreated();
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/diagnoses", [
            'domain' => 'NI',
            'problem' => 'Inadequate oral food intake',
            'etiology' => 'reduced appetite',
            'signs_symptoms' => 'Reported intake below estimated needs',
        ])->assertCreated();
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/intervention", [
            'goal_type' => 'custom',
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 55,
        ])->assertCreated();

        $visit = NcpAppointment::where('uuid', $visitId)->firstOrFail();
        $this->assertSame(['assessment', 'diagnosis', 'intervention'], $visit->worked_on);
        $this->assertSame(['assessment', 'diagnosis', 'intervention'], $visit->newly_completed);
    }

    public function test_successful_clinical_mutations_are_paired_once_with_active_visit_but_failed_save_is_not(): void
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);
        Intervention::factory()->create(['ncp_record_id' => $ncp->id]);

        $visitId = $this->actingAs($rnd, 'sanctum')->postJson(
            "/api/rnd/patients/{$patient->uuid}/appointments",
            ['source' => 'walk_in', 'purpose' => 'Monitoring follow-up', 'ncp_record_id' => $ncp->uuid],
        )->assertCreated()->json('data.id');

        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", ['weight' => 'invalid'])
            ->assertUnprocessable();
        $this->assertSame([], NcpAppointment::where('uuid', $visitId)->firstOrFail()->worked_on ?? []);

        $unauthorizedUser = User::factory()->fss()->create();
        $this->actingAs($unauthorizedUser, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", ['weight' => 73])
            ->assertForbidden();
        $this->assertSame([], NcpAppointment::where('uuid', $visitId)->firstOrFail()->worked_on ?? []);

        $monitoringId = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", ['weight' => 72])
            ->assertCreated()
            ->json('data.id');
        $this->patchJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/{$monitoringId}", ['weight' => 71.5])
            ->assertOk();

        $visit = NcpAppointment::where('uuid', $visitId)->firstOrFail();
        $this->assertSame(['monitoring'], $visit->worked_on);
    }
}
