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
}
