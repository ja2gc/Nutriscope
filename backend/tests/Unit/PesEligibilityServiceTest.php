<?php

namespace Tests\Unit;

use App\Services\Diagnosis\PesEligibilityService;
use App\Services\Diagnosis\PesRuleCatalog;
use App\Support\ClinicalAiContext;
use App\Support\DiagnosisBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PesEligibilityServiceTest extends TestCase
{
    public function test_asia_pacific_bmi_sign_maps_to_a_current_builder_choice(): void
    {
        $sign = 'BMI ≥ 23 (Asia-Pacific overweight / obesity)';
        $builder = DiagnosisBuilder::fromDraft('NC', 'Overweight / Obesity', 'Documented cause', $sign);
        $this->assertSame([$sign], $builder['sign_checks']);
        $this->assertSame('', $builder['sign_other']);
    }

    public function test_knowledge_concern_does_not_open_unrelated_behavioral_candidates(): void
    {
        $ids = array_column(app(PesEligibilityService::class)->eligible([
            'knowledge_notes' => 'Cannot identify carbohydrate foods.',
            'social_history' => 'Limited access to nutrition education.',
        ]), 'candidate_id');
        $this->assertContains('knowledge_deficit_v2', $ids);
        foreach (['physical_inactivity_v2', 'food_insecurity_v2', 'readiness_v2', 'self_monitoring_v2', 'adherence_v2', 'food_beliefs_v2'] as $id) {
            $this->assertNotContains($id, $ids);
        }
    }

    public function test_intake_candidates_require_the_corresponding_nutrient_and_direction(): void
    {
        $service = app(PesEligibilityService::class);
        $ids = array_column($service->eligible(['dietary_intake' => 'Consumes half of usual meals; protein intake is adequate.']), 'candidate_id');
        $this->assertContains('inadequate_energy_v2', $ids);
        $this->assertContains('inadequate_oral_v2', $ids);
        foreach (['excessive_energy_v2', 'inadequate_protein_v2', 'excessive_protein_v2', 'inadequate_fat_v2', 'excessive_carbohydrates_v2'] as $id) {
            $this->assertNotContains($id, $ids);
        }
        $this->assertTrue($service->supportsIntakeProblem('Inadequate Protein Intake', 'Protein intake below prescribed needs'));
        $this->assertFalse($service->supportsIntakeProblem('Excessive Protein Intake', 'Protein intake below prescribed needs'));
        $this->assertFalse($service->supportsIntakeProblem('Inadequate Protein Intake', 'Protein intake is adequate'));
        $this->assertFalse($service->supportsIntakeProblem('Inadequate Energy Intake', 'Protein intake below prescribed needs'));
        $this->assertTrue($service->supportsIntakeProblem('Inadequate Oral Intake', 'Poor intake for three weeks'));
    }

    public function test_rule_cards_have_versioned_verified_contracts(): void
    {
        $catalog = app(PesRuleCatalog::class);

        $this->assertSame('2026-10-26-v9:'.ClinicalAiContext::version(), $catalog->version());
        $this->assertEqualsCanonicalizing(['NI', 'NC', 'NB'], array_values(array_unique(array_column($catalog->cards(), 'domain'))));

        foreach ($catalog->cards() as $card) {
            $this->assertSame(
                ['id', 'domain', 'problem_key', 'required_evidence', 'corroborating_evidence', 'disqualifiers', 'source'],
                array_keys($card),
            );
            $this->assertMatchesRegularExpression('/_v[12]$/', $card['id']);
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

    public function test_behavioral_signals_distinguish_food_access_from_other_costs_and_recognize_inactivity(): void
    {
        $service = app(PesEligibilityService::class);
        $this->assertNotContains('food_insecurity_v2', array_column($service->eligible(['social_history' => 'Cannot afford prescribed medications.']), 'candidate_id'));
        $this->assertContains('food_insecurity_v2', array_column($service->eligible(['social_history' => 'Cannot afford food and misses meals.']), 'candidate_id'));
        $this->assertContains('physical_inactivity_v2', array_column($service->eligible(['lifestyle' => 'Long working hours; no planned physical activity.']), 'candidate_id'));
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
                'weight_change_direction' => 'loss',
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
        ];
    }

    public function test_missing_negative_and_disqualified_evidence_are_not_eligible(): void
    {
        $service = app(PesEligibilityService::class);

        $this->assertSame([], $service->eligible([
            'weight_loss_percentage' => 0,
            'bmi' => 22.99,
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
        $this->assertSame([], $service->eligible([
            'chewing_swallowing_difficulties' => 'No',
        ]));
        $this->assertSame([], $service->eligible([
            'gi_symptoms' => ['constipation' => 'No'],
        ]));
    }

    public function test_results_exclude_existing_and_dismissed_problems_without_limiting_contextual_review_to_first_three(): void
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
            'dietary_intake' => 'Protein intake below prescribed needs.',
        ];

        $eligible = app(PesEligibilityService::class)->eligible(
            $evidence,
            ['swallowing_chewing_difficulty_v1'],
        );

        $this->assertGreaterThanOrEqual(4, count($eligible));
        $this->assertNotContains('unintended_weight_loss_v1', array_column($eligible, 'candidate_id'));
        $this->assertNotContains('swallowing_chewing_difficulty_v1', array_column($eligible, 'candidate_id'));
        $this->assertNotContains('predicted_suboptimal_intake_v1', array_column($eligible, 'candidate_id'));
    }
}
