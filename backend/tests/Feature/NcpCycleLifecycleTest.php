<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcpCycleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_cycle_requires_clinically_complete_assessment_diagnosis_and_intervention(): void
    {
        [$rnd, , $ncp] = $this->context();

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}", [
            'action' => 'complete',
        ])->assertUnprocessable();

        $this->completeAdi($ncp);

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}", [
            'action' => 'complete',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('ncp_records', ['id' => $ncp->id, 'status' => 'completed']);
    }

    public function test_discontinue_cycle_requires_a_reason_and_allows_a_new_cycle(): void
    {
        [$rnd, $patient, $ncp] = $this->context();

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}", [
            'action' => 'discontinue',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason_code');

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/ncp-records/{$ncp->uuid}", [
            'action' => 'discontinue',
            'reason_code' => 'lost_to_follow_up',
        ])->assertOk()->assertJsonPath('data.status', 'discontinued');

        $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/patients/{$patient->uuid}/ncp-records")
            ->assertCreated();
    }

    public function test_delete_uses_clinical_completeness_not_related_row_existence(): void
    {
        [$rnd, , $ncp] = $this->context();
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => null, 'height' => null]);
        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'problem' => '[Select problem]',
            'etiology' => '[Select etiology]',
            'signs_symptoms' => '[Select signs]',
            'pes_statement' => '[Select problem] related to [Select etiology] as evidenced by [Select signs]',
        ]);
        Intervention::forceCreate(['ncp_record_id' => $ncp->id, 'goal_type' => null]);

        $this->actingAs($rnd, 'sanctum')
            ->deleteJson("/api/rnd/ncp-records/{$ncp->uuid}")
            ->assertNoContent();

        $this->assertDatabaseMissing('ncp_records', ['id' => $ncp->id]);
    }

    public function test_current_and_past_history_are_separate_and_past_is_two_per_page(): void
    {
        [$rnd, $patient, $current] = $this->context();
        NcpRecord::factory()->count(3)->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'completed',
        ]);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/ncp-records?scope=current")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $current->uuid);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/patients/{$patient->uuid}/ncp-records?scope=past&page=1")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    private function context(): array
    {
        $rnd = User::factory()->rnd()->create();
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'status' => 'active',
        ]);

        return [$rnd, $patient, $ncp];
    }

    private function completeAdi(NcpRecord $ncp): void
    {
        Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 60, 'height' => 165]);
        Diagnosis::forceCreate([
            'ncp_record_id' => $ncp->id,
            'problem' => 'Inadequate energy intake',
            'etiology' => 'reduced appetite',
            'signs_symptoms' => 'intake below estimated needs',
            'pes_statement' => 'Inadequate energy intake related to reduced appetite as evidenced by intake below estimated needs',
        ]);
        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'maintenance',
            'energy_kcal' => 1800,
            'protein_g' => 70,
            'carbs_g' => 250,
            'fat_g' => 60,
        ]);
    }
}
