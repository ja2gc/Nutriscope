<?php

namespace App\Services;

use App\Models\NcpRecord;

/**
 * Builds the per-patient Monitoring Plan — the focused set of indicators a
 * patient is tracked against, derived from everything gathered across the NCP:
 * assessment-flagged abnormal labs, the nutrition prescription, the intervention
 * goal, PES diagnoses, and calculated anthropometrics. Seeds the assessment as
 * "Visit 1" so every trend runs from intake → follow-ups.
 *
 * Single source of truth consumed by the monitoring visit form, trend charts,
 * progress tracker, and the monitoring AI review.
 */
class MonitoringPlanService
{
    /** Labs surfaced per intervention goal (mirrors frontend GOAL_LAB_FLAGS; bp excluded — non-numeric). */
    private const GOAL_LAB_FLAGS = [
        'renal_diet' => ['albumin', 'creatinine', 'potassium', 'hemoglobin'],
        'diabetic_control' => ['hba1c', 'glucose', 'albumin'],
        'cardiac_diet' => ['ldl', 'cholesterol'],
        'weight_loss' => [],
        'weight_gain' => ['albumin', 'hemoglobin'],
        'high_protein' => ['albumin', 'creatinine'],
        'liver_disease' => ['albumin'],
        'malnutrition' => ['albumin', 'hemoglobin'],
        'custom' => ['albumin', 'hba1c', 'ldl', 'cholesterol', 'creatinine', 'potassium', 'hemoglobin', 'glucose'],
    ];

    /** PES statement keyword → implied labs (best-effort, case-insensitive substrings). */
    private const PES_KEYWORD_LABS = [
        'glucose' => ['glucose', 'hba1c'],
        'diabet' => ['glucose', 'hba1c'],
        'glycemic' => ['glucose', 'hba1c'],
        'hyperglyc' => ['glucose', 'hba1c'],
        'renal' => ['creatinine', 'potassium', 'bun'],
        'kidney' => ['creatinine', 'potassium', 'bun'],
        'dialysis' => ['creatinine', 'potassium', 'bun'],
        'ckd' => ['creatinine', 'potassium', 'bun'],
        'protein' => ['albumin'],
        'albumin' => ['albumin'],
        'wound' => ['albumin'],
        'malnutrition' => ['albumin'],
        'anemia' => ['hemoglobin', 'hematocrit'],
        'hemoglobin' => ['hemoglobin', 'hematocrit'],
        'lipid' => ['ldl', 'cholesterol', 'triglycerides'],
        'cholesterol' => ['ldl', 'cholesterol', 'triglycerides'],
        'cardiac' => ['ldl', 'cholesterol', 'triglycerides'],
        'dyslipid' => ['ldl', 'cholesterol', 'triglycerides'],
    ];

    private const INTAKE_NUTRIENTS = [
        'energy_kcal' => ['label' => 'Energy intake',  'unit' => 'kcal'],
        'protein_g' => ['label' => 'Protein intake', 'unit' => 'g'],
        'carbs_g' => ['label' => 'Carbs intake',   'unit' => 'g'],
        'fat_g' => ['label' => 'Fat intake',     'unit' => 'g'],
        'fluid_ml' => ['label' => 'Fluid intake',   'unit' => 'mL'],
    ];

    private const LAB_META = [
        'albumin' => ['label' => 'Albumin',          'unit' => 'g/dL'],
        'hemoglobin' => ['label' => 'Hemoglobin',       'unit' => 'g/dL'],
        'hematocrit' => ['label' => 'Hematocrit',       'unit' => '%'],
        'glucose' => ['label' => 'Glucose',          'unit' => 'mg/dL'],
        'hba1c' => ['label' => 'HbA1c',            'unit' => '%'],
        'bun' => ['label' => 'BUN',              'unit' => 'mg/dL'],
        'creatinine' => ['label' => 'Creatinine',       'unit' => 'mg/dL'],
        'sodium' => ['label' => 'Sodium',           'unit' => 'mEq/L'],
        'potassium' => ['label' => 'Potassium',        'unit' => 'mEq/L'],
        'calcium' => ['label' => 'Calcium',          'unit' => 'mg/dL'],
        'phosphate' => ['label' => 'Phosphate',        'unit' => 'mg/dL'],
        'magnesium' => ['label' => 'Magnesium',        'unit' => 'mg/dL'],
        'cholesterol' => ['label' => 'Total Cholesterol', 'unit' => 'mg/dL'],
        'ldl' => ['label' => 'LDL',              'unit' => 'mg/dL'],
        'hdl' => ['label' => 'HDL',              'unit' => 'mg/dL'],
        'triglycerides' => ['label' => 'Triglycerides',    'unit' => 'mg/dL'],
    ];

    public function __construct(
        private LabFlagService $labFlags,
        private NutritionPrescriptionService $prescription,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function build(NcpRecord $ncpRecord): array
    {
        $ncpRecord->loadMissing([
            'patient',
            'assessment.biochemicalData',
            'diagnoses',
            'intervention',
            'monitorings',
        ]);

        $patient = $ncpRecord->patient;
        $assessment = $ncpRecord->assessment;
        $biochem = $assessment?->biochemicalData;
        $sex = $patient?->sex ?? 'Male';
        $goalType = $ncpRecord->intervention?->goal_type;
        $goalStage = $ncpRecord->intervention?->disease_stage;

        $monitorings = $ncpRecord->monitorings->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();

        $visits = [['label' => 'Visit 1', 'type' => 'assessment', 'date' => optional($assessment?->created_at)->toDateString()]];
        foreach ($monitorings as $i => $m) {
            $visits[] = ['label' => 'Follow-up '.($i + 1), 'type' => 'monitoring', 'id' => $m->id, 'date' => optional($m->created_at)->toDateString()];
        }

        $pesStatements = $ncpRecord->diagnoses
            ->pluck('pes_statement')
            ->filter()
            ->take(3)
            ->values()
            ->all();

        $indicators = array_merge(
            $this->labIndicators($biochem, $sex, $goalType, $goalStage, $pesStatements, $monitorings),
            $this->anthroIndicators($assessment, $sex, $monitorings),
            $this->intakeIndicators($ncpRecord->intervention, $monitorings),
        );

        return [
            'visits' => $visits,
            'pes_statements' => $pesStatements,
            'goal_type' => $goalType,
            'nutritional_status' => $assessment?->nutritional_status,
            'indicators' => $indicators,
        ];
    }

    /**
     * @param  array<int,string>  $pesStatements
     * @return array<int,array<string,mixed>>
     */
    private function labIndicators($biochem, string $sex, ?string $goalType, ?string $goalStage, array $pesStatements, $monitorings): array
    {
        $ranges = $this->labFlags->ranges($sex);
        $labArray = $biochem ? $biochem->toArray() : [];

        // Union of sources, tracking why each lab is in the set.
        $sources = [];
        foreach (array_keys($this->labFlags->flag($labArray, $sex)) as $key) {
            $sources[$key][] = 'flagged_abnormal';
        }
        foreach (self::GOAL_LAB_FLAGS[$goalType] ?? [] as $key) {
            $sources[$key][] = 'goal';
        }
        if (in_array($goalType, ['malnutrition', 'weight_gain'], true) && $goalStage === 'severe') {
            foreach (['potassium', 'phosphate', 'magnesium'] as $key) {
                $sources[$key][] = 'refeeding';
            }
        }
        foreach ($this->pesLabs($pesStatements) as $key) {
            $sources[$key][] = 'pes';
        }

        $indicators = [];
        foreach ($sources as $key => $why) {
            if (! isset($ranges[$key]) || ! isset(self::LAB_META[$key])) {
                continue;
            }
            $range = $ranges[$key];
            $baseline = isset($labArray[$key]) && is_numeric($labArray[$key]) ? (float) $labArray[$key] : null;

            $points = [['visit' => 'Visit 1', 'value' => $baseline]];
            foreach ($monitorings as $i => $m) {
                $labs = is_array($m->lab_values) ? $m->lab_values : [];
                $v = isset($labs[$key]) && is_numeric($labs[$key]) ? (float) $labs[$key] : null;
                $points[] = ['visit' => 'Follow-up '.($i + 1), 'value' => $v];
            }

            $indicators[] = $this->finishIndicator($key, self::LAB_META[$key], 'lab', array_values(array_unique($why)), [
                'min' => $range['low'],
                'max' => $range['high'],
            ], null, $points, fn ($curr, $prev) => $this->labStatus($curr, $prev, $range));
        }

        return $indicators;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function anthroIndicators($assessment, string $sex, $monitorings): array
    {
        if (! $assessment) {
            return [];
        }

        $height = $assessment->height !== null ? (float) $assessment->height : null;
        $ibw = $height !== null && $height > 0 ? $this->prescription->ibw($height, $sex) : null;
        $nutritionalStatus = $assessment->nutritional_status;

        // Weight — trended; target = IBW; status uses gain/loss goal logic.
        $weightPoints = [['visit' => 'Visit 1', 'value' => $assessment->weight !== null ? (float) $assessment->weight : null]];
        foreach ($monitorings as $i => $m) {
            $weightPoints[] = ['visit' => 'Follow-up '.($i + 1), 'value' => $m->weight !== null ? (float) $m->weight : null];
        }
        $weight = $this->finishIndicator('weight', ['label' => 'Weight', 'unit' => 'kg'], 'anthro', ['calculated'], null, $ibw, $weightPoints,
            fn ($curr, $prev) => $curr === null ? 'no_data' : MonitoringSummaryService::metricStatus('weight', $curr, $prev, $nutritionalStatus));

        // BMI — trended; WHO normal band.
        $bmiPoints = [['visit' => 'Visit 1', 'value' => $assessment->bmi !== null ? (float) $assessment->bmi : null]];
        foreach ($monitorings as $i => $m) {
            $bmiPoints[] = ['visit' => 'Follow-up '.($i + 1), 'value' => $m->bmi !== null ? (float) $m->bmi : null];
        }
        $bmi = $this->finishIndicator('bmi', ['label' => 'BMI', 'unit' => 'kg/m²'], 'anthro', ['calculated'], ['min' => 18.5, 'max' => 24.9], null, $bmiPoints,
            fn ($curr, $prev) => $this->labStatus($curr, $prev, ['low' => 18.5, 'high' => 24.9]));

        // %IBW — recomputed each visit from weight when IBW is known.
        $ibwPctPoints = [['visit' => 'Visit 1', 'value' => $assessment->ibw_percentage !== null ? (float) $assessment->ibw_percentage : null]];
        foreach ($monitorings as $i => $m) {
            $val = ($ibw !== null && $m->weight !== null) ? round($this->prescription->percentIbw((float) $m->weight, $ibw), 1) : null;
            $ibwPctPoints[] = ['visit' => 'Follow-up '.($i + 1), 'value' => $val];
        }
        $ibwPct = $this->finishIndicator('ibw_percentage', ['label' => '% IBW', 'unit' => '%'], 'anthro', ['calculated'], ['min' => 90, 'max' => 110], null, $ibwPctPoints,
            fn ($curr, $prev) => $this->labStatus($curr, $prev, ['low' => 90, 'high' => 110]));

        return [$weight, $bmi, $ibwPct];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function intakeIndicators($intervention, $monitorings): array
    {
        if (! $intervention) {
            return [];
        }

        $indicators = [];
        foreach (self::INTAKE_NUTRIENTS as $key => $meta) {
            $target = $intervention->{$key} !== null ? (float) $intervention->{$key} : null;
            if ($target === null) {
                continue;
            }

            $points = [];
            foreach ($monitorings as $i => $m) {
                $labs = is_array($m->lab_values) ? $m->lab_values : [];
                $v = isset($labs[$key]) && is_numeric($labs[$key]) ? (float) $labs[$key] : null;
                $points[] = ['visit' => 'Follow-up '.($i + 1), 'value' => $v];
            }

            $indicators[] = $this->finishIndicator($key, $meta, 'intake', ['prescription'], null, $target, $points,
                fn ($curr) => $this->intakeStatus($curr, $target));
        }

        return $indicators;
    }

    /**
     * Attach per-point status (walking previous values) + latest_status, and assemble the indicator.
     *
     * @param  array<string,string>  $meta
     * @param  array<int,string>  $sources
     * @param  array<string,?float>|null  $reference
     * @param  array<int,array<string,mixed>>  $points
     * @return array<string,mixed>
     */
    private function finishIndicator(string $key, array $meta, string $category, array $sources, ?array $reference, ?float $target, array $points, callable $statusFn): array
    {
        $prev = null;
        $series = [];
        foreach ($points as $p) {
            $value = $p['value'];
            $status = $value === null ? 'no_data' : $statusFn($value, $prev);
            $series[] = ['visit' => $p['visit'], 'value' => $value, 'status' => $status];
            if ($value !== null) {
                $prev = $value;
            }
        }

        $latest = 'no_data';
        foreach (array_reverse($series) as $s) {
            if ($s['value'] !== null) {
                $latest = $s['status'];
                break;
            }
        }

        return [
            'key' => $key,
            'label' => $meta['label'],
            'unit' => $meta['unit'],
            'category' => $category,
            'sources' => $sources,
            'reference' => $reference,
            'target' => $target,
            'series' => $series,
            'latest_status' => $latest,
        ];
    }

    /**
     * @param  array<int,string>  $pesStatements
     * @return array<int,string>
     */
    private function pesLabs(array $pesStatements): array
    {
        $text = strtolower(implode(' ', $pesStatements));
        $labs = [];
        foreach (self::PES_KEYWORD_LABS as $keyword => $keys) {
            if (str_contains($text, $keyword)) {
                $labs = array_merge($labs, $keys);
            }
        }

        return array_values(array_unique($labs));
    }

    /**
     * @param  array{low:?float,high:?float}  $range
     */
    private function labStatus(?float $curr, ?float $prev, array $range): string
    {
        if ($curr === null) {
            return 'no_data';
        }

        $low = $range['low'];
        $high = $range['high'];
        $inRange = ($low === null || $curr >= $low) && ($high === null || $curr <= $high);
        if ($inRange) {
            return 'met';
        }

        if ($prev !== null) {
            if ($high !== null && $curr > $high) {
                $improving = $curr < $prev;
            } elseif ($low !== null && $curr < $low) {
                $improving = $curr > $prev;
            } else {
                $improving = false;
            }
            if ($improving) {
                return 'in_progress';
            }
        }

        return 'not_met';
    }

    private function intakeStatus(?float $actual, ?float $target): string
    {
        if ($actual === null || $target === null || $target <= 0) {
            return 'no_data';
        }

        return ($actual / $target * 100) < 90 ? 'not_met' : 'met';
    }
}
