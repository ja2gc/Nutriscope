<?php

namespace App\Services;

/**
 * Nutrition prescription engine — AUTHORITATIVE source of truth.
 *
 * Implements docs/logic/prescription-targets.json EXACTLY. The frontend
 * (frontend/lib/nutritionCalculations.ts) is a live-preview mirror; persisted
 * prescriptions must come from THIS service. The 90 frozen golden cases in the
 * spec are asserted against this class in tests/Unit/NutritionPrescriptionServiceTest.php
 * to guarantee the two runtimes never drift.
 *
 * Pure computation — no database, no Eloquent. Inputs are primitives.
 */
class NutritionPrescriptionService
{
    public const MATERNAL_SOURCE_KEY = 'FNRI_PDRI_2015_REV_2018_SUMMARY_TABLES';

    private const FLUID_FACTOR_ML_PER_KG = 32.5;

    private const CALORIC_FLOOR = ['Female' => 1200, 'Male' => 1500];

    public const ACTIVITY_FACTORS = [
        'sedentary' => 1.2,
        'light' => 1.375,
        'moderate' => 1.55,
        'very_active' => 1.725,
        'extra_active' => 1.9,
    ];

    // ── Anthropometric helpers ──────────────────────────────────────────────

    /** Hamwi IBW (kg), floor 30. */
    public function ibw(float $heightCm, string $sex): float
    {
        $inchesOver = ($heightCm / 2.54) - 60;
        $base = $sex === 'Male' ? 48.0 : 45.5;
        $per = $sex === 'Male' ? 2.7 : 2.2;

        return max($base + $per * $inchesOver, 30);
    }

    public function ajbw(float $actualKg, float $ibwKg): float
    {
        return $ibwKg + 0.25 * ($actualKg - $ibwKg);
    }

    public function percentIbw(float $actualKg, float $ibwKg): float
    {
        return ($actualKg / $ibwKg) * 100;
    }

    /** Energy/fluid working weight: %IBW > 120 ? AjBW : actual. */
    public function workingWeight(float $actualKg, float $ibwKg): float
    {
        return $this->percentIbw($actualKg, $ibwKg) > 120
            ? $this->ajbw($actualKg, $ibwKg)
            : $actualKg;
    }

    /** Mifflin-St Jeor BMR (kcal/day). */
    public function bmr(float $weightKg, float $heightCm, int $age, string $sex): float
    {
        $base = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;

        return $sex === 'Male' ? $base + 5 : $base - 161;
    }

    public function schofield(float $weightKg, float $ageYears, string $sex): float
    {
        if ($sex === 'Male') {
            if ($ageYears < 3) {
                return 59.512 * $weightKg - 30.4;
            }
            if ($ageYears < 10) {
                return 22.706 * $weightKg + 504.3;
            }

            return 17.686 * $weightKg + 658.2;
        }
        if ($ageYears < 3) {
            return 58.317 * $weightKg - 31.1;
        }
        if ($ageYears < 10) {
            return 20.315 * $weightKg + 485.9;
        }

        return 13.384 * $weightKg + 692.8;
    }

    public function hollidaySegar(float $weightKg): float
    {
        if ($weightKg <= 10) {
            return $weightKg * 100;
        }
        if ($weightKg <= 20) {
            return 1000 + ($weightKg - 10) * 50;
        }

        return 1500 + ($weightKg - 20) * 20;
    }

    private function pediatricProteinPerKg(float $ageYears): float
    {
        if ($ageYears < 0.5) {
            return 1.52;
        }
        if ($ageYears < 1) {
            return 1.20;
        }
        if ($ageYears < 4) {
            return 1.05;
        }
        if ($ageYears < 14) {
            return 0.95;
        }

        return 0.85;
    }

    private function growthAllowance(float $ageYears): int
    {
        if ($ageYears < 0.5) {
            return 70;
        }
        if ($ageYears < 1) {
            return 45;
        }
        if ($ageYears < 4) {
            return 20;
        }

        return 15;
    }

    /** @return array{carbs_g:int, fat_g:int} */
    private function macros(int $energy, int $proteinG, float $fatPct = 0.25): array
    {
        $fatG = (int) round(($energy * $fatPct) / 9);
        $carbsG = max((int) round(($energy - $proteinG * 4 - $fatG * 9) / 4), 0);

        return ['carbs_g' => $carbsG, 'fat_g' => $fatG];
    }

    // ── Autofill ─────────────────────────────────────────────────────────────

    /**
     * @param  array{
     *   weightKg: float,
     *   heightCm: float,
     *   ageYears: int,
     *   sex: string,
     *   isAdult: bool,
     *   activityFactor?: float,
     *   pregnancyLactationStatus?: string,
     * }  $m
     *
     * pregnancyLactationStatus (optional):
     *   'pregnant_t1' — +0 kcal/day, +27 g protein (PDRI)
     *   'pregnant_t2' / 'pregnant_t3' — +300 kcal/day, +27 g protein (PDRI)
     *   'lactating' — +500 kcal/day, +27 g protein (PDRI)
     *   null / 'none' / missing — no adjustment (default; all 90 golden cases use this path)
     *
     * The pregnancy gate is checked ONLY when the key is explicitly set AND non-null/non-empty.
     * This guarantees the 90 frozen golden cases (none set pregnancyLactationStatus) are unaffected.
     * @return array{energy_kcal:int,protein_g:int,carbs_g:int,fat_g:int,fluid_ml:int,fiber_g?:int,sodium_max_mg?:int,free_sugar_max_pct?:float,cholesterol_max_mg?:int,note?:string}
     */
    public function autofill(string $goalType, ?string $stage, array $m): array
    {
        if (empty($m['isAdult'])) {
            return $this->autofillPediatric($m);
        }

        $pal = $m['activityFactor'] ?? 1.2;
        $ibw = $this->ibw($m['heightCm'], $m['sex']);
        $working = $this->workingWeight($m['weightKg'], $ibw);
        $bmrWt = $this->percentIbw($m['weightKg'], $ibw) > 120 ? $this->ajbw($m['weightKg'], $ibw) : $m['weightKg'];
        $tee = $this->bmr($bmrWt, $m['heightCm'], (int) $m['ageYears'], $m['sex']) * $pal;
        $stdFluid = (int) round($working * self::FLUID_FACTOR_ML_PER_KG);
        $floor = self::CALORIC_FLOOR[$m['sex']];

        $result = $this->computeGoal($goalType, $stage, $m, $pal, $ibw, $working, $bmrWt, $tee, $stdFluid, $floor);

        $pregnancyStatus = $m['pregnancyLactationStatus'] ?? null;
        // FNRI_PDRI_2015_REV_2018_SUMMARY_TABLES: Summary Tables 1–2, PDF pages 1–2.
        $modifier = match ($pregnancyStatus) {
            'pregnant_t1' => ['energy_kcal' => 0, 'protein_g' => 27, 'water_guidance_ml' => 300],
            'pregnant_t2', 'pregnant_t3' => ['energy_kcal' => 300, 'protein_g' => 27, 'water_guidance_ml' => 300],
            'lactating' => ['energy_kcal' => 500, 'protein_g' => 27, 'water_guidance_ml' => 700],
            default => null,
        };
        if ($modifier !== null) {
            $baseline = [
                'energy_kcal' => $result['energy_kcal'],
                'protein_g' => $result['protein_g'],
                'fluid_ml' => $result['fluid_ml'],
            ];
            $fatPct = $result['fat_g'] > 0
                ? ($result['fat_g'] * 9) / max($result['energy_kcal'], 1)
                : 0.25;
            $result['energy_kcal'] += $modifier['energy_kcal'];
            $result['protein_g'] += $modifier['protein_g'];
            $recalculatedMacros = $this->macros($result['energy_kcal'], $result['protein_g'], $fatPct);
            $result['carbs_g'] = $recalculatedMacros['carbs_g'];
            $result['fat_g'] = $recalculatedMacros['fat_g'];

            $label = match ($pregnancyStatus) {
                'pregnant_t1' => 'Pregnant — first trimester',
                'pregnant_t2' => 'Pregnant — second trimester',
                'pregnant_t3' => 'Pregnant — third trimester',
                default => 'Lactating',
            };
            $existingNote = $result['note'] ?? '';
            $maternalNote = "{$label}: +{$modifier['energy_kcal']} kcal and +{$modifier['protein_g']} g protein applied (PDRI).";
            $result['note'] = $existingNote ? $existingNote.' '.$maternalNote : $maternalNote;
            $result['maternal_modifier'] = [
                'status' => $pregnancyStatus,
                'baseline' => $baseline,
                'modifier' => $modifier,
                'final' => [
                    'energy_kcal' => $result['energy_kcal'],
                    'protein_g' => $result['protein_g'],
                    'fluid_ml' => $result['fluid_ml'],
                ],
                'source_key' => self::MATERNAL_SOURCE_KEY,
            ];
        }

        return $result;
    }

    /**
     * Core goal computation — extracted from autofill() so the pregnancy gate
     * can be applied uniformly after the switch without duplicating every case.
     */
    private function computeGoal(
        string $goalType, ?string $stage, array $m,
        float $pal, float $ibw, float $working, float $bmrWt,
        float $tee, int $stdFluid, int $floor
    ): array {
        switch ($goalType) {
            case 'renal_diet':
                $energy = (int) round($working * 30);
                $proteinPerKg = [
                    'stage_1' => 0.8, 'stage_2' => 0.8, 'stage_3' => 0.7,
                    'stage_4' => 0.6, 'stage_5_predialysis' => 0.6,
                    'hemodialysis' => 1.2, 'peritoneal' => 1.35,
                ];
                $sodium = [
                    'stage_1' => 2000, 'stage_2' => 2000, 'stage_3' => 2000, 'stage_4' => 2000,
                    'stage_5_predialysis' => 1500, 'hemodialysis' => 1500, 'peritoneal' => 2000,
                ];
                $proteinG = (int) round($ibw * ($proteinPerKg[$stage ?? 'stage_1'] ?? 0.8));
                $fluidMap = ['hemodialysis' => 750, 'peritoneal' => 1000];
                $fluid = $fluidMap[$stage ?? ''] ?? $stdFluid;
                $notes = [
                    'hemodialysis' => 'Add prior-day urine output to 750 mL fluid base.',
                    'peritoneal' => 'Subtract ~500–800 kcal/day for dialysate glucose; individualize fluid.',
                ];

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.25),
                    ['fluid_ml' => $fluid, 'sodium_max_mg' => $sodium[$stage ?? 'stage_1'] ?? 2000, 'note' => $notes[$stage ?? ''] ?? null],
                );

            case 'diabetic_control':
                $proteinG = (int) round($ibw * ($stage === 'stage_3' ? 0.8 : 0.9));
                $energy = (int) round($tee);
                if ($stage === 'stage_2') {
                    $energy = max((int) round($tee - 500), $floor);
                }

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.28),
                    ['fluid_ml' => $stdFluid, 'fiber_g' => 25, 'sodium_max_mg' => 2000, 'free_sugar_max_pct' => 0.10],
                );

            case 'cardiac_diet':
                $energy = (int) round($tee);
                $proteinG = (int) round($ibw * 0.8);
                $fatPct = $stage === 'severe' ? 0.24 : ($stage === 'moderate' ? 0.26 : 0.28);
                $sodium = ['mild' => 2000, 'moderate' => 2000, 'severe' => 1500];
                $chol = ['mild' => 300, 'moderate' => 200, 'severe' => 200];
                $cardiacFluid = ['moderate' => 2000, 'severe' => 1500];

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, $fatPct),
                    ['fluid_ml' => $cardiacFluid[$stage ?? ''] ?? $stdFluid,
                        'sodium_max_mg' => $sodium[$stage ?? 'mild'] ?? 2000,
                        'cholesterol_max_mg' => $chol[$stage ?? 'mild'] ?? 300],
                );

            case 'weight_loss':
                $deficits = ['overweight' => 375, 'class_1' => 500, 'class_2' => 625, 'class_3' => 875];
                $energy = max((int) round($tee - ($deficits[$stage ?? 'class_1'] ?? 500)), $floor);
                $proteinG = (int) round($ibw * 1.4);

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.275),
                    ['fluid_ml' => $stdFluid, 'fiber_g' => 25],
                );

            case 'weight_gain':
                if ($stage === 'severe') {
                    $energy = (int) round($working * 32.5);
                    $proteinG = (int) round($ibw * 1.0);

                    return array_merge(
                        ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                        $this->macros($energy, $proteinG, 0.275),
                        ['fluid_ml' => $stdFluid],
                    );
                }
                $surplus = $stage === 'mild' ? 400 : 625;
                $energy = (int) round($tee + $surplus);
                $proteinG = (int) round($ibw * 1.6);

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.275),
                    ['fluid_ml' => $stdFluid],
                );

            case 'high_protein':
                $kcalPerKg = $stage === 'burns' ? 32.5 : 27.5;
                $energy = (int) round($working * $kcalPerKg);
                $protPerKg = ['mild_stress' => 1.1, 'moderate_stress' => 1.35, 'severe_stress' => 1.75, 'burns' => 1.75];
                $proteinG = (int) round($ibw * ($protPerKg[$stage ?? 'mild_stress'] ?? 1.1));

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.275),
                    ['fluid_ml' => $stdFluid, 'note' => 'Flat kcal/kg already incorporates the stress factor — do not apply an additional stress multiplier.'],
                );

            case 'liver_disease':
                $energy = (int) round($working * 37.5);
                $proteinG = (int) round($ibw * 1.35); // restriction contraindicated; 1.2–1.5 all stages
                $note = match ($stage) {
                    'encephalopathy_grade_3_4' => 'Maintain protein 1.2–1.5 g/kg. Temporary reduction to 1.0 ONLY if protein-intolerant and unresponsive to BCAA/lactulose/rifaximin. Prefer vegetable/dairy protein; BCAA 0.25 g/kg/day; late-evening snack.',
                    'encephalopathy_grade_1_2' => 'Do not restrict protein. Prefer vegetable/dairy protein; BCAA preferred; late-evening snack.',
                    default => 'Late-evening snack recommended; prefer small frequent meals.',
                };

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.275),
                    ['fluid_ml' => $stdFluid, 'sodium_max_mg' => 2000, 'note' => $note],
                );

            case 'malnutrition':
                if ($stage === 'severe') {
                    $energy = (int) round($working * 32.5);
                    $proteinG = (int) round($ibw * 1.0);

                    return array_merge(
                        ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                        $this->macros($energy, $proteinG, 0.275),
                        ['fluid_ml' => $stdFluid],
                    );
                }
                $energy = (int) round($working * 32.5);
                $proteinG = (int) round($ibw * 1.35);

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.275),
                    ['fluid_ml' => $stdFluid],
                );

            default:
                $energy = (int) round($tee);
                $proteinG = (int) round($ibw * 0.8);

                return array_merge(
                    ['energy_kcal' => $energy, 'protein_g' => $proteinG],
                    $this->macros($energy, $proteinG, 0.25),
                    ['fluid_ml' => $stdFluid, 'fiber_g' => 22, 'sodium_max_mg' => 2000],
                );

        }
    }

    /** @param array{weightKg:float,ageYears:float,sex:string,activityFactor?:float} $m */
    private function autofillPediatric(array $m): array
    {
        $pal = $m['activityFactor'] ?? 1.2;
        $tee = $this->schofield($m['weightKg'], $m['ageYears'], $m['sex']) * $pal + $this->growthAllowance($m['ageYears']);
        $energy = (int) round($tee);
        $proteinG = (int) round($m['weightKg'] * $this->pediatricProteinPerKg($m['ageYears']));
        $fatG = (int) round(($energy * 0.30) / 9);
        $carbsG = max((int) round(($energy - $proteinG * 4 - $fatG * 9) / 4), 0);

        return [
            'energy_kcal' => $energy, 'protein_g' => $proteinG,
            'carbs_g' => $carbsG, 'fat_g' => $fatG,
            'fluid_ml' => (int) round($this->hollidaySegar($m['weightKg'])),
        ];
    }
}
