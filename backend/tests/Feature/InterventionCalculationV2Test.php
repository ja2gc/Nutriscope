<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InterventionCalculationV2Test extends TestCase
{
    use RefreshDatabase;

    private function record(): NcpRecord
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $rnd = User::factory()->create(['role' => 'RND', 'is_active' => true]);
        $patient = Patient::factory()->create(['dob' => '1986-10-21', 'sex' => 'Male']);
        $record = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        Assessment::factory()->create([
            'ncp_record_id' => $record->id, 'weight' => 70, 'height' => 170,
            'edema_present' => false, 'pregnancy_lactation_status' => 'none',
            'physical_activity_level' => 'sedentary', 'created_at' => '2026-10-21 08:00:00',
        ]);
        Diagnosis::factory()->create(['ncp_record_id' => $record->id]);
        $this->actingAs($rnd, 'sanctum');

        return $record;
    }

    public function test_autofill_and_saved_snapshot_use_fixed_adjustment_and_preserve_history(): void
    {
        $record = $this->record();
        $url = "/api/rnd/ncp-records/{$record->uuid}/interventions";
        $options = [
            'goal_type' => 'general_diet', 'energy_adjustment_mode' => 'surplus',
            'energy_adjustment_kcal' => 300, 'fluid_mode' => 'restricted', 'fluid_ml' => 1700,
            'calculation_options' => ['refeeding_risk' => true],
        ];
        $this->postJson("$url/autofill", $options)->assertOk()
            ->assertJsonPath('data.energy_kcal', 2181)
            ->assertJsonPath('data.fluid_ml', 1700)
            ->assertJsonPath('data.guidance.strategies', 'Advance intake under clinical supervision with electrolyte monitoring; displayed calories are the final target; count all sources toward the fluid maximum.');
        $saved = $this->postJson($url, $options)->assertCreated()
            ->assertJsonPath('data.calculation_snapshot.version', 2)
            ->assertJsonPath('data.energy_adjustment_kcal', 300);
        $id = $saved->json('data.id');
        $snapshot = Intervention::sole()->calculation_snapshot;
        $monitoring = Monitoring::factory()->create(['ncp_record_id' => $record->id, 'weight' => 90, 'observed_at' => '2026-11-21']);
        $this->getJson("$url/$id")->assertOk()->assertJsonPath('data.calculation_snapshot', $snapshot);
        $this->patchJson("$url/$id", ['education_notes' => 'Edited guidance'])->assertOk()
            ->assertJsonPath('data.calculation_snapshot', $snapshot);
        $this->patchJson("$url/$id", ['calculation_options' => ['refeeding_risk' => false]])->assertOk()
            ->assertJsonPath('data.calculation_snapshot', $snapshot)
            ->assertJsonPath('data.energy_kcal', '2181.00');
        $this->patchJson("$url/$id", ['recalculate' => true])->assertOk()
            ->assertJsonPath('data.calculation_snapshot.inputs.weightKg', 90)
            ->assertJsonPath('data.source_monitoring_id', $monitoring->uuid);
    }

    public function test_manual_override_is_identified_and_client_snapshot_cannot_be_forged(): void
    {
        $record = $this->record();
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/interventions", [
            'goal_type' => 'general_diet', 'energy_kcal' => 1900,
            'energy_adjustment_mode' => 'deficit', 'energy_adjustment_kcal' => 300,
            'counseling_goals' => 'The total target includes −300 kcal/day; review weight, intake and tolerance.',
            'calculation_snapshot' => ['version' => 999, 'baseline_energy_kcal' => 1],
        ])->assertCreated()->assertJsonPath('data.calculation_snapshot.version', 2)
            ->assertJsonPath('data.calculation_snapshot.manual_overrides.energy_kcal', 1900)
            ->assertJsonPath('data.calculation_snapshot.calculated.energy_kcal', 1581)
            ->assertJsonPath('data.counseling_goals', 'Follow the confirmed energy target and review weight, intake and tolerance at follow-up.');
    }

    public function test_invalid_adjustments_and_missing_fluid_limit_are_rejected(): void
    {
        $record = $this->record();
        $url = "/api/rnd/ncp-records/{$record->uuid}/interventions/autofill";
        foreach ([0, -300, 0.5, 10001] as $amount) {
            $this->postJson($url, ['goal_type' => 'general_diet', 'energy_adjustment_mode' => 'deficit', 'energy_adjustment_kcal' => $amount])
                ->assertUnprocessable();
        }
        $this->postJson($url, ['goal_type' => 'general_diet', 'fluid_mode' => 'restricted'])->assertUnprocessable();
        $this->postJson($url, ['goal_type' => 'diabetic_control', 'disease_stage' => 'stage_2'])->assertUnprocessable();
    }

    public function test_infant_age_is_measured_at_observation_date_without_adult_target_weight(): void
    {
        $record = $this->record();
        $record->patient->update(['dob' => '2026-01-21']);
        $record->assessment->update(['weight' => 8, 'height' => 70, 'created_at' => '2026-10-21 08:00:00']);
        $response = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/interventions/autofill", ['goal_type' => 'general_diet'])
            ->assertOk()->assertJsonPath('data.energy_kcal', 671)
            ->assertJsonPath('data.calculation_status', 'warning');
        $this->assertGreaterThan(0.5, $response->json('data.calculation_snapshot.inputs.ageYears'));
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/interventions", [
            'goal_type' => 'general_diet', 'energy_kcal' => 700, 'protein_g' => 12, 'carbs_g' => 85, 'fat_g' => 30,
        ])->assertCreated()->assertJsonPath('data.weight_target', null);
    }

    public function test_legacy_goal_can_be_saved_unchanged_without_recalculation_or_relabeling(): void
    {
        $record = $this->record();
        $legacy = Intervention::factory()->create(['ncp_record_id' => $record->id,
            'goal_type' => 'malnutrition', 'disease_stage' => 'moderate', 'energy_kcal' => 500, 'protein_g' => 200, 'fat_g' => 0,
            'education_notes' => str_repeat('A', 1300), 'counseling_goals' => str_repeat('B', 1000),
        ]);
        $before = $legacy->refresh()->getAttributes();
        $this->patchJson("/api/rnd/ncp-records/{$record->uuid}/interventions/{$legacy->uuid}", [
            'goal_type' => 'malnutrition', 'disease_stage' => 'moderate', 'energy_kcal' => 500, 'protein_g' => 200, 'fat_g' => 0,
            'education_notes' => $legacy->education_notes, 'counseling_goals' => $legacy->counseling_goals,
            'energy_adjustment_mode' => 'none', 'energy_adjustment_kcal' => 0,
        ])->assertOk()->assertJsonPath('data.calculation_snapshot', null);
        foreach (['goal_type', 'disease_stage', 'energy_kcal', 'education_notes', 'counseling_goals'] as $field) {
            $this->assertSame($before[$field], $legacy->fresh()->getAttributes()[$field]);
        }
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/interventions", ['goal_type' => 'malnutrition', 'disease_stage' => 'moderate'])->assertUnprocessable();
    }

    public function test_custom_manual_targets_reject_impossible_energy_and_preserve_explicit_empty_notes(): void
    {
        $record = $this->record();
        $url = "/api/rnd/ncp-records/{$record->uuid}/interventions";
        $this->postJson($url, ['goal_type' => 'custom', 'energy_kcal' => 500, 'protein_g' => 200])->assertUnprocessable()->assertJsonValidationErrors('energy_kcal');
        $this->postJson($url, ['goal_type' => 'general_diet', 'education_notes' => ''])->assertCreated()
            ->assertJsonPath('data.education_notes', null);
        $this->assertNotEmpty(Intervention::sole()->counseling_goals);
    }

    public function test_under_six_months_uses_feeding_guidance_and_cannot_generate_an_ordinary_food_menu(): void
    {
        $record = $this->record();
        $record->patient->update(['dob' => '2026-07-21']);
        $record->assessment->update(['weight' => 6, 'height' => 60]);
        $plan = $this->postJson("/api/rnd/ncp-records/{$record->uuid}/interventions", ['goal_type' => 'general_diet'])
            ->assertCreated()->json('data');
        $this->assertStringContainsString('breastmilk or infant-formula', $plan['education_notes']);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/meal-plans/generate", [
            'intervention_plan_id' => $plan['id'], 'week_start_date' => '2026-10-26', 'exclude_carbs' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('intervention');
        $this->assertDatabaseCount('meal_plans', 0);
    }

    public function test_new_measurements_select_adult_options_without_changing_a_saved_pediatric_plan(): void
    {
        $record = $this->record();
        $record->patient->update(['dob' => '2008-10-22']);
        $url = "/api/rnd/ncp-records/{$record->uuid}/interventions";
        $this->postJson("$url/autofill", ['goal_type' => 'renal_diet', 'disease_stage' => 'ckd_1_2'])->assertUnprocessable();
        $child = $this->postJson($url, ['goal_type' => 'general_diet'])->assertCreated()->json('data');
        $this->assertSame('pediatric_eer', $child['calculation_snapshot']['baseline_kind']);
        $savedSnapshot = Intervention::where('uuid', $child['id'])->firstOrFail()->calculation_snapshot;
        Monitoring::factory()->create(['ncp_record_id' => $record->id, 'weight' => 70, 'observed_at' => '2026-10-23']);
        $this->postJson("$url/autofill", ['goal_type' => 'renal_diet', 'disease_stage' => 'ckd_1_2'])->assertOk();
        $this->getJson($url.'/'.$child['id'])->assertOk()->assertJsonPath('data.calculation_snapshot', $savedSnapshot)
            ->assertJsonPath('data.goal_type', 'general_diet');
        $this->postJson("$url/autofill", ['goal_type' => 'general_diet', 'calculation_options' => ['weight_basis' => 'dry']])
            ->assertUnprocessable()->assertJsonValidationErrors('calculation_options.weight_basis');
    }
}
