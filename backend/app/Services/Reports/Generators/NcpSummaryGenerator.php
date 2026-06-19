<?php

namespace App\Services\Reports\Generators;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;

/**
 * NCP Summary — a patient's Nutrition Care Plan (ADIME) as the standard
 * "Medical Nutrition Therapy (Nutrition Care Plan)" form: Assessment → Diagnosis
 * (PES) → Intervention → all dated Monitoring/Evaluation entries. Read-only output
 * over an existing {@see NcpRecord}; no clinical recompute. Educational handout for
 * the patient + the hospital's filed record. RND-only (PHI).
 */
class NcpSummaryGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'ncp_summary';
    }

    public function view(): string
    {
        return 'reports.ncp-summary';
    }

    public function paper(): array
    {
        return ['a4', 'portrait'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        $ncp = NcpRecord::with([
            'patient', 'assessment.biochemicalData', 'assessment.screeningDocuments',
            'diagnoses', 'intervention', 'monitorings',
        ])->findOrFail($params['ncp_record_id']);

        $patient    = $ncp->patient;
        $assessment = $ncp->assessment;

        return [
            'patient' => [
                'name'            => $patient?->name,
                'hospital_number' => $patient?->hospital_number,
                'age'             => self::ageFrom($patient?->dob, $patient?->admission_date),
                'sex'             => $patient?->sex,
                'physician'       => $patient?->physician,
                'admission_date'  => optional($patient?->admission_date)->format('M j, Y'),
                'diagnosis'       => $patient?->medical_diagnosis,
                'religion'        => $assessment?->religion ?? $patient?->religion,
            ],
            'assessment'         => $assessment,
            'biochem'            => $assessment?->biochemicalData,
            'nutritional_status' => $assessment?->nutritional_status,
            'risk_score'         => $ncp->risk_score,
            'risk_band'          => self::riskBand($ncp->risk_score !== null ? (float) $ncp->risk_score : null),
            'diagnoses'          => $ncp->diagnoses->map(fn (Diagnosis $d) => [
                'domain' => $d->domain,
                'pes'    => $d->pes_statement
                    ?: Diagnosis::buildPes((string) $d->problem, (string) $d->etiology, (string) $d->signs_symptoms),
            ])->all(),
            'intervention'       => $ncp->intervention,
            'monitorings'        => $ncp->monitorings->sortBy('created_at')->values(),
            'attachments'        => $assessment?->screeningDocuments->sortByDesc('created_at')->values() ?? collect(),
            'record_status'      => $ncp->status,
        ];
    }

    /** Age in whole years from DOB to a reference date (admission, else today). */
    public static function ageFrom($dob, $reference = null): ?int
    {
        if (! $dob) {
            return null;
        }
        $dob = $dob instanceof Carbon ? $dob : Carbon::parse($dob);
        $ref = $reference ? ($reference instanceof Carbon ? $reference : Carbon::parse($reference)) : Carbon::now();

        return (int) $dob->diffInYears($ref);
    }

    /**
     * Nutritional-risk band from the score, per the form's scoring key:
     * 1 = Low, 2–3 = Moderate, >3 = High.
     */
    public static function riskBand(?float $score): string
    {
        if ($score === null) {
            return '—';
        }
        return match (true) {
            $score <= 1 => 'Low Risk',
            $score <= 3 => 'Moderate Risk',
            default     => 'High Risk',
        };
    }
}
