<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Services\NutritionPrescriptionService;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Pure-logic Unit tests for Phase 5 additions.
 * No database — Assessment is instantiated without saving.
 *
 * Covers:
 *   5.2  PAL normalisation map (Activity::normalizedActivityLevel)
 *   5.3  Pregnancy / lactation PDRI adjustment in NutritionPrescriptionService
 *        — Golden gate: none/missing pregnancyLactationStatus → no change
 *        — Pregnant adjustment: +300 kcal, +27 g protein
 *        — Lactating adjustment: +500 kcal, +27 g protein
 */
class AssessmentPhase5Test extends BaseTestCase
{
    // ── 5.2: PAL normalisation ────────────────────────────────────────────────

    private function assessment(string $pal): Assessment
    {
        $a = new Assessment;
        $a->physical_activity_level = $pal;

        return $a;
    }

    public function test_canonical_keys_pass_through(): void
    {
        foreach (['sedentary', 'light', 'moderate', 'very_active', 'extra_active'] as $key) {
            $this->assertSame($key, $this->assessment($key)->normalizedActivityLevel(),
                "Canonical key '{$key}' should pass through unchanged");
        }
    }

    public function test_lightly_active_maps_to_light(): void
    {
        $this->assertSame('light', $this->assessment('lightly_active')->normalizedActivityLevel());
        $this->assertSame('light', $this->assessment('lightly active')->normalizedActivityLevel());
    }

    public function test_moderately_active_maps_to_moderate(): void
    {
        $this->assertSame('moderate', $this->assessment('moderately_active')->normalizedActivityLevel());
        $this->assertSame('moderate', $this->assessment('moderately active')->normalizedActivityLevel());
        $this->assertSame('moderate', $this->assessment('active')->normalizedActivityLevel());
    }

    public function test_very_active_variants_map_correctly(): void
    {
        $this->assertSame('very_active', $this->assessment('very active')->normalizedActivityLevel());
    }

    public function test_extra_active_variants_map_correctly(): void
    {
        $this->assertSame('extra_active', $this->assessment('extra active')->normalizedActivityLevel());
        $this->assertSame('extra_active', $this->assessment('extremely active')->normalizedActivityLevel());
    }

    public function test_bedridden_maps_to_sedentary(): void
    {
        $this->assertSame('sedentary', $this->assessment('bedridden')->normalizedActivityLevel());
        $this->assertSame('sedentary', $this->assessment('bed ridden')->normalizedActivityLevel());
    }

    public function test_unknown_value_defaults_to_sedentary(): void
    {
        $this->assertSame('sedentary', $this->assessment('walks_on_water')->normalizedActivityLevel());
        $this->assertSame('sedentary', $this->assessment('')->normalizedActivityLevel());
    }

    public function test_map_keys_all_map_to_valid_activity_factors(): void
    {
        $validKeys = array_keys(NutritionPrescriptionService::ACTIVITY_FACTORS);
        foreach (Assessment::ACTIVITY_LEVEL_MAP as $input => $canonical) {
            $this->assertContains($canonical, $validKeys,
                "Map entry '{$input}' => '{$canonical}' is not a valid ACTIVITY_FACTORS key");
        }
    }

    // ── 5.3: Pregnancy PDRI adjustment ───────────────────────────────────────

    private function baseMetrics(): array
    {
        return [
            'weightKg' => 65.0,
            'heightCm' => 160.0,
            'ageYears' => 28,
            'sex' => 'Female',
            'isAdult' => true,
            'activityFactor' => 1.2,
        ];
    }

    public function test_no_pregnancy_status_leaves_output_unchanged(): void
    {
        $svc = new NutritionPrescriptionService;
        $metrics = $this->baseMetrics(); // no pregnancyLactationStatus key

        $base = $svc->autofill('default', null, $metrics);

        $metrics['pregnancyLactationStatus'] = null;
        $withNull = $svc->autofill('default', null, $metrics);

        $metrics['pregnancyLactationStatus'] = 'none';
        $withNone = $svc->autofill('default', null, $metrics);

        $this->assertSame($base['energy_kcal'], $withNull['energy_kcal']);
        $this->assertSame($base['energy_kcal'], $withNone['energy_kcal']);
        $this->assertSame($base['protein_g'], $withNull['protein_g']);
        $this->assertSame($base['protein_g'], $withNone['protein_g']);
    }

    public function test_pregnant_adds_300_kcal_and_27g_protein(): void
    {
        $svc = new NutritionPrescriptionService;
        $base = $svc->autofill('default', null, $this->baseMetrics());

        $pregnant = $svc->autofill('default', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'pregnant']
        ));

        $this->assertSame($base['energy_kcal'] + 300, $pregnant['energy_kcal'],
            'Pregnant should add +300 kcal');
        $this->assertSame($base['protein_g'] + 27, $pregnant['protein_g'],
            'Pregnant should add +27 g protein');
    }

    public function test_lactating_adds_500_kcal_and_27g_protein(): void
    {
        $svc = new NutritionPrescriptionService;
        $base = $svc->autofill('default', null, $this->baseMetrics());

        $lactating = $svc->autofill('default', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'lactating']
        ));

        $this->assertSame($base['energy_kcal'] + 500, $lactating['energy_kcal'],
            'Lactating should add +500 kcal');
        $this->assertSame($base['protein_g'] + 27, $lactating['protein_g'],
            'Lactating should add +27 g protein');
    }

    public function test_pregnancy_note_appended_to_result(): void
    {
        $svc = new NutritionPrescriptionService;
        $result = $svc->autofill('default', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'pregnant']
        ));

        $this->assertArrayHasKey('note', $result);
        $this->assertStringContainsString('Pregnant adjustment applied', $result['note']);
        $this->assertStringContainsString('+300 kcal', $result['note']);
        $this->assertStringContainsString('+27 g protein', $result['note']);
    }

    public function test_lactating_note_appended_to_result(): void
    {
        $svc = new NutritionPrescriptionService;
        $result = $svc->autofill('default', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'lactating']
        ));

        $this->assertArrayHasKey('note', $result);
        $this->assertStringContainsString('Lactating adjustment applied', $result['note']);
        $this->assertStringContainsString('+500 kcal', $result['note']);
    }

    public function test_pregnancy_adjustment_works_with_goal_that_has_note(): void
    {
        // liver_disease already has a note — ensure both notes are preserved
        $svc = new NutritionPrescriptionService;
        $result = $svc->autofill('liver_disease', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'pregnant']
        ));

        $this->assertStringContainsString('late-evening snack', strtolower($result['note']));
        $this->assertStringContainsString('Pregnant adjustment applied', $result['note']);
    }

    public function test_pregnancy_macros_remain_self_consistent(): void
    {
        $svc = new NutritionPrescriptionService;
        $result = $svc->autofill('default', null, array_merge(
            $this->baseMetrics(),
            ['pregnancyLactationStatus' => 'pregnant']
        ));

        // carbs_g*4 + fat_g*9 + protein_g*4 should approximately equal energy_kcal (±5%)
        $reconstructed = $result['carbs_g'] * 4 + $result['fat_g'] * 9 + $result['protein_g'] * 4;
        $this->assertEqualsWithDelta($result['energy_kcal'], $reconstructed,
            $result['energy_kcal'] * 0.05,
            'Macro totals should reconstruct energy within 5%');
    }

    public function test_golden_cases_unaffected_no_pregnancy_key(): void
    {
        // Spot-check: the frozen golden A.renal_diet/stage_1 case must still pass
        // (Patient A: 80 kg Male, 170 cm, sedentary — from prescription-targets.json)
        $svc = new NutritionPrescriptionService;
        $metrics = [
            'weightKg' => 80.0,
            'heightCm' => 170.0,
            'ageYears' => 45,
            'sex' => 'Male',
            'isAdult' => true,
            'activityFactor' => NutritionPrescriptionService::ACTIVITY_FACTORS['sedentary'],
        ];
        // No pregnancyLactationStatus key → gate must not fire
        $result = $svc->autofill('renal_diet', 'stage_1', $metrics);
        $this->assertSame(2400, $result['energy_kcal']);
        $this->assertSame(53, $result['protein_g']);
    }
}
