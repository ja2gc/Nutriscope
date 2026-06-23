<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\BiochemicalData;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\RiskScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiskScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function createAssessment(array $assessmentAttributes = [], array $biochemicalAttributes = [], string $sex = 'Male'): Assessment
    {
        $rnd = User::forceCreate([
            'name' => 'RND User',
            'email' => 'rnd' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'RND',
            'is_active' => true,
        ]);

        $patient = Patient::forceCreate([
            'name' => 'Test Patient',
            'dob' => '1990-01-01',
            'sex' => $sex,
            'admission_date' => now()->toDateString(),
            'screening_type' => 'adult',
        ]);

        $ncp = NcpRecord::forceCreate([
            'patient_id' => $patient->id,
            'rnd_user_id' => $rnd->id,
            'type' => 'new',
            'status' => 'draft',
        ]);

        $assessment = Assessment::forceCreate(array_merge([
            'ncp_record_id' => $ncp->id,
        ], $assessmentAttributes));

        if (!empty($biochemicalAttributes)) {
            BiochemicalData::forceCreate(array_merge([
                'assessment_id' => $assessment->id,
            ], $biochemicalAttributes));
        }

        return $assessment;
    }

    public function test_calculate_risk_score_low_risk(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 100.0, // Normal range (85% to 130%)
            'weight_loss_percentage' => 0.0,
        ]);

        $calculator = app(RiskScoreCalculator::class);
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

        $calculator = app(RiskScoreCalculator::class);
        $result = $calculator->calculate($assessment);

        // 1 point (screening_type) + 1 point (IBW) + 1 point (mechanical) = 3 points
        $this->assertEquals(3.0, $result['score']);
        $this->assertEquals('Moderate Malnutrition', $result['nutritional_status']);
    }

    public function test_calculate_risk_score_severe_malnutrition(): void
    {
        $assessment = $this->createAssessment([
            'ibw_percentage' => 140.0, // Checked (> 130%) -> 1 point
            'weight_loss_percentage' => 10.0, // Checked (> 0%) -> 2 points
            'chewing_swallowing_difficulties' => 'Needs soft diet', // Checked -> 1 point
        ], [
            'albumin' => 3.2, // Checked (< 3.5) -> 1 point
        ]);

        $calculator = app(RiskScoreCalculator::class);
        $result = $calculator->calculate($assessment);

        // 1 point (screening_type) + 1 point (IBW) + 2 points (weight loss) + 1 point (mechanical) + 1 point (low albumin) = 6 points
        $this->assertEquals(6.0, $result['score']);
        $this->assertEquals('Severe Malnutrition', $result['nutritional_status']);
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

        $calculator = app(RiskScoreCalculator::class);

        $this->assertContains('significant_lab_result', $calculator->calculate($female)['checked_factors']);
        $this->assertNotContains('significant_lab_result', $calculator->calculate($male)['checked_factors']);
    }
}
