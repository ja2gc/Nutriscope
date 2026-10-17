<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\MonitoringPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MonitoringPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeNcp(): NcpRecord
    {
        $patient = Patient::factory()->create(['sex' => 'Male', 'dob' => '1980-01-01']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id]);

        $assessment = Assessment::factory()->create([
            'ncp_record_id' => $ncp->id,
            'weight' => 50,
            'height' => 170,
            'bmi' => 17.3,
            'ibw_percentage' => 75,
        ]);
        $assessment->biochemicalData()->create([
            'albumin' => 2.8,   // abnormal (LOW)
            'glucose' => 90,    // normal
        ]);

        Intervention::create([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'renal_diet',
            'energy_kcal' => 1800,
            'protein_g' => 60,
        ]);

        Diagnosis::create([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NC',
            'problem' => 'Altered nutrition-related lab values',
            'etiology' => 'renal disease',
            'signs_symptoms' => 'low albumin',
            'pes_statement' => 'Altered lab values related to renal disease as evidenced by low albumin',
        ]);

        Monitoring::create([
            'ncp_record_id' => $ncp->id,
            'weight' => 52,
            'bmi' => 18.0,
            'lab_values' => ['albumin' => 3.0, 'energy_kcal' => 1600],
        ]);

        return $ncp->fresh();
    }

    public function test_builds_plan_with_visit1_baseline_and_followups(): void
    {
        $plan = app(MonitoringPlanService::class)->build($this->makeNcp());

        $this->assertSame('Visit 1', $plan['visits'][0]['label']);
        $this->assertSame('assessment', $plan['visits'][0]['type']);
        $this->assertSame('Follow-up 1', $plan['visits'][1]['label']);
    }

    public function test_albumin_tracked_with_baseline_seeded(): void
    {
        $plan = app(MonitoringPlanService::class)->build($this->makeNcp());

        $albumin = $this->indicator($plan, 'albumin');
        $this->assertNotNull($albumin, 'albumin should be tracked');
        $this->assertContains('flagged_abnormal', $albumin['sources']);
        $this->assertSame('Visit 1', $albumin['series'][0]['visit']);
        $this->assertSame(2.8, $albumin['series'][0]['value']);
        $this->assertSame(3.0, $albumin['series'][1]['value']);
    }

    public function test_prescription_intake_indicator_has_target(): void
    {
        $plan = app(MonitoringPlanService::class)->build($this->makeNcp());

        $energy = $this->indicator($plan, 'energy_kcal');
        $this->assertNotNull($energy);
        $this->assertSame('intake', $energy['category']);
        $this->assertSame(1800.0, $energy['target']);
        $this->assertSame('Visit 1', $energy['series'][0]['visit']);
        $this->assertNull($energy['series'][0]['value'], 'Missing assessment intake must not be replaced by the first follow-up.');
    }

    public function test_weight_target_is_display_ready_and_progress_requires_target_attainment(): void
    {
        $plan = app(MonitoringPlanService::class)->build($this->makeNcp());

        $weight = $this->indicator($plan, 'weight');
        $this->assertNotNull($weight);
        $this->assertSame(round($weight['target'], 1), $weight['target']);
        $this->assertSame('in_progress', $weight['latest_status']);
    }

    public function test_pes_statements_present(): void
    {
        $plan = app(MonitoringPlanService::class)->build($this->makeNcp());

        $this->assertNotEmpty($plan['pes_statements']);
    }

    public function test_micronutrient_limits_and_previously_logged_indicators_share_the_plan(): void
    {
        $ncp = $this->makeNcp();
        Intervention::create([
            'ncp_record_id' => $ncp->id, 'goal_type' => 'custom',
            'displayed_nutrients' => ['vitamin_c'],
            'micronutrient_limits' => ['fiber' => ['min' => 25, 'max' => 35, 'unit' => 'g']],
        ]);
        Monitoring::create(['ncp_record_id' => $ncp->id, 'lab_values' => ['micro_fiber' => 20, 'glucose' => 110]]);
        $plan = app(MonitoringPlanService::class)->build($ncp);
        $fiber = $this->indicator($plan, 'micro_fiber');
        $this->assertNotNull($fiber);
        $this->assertSame(['min' => 25.0, 'max' => 35.0], $fiber['reference']);
        $this->assertSame(20.0, collect($fiber['series'])->last()['value']);
        $this->assertNotNull($this->indicator($plan, 'micro_vitamin_c'));
        $this->assertNotNull($this->indicator($plan, 'glucose'));
    }

    /** @return array<string,mixed>|null */
    private function indicator(array $plan, string $key): ?array
    {
        return (new Collection($plan['indicators']))->firstWhere('key', $key);
    }
}
