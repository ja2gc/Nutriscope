<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\MonitoringPlanService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class MonitoringPlanBaselineTest extends TestCase
{
    public function test_alternate_micronutrient_units_use_the_same_menu_limits_and_monitoring_reference(): void
    {
        $assessment = new Assessment(['weight' => 60, 'height' => 170, 'bmi' => 20.76]);
        $assessment->setRelation('biochemicalData', null);
        $plan = new Intervention(['micronutrient_limits' => ['sodium' => ['max' => 2, 'unit' => 'g']]]);
        $ncp = new NcpRecord;
        $ncp->setRelations([
            'patient' => new Patient(['sex' => 'Male']), 'assessment' => $assessment,
            'diagnoses' => new Collection, 'intervention' => $plan,
            'monitorings' => new Collection([new Monitoring(['observed_at' => '2026-11-12', 'lab_values' => ['micro_sodium' => 1500]])]),
        ]);
        $indicator = collect(app(MonitoringPlanService::class)->build($ncp)['indicators'])->firstWhere('key', 'micro_sodium');
        $this->assertSame('mg', $indicator['unit']);
        $this->assertEquals(2000, $indicator['reference']['max']);
        $this->assertEquals(1500, $indicator['series'][1]['value']);
        $this->assertEquals(['sodium' => ['max' => 2000, 'unit' => 'mg']], $plan->foodNutrientLimits());
        $this->assertSame(['sodium' => ['max' => 2, 'unit' => 'g']], $plan->micronutrient_limits);
    }

    public function test_unmeasured_assessment_intake_is_not_replaced_with_the_first_logged_value(): void
    {
        $assessment = new Assessment(['weight' => 60, 'height' => 170, 'bmi' => 20.76]);
        $assessment->setRelation('biochemicalData', null);
        $ncp = new NcpRecord;
        $ncp->setRelations([
            'patient' => new Patient(['sex' => 'Male']), 'assessment' => $assessment,
            'diagnoses' => new Collection,
            'intervention' => new Intervention(['energy_kcal' => 1800, 'micronutrient_limits' => ['fiber' => ['min' => 25, 'unit' => 'g']]]),
            'monitorings' => new Collection([new Monitoring(['observed_at' => '2026-10-17', 'lab_values' => ['energy_kcal' => 1400, 'micro_fiber' => 20]])]),
        ]);
        $indicators = collect(app(MonitoringPlanService::class)->build($ncp)['indicators'])->keyBy('key');
        foreach (['energy_kcal', 'micro_fiber'] as $key) {
            $this->assertSame('Visit 1', $indicators[$key]['series'][0]['visit']);
            $this->assertNull($indicators[$key]['series'][0]['value']);
            $this->assertSame('Follow-up 1', $indicators[$key]['series'][1]['visit']);
        }
    }
}
