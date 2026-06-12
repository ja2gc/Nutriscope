<?php

namespace App\Services\Reports;

use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Services\Reports\Contracts\ReportGenerator;
use App\Services\Reports\Generators\BudgetReportGenerator;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use App\Services\Reports\Generators\DietaryCashBookGenerator;
use App\Services\Reports\Generators\InventoryReportGenerator;
use App\Services\Reports\Generators\MenuCalendarGenerator;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
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
    /** type => generator class. */
    private const GENERATORS = [
        'program_project_activity' => ProgramProjectActivityGenerator::class,
        'menu_calendar'            => MenuCalendarGenerator::class,
        'dietary_cash_book'        => DietaryCashBookGenerator::class,
        'procurement_pack'         => ProcurementPackGenerator::class,
        'demographic_census'       => DemographicCensusGenerator::class,
        'patient_menu_plan'        => PatientMenuPlanGenerator::class,
        'budget_report'            => BudgetReportGenerator::class,
        'budget'                   => BudgetReportGenerator::class,
        'inventory_report'         => InventoryReportGenerator::class,
        'inventory'                => InventoryReportGenerator::class,
    ];

    public function generate(Report $report): string
    {
        $generator = $this->resolve($report->type);

        $data = array_merge($generator->data($report), [
            'branding'     => ReportBranding::singleton(),
            'signatories'  => $this->signatories($report),
            'report'       => $report,
            'generated_at' => now(),
        ]);

        [$size, $orientation] = $generator->paper();

        $pdf  = Pdf::loadView($generator->view(), $data)->setPaper($size, $orientation);
        $path = "reports/{$report->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

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
    private function signatories(Report $report): array
    {
        $template    = ReportTemplate::where('type', $report->type)->first();
        $defaults    = $template?->signatories ?? [];
        $preparedBy  = $report->parameters['prepared_by_name'] ?? null;

        return array_map(function (array $sig) use ($preparedBy) {
            if (($sig['role'] ?? null) === 'prepared_by' && $preparedBy) {
                $sig['name'] = $preparedBy;
            }
            return $sig;
        }, $defaults);
    }
}
