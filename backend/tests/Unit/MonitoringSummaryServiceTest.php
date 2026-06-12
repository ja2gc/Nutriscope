<?php

namespace Tests\Unit;

use App\Services\MonitoringSummaryService;
use Tests\TestCase;

/**
 * Phase 6 — rule-based delta engine guard. Exercises the pure summarizePair /
 * evaluateGoal logic with plain arrays (no DB) so the zero-token monitoring
 * summary stays correct and deterministic.
 */
class MonitoringSummaryServiceTest extends TestCase
{
    private MonitoringSummaryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $svc = new MonitoringSummaryService();
        $this->svc = $svc;
    }

    public function test_first_visit_has_no_previous_but_still_reports_intake(): void
    {
        $current = ['date' => '2026-06-11', 'weight' => 60.0, 'bmi' => 22.0, 'energy_kcal' => 1600.0, 'protein_g' => 70.0];
        $out = $this->svc->summarizePair(null, $current, ['energy_kcal' => 2000.0, 'protein_g' => 80.0]);

        $this->assertTrue($out['has_data']);
        $this->assertFalse($out['has_previous']);
        // Weight change present but with null delta (no previous)
        $weight = collect($out['changes'])->firstWhere('metric', 'weight');
        $this->assertNull($weight['delta']);
        // Energy intake 1600/2000 = 80% → under-target flag
        $energy = collect($out['intake'])->firstWhere('metric', 'energy_kcal');
        $this->assertSame(80, $energy['pct']);
        $this->assertSame('under', $energy['flag']);
    }

    public function test_weight_loss_goal_marks_dropping_weight_as_met(): void
    {
        $prev = ['date' => '2026-05-01', 'weight' => 90.0];
        $curr = ['date' => '2026-06-01', 'weight' => 87.0];
        $out  = $this->svc->summarizePair($prev, $curr, [], 'Normal');

        $weight = collect($out['changes'])->firstWhere('metric', 'weight');
        $this->assertSame(-3.0, $weight['delta']);
        $this->assertSame('down', $weight['direction']);
        $this->assertSame('met', $weight['status']); // losing weight is the goal
    }

    public function test_malnutrition_status_flips_weight_direction(): void
    {
        $prev = ['date' => '2026-05-01', 'weight' => 45.0];
        $curr = ['date' => '2026-06-01', 'weight' => 47.0];
        $out  = $this->svc->summarizePair($prev, $curr, [], 'Severe Malnutrition');

        $weight = collect($out['changes'])->firstWhere('metric', 'weight');
        $this->assertSame('met', $weight['status']); // gaining weight is the goal here
    }

    public function test_lab_in_range_is_met_and_improving_lab_is_in_progress(): void
    {
        // HbA1c: max 7.0, lowerIsBetter. 6.5 is in range → met.
        $out = $this->svc->summarizePair(['hba1c' => 8.0], ['date' => 'x', 'hba1c' => 6.5]);
        $hba1c = collect($out['changes'])->firstWhere('metric', 'hba1c');
        $this->assertSame('met', $hba1c['status']);

        // Still 7.8 but dropping from 9.0 → in_progress.
        $out2 = $this->svc->summarizePair(['hba1c' => 9.0], ['date' => 'x', 'hba1c' => 7.8]);
        $hba1c2 = collect($out2['changes'])->firstWhere('metric', 'hba1c');
        $this->assertSame('in_progress', $hba1c2['status']);
    }

    public function test_goal_evaluation_met_when_all_targets_in_range_and_intake_adequate(): void
    {
        $prev = ['weight' => 70.0, 'albumin' => 3.2];
        $curr = ['date' => 'x', 'weight' => 68.0, 'albumin' => 4.0, 'energy_kcal' => 1950.0];
        $out  = $this->svc->summarizePair($prev, $curr, ['energy_kcal' => 2000.0], 'Normal');

        // weight met (down), albumin met (>=3.5), intake 97% on target → met
        $this->assertSame('met', $out['goal_evaluation']['status']);
    }

    public function test_goal_evaluation_partial_with_under_intake(): void
    {
        $prev = ['albumin' => 3.2];
        $curr = ['date' => 'x', 'albumin' => 4.0, 'energy_kcal' => 1000.0];
        $out  = $this->svc->summarizePair($prev, $curr, ['energy_kcal' => 2000.0]);

        // albumin met but energy intake 50% → under → not fully met
        $this->assertSame('partial', $out['goal_evaluation']['status']);
        $this->assertNotEmpty($out['goal_evaluation']['reasons']);
    }
}
