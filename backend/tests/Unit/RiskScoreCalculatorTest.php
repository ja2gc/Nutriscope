<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\BiochemicalData;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\LabFlagService;
use App\Services\RiskScoreCalculator;
use PHPUnit\Framework\TestCase;

class RiskScoreCalculatorTest extends TestCase
{
    private function createAssessment(array $assessmentAttributes = [], array $biochemicalAttributes = [], string $sex = 'Male'): Assessment
    {
        $patient = new Patient(['sex' => $sex, 'screening_type' => 'adult']);
        $ncp = new NcpRecord;
        $ncp->setRelation('patient', $patient);
        $assessment = new Assessment($assessmentAttributes);
        $assessment->setRelation('ncpRecord', $ncp);
        $assessment->setRelation('biochemicalData', $biochemicalAttributes ? new BiochemicalData($biochemicalAttributes) : null);

        return $assessment;
    }

    public function test_calculate_risk_score_low_risk(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 100.0, // Normal range (85% to 130%)
            'weight_loss_percentage' => 0.0,
        ]);

        $calculator = new RiskScoreCalculator(new LabFlagService);
        $result = $calculator->calculate($assessment);

        // Should have 1 point for screening_type = 'adult'
        $this->assertEquals(1.0, $result['score']);
        $this->assertEquals('Normal', $result['nutritional_status']);
    }

    public function test_calculate_risk_score_moderate_malnutrition(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 80.0, // Checked (< 85%) -> 1 point
            'weight_loss_percentage' => 0.0,
            'chewing_swallowing_difficulties' => 'Cannot swallow solid food', // Checked -> 1 point
        ]);

        $calculator = new RiskScoreCalculator(new LabFlagService);
        $result = $calculator->calculate($assessment);

        // 1 point (screening_type) + 1 point (IBW) + 1 point (mechanical) = 3 points
        $this->assertEquals(3.0, $result['score']);
        $this->assertEquals('Moderate Malnutrition', $result['nutritional_status']);
    }

    public function test_no_symptom_answers_do_not_add_mechanical_risk(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 100.0,
            'weight_loss_percentage' => 0.0,
            'chewing_swallowing_difficulties' => 'No',
            'constipation' => 'No',
            'diarrhea_notes' => 'No',
        ]);

        $result = (new RiskScoreCalculator(new LabFlagService))->calculate($assessment);

        $this->assertSame(1.0, $result['score']);
    }

    public function test_calculate_risk_score_severe_malnutrition(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 140.0, // Checked (> 130%) -> 1 point
            'usual_weight' => 70.0,
            'weight' => 63.0,
            'weight_loss_percentage' => 10.0, // Recorded loss does not establish intentionality.
            'chewing_swallowing_difficulties' => 'Needs soft diet', // Checked -> 1 point
        ], [
            'albumin' => 3.2, // Checked (< 3.5) -> 1 point
        ]);

        $calculator = new RiskScoreCalculator(new LabFlagService);
        $result = $calculator->calculate($assessment);

        // Screening, IBW, mechanical difficulty and albumin each contribute one point.
        $this->assertEquals(4.0, $result['score']);
        $this->assertEquals('Severe Malnutrition', $result['nutritional_status']);
    }

    public function test_recorded_loss_does_not_establish_unintentional_weight_loss(): void
    {
        foreach ([2.0, 12.5] as $percentage) {
            $assessment = $this->createAssessment([
                'weight_loss_percentage' => $percentage,
                'usual_weight' => 70,
                'weight' => 70 * (1 - $percentage / 100),
                'weight_change_period_value' => 4,
                'weight_change_period_unit' => 'months',
            ]);
            $result = (new RiskScoreCalculator(new LabFlagService))->calculate($assessment);
            $this->assertSame(1.0, $result['score']);
            $this->assertNotContains('unintentional_weight_loss', $result['checked_factors']);
        }
    }

    public function test_clinician_selected_weight_loss_remains_scored(): void
    {
        $this->assertSame(3.0, RiskScoreCalculator::scoreFactors(['screening_criteria', 'unintentional_weight_loss']));
    }

    public function test_weight_gain_percentage_does_not_score_as_unintentional_weight_loss(): void
    {
        $assessment = $this->createAssessment([
            'usual_weight' => 70.0,
            'weight' => 73.5,
            'ibw_percentage' => 100.0,
            'weight_loss_percentage' => 5.0,
        ]);

        $result = (new RiskScoreCalculator(new LabFlagService))->calculate($assessment);

        $this->assertEquals(1.0, $result['score']);
        $this->assertNotContains('unintentional_weight_loss', $result['checked_factors']);
    }

    public function test_significant_lab_creatinine_is_sex_aware(): void
    {
        // Creatinine 1.0 mg/dL: normal for males (≤1.2), elevated for females (>0.9).
        // The "significant lab result" point must fire for the female but not the male.
        $female = $this->createAssessment([
            'ibw_percentage' => 100.0,
            'weight_loss_percentage' => 0.0,
        ], ['creatinine' => 1.0], 'Female');

        $male = $this->createAssessment([
            'ibw_percentage' => 100.0,
            'weight_loss_percentage' => 0.0,
        ], ['creatinine' => 1.0], 'Male');

        $calculator = new RiskScoreCalculator(new LabFlagService);

        $this->assertContains('significant_lab_result', $calculator->calculate($female)['checked_factors']);
        $this->assertNotContains('significant_lab_result', $calculator->calculate($male)['checked_factors']);
    }
}
