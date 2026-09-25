<?php

namespace Tests\Unit;

use App\Services\Diagnosis\PesEligibilityService;
use App\Services\Diagnosis\PesRuleCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PesEligibilityServiceTest extends TestCase
{
    public function test_rule_cards_have_versioned_verified_contracts(): void
    {
        $catalog = app(PesRuleCatalog::class);

        $this->assertSame('2026-09-21-v1', $catalog->version());
        $this->assertCount(6, $catalog->cards());

        foreach ($catalog->cards() as $card) {
            $this->assertSame(
                ['id', 'problem_key', 'required_evidence', 'corroborating_evidence', 'disqualifiers', 'source'],
                array_keys($card),
            );
            $this->assertMatchesRegularExpression('/_v1$/', $card['id']);
            $this->assertNotSame('', $card['problem_key']);
            $this->assertNotEmpty($card['required_evidence']);
            $this->assertSame(
                ['id', 'issuer', 'title', 'version', 'location', 'url'],
                array_keys($card['source']),
            );
            foreach ($card['source'] as $value) {
                $this->assertNotSame('', $value);
            }
            $this->assertStringStartsWith('https://', $card['source']['url']);
        }
    }

    #[DataProvider('eligibleEvidenceProvider')]
    public function test_each_supported_family_requires_direct_structured_evidence(
        string $expectedRule,
        array $evidence,
    ): void {
        $eligible = app(PesEligibilityService::class)->eligible($evidence);

        $this->assertContains($expectedRule, array_column($eligible, 'candidate_id'));
    }

    public static function eligibleEvidenceProvider(): array
    {
        return [
            'unintended weight loss' => ['unintended_weight_loss_v1', [
                'weight_loss_percentage' => 6.5,
                'weight_change_period' => ['value' => 2, 'unit' => 'months'],
                'appetite' => 'decreased',
            ]],
            'adult overweight' => ['overweight_obesity_v1', [
                'bmi' => 27.2,
                'age_group' => 'adult',
                'weight_kg' => 72.0,
                'height_cm' => 163.0,
            ]],
            'swallowing or chewing difficulty' => ['swallowing_chewing_difficulty_v1', [
                'chewing_swallowing_difficulties' => 'Coughing with thin liquids',
                'present_diet' => 'Soft diet',
            ]],
            'altered GI function' => ['altered_gi_function_v1', [
                'gi_symptoms' => ['constipation' => 'No bowel movement for four days'],
                'present_diet' => 'Regular diet',
            ]],
            'food medication interaction' => ['food_medication_interaction_v1', [
                'nutrient_drug_interaction' => 'Warfarin and inconsistent vitamin K intake documented',
                'medication_count' => 1,
            ]],
            'predicted suboptimal intake' => ['predicted_suboptimal_intake_v1', [
                'energy_intake_status' => 'Sub-optimal',
                'appetite' => 'decreased',
                'present_diet' => 'Mostly liquids',
            ]],
        ];
    }

    public function test_missing_negative_and_disqualified_evidence_are_not_eligible(): void
    {
        $service = app(PesEligibilityService::class);

        $this->assertSame([], $service->eligible([
            'weight_loss_percentage' => 0,
            'bmi' => 24.9,
            'age_group' => 'adult',
            'energy_intake_status' => 'No change',
        ]));
        $this->assertSame([], $service->eligible([
            'weight_loss_percentage' => 8,
            'weight_change_period' => null,
        ]));
        $this->assertSame([], $service->eligible([
            'bmi' => 31,
            'age_group' => 'pediatric',
        ]));
        $this->assertSame([], $service->eligible([
            'chewing_swallowing_difficulties' => 'Denies chewing or swallowing difficulty',
            'gi_symptoms' => ['constipation' => 'No constipation'],
            'nutrient_drug_interaction' => 'None',
            'medication_count' => 2,
        ]));
    }

    public function test_results_exclude_existing_and_dismissed_problems_and_stop_at_three(): void
    {
        $evidence = [
            'weight_loss_percentage' => 8,
            'weight_change_period' => ['value' => 1, 'unit' => 'months'],
            'bmi' => 31,
            'age_group' => 'adult',
            'weight_kg' => 82,
            'height_cm' => 163,
            'chewing_swallowing_difficulties' => 'Difficulty chewing solids',
            'gi_symptoms' => ['constipation' => 'Persistent constipation'],
            'nutrient_drug_interaction' => 'Explicit food and medication interaction',
            'medication_count' => 2,
            'energy_intake_status' => 'Sub-optimal',
            'appetite' => 'decreased',
            'present_diet' => 'Mostly liquids',
            'existing_problem_keys' => ['Unintended Weight Loss'],
        ];

        $eligible = app(PesEligibilityService::class)->eligible(
            $evidence,
            ['swallowing_chewing_difficulty_v1'],
        );

        $this->assertCount(3, $eligible);
        $this->assertNotContains('unintended_weight_loss_v1', array_column($eligible, 'candidate_id'));
        $this->assertNotContains('swallowing_chewing_difficulty_v1', array_column($eligible, 'candidate_id'));
    }
}
