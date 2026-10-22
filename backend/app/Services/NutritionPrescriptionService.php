<?php

namespace App\Services;

use App\Support\InterventionGoalCatalog;
use InvalidArgumentException;

/**
 * Nutrition prescription engine — AUTHORITATIVE source of truth.
 *
 * Version 2 powers current previews and saved calculation snapshots.
 * autofillLegacy retains the frozen version 1 contract for regression tests;
 * it is not selected by current API requests or used to reconstruct history.
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
    public function bmr(float $weightKg, float $heightCm, float $age, string $sex): float
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
    public function autofillLegacy(string $goalType, ?string $stage, array $m): array
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

    /** Revised contract v2. Progression and free-text notes never enter arithmetic. */
    public function autofill(string $goalType, ?string $stage, array $m): array
    {
        if (! InterventionGoalCatalog::stageIsValid($goalType, $stage)) {
            throw new InvalidArgumentException('Select a current goal and type before calculation.');
        }
        foreach (['weightKg', 'heightCm', 'ageYears'] as $key) {
            if (! isset($m[$key]) || ! is_numeric($m[$key]) || ! is_finite((float) $m[$key]) || $m[$key] < 0) {
                throw new InvalidArgumentException('Confirm weight, height and age before calculation.');
            }
        }
        if ($m['weightKg'] <= 0 || $m['heightCm'] <= 0 || ! in_array($m['sex'] ?? null, ['Male', 'Female'], true)) {
            throw new InvalidArgumentException('Confirm positive measurements and equation sex.');
        }
        $weight = (float) ($m['clinicalWeightKg'] ?? $m['weightKg']);
        $proteinWeight = (float) ($m['proteinWeightKg'] ?? $weight);
        $mode = $m['energyAdjustmentMode'] ?? 'none';
        $amount = $mode === 'none' ? 0 : ($m['energyAdjustmentKcal'] ?? 0);
        if (! in_array($mode, ['none', 'surplus', 'deficit'], true)
            || ! is_numeric($amount) || ! is_finite((float) $amount)
            || ($mode !== 'none' && ($amount <= 0 || floor((float) $amount) !== (float) $amount))) {
            throw new InvalidArgumentException('Enter a positive whole-number kcal adjustment.');
        }
        $signed = $mode === 'deficit' ? -(int) $amount : (int) $amount;
        $adult = (float) $m['ageYears'] >= 18;
        if (! in_array($goalType, InterventionGoalCatalog::goalTypesForAge((float) $m['ageYears']), true)) {
            throw new InvalidArgumentException('This adult treatment preset is unavailable for pediatric patients. Choose General Diet, Diabetic Control or Custom Plan.');
        }
        $activity = (float) ($m['activityFactor'] ?? 1.2);
        if (! is_finite($weight) || ! is_finite($proteinWeight) || $weight <= 0 || $weight > 400 || $proteinWeight <= 0 || $proteinWeight > 400 || ! is_finite($activity) || $activity <= 0) {
            throw new InvalidArgumentException('Confirm a usable calculation weight and activity estimate.');
        }
        $resting = $adult
            ? $this->bmr((float) $m['weightKg'], (float) $m['heightCm'], (float) $m['ageYears'], $m['sex'])
            : $this->pediatricRestingEnergy((float) $m['weightKg'], (float) $m['ageYears'], $m['sex']);
        $snapshot = [
            'version' => 2, 'goal_type' => $goalType, 'disease_stage' => $stage,
            'measurement_date' => $m['measurementDate'] ?? null,
            'inputs' => array_intersect_key($m, array_flip([
                'weightKg', 'heightCm', 'ageYears', 'ageMonths', 'sex', 'activityFactor', 'weightBasis',
                'clinicalWeightKg', 'proteinWeightKg', 'nutritionStatus', 'pregnancyLactationStatus',
            ])),
            'weight_kg' => $weight, 'protein_weight_kg' => $proteinWeight,
            'weight_basis' => $m['weightBasis'] ?? 'measured',
            'resting_energy_kcal' => $resting,
            'baseline_energy_kcal' => null,
            'energy_adjustment_mode' => $mode, 'energy_adjustment_kcal' => (int) $amount,
            'signed_adjustment_kcal' => $signed,
            'maternal_energy_kcal' => 0, 'maternal_protein_g' => 0,
            'method' => $adult ? 'Mifflin–St Jeor × confirmed adult activity estimate' : 'Schofield resting estimate only',
            'source' => $adult ? 'Mifflin 1990; local adult activity estimate' : 'ESPGHAN/ESPEN/ESPR 2018, energy Table 1',
        ];
        $fluidMode = $m['fluidMode'] ?? 'estimated';
        $fluid = $fluidMode === 'restricted' ? ($m['fluidLimitMl'] ?? null)
            : ($adult ? round($weight * 32.5) : round($this->hollidaySegar((float) $m['weightKg'])));
        if (! in_array($fluidMode, ['estimated', 'restricted'], true) || ! is_numeric($fluid) || ! is_finite((float) $fluid) || $fluid <= 0 || $fluid > 10000) {
            throw new InvalidArgumentException('Enter a positive prescribed fluid limit up to 10,000 mL/day.');
        }
        $snapshot['fluid_mode'] = $fluidMode;
        $snapshot['fluid_formula'] = $fluidMode === 'restricted' ? 'Prescribed maximum; all sources'
            : ($adult ? '32.5 mL/kg; local routine estimate, individualize for illness' : 'Holliday–Segar daily maintenance reference');
        if (! $adult && $goalType !== 'custom') {
            return $this->pediatricReference($m, $snapshot, (int) $fluid, $fluidMode, $signed);
        }
        $manual = $goalType === 'custom';
        if ($manual && $mode !== 'none') {
            throw new InvalidArgumentException('Enter an individualized final prescription for pediatric or Custom Plan care; automatic energy adjustment is unavailable.');
        }
        if ($manual) {
            $snapshot['method'] = ! $adult ? $snapshot['method'] : 'Clinician-entered prescription';
            $snapshot['calculated'] = ['energy_kcal' => null, 'protein_g' => null, 'carbs_g' => null, 'fat_g' => null, 'fluid_ml' => (int) $fluid];

            return [...$snapshot['calculated'], 'calculation_snapshot' => $snapshot,
                'calculation_status' => 'manual_targets_required', 'fluid_mode' => $fluidMode,
                'nutrient_operators' => $fluidMode === 'restricted' ? ['fluid_ml' => 'max'] : [],
                'note' => ! $adult ? 'Resting energy is an estimate, not a total intake prescription. Confirm pediatric targets and growth needs.' : 'Enter the individualized prescription.'];
        }
        $clinical = in_array($goalType, ['renal_diet', 'high_protein', 'liver_disease'], true);
        $coefficient = (float) ($m['energyPerKg'] ?? ($goalType === 'liver_disease' ? 32.5 : 30));
        $proteinCoefficient = $m['proteinPerKg'] ?? match (true) {
            $goalType === 'renal_diet' && $stage === 'ckd_3_5_non_dialysis' => 0.8,
            $goalType === 'renal_diet' && $stage === 'hemodialysis', $goalType === 'high_protein' => 1.2,
            $goalType === 'liver_disease' && ($m['nutritionStatus'] ?? null) === 'malnutrition_or_muscle_loss' => 1.5,
            $goalType === 'liver_disease' && $stage === 'compensated' && ($m['nutritionStatus'] ?? null) === 'adequate' => 1.2,
            $goalType === 'liver_disease' => null,
            default => 1.0,
        };
        if ($proteinCoefficient === null) {
            throw new InvalidArgumentException('Confirm cirrhosis nutritional status or enter the assessed protein coefficient.');
        }
        $fatFraction = (float) ($m['fatFraction'] ?? 0.25);
        if (! is_finite($coefficient) || ! is_numeric($proteinCoefficient) || ! is_finite((float) $proteinCoefficient) || ! is_finite($fatFraction) || $coefficient <= 0 || $coefficient > 100 || $proteinCoefficient <= 0 || $proteinCoefficient > 5 || $fatFraction <= 0 || $fatFraction >= 1) {
            throw new InvalidArgumentException('Confirm clinically appropriate energy, protein and fat coefficients.');
        }
        $baseline = $clinical ? $coefficient * $weight : $resting * $activity;
        $maternalStatus = $m['pregnancyLactationStatus'] ?? 'none';
        if ($maternalStatus === 'pregnant_unspecified') {
            throw new InvalidArgumentException('Confirm pregnancy trimester before calculation.');
        }
        $maternal = match ($maternalStatus) {
            'pregnant_t1' => [0, 27, 300],
            'pregnant_t2', 'pregnant_t3' => [300, 27, 300],
            'lactating' => [500, 27, 700],
            default => [0, 0, 0],
        };
        if ($clinical && $maternalStatus !== 'none' && empty($m['maternalConfirmed'])) {
            throw new InvalidArgumentException('Confirm maternal additions are appropriate for this clinical prescription.');
        }
        $energy = (int) round($baseline + $maternal[0] + $signed);
        $protein = (int) round($proteinCoefficient * $proteinWeight + $maternal[1]);
        $fat = (int) round($energy * $fatFraction / 9);
        $residual = $energy - 4 * $protein - 9 * $fat;
        if ($energy <= 0 || $energy > 10000 || $residual < 0) {
            throw new InvalidArgumentException('The energy adjustment and protein/fat targets are incompatible. Enter a coherent final prescription.');
        }
        $calculated = ['energy_kcal' => $energy, 'protein_g' => $protein, 'carbs_g' => (int) round($residual / 4), 'fat_g' => $fat, 'fluid_ml' => (int) $fluid];
        $snapshot = [...$snapshot, 'baseline_energy_kcal' => $baseline,
            'baseline_kind' => $clinical ? 'clinical_target' : 'estimated_maintenance',
            'method' => $clinical ? 'Confirmed kcal/kg clinical target' : $snapshot['method'],
            'source' => match ($goalType) {
                'renal_diet' => 'KDOQI 2020 energy; KDIGO 2024 G3–G5 protein; local defaults',
                'high_protein' => 'ESPEN hospital nutrition 2021, recommendation 15; local defaults',
                'liver_disease' => 'ESPEN liver nutrition 2020, points 71–74; local energy midpoint',
                default => $snapshot['source'],
            },
            'energy_per_kg' => $clinical ? $coefficient : null,
            'protein_per_kg' => (float) $proteinCoefficient, 'fat_fraction' => $fatFraction,
            'maternal_energy_kcal' => $maternal[0], 'maternal_protein_g' => $maternal[1],
            'maternal_water_guidance_ml' => $maternal[2], 'calculated' => $calculated,
        ];
        if ($maternalStatus !== 'none') {
            $snapshot['source'] .= '; FNRI-DOST PDRI 2015, Tables 1–2 (revised 2018), maternal additions';
        }
        $extrapolated = ! $clinical && ($m['ageYears'] < 19 || $m['ageYears'] > 78);

        return [...$calculated, 'calculation_snapshot' => $snapshot, 'calculation_status' => $extrapolated ? 'warning' : 'ok',
            ...($extrapolated ? ['note' => 'Age is outside the original Mifflin study range of 19–78 years. Review the extrapolated estimate before prescribing.'] : []),
            'fluid_mode' => $fluidMode, 'nutrient_operators' => $fluidMode === 'restricted' ? ['fluid_ml' => 'max'] : []];
    }

    private function pediatricRestingEnergy(float $weight, float $age, string $sex): float
    {
        [$coefficient, $constant] = $sex === 'Male'
            ? ($age < 3 ? [59.5, -30] : ($age < 10 ? [22.7, 504] : [17.7, 658]))
            : ($age < 3 ? [58.3, -31] : ($age < 10 ? [20.3, 486] : [13.4, 692]));

        return $coefficient * $weight + $constant;
    }

    /** NASEM 2023 Table 5-15; PDRI 2015 Table 1 local macro midpoints. */
    private function pediatricReference(array $m, array $snapshot, int $fluid, string $fluidMode, int $signed): array
    {
        $age = (float) $m['ageYears'];
        $months = (float) ($m['ageMonths'] ?? $age * 12);
        $male = $m['sex'] === 'Male';
        $weight = (float) $m['weightKg'];
        $height = (float) $m['heightCm'];
        $category = $m['pediatricActivityCategory'] ?? null;
        if ($age < 3) {
            $terms = $male ? [-716.45, -1.0, 17.82, 15.06] : [-69.15, 80.0, 2.65, 54.15];
            $growth = $male ? ($months < 3 ? 200 : ($months < 6 ? 50 : 20))
                : ($months < 3 ? 180 : ($months < 6 ? 60 : ($months < 12 ? 20 : 15)));
        } else {
            $equations = $male ? [
                'inactive' => [-447.51, 3.68, 13.01, 13.15],
                'low_active' => [19.12, 3.68, 8.62, 20.28],
                'active' => [-388.19, 3.68, 12.66, 20.46],
                'very_active' => [-671.75, 3.68, 15.38, 23.25],
            ] : [
                'inactive' => [55.59, -22.25, 8.43, 17.07],
                'low_active' => [-297.54, -22.25, 12.77, 14.73],
                'active' => [-189.55, -22.25, 11.74, 18.34],
                'very_active' => [-709.59, -22.25, 18.22, 14.25],
            ];
            if (! isset($equations[$category ?? ''])) {
                throw new InvalidArgumentException('Confirm an activity category for the pediatric energy estimate.');
            }
            $terms = $equations[$category];
            $growth = $age < 4 ? ($male ? 20 : 15) : ($age < 9 ? 15 : ($age < 14 ? ($male ? 25 : 30) : 20));
        }
        [$constant, $ageCoefficient, $heightCoefficient, $weightCoefficient] = $terms;
        $baseline = $constant + $ageCoefficient * $age + $heightCoefficient * $height + $weightCoefficient * $weight + $growth;
        $energy = (int) round($baseline + $signed);
        $proteinFraction = $months < 6 ? 0.05 : 0.10;
        $fatFraction = $months < 6 ? 0.50 : ($months < 12 ? 0.35 : ($age < 3 ? 0.30 : 0.25));
        $protein = (int) round($energy * $proteinFraction / 4);
        $fat = (int) round($energy * $fatFraction / 9);
        $carbs = (int) round(($energy - $protein * 4 - $fat * 9) / 4);
        if ($energy <= 0 || $energy > 10000 || $carbs < 0) {
            throw new InvalidArgumentException('Measurements or adjustment produce an incompatible pediatric estimate. Review the prescription.');
        }
        if (($m['pregnancyLactationStatus'] ?? 'none') !== 'none') {
            throw new InvalidArgumentException('Adolescent pregnancy or lactation requires an individualized maternal prescription in Custom Plan.');
        }
        $calculated = ['energy_kcal' => $energy, 'protein_g' => $protein, 'fat_g' => $fat, 'carbs_g' => $carbs, 'fluid_ml' => $fluid];
        $snapshot = [...$snapshot, 'baseline_kind' => 'pediatric_eer',
            'method' => 'NASEM 2023 pediatric estimated energy requirement',
            'source' => 'NASEM 2023 Table 5-15; PDRI 2015 Table 1 macro distribution; local percentage choices',
            'baseline_energy_kcal' => $baseline, 'pediatric_activity_category' => $age < 3 ? null : $category,
            'eer_coefficients' => $terms, 'growth_energy_kcal' => $growth,
            'protein_fraction' => $proteinFraction, 'fat_fraction' => $fatFraction,
            'calculated' => $calculated,
        ];

        return [...$calculated, 'calculation_snapshot' => $snapshot, 'calculation_status' => 'warning',
            'fluid_mode' => $fluidMode, 'nutrient_operators' => $fluidMode === 'restricted' ? ['fluid_ml' => 'max'] : [],
            'note' => 'General pediatric energy and growth estimate; review clinical needs and growth. Macro percentages are local choices within PDRI ranges, not disease-specific treatment doses.'];
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
