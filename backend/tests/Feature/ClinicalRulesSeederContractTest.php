<?php

namespace Tests\Feature;

use App\Models\ClinicalRule;
use Database\Seeders\ClinicalRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalRulesSeederContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_rules_match_current_prescription_contracts_without_removing_manual_rules(): void
    {
        $manualRule = ClinicalRule::query()->create([
            'condition' => 'custom_condition',
            'stage' => 'all',
            'nutrient_or_food_tag' => 'custom_food',
            'rule_type' => 'avoid',
            'threshold' => null,
            'unit' => null,
            'reason' => 'Dietitian-authored rule.',
        ]);

        $this->seed(ClinicalRulesSeeder::class);
        $firstCount = ClinicalRule::query()->count();
        $this->seed(ClinicalRulesSeeder::class);

        $this->assertSame($firstCount, ClinicalRule::query()->count());
        $this->assertTrue(ClinicalRule::query()->whereKey($manualRule->id)->exists());

        $this->assertFalse(ClinicalRule::query()
            ->where('condition', 'DM')
            ->where('nutrient_or_food_tag', 'carbs')
            ->where('rule_type', 'limit')
            ->exists(), 'Diabetes carbohydrate targets must come from the individualized prescription.');

        $this->assertFalse(ClinicalRule::query()
            ->where('condition', 'CKD')
            ->where('stage', 'all')
            ->whereIn('nutrient_or_food_tag', ['potassium', 'phosphate', 'fluid'])
            ->exists(), 'Renal electrolyte and fluid limits must not be applied to every CKD stage.');

        $this->assertFalse(ClinicalRule::query()
            ->where('condition', 'CKD')
            ->whereIn('stage', ['stage1-2', 'stage3-4'])
            ->exists(), 'Seeded CKD stages must use the current disease_stage vocabulary.');

        $liverProtein = ClinicalRule::query()
            ->where('condition', 'liver_disease')
            ->where('stage', 'all')
            ->where('nutrient_or_food_tag', 'protein')
            ->where('rule_type', 'recommend')
            ->sole();

        $this->assertSame(1.2, (float) $liverProtein->threshold);
        $this->assertStringContainsString('not be restricted', strtolower($liverProtein->reason));
        $this->assertTrue(ClinicalRule::query()
            ->where('condition', 'liver_disease')
            ->where('stage', 'all')
            ->where('nutrient_or_food_tag', 'late_evening_snack')
            ->where('rule_type', 'recommend')
            ->exists());
        $this->assertFalse(ClinicalRule::query()
            ->where('condition', 'liver_disease')
            ->where('nutrient_or_food_tag', 'protein')
            ->where('rule_type', 'limit')
            ->exists());
    }
}
