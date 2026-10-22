<?php

namespace Tests\Unit;

use App\Services\NutritionPrescriptionService;
use InvalidArgumentException;
use Tests\TestCase;

class NutritionPrescriptionV2Test extends TestCase
{
    private function metrics(array $overrides = []): array
    {
        return array_replace([
            'weightKg' => 70.0, 'heightCm' => 170.0, 'ageYears' => 40.0,
            'sex' => 'Male', 'isAdult' => true, 'activityFactor' => 1.2,
        ], $overrides);
    }

    public function test_mifflin_uses_measured_weight_and_fixed_adjustment_once(): void
    {
        $service = new NutritionPrescriptionService;
        $this->assertSame(2241, $service->autofill('general_diet', null, $this->metrics(['weightKg' => 100.0]))['energy_kcal']);
        $result = $service->autofill('general_diet', null, $this->metrics([
            'energyAdjustmentMode' => 'deficit', 'energyAdjustmentKcal' => 300,
        ]));
        $this->assertSame(1881.0, $result['calculation_snapshot']['baseline_energy_kcal']);
        $this->assertSame(1581, $result['energy_kcal']);
        $this->assertSame(70, $result['protein_g']);
        $this->assertSame(44, $result['fat_g']);
        $this->assertSame(226, $result['carbs_g']);
        $this->assertSame($result, $service->autofill('general_diet', null, $this->metrics([
            'energyAdjustmentMode' => 'deficit', 'energyAdjustmentKcal' => 300,
        ])));
    }

    public function test_diabetes_type_never_selects_an_energy_direction(): void
    {
        $service = new NutritionPrescriptionService;
        foreach ([45.0, 100.0] as $weight) {
            $metrics = $this->metrics(['weightKg' => $weight]);
            $this->assertSame(
                $service->autofill('diabetic_control', 'type_1', $metrics)['energy_kcal'],
                $service->autofill('diabetic_control', 'type_2', $metrics)['energy_kcal'],
            );
        }
    }

    public function test_refeeding_and_gradual_notes_do_not_change_any_target(): void
    {
        $service = new NutritionPrescriptionService;
        $metrics = $this->metrics(['energyAdjustmentMode' => 'surplus', 'energyAdjustmentKcal' => 300]);
        $plain = $service->autofill('general_diet', null, $metrics);
        $noted = $service->autofill('general_diet', null, [...$metrics,
            'refeedingRisk' => true, 'strategies' => 'Increase intake gradually under supervision.',
        ]);
        foreach (['energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml'] as $field) {
            $this->assertSame($plain[$field], $noted[$field], $field);
        }
        $this->assertSame(2181, $noted['energy_kcal']);
        $this->assertArrayNotHasKey('feeding_phase', $noted);
        $this->assertArrayNotHasKey('starting_energy_kcal', $noted);
    }

    public function test_renal_and_cirrhosis_use_confirmed_weight_and_nutrition_context(): void
    {
        $service = new NutritionPrescriptionService;
        $metrics = $this->metrics(['weightKg' => 60.0]);
        $this->assertSame(48, $service->autofill('renal_diet', 'ckd_3_5_non_dialysis', $metrics)['protein_g']);
        $this->assertSame(72, $service->autofill('renal_diet', 'hemodialysis', $metrics)['protein_g']);
        foreach (['compensated', 'decompensated'] as $stage) {
            $result = $service->autofill('liver_disease', $stage, [...$metrics, 'nutritionStatus' => 'malnutrition_or_muscle_loss']);
            $this->assertSame(1950, $result['energy_kcal']);
            $this->assertSame(90, $result['protein_g']);
        }
    }

    public function test_fluid_restriction_is_preserved_independently_of_energy(): void
    {
        $service = new NutritionPrescriptionService;
        $result = $service->autofill('cardiac_diet', 'heart_failure', $this->metrics([
            'fluidMode' => 'restricted', 'fluidLimitMl' => 1700,
            'energyAdjustmentMode' => 'surplus', 'energyAdjustmentKcal' => 300,
        ]));
        $this->assertSame(1700, $result['fluid_ml']);
        $this->assertSame('max', $result['nutrient_operators']['fluid_ml']);
        $this->assertSame(2181, $result['energy_kcal']);
    }

    public function test_pediatric_eer_includes_normal_growth_without_using_adult_activity_factors(): void
    {
        $service = new NutritionPrescriptionService;
        $result = $service->autofill('general_diet', null, $this->metrics([
            'weightKg' => 20.0, 'heightCm' => 115.0, 'ageYears' => 5.0, 'isAdult' => false,
            'pediatricActivityCategory' => 'inactive',
        ]));
        $this->assertSame(958.0, $result['calculation_snapshot']['resting_energy_kcal']);
        $this->assertSame(1345, $result['energy_kcal']);
        $this->assertSame(34, $result['protein_g']);
        $this->assertSame('pediatric_eer', $result['calculation_snapshot']['baseline_kind']);
        $this->assertSame('warning', $result['calculation_status']);
        $this->assertSame(1345, $service->autofill('general_diet', null, $this->metrics([
            'weightKg' => 20.0, 'heightCm' => 115.0, 'ageYears' => 5.0,
            'pediatricActivityCategory' => 'inactive', 'activityFactor' => 1.9,
        ]))['energy_kcal']);
    }

    public function test_infeasible_deficit_is_rejected_instead_of_clamped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NutritionPrescriptionService)->autofill('general_diet', null, $this->metrics([
            'energyAdjustmentMode' => 'deficit', 'energyAdjustmentKcal' => 2000,
        ]));
    }

    public function test_nasem_table_5_15_golden_cases_cover_both_sexes_and_all_activity_categories(): void
    {
        $service = new NutritionPrescriptionService;
        foreach ([
            ['Male', 'inactive', 1345], ['Male', 'low_active', 1449],
            ['Male', 'active', 1510], ['Male', 'very_active', 1595],
            ['Female', 'inactive', 1270], ['Female', 'low_active', 1369],
            ['Female', 'active', 1431], ['Female', 'very_active', 1574],
        ] as [$sex, $category, $energy]) {
            $result = $service->autofill('general_diet', null, $this->metrics([
                'weightKg' => 20, 'heightCm' => 115, 'ageYears' => 5,
                'sex' => $sex, 'pediatricActivityCategory' => $category,
            ]));
            $this->assertSame($energy, $result['energy_kcal'], "$sex $category");
        }
        foreach ([['Male', 2, 200], ['Male', 3, 50], ['Male', 6, 20],
            ['Female', 2, 180], ['Female', 3, 60], ['Female', 6, 20], ['Female', 12, 15],
        ] as [$sex, $months, $growth]) {
            $result = $service->autofill('general_diet', null, $this->metrics([
                'weightKg' => 8, 'heightCm' => 70, 'ageYears' => $months / 12, 'ageMonths' => $months, 'sex' => $sex,
            ]));
            $this->assertSame($growth, $result['calculation_snapshot']['growth_energy_kcal']);
            $this->assertSame($months < 6 ? 0.05 : 0.10, $result['calculation_snapshot']['protein_fraction']);
        }
    }

    public function test_adult_treatment_presets_are_rejected_for_children(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NutritionPrescriptionService)->autofill('renal_diet', 'ckd_1_2', $this->metrics(['ageYears' => 8]));
    }
}
