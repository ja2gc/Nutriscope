<?php

namespace Tests\Feature;

use App\Models\MenuCycle;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Services\Reports\Generators\AccomplishmentReportGenerator;
use App\Services\Reports\Generators\MenuCalendarGenerator;
use App\Services\Reports\Generators\NcpSummaryGenerator;
use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use App\Services\Reports\Generators\ProcurementPackGenerator;
use App\Services\Reports\Generators\ProgramProjectActivityGenerator;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class ReportPaperSizeTest extends TestCase
{
    public function test_every_report_uses_long_bond_paper_with_a_report_specific_orientation(): void
    {
        $expectedPaper = [0, 0, 612, 936];
        $generators = [
            AccomplishmentReportGenerator::class => 'landscape',
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

    public function test_demographic_census_is_not_registered_as_a_pdf_report(): void
    {
        $this->assertFalse(app(ReportService::class)->supports('demographic_census'));
        $this->assertFalse(app(ReportBrowser::class)->supports('demographic_census'));
    }

    public function test_menu_calendar_renders_only_letterhead_weekly_menu_and_signatory_section(): void
    {
        $html = view('reports.menu-calendar', [
            'report' => new Report(['title' => 'Menu Calendar']),
            'branding' => new ReportBranding([
                'hospital_name' => 'Romana Pangan District Hospital',
                'address' => 'Labangan, Zamboanga del Sur',
                'service_name' => 'Dietary Service',
            ]),
            'cycle' => new MenuCycle(['name' => 'October 5–11, 2026']),
            'days' => ['Monday'],
            'dates' => ['Monday' => 'Oct 5'],
            'meals' => ['Lunch'],
            'grid' => ['Lunch' => ['Monday' => ['Chicken adobo']]],
            'signatories' => [['label' => 'Prepared by', 'name' => 'Jane Doe', 'title' => 'Dietitian']],
        ])->render();

        $this->assertStringContainsString('Romana Pangan District Hospital', $html);
        $this->assertStringContainsString('WEEKLY MENU CALENDAR', $html);
        $this->assertStringContainsString('Chicken adobo', $html);
        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringNotContainsString('Population:', $html);
        $this->assertStringNotContainsString('Weekly cost:', $html);
        $this->assertStringNotContainsString('Cost / head / day:', $html);
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
        $this->assertStringContainsString("setOption('enable_font_subsetting', true)", $service);
        $this->assertStringContainsString('page-break-after: avoid', $layout);
        $this->assertStringContainsString('table-layout: fixed', $layout);
        $this->assertStringContainsString('word-wrap: break-word', $layout);
        $this->assertStringContainsString('class="report-page page-start"', $procurement);
        $this->assertStringContainsString('<div class="page-start" style="height:1px; font-size:1px; line-height:1px;">&nbsp;</div>', $procurement);
        $this->assertStringNotContainsString("report-page{{ \$i > 0 ? ' page-start' : '' }}", $procurement);
    }

    public function test_procurement_pack_first_page_uses_the_shared_hospital_letterhead(): void
    {
        $branding = new ReportBranding([
            'hospital_name' => 'Romana Pangan District Hospital',
            'address' => 'Labangan, Zamboanga del Sur',
            'accreditation' => 'DOH Licensed',
            'service_name' => 'Dietary Service',
            'province' => 'Zamboanga del Sur',
            'lgu' => 'Labangan',
        ]);
        $po = (object) ['id' => 1, 'po_number' => 'PO-1'];
        $html = view('reports.procurement-pack', [
            'packs' => [[
                'is_final' => true,
                'supplier' => null,
                'po' => $po,
                'order_date' => 'Sep 30, 2026',
                'or_number' => '-',
                'air_items' => [],
                'statement_items' => [],
                'grand_total' => 0,
                'summary' => ['inclusive' => 'Sep 30, 2026', 'date_purchased' => 'Sep 30, 2026', 'amount' => 0],
                'attachments' => [],
            ]],
            'branding' => $branding,
            'air_signatories' => [],
            'statement_signatories' => [],
            'summary_signatories' => [],
            'report' => new Report(['title' => 'Procurement Pack']),
            'paper_orientation' => 'portrait',
        ])->render();

        $firstTitle = strpos($html, 'ACCEPTANCE AND INSPECTION REPORT');
        $firstHospitalName = strpos($html, 'Romana Pangan District Hospital');

        $this->assertNotFalse($firstTitle);
        $this->assertNotFalse($firstHospitalName);
        $this->assertLessThan($firstTitle, $firstHospitalName);
    }

    public function test_shared_letterhead_is_a_block_that_dompdf_keeps_on_dense_first_pages(): void
    {
        $letterhead = file_get_contents(resource_path('views/reports/partials/letterhead.blade.php'));

        $this->assertStringContainsString('class="report-letterhead"', $letterhead);
        $this->assertStringNotContainsString('<table style="width:100%; border:0;">', $letterhead);
        $this->assertStringNotContainsString('page-break-inside:avoid', $letterhead);
    }
}
