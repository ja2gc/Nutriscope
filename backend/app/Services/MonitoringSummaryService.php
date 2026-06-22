<?php

namespace App\Services;

use App\Models\NcpRecord;

/**
 * Phase 6 — Monitoring & Evaluation.
 *
 * Rule-based, ZERO-TOKEN delta engine. Compares the two most recent monitoring
 * visits and emits a structured "what changed + flags + goal evaluation" object.
 * This is the default monitoring summary; the optional Haiku narrative
 * (AIService::narrateMonitoring) consumes this same compact object so the AI
 * never re-parses raw NCP text.
 *
 * The heavy lifting lives in pure array methods (summarizePair / evaluateGoal)
 * so the logic is unit-testable without a database.
 */
class MonitoringSummaryService
{
    /**
     * Clinical reference ranges — mirrors the frontend LAB_REFERENCE_RANGES so
     * both runtimes flag the same way. min/max are the normal band; lowerIsBetter
     * marks labs where dropping toward/below max is the improvement direction.
     *
     * @var array<string, array{label:string, unit:string, min?:float, max?:float, lowerIsBetter?:bool}>
     */
    public const LAB_RANGES = [
        'albumin'     => ['label' => 'Albumin',     'unit' => 'g/dL',  'min' => 3.5],
        'hba1c'       => ['label' => 'HbA1c',       'unit' => '%',     'max' => 7.0,  'lowerIsBetter' => true],
        'ldl'         => ['label' => 'LDL',         'unit' => 'mg/dL', 'max' => 100,  'lowerIsBetter' => true],
        'cholesterol' => ['label' => 'Cholesterol', 'unit' => 'mg/dL', 'max' => 200,  'lowerIsBetter' => true],
        'creatinine'  => ['label' => 'Creatinine',  'unit' => 'mg/dL', 'max' => 1.2,  'lowerIsBetter' => true],
        'potassium'   => ['label' => 'Potassium',   'unit' => 'mEq/L', 'min' => 3.5,  'max' => 5.0],
        'hemoglobin'  => ['label' => 'Hemoglobin',  'unit' => 'g/dL',  'min' => 12.0],
        'glucose'     => ['label' => 'Glucose',     'unit' => 'mg/dL', 'max' => 100,  'lowerIsBetter' => true],
    ];

    /**
     * Patient-context-adjusted reference ranges (A7). The base {@see LAB_RANGES} are
     * adult, sex-neutral; this overrides the labs with well-established sex differences
     * (hemoglobin and creatinine) so delta flags aren't clinically wrong at the edges.
     * Falls back to the base ranges when sex is unknown.
     *
     * @return array<string, array{label:string, unit:string, min?:float, max?:float, lowerIsBetter?:bool}>
     */
    public static function rangesFor(?string $sex = null, ?int $age = null): array
    {
        $ranges = self::LAB_RANGES;
        $s = strtoupper(substr(trim((string) $sex), 0, 1));

        if ($s === 'M') {
            $ranges['hemoglobin']['min'] = 13.5; // adult male
            $ranges['creatinine']['max'] = 1.3;
        } elseif ($s === 'F') {
            $ranges['hemoglobin']['min'] = 12.0; // adult female
            $ranges['creatinine']['max'] = 1.1;
        }

        return $ranges;
    }

    /** Macro intake keys logged per visit inside lab_values, compared against the Rx. */
    public const INTAKE_KEYS = [
        'energy_kcal' => ['label' => 'Energy intake', 'unit' => 'kcal', 'rx' => 'energy_kcal'],
        'protein_g'   => ['label' => 'Protein intake', 'unit' => 'g',   'rx' => 'protein_g'],
    ];

    /**
     * Build the structured summary for an NCP record from its last two visits.
     *
     * @return array<string, mixed>
     */
    public function summarize(NcpRecord $ncpRecord): array
    {
        $visits = $ncpRecord->monitorings()
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take(2)
            ->get();

        if ($visits->isEmpty()) {
            return ['has_data' => false, 'has_previous' => false, 'changes' => [], 'intake' => [], 'goal_evaluation' => ['status' => 'no_data', 'reasons' => []]];
        }

        $current  = $this->extractMetrics($visits[0]);
        $previous = $visits->count() > 1 ? $this->extractMetrics($visits[1]) : null;

        $intervention = $ncpRecord->intervention()->first();
        $rxTargets = $intervention ? [
            'energy_kcal' => $intervention->energy_kcal !== null ? (float) $intervention->energy_kcal : null,
            'protein_g'   => $intervention->protein_g   !== null ? (float) $intervention->protein_g   : null,
        ] : [];

        $nutritionalStatus = $ncpRecord->assessment()->first()?->nutritional_status;

        $activeDiagnoses = $ncpRecord->diagnoses()
            ->orderBy('id')
            ->limit(3)
            ->pluck('pes_statement')
            ->all();

        // A7: pick patient-context-adjusted lab ranges (sex-specific where it matters).
        $patient = $ncpRecord->patient;
        $age     = $patient?->dob ? (int) \Illuminate\Support\Carbon::parse($patient->dob)->age : null;
        $ranges  = self::rangesFor($patient?->sex, $age);

        return $this->summarizePair($previous, $current, $rxTargets, $nutritionalStatus, $ranges, [
            'intervention_goal'  => $intervention?->goal_type,
            'active_diagnoses'   => $activeDiagnoses,
            'nutritional_status' => $nutritionalStatus,
        ]);
    }

    /**
     * Flatten a Monitoring row into a comparable metric map.
     *
     * @return array<string, mixed>
     */
    public function extractMetrics($monitoring): array
    {
        $labs = is_array($monitoring->lab_values) ? $monitoring->lab_values : [];

        $metrics = [
            'date'             => $monitoring->created_at?->toDateString(),
            'weight'           => $monitoring->weight !== null ? (float) $monitoring->weight : null,
            'bmi'              => $monitoring->bmi !== null ? (float) $monitoring->bmi : null,
            'goal_achievement' => $monitoring->goal_achievement ?? null,
            'symptoms'         => $monitoring->symptoms ?? null,
            'clinical_summary' => $monitoring->clinical_summary ?? null,
        ];

        foreach (array_keys(self::LAB_RANGES) as $key) {
            $metrics[$key] = isset($labs[$key]) && is_numeric($labs[$key]) ? (float) $labs[$key] : null;
        }
        foreach (array_keys(self::INTAKE_KEYS) as $key) {
            $metrics[$key] = isset($labs[$key]) && is_numeric($labs[$key]) ? (float) $labs[$key] : null;
        }

        return $metrics;
    }

    /**
     * Pure delta computation — no DB. Compares two metric maps and evaluates the goal.
     *
     * @param  array<string,mixed>|null $previous
     * @param  array<string,mixed>      $current
     * @param  array<string,float|null> $rxTargets
     * @return array<string, mixed>
     */
    public function summarizePair(?array $previous, array $current, array $rxTargets = [], ?string $nutritionalStatus = null, ?array $ranges = null, array $context = []): array
    {
        $ranges ??= self::LAB_RANGES;
        $changes = [];

        // Anthropometrics
        foreach (['weight' => ['Weight', 'kg'], 'bmi' => ['BMI', '']] as $key => [$label, $unit]) {
            $change = $this->buildChange($key, $label, $unit, $previous[$key] ?? null, $current[$key] ?? null, $nutritionalStatus, $ranges);
            if ($change) {
                $changes[] = $change;
            }
        }

        // Labs
        foreach ($ranges as $key => $ref) {
            $change = $this->buildChange($key, $ref['label'], $ref['unit'], $previous[$key] ?? null, $current[$key] ?? null, $nutritionalStatus, $ranges);
            if ($change) {
                $changes[] = $change;
            }
        }

        // Intake adequacy vs Rx (current visit)
        $intake = [];
        foreach (self::INTAKE_KEYS as $key => $meta) {
            $actual = $current[$key] ?? null;
            $target = $rxTargets[$meta['rx']] ?? null;
            if ($actual !== null && $target !== null && $target > 0) {
                $pct = (int) round(($actual / $target) * 100);
                $intake[] = [
                    'metric'  => $key,
                    'label'   => $meta['label'],
                    'unit'    => $meta['unit'],
                    'actual'  => $actual,
                    'target'  => $target,
                    'pct'     => $pct,
                    // <90% under-target, >110% over-target (energy over may be intentional in repletion)
                    'flag'    => $pct < 90 ? 'under' : ($pct > 110 ? 'over' : 'on_target'),
                ];
            }
        }

        return [
            'has_data'           => true,
            'has_previous'       => $previous !== null,
            'previous_date'      => $previous['date'] ?? null,
            'current_date'       => $current['date'] ?? null,
            'changes'            => $changes,
            'intake'             => $intake,
            'goal_evaluation'    => $this->evaluateGoal($changes, $intake),
            'intervention_goal'  => $context['intervention_goal'] ?? null,
            'active_diagnoses'   => $context['active_diagnoses'] ?? [],
            'nutritional_status' => $context['nutritional_status'] ?? null,
        ];
    }

    /**
     * Build one change entry comparing previous→current for a single metric.
     * Returns null when there is nothing useful to show (no current value).
     *
     * @return array<string, mixed>|null
     */
    private function buildChange(string $key, string $label, string $unit, $prev, $curr, ?string $nutritionalStatus, ?array $ranges = null): ?array
    {
        if ($curr === null) {
            return null;
        }

        $delta     = $prev !== null ? round($curr - $prev, 2) : null;
        $deltaPct  = ($prev !== null && $prev != 0.0) ? (int) round((($curr - $prev) / abs($prev)) * 100) : null;
        $direction = $delta === null ? 'flat' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'));

        return [
            'metric'    => $key,
            'label'     => $label,
            'unit'      => $unit,
            'previous'  => $prev,
            'current'   => $curr,
            'delta'     => $delta,
            'delta_pct' => $deltaPct,
            'direction' => $direction,
            'status'    => $this->metricStatus($key, $curr, $prev, $nutritionalStatus, $ranges),
        ];
    }

    /**
     * Goal status for one metric: met (in normal range) / in_progress (improving
     * from previous but not yet normal) / not_met / no_data.
     */
    private function metricStatus(string $key, float $curr, ?float $prev, ?string $nutritionalStatus, ?array $ranges = null): string
    {
        $ranges ??= self::LAB_RANGES;

        // Weight: direction of "good" depends on the nutritional goal.
        if ($key === 'weight') {
            if ($prev === null) {
                return 'no_data';
            }
            $gainGoal = $nutritionalStatus !== null && str_contains($nutritionalStatus, 'Malnutrition');
            if ($curr == $prev) {
                return 'in_progress';
            }
            $moving = $gainGoal ? ($curr > $prev) : ($curr < $prev);
            return $moving ? 'met' : 'not_met';
        }

        if ($key === 'bmi') {
            return 'no_data'; // tracked as a trend; evaluated via weight + labs
        }

        $ref = $ranges[$key] ?? null;
        if ($ref === null) {
            return 'no_data';
        }

        $inRange = (! isset($ref['min']) || $curr >= $ref['min'])
            && (! isset($ref['max']) || $curr <= $ref['max']);
        if ($inRange) {
            return 'met';
        }

        if ($prev !== null) {
            $improving = ($ref['lowerIsBetter'] ?? false)
                ? $curr < $prev
                : (isset($ref['min']) ? $curr > $prev : $curr < $prev);
            if ($improving) {
                return 'in_progress';
            }
        }

        return 'not_met';
    }

    /**
     * Roll per-metric statuses + intake adequacy into one overall evaluation that
     * flows into the NCP Evaluation step.
     *
     * @param  array<int,array<string,mixed>> $changes
     * @param  array<int,array<string,mixed>> $intake
     * @return array{status:string, reasons:array<int,string>}
     */
    public function evaluateGoal(array $changes, array $intake): array
    {
        $statuses = array_values(array_filter(
            array_map(fn ($c) => $c['status'], $changes),
            fn ($s) => $s !== 'no_data'
        ));

        $reasons = [];
        foreach ($changes as $c) {
            if ($c['status'] === 'not_met') {
                $reasons[] = "{$c['label']} not yet at target";
            } elseif ($c['status'] === 'in_progress') {
                $reasons[] = "{$c['label']} improving";
            }
        }
        foreach ($intake as $i) {
            if ($i['flag'] === 'under') {
                $reasons[] = "{$i['label']} only {$i['pct']}% of prescribed";
            }
        }

        if (empty($statuses)) {
            return ['status' => 'no_data', 'reasons' => $reasons];
        }

        $met        = count(array_filter($statuses, fn ($s) => $s === 'met'));
        $notMet     = count(array_filter($statuses, fn ($s) => $s === 'not_met'));
        $total      = count($statuses);
        $underIntake = (bool) array_filter($intake, fn ($i) => $i['flag'] === 'under');

        // All targets in range AND intake adequate → met. None met / mostly off → not_met.
        if ($met === $total && ! $underIntake) {
            $status = 'met';
        } elseif ($met === 0 && $notMet === $total) {
            $status = 'not_met';
        } else {
            $status = 'partial';
        }

        return ['status' => $status, 'reasons' => array_values(array_unique($reasons))];
    }
}
