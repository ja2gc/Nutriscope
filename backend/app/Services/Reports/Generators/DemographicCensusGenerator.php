<?php

namespace App\Services\Reports\Generators;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * Demographic / Research Census — patient counts broken down by age group, sex,
 * ward, admission diagnosis, nutritional status, and risk level over any date range.
 *
 * Refocus of the old "NCP bi-annual" sheet (now a layout reference only): the form's
 * age × sex matrix is kept, but it's a research census, not a fixed bi-annual format.
 * aggregate() is pure over plain rows (unit-tested); data() is the date-range loader.
 */
class DemographicCensusGenerator implements ReportGenerator
{
    /** Age buckets mirror the bi-annual census columns. */
    public const AGE_GROUPS = ['0-4', '5-9', '10-14', '15-18', '19-29', '30-39', '40-59', '60+'];

    public function type(): string
    {
        return 'demographic_census';
    }

    public function view(): string
    {
        return 'reports.demographic-census';
    }

    public function paper(): array
    {
        return ['a4', 'landscape'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        // Period must be explicit so the census is reproducible — no current-date fallback.
        if (empty($params['start']) || empty($params['end'])) {
            throw new \InvalidArgumentException('Demographic census requires an explicit start/end period.');
        }
        $start = Carbon::parse($params['start']);
        $end = Carbon::parse($params['end']);

        $patients = Patient::query()
            ->whereBetween('admission_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Patient $p) => [
                'age' => $p->dob ? $p->dob->diffInYears($p->admission_date ?? now()) : null,
                'sex' => $p->sex,
                'ward' => $p->ward,
                'diagnosis' => $p->medical_diagnosis,
                // nutritional_status from the patient's most-recent assessment.
                'nutritional_status' => $this->latestNutritionalStatus($p->id),
                // RP-05: risk level is the patient's latest computed NCP risk score,
                // NOT the screening type (adult/pediatric), which is not a risk level.
                'risk_level' => $this->latestRiskLevel($p->id),
            ])->all();

        return [
            'census' => self::aggregate($patients),
            'inclusive_start' => $start->toDateString(),
            'inclusive_end' => $end->toDateString(),
            'inclusive_label' => $start->format('m/d/y').' - '.$end->format('m/d/y'),
            'age_groups' => self::AGE_GROUPS,
        ];
    }

    /** Most-recent assessment nutritional_status for a patient, or null. */
    private function latestNutritionalStatus(int $patientId): ?string
    {
        return Assessment::whereHas('ncpRecord', fn ($q) => $q->where('patient_id', $patientId))
            ->latest('id')
            ->value('nutritional_status');
    }

    /** Risk category from the patient's most-recent NCP risk score, or null. */
    private function latestRiskLevel(int $patientId): ?string
    {
        $score = NcpRecord::where('patient_id', $patientId)
            ->whereNotNull('risk_score')
            ->latest('id')
            ->value('risk_score');

        return $score === null ? null : self::riskLevel((float) $score);
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
     * @param  array<int,array{age:?int,sex:?string,ward:?string,diagnosis:?string,nutritional_status:?string,risk_level:?string}>  $patients
     */
    public static function aggregate(array $patients): array
    {
        $ageSex = [];
        foreach (self::AGE_GROUPS as $g) {
            $ageSex[$g] = ['M' => 0, 'F' => 0, 'total' => 0];
        }

        $bySex = ['M' => 0, 'F' => 0, 'Unknown' => 0];
        $byWard = $byDiagnosis = $byStatus = $byRisk = [];

        foreach ($patients as $p) {
            $sex = self::normalizeSex($p['sex'] ?? null);
            $bySex[$sex] = ($bySex[$sex] ?? 0) + 1;

            $group = self::ageGroup(isset($p['age']) ? (int) $p['age'] : null);
            if (isset($ageSex[$group]) && in_array($sex, ['M', 'F'], true)) {
                $ageSex[$group][$sex]++;
                $ageSex[$group]['total']++;
            }

            self::bump($byWard, $p['ward'] ?? null);
            self::bump($byDiagnosis, $p['diagnosis'] ?? null);
            self::bump($byStatus, $p['nutritional_status'] ?? null);
            self::bump($byRisk, $p['risk_level'] ?? null);
        }

        arsort($byWard);
        arsort($byDiagnosis);
        arsort($byStatus);
        arsort($byRisk);

        return [
            'total' => count($patients),
            'age_sex' => $ageSex,
            'by_sex' => $bySex,
            'by_ward' => $byWard,
            'by_diagnosis' => $byDiagnosis,
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

    private static function bump(array &$bucket, ?string $key): void
    {
        $k = trim((string) $key);
        if ($k === '') {
            $k = 'Unspecified';
        }
        $bucket[$k] = ($bucket[$k] ?? 0) + 1;
    }
}
