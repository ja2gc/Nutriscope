<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportBranding;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use App\Services\Reports\Generators\MenuCalendarGenerator;
use App\Services\Reports\Generators\NcpSummaryGenerator;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class ReportPaperSizeTest extends TestCase
{
    public function test_every_report_uses_long_bond_paper_with_a_report_specific_orientation(): void
    {
        $expectedPaper = [0, 0, 612, 936];
        $generators = [
            AccomplishmentReportGenerator::class => 'landscape',
            DemographicCensusGenerator::class => 'landscape',
            MenuCalendarGenerator::class => 'landscape',
            NcpSummaryGenerator::class => 'portrait',
            PatientMenuPlanGenerator::class => 'landscape',
            ProcurementPackGenerator::class => 'portrait',
            ProgramProjectActivityGenerator::class => 'portrait',
        ];

        foreach ($generators as $generatorClass => $expectedOrientation) {
            $this->assertSame(
                [$expectedPaper, $expectedOrientation],
                app($generatorClass)->paper(),
                "{$generatorClass} must use 8.5 x 13 inch long bond paper.",
            );
        }
    }

    public function test_demographic_census_pdf_omits_ward_and_uses_three_equal_breakdowns(): void
    {
        $census = [
            'total' => 1,
            'age_sex' => collect(DemographicCensusGenerator::AGE_GROUPS)
                ->mapWithKeys(fn (string $group): array => [$group => ['M' => 0, 'F' => 0, 'total' => 0]])
                ->all(),
            'by_sex' => ['M' => 0, 'F' => 1],
            'by_ward' => ['Medical' => 1],
            'by_primary_diagnosis_category' => ['Diabetes' => 1],
            'by_status' => ['Normal' => 1],
            'by_risk' => ['Low' => 1],
        ];
        $report = new Report(['type' => 'demographic_census']);
        $html = view('reports.demographic-census', [
            'census' => $census,
            'inclusive_label' => '09/01/26 - 09/30/26',
            'age_groups' => DemographicCensusGenerator::AGE_GROUPS,
            'branding' => new ReportBranding,
            'signatories' => [],
            'generated_at' => now(),
            'report' => $report,
        ])->render();

        $document = new \DOMDocument;
        $previousLibxmlState = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);
        $xpath = new \DOMXPath($document);
        $breakdownCells = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " census-breakdowns ")]/tr[1]/td');

        $this->assertStringNotContainsString('By Ward', $html);
        $this->assertCount(3, $breakdownCells);
        foreach ($breakdownCells as $cell) {
            $this->assertSame('33.33%', $cell->getAttribute('width'));
        }
    }

    public function test_long_bond_css_preserves_landscape_media_box(): void
    {
        $bytes = Pdf::loadHTML('<style>@page { size: 13in 8.5in; }</style><p>Landscape</p>')->output();

        $this->assertMatchesRegularExpression(
            '/\/MediaBox \[0\.000 0\.000 936\.000 612\.000\]/',
            $bytes,
        );
    }

    public function test_shared_layout_prevents_orphan_sections_and_cramped_signatories(): void
    {
        $layout = file_get_contents(resource_path('views/reports/layout.blade.php'));
        $procurement = file_get_contents(resource_path('views/reports/procurement-pack.blade.php'));
        $service = file_get_contents(app_path('Services/Reports/ReportService.php'));

        $this->assertStringContainsString("'paper_orientation' => \$orientation", $service);
        $this->assertStringContainsString('page-break-after: avoid', $layout);
        $this->assertStringContainsString('table-layout: fixed', $layout);
        $this->assertStringContainsString('word-wrap: break-word', $layout);
        $this->assertStringContainsString('class="report-page page-start"', $procurement);
        $this->assertStringNotContainsString('<div class="page-break"></div>', $procurement);
    }
}
