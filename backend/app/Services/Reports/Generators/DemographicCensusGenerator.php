<?php

namespace App\Services\Reports\Generators;

use App\Models\Assessment;
use App\Models\NcpRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * ADIME-cycle aggregates for the on-screen Census. Completed-month snapshots
 * retain legacy aggregate keys, while the API exposes age/sex, risk, and
 * Nutrition care category totals.
 */
class DemographicCensusGenerator
{
    public const BASIS_VERSION = 6;

    /** Visit workflow was introduced during September 2026. Earlier assessed cycles have no visit record. */
    private const LEGACY_VISIT_CUTOFF = '2026-09-08 00:00:00';

    /** Age buckets mirror the bi-annual census columns. */
    public const AGE_GROUPS = ['0-4', '5-9', '10-14', '15-18', '19-29', '30-39', '40-59', '60+'];

    public const NUTRITIONAL_STATUSES = [
        'Severe Malnutrition', 'Moderate Malnutrition', 'Mild Malnutrition / Underweight',
        'Normal', 'Overweight', 'Obese Class I', 'Obese Class II', 'Obese Class II (Severe)',
        'Unspecified',
    ];

    /** @return array<string,mixed> */
    public function currentCensus(Carbon $start, Carbon $end): array
    {
        $cycles = $this->qualifyingCyclesQuery()
            ->with(['patient', 'assessment'])
            ->whereBetween('created_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->get()
            ->map(function (NcpRecord $cycle): array {
                $age = $cycle->patient?->dob?->diffInYears($cycle->created_at);

                return [
                    'age' => $age,
                    'sex' => $cycle->patient?->sex,
                    'ward' => $cycle->patient?->ward,
                    'primary_diagnosis_category' => $cycle->assessment?->primary_diagnosis_category,
                    'nutritional_status' => self::adultNutritionalStatus($cycle->assessment, $age),
                    'risk_level' => self::riskLevel(
                        $cycle->risk_score === null ? null : (float) $cycle->risk_score,
                    ),
                ];
            })->all();

        return self::aggregate($cycles);
    }

    public function earliestQualifyingCycleAt(): ?string
    {
        return $this->qualifyingCyclesQuery()->min('created_at');
    }

    /** @return Builder<NcpRecord> */
    private function qualifyingCyclesQuery(): Builder
    {
        return NcpRecord::query()
            ->whereHas('assessment')
            ->where(function (Builder $query): void {
                $query->where('created_at', '<', self::LEGACY_VISIT_CUTOFF)
                    ->orWhereHas('appointments', function (Builder $visit): void {
                        $visit->where('status', 'completed')
                            ->whereJsonContains('worked_on', 'assessment');
                    });
            });
    }

    /**
     * Map a numeric risk score to its category (mirrors RiskScoreCalculator):
     * ≤ 1 Low · 2–3 Moderate · > 3 High.
     */
    public static function riskLevel(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score > 3.0 => 'High',
            $score >= 2.0 => 'Moderate',
            default => 'Low',
        };
    }

    private static function adultNutritionalStatus(?Assessment $assessment, ?float $age): string
    {
        if ($assessment === null || $age === null || $age < 19) {
            return 'Unspecified';
        }

        $bmiValue = $assessment->bmi ?? $assessment->calculateBmi();
        if (! is_numeric($bmiValue) || (float) $bmiValue <= 0) {
            return 'Unspecified';
        }

        $bmi = (float) $bmiValue;
        $ibw = is_numeric($assessment->ibw_percentage) && (float) $assessment->ibw_percentage > 0
            ? (float) $assessment->ibw_percentage
            : 100.0;

        return match (true) {
            $ibw < 70 || $bmi < 16 => 'Severe Malnutrition',
            $ibw < 85 || $bmi < 17 => 'Moderate Malnutrition',
            $ibw < 90 || $bmi < 18.5 => 'Mild Malnutrition / Underweight',
            $bmi < 23 && $ibw <= 120 => 'Normal',
            $bmi < 25 => 'Overweight',
            $bmi < 30 => 'Obese Class I',
            $bmi < 35 => 'Obese Class II',
            default => 'Obese Class II (Severe)',
        };
    }

    public static function ageGroup(?int $age): string
    {
        if ($age === null) {
            return 'Unspecified';
        }

        return match (true) {
            $age <= 4 => '0-4',
            $age <= 9 => '5-9',
            $age <= 14 => '10-14',
            $age <= 18 => '15-18',
            $age <= 29 => '19-29',
            $age <= 39 => '30-39',
            $age <= 59 => '40-59',
            default => '60+',
        };
    }

    /**
     * @param  array<int,array{age:?int,sex:?string,ward:?string,primary_diagnosis_category:?string,nutritional_status:?string,risk_level:?string}>  $cycles
     */
    public static function aggregate(array $cycles): array
    {
        $ageSex = [];
        foreach (self::AGE_GROUPS as $g) {
            $ageSex[$g] = ['M' => 0, 'F' => 0, 'total' => 0];
        }

        $bySex = ['M' => 0, 'F' => 0, 'Unknown' => 0];
        $byWard = $byPrimaryDiagnosisCategory = [];
        $byRisk = ['Low' => 0, 'Moderate' => 0, 'High' => 0];
        $byStatus = array_fill_keys(self::NUTRITIONAL_STATUSES, 0);

        foreach ($cycles as $p) {
            $sex = self::normalizeSex($p['sex'] ?? null);
            $bySex[$sex] = ($bySex[$sex] ?? 0) + 1;

            $group = self::ageGroup(isset($p['age']) ? (int) $p['age'] : null);
            if (isset($ageSex[$group]) && in_array($sex, ['M', 'F'], true)) {
                $ageSex[$group][$sex]++;
                $ageSex[$group]['total']++;
            }

            self::bump($byWard, $p['ward'] ?? null);
            self::bump($byPrimaryDiagnosisCategory, $p['primary_diagnosis_category'] ?? null, 'Unclassified');
            self::bump($byStatus, $p['nutritional_status'] ?? null);
            self::bump($byRisk, $p['risk_level'] ?? null);
        }

        arsort($byWard);
        arsort($byPrimaryDiagnosisCategory);

        return [
            'total' => count($cycles),
            'age_sex' => $ageSex,
            'by_sex' => $bySex,
            'by_ward' => $byWard,
            'by_primary_diagnosis_category' => $byPrimaryDiagnosisCategory,
            'by_status' => $byStatus,
            'by_risk' => $byRisk,
        ];
    }

    private static function normalizeSex(?string $sex): string
    {
        $s = strtoupper(substr(trim((string) $sex), 0, 1));

        return match ($s) {
            'M' => 'M',
            'F' => 'F',
            default => 'Unknown',
        };
    }

    private static function bump(array &$bucket, ?string $key, string $emptyLabel = 'Unspecified'): void
    {
        $k = trim((string) $key);
        if ($k === '') {
            $k = $emptyLabel;
        }
        $bucket[$k] = ($bucket[$k] ?? 0) + 1;
    }
}
