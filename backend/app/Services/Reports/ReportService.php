<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Services\Reports\Contracts\ReportGenerator;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use App\Services\Reports\Generators\MenuCalendarGenerator;
use App\Services\Reports\Generators\NcpSummaryGenerator;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates report generation: resolves the right generator, injects the shared
 * branding + signatory blocks (with the "prepared by" name from the requesting user),
 * renders the Blade view through DomPDF, and stores the PDF on the public disk.
 *
 * Generators only build data; this class owns all the I/O.
 */
class ReportService
{
    public function __construct(private readonly ReportArchiveStorage $archiveStorage) {}

    /** type => generator class. */
    private const GENERATORS = [
        'program_project_activity' => ProgramProjectActivityGenerator::class,
        'menu_calendar' => MenuCalendarGenerator::class,
        'procurement_pack' => ProcurementPackGenerator::class,
        'demographic_census' => DemographicCensusGenerator::class,
        'patient_menu_plan' => PatientMenuPlanGenerator::class,
        'ncp_summary' => NcpSummaryGenerator::class,
        'accomplishment_report' => AccomplishmentReportGenerator::class,
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::GENERATORS);
    }

    /**
     * Render a report to PDF bytes WITHOUT persisting anything. Used by on-demand
     * render (browse-don't-generate) and reused by {@see generate()} for archives.
     *
     * @return array{bytes:string,meta:array{branding:ReportBranding,signatories:array,generated_at:Carbon}}
     */
    public function buildPdf(Report $report): array
    {
        $generator = $this->resolve($report->type);

        $meta = [
            'branding' => ReportBranding::singleton(),
            'signatories' => $this->signatoriesFor($report),
            'generated_at' => now(),
        ];

        $data = array_merge($generator->data($report), $meta, ['report' => $report]);

        [$size, $orientation] = $generator->paper();

        $pdf = Pdf::loadView($generator->view(), $data)->setPaper($size, $orientation);

        return ['bytes' => $pdf->output(), 'meta' => $meta];
    }

    /**
     * Render bytes for an on-demand request, building a transient (un-saved) Report
     * from the type + params. Nothing is written to the database.
     */
    public function streamBytes(string $type, array $params): string
    {
        $report = new Report(['type' => $type, 'parameters' => $params]);

        return $this->buildPdf($report)['bytes'];
    }

    public function generate(Report $report): string
    {
        $bytes = $this->buildPdf($report)['bytes'];

        $path = "reports/{$report->uuid}.pdf";
        try {
            if (! Storage::disk('public')->put($path, $bytes)) {
                throw new \RuntimeException('Report archive storage failed.');
            }
        } catch (\Throwable $exception) {
            try {
                $this->archiveStorage->scheduleOriginalCleanup($path);
            } catch (\Throwable $cleanupException) {
                report($cleanupException);
            }
            throw $exception;
        }

        return $path;
    }

    public function resolve(string $type): ReportGenerator
    {
        $class = self::GENERATORS[$type] ?? null;
        if (! $class) {
            throw new \InvalidArgumentException("No report generator for type [{$type}].");
        }

        return app($class);
    }

    public function supports(string $type): bool
    {
        return isset(self::GENERATORS[$type]);
    }

    /**
     * Merge the template's signatory defaults with the "prepared by" name captured
     * from the requesting user at dispatch time (report.parameters.prepared_by_name).
     *
     * @return array<int,array{role:string,label:string,name:string,title:string}>
     */
    public function signatoriesFor(Report $report): array
    {
        $template = ReportTemplate::where('type', $report->type)->first();
        $defaults = $template?->signatories ?? [];
        $preparedBy = $report->parameters['prepared_by_name'] ?? null;

        return array_map(function (array $sig) use ($preparedBy) {
            if (($sig['role'] ?? null) === 'prepared_by' && $preparedBy) {
                $sig['name'] = $preparedBy;
            }

            return $sig;
        }, $defaults);
    }
}
