<?php

namespace App\Services\Reports\Generators;

use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Report;
use App\Models\ScreeningDocument;
use App\Services\ClinicalCompletenessService;
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
    public function __construct(private ClinicalCompletenessService $completeness) {}

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
            'patient', 'assessment.biochemicalData',
            'diagnoses', 'intervention.mealPlans', 'monitorings',
        ])->findOrFail($params['ncp_record_id']);

        $patient    = $ncp->patient;
        $assessment = $ncp->assessment;

        // AD-02 / RP-01-02: distinguish a complete initial ADI from a full ADIME
        // and flag incompleteness so the view can watermark a non-final report.
        $missing = array_merge(
            $this->completeness->assessmentMissing($ncp),
            $this->completeness->diagnosisMissing($ncp),
            $this->completeness->interventionMissing($ncp),
        );
        $initialComplete = $this->completeness->initialAdiComplete($ncp);
        $fullComplete    = $this->completeness->fullAdimeComplete($ncp);
        $completionStage = $fullComplete ? 'Full ADIME'
            : ($initialComplete ? 'Initial ADI' : 'Incomplete — Draft');

        // RP-03: reference the patient's meal plan(s) for this cycle.
        $mealPlan = optional($ncp->intervention)->mealPlans
            ?->sortByDesc('created_at')->first();

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
            // Attachments are cycle-scoped (AS-02) — load by ncp_record_id, not assessment.
            'attachments'        => $attachments = ScreeningDocument::where('ncp_record_id', $ncp->id)
                ->orderByDesc('created_at')->get(),
            // Image attachments resolved to absolute paths for the PDF appendix
            // (dompdf embeds local image files; PDFs can't be inlined and are listed only).
            'attachment_images'  => $this->imageAppendix($attachments),
            'record_status'      => $ncp->status,
            // Completeness / report integrity (RP-01/02, AD-02)
            'completion_stage'   => $completionStage,
            'is_complete'        => $initialComplete,
            'incomplete_items'   => $missing,
            'meal_plan'          => $mealPlan ? [
                'id'              => $mealPlan->id,
                'week_start_date' => optional($mealPlan->week_start_date)->format('M j, Y')
                    ?? (string) $mealPlan->week_start_date,
                'status'          => $mealPlan->status,
            ] : null,
        ];
    }

    /**
     * Resolve image attachments to absolute filesystem paths for the PDF appendix.
     * Only raster images (jpg/jpeg/png) can be embedded by dompdf.
     *
     * @return array<int,array{name:string,path:string,date:?string}>
     */
    private function imageAppendix($attachments): array
    {
        $images = [];
        foreach ($attachments as $doc) {
            $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                continue;
            }
            $abs = \Illuminate\Support\Facades\Storage::exists($doc->file_path)
                ? \Illuminate\Support\Facades\Storage::path($doc->file_path)
                : (is_file($doc->file_path) ? $doc->file_path : null); // legacy absolute path
            if ($abs && is_file($abs)) {
                $images[] = [
                    'name' => $doc->original_name ?? basename($doc->file_path),
                    'path' => $abs,
                    'date' => optional($doc->created_at)->format('M j, Y'),
                ];
            }
        }
        return $images;
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
