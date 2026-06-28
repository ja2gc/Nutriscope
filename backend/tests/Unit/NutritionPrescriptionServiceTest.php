<?php

namespace Tests\Unit;

use App\Services\NutritionPrescriptionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Authoritative golden-vector guard. Asserts the PHP engine reproduces every
 * frozen case in docs/logic/prescription-targets.json. If this fails, the
 * backend has drifted from the spec (and from the frontend mirror).
 */
class NutritionPrescriptionServiceTest extends TestCase
{
    private static function spec(): array
    {
        // NOTE: data providers run before the Laravel app is booted, so base_path()
        // is unavailable here. Resolve relative to this file instead.
        // __DIR__ = backend/tests/Unit → repo root is three levels up.
        $path = dirname(__DIR__, 3) . '/docs/logic/prescription-targets.json';
        return json_decode(file_get_contents($path), true);
    }

    public static function goldenCases(): array
    {
        $spec = self::spec();
        $patients = $spec['golden_patients'];
        $out = [];
        foreach ($spec['golden_cases'] as $c) {
            $p = $patients[$c['patient']];
            $name = "{$c['patient']} {$c['goal']}/" . ($c['stage'] ?? 'null');
            $out[$name] = [$p, $c['goal'], $c['stage'], $c['expected']];
        }
        return $out;
    }

    #[DataProvider('goldenCases')]
    public function test_matches_golden_case(array $p, string $goal, ?string $stage, array $expected): void
    {
        $svc = new NutritionPrescriptionService();
        $metrics = [
            'weightKg' => $p['weightKg'],
            'heightCm' => $p['heightCm'],
            'ageYears' => $p['ageYears'],
            'sex'      => $p['sex'],
            'isAdult'  => true,
            'activityFactor' => NutritionPrescriptionService::ACTIVITY_FACTORS[$p['activity']],
        ];

        $got = $svc->autofill($goal, $stage, $metrics);

        foreach (['energy_kcal', 'protein_g', 'carbs_g', 'fat_g', 'fluid_ml'] as $k) {
            $this->assertSame(
                $expected[$k],
                $got[$k],
                "Mismatch on {$k} for {$goal}/{$stage}: expected {$expected[$k]}, got {$got[$k]}"
            );
        }
    }

    /**
     * Regression guard: a normal-sized adult must never produce an implausible
     * energy target across ANY goal/stage/activity combination. This pins the
     * ceiling that the (since-fixed) "5000 kcal" report violated, so a formula
     * regression can't silently reintroduce it.
     */
    public function test_no_goal_produces_implausible_energy_for_a_normal_adult(): void
    {
        $svc = new NutritionPrescriptionService();
        $goals = [
            'renal_diet' => ['stage_1','stage_2','stage_3','stage_4','stage_5_predialysis','hemodialysis','peritoneal'],
            'diabetic_control' => ['stage_1','stage_2','stage_3'],
            'cardiac_diet' => ['mild','moderate','severe'],
            'weight_loss' => ['overweight','class_1','class_2','class_3'],
            'weight_gain' => ['mild','moderate','severe'],
            'high_protein' => ['mild_stress','moderate_stress','severe_stress','burns'],
            'liver_disease' => ['compensated','decompensated','encephalopathy_grade_1_2','encephalopathy_grade_3_4'],
            'malnutrition' => ['moderate','severe'],
            'custom' => [null],
        ];

        foreach (['sedentary','moderate','extra_active'] as $activity) {
            $metrics = [
                'weightKg' => 70.0, 'heightCm' => 183.0, 'ageYears' => 30,
                'sex' => 'Male', 'isAdult' => true,
                'activityFactor' => NutritionPrescriptionService::ACTIVITY_FACTORS[$activity],
            ];
            foreach ($goals as $goal => $stages) {
                foreach ($stages as $stage) {
                    $energy = $svc->autofill($goal, $stage, $metrics)['energy_kcal'];
                    $this->assertLessThanOrEqual(
                        4500, $energy,
                        "Implausible energy {$energy} kcal for {$goal}/{$stage} @ {$activity} (70kg/183cm)."
                    );
                    $this->assertGreaterThan(0, $energy, "Non-positive energy for {$goal}/{$stage}.");
                }
            }
        }
    }
}
