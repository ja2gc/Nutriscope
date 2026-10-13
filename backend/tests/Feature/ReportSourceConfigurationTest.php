<?php

namespace Tests\Feature;

use App\Actions\Reports\PrepareSavedReport;
use App\Models\MealPlan;
use App\Models\MenuCycle;
use App\Models\ReportBranding;
use App\Models\ReportConfigurationRevision;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\Reports\ReportCoveredDate;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportSourceConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_and_meal_plan_reports_use_configuration_from_source_creation_while_data_can_change(): void
    {
        Storage::fake('report_cache');
        ReportBranding::singleton()->update(['hospital_name' => 'Hospital at creation']);
        foreach (['menu_calendar', 'patient_menu_plan'] as $type) {
            ReportTemplate::create([
                'type' => $type,
                'name' => $type,
                'blade_view' => 'reports.'.$type,
                'signatories' => [['role' => 'approved_by', 'label' => 'Approved by', 'name' => 'Original Officer', 'title' => 'Director']],
            ]);
        }
        $actor = User::factory()->rnd()->create();
        $menu = MenuCycle::factory()->create();
        $mealPlan = MealPlan::factory()->create();

        $this->assertSame(
            $menu->week_start_date->copy()->addDays(6)->toDateString(),
            ReportCoveredDate::forParameters('menu_calendar', ['menu_cycle_id' => $menu->uuid]),
        );
        $this->assertSame('2026-06-15', ReportCoveredDate::forParameters('procurement_pack', ['start' => '2026-06-01', 'end' => '2026-06-15']));
        $this->assertSame('2026-06-15', ReportCoveredDate::forParameters('program_project_activity', ['start' => '2026-06-01', 'end' => '2026-06-15']));
        $this->assertNull(ReportCoveredDate::forParameters('accomplishment_report', ['end' => '2026-02-31']));

        $this->assertSame('Hospital at creation', $menu->report_configuration_snapshot['branding']['hospital_name']);
        $this->assertSame('Hospital at creation', $mealPlan->report_configuration_snapshot['branding']['hospital_name']);
        $this->assertNotNull($menu->report_configuration_snapshot['revisions']['branding']);
        $this->assertNotNull($menu->report_configuration_snapshot['revisions']['templates']['menu_calendar']);
        $this->assertNotNull($mealPlan->report_configuration_snapshot['revisions']['templates']['patient_menu_plan']);
        ReportBranding::singleton()->update(['hospital_name' => 'Hospital after creation']);
        ReportTemplate::query()->whereIn('type', ['menu_calendar', 'patient_menu_plan'])->update(['signatories' => json_encode([
            ['role' => 'approved_by', 'label' => 'Approved by', 'name' => 'New Officer', 'title' => 'Director'],
        ])]);

        $resolver = app(ReportService::class);
        $renderer = $this->createMock(ReportService::class);
        $renderer->method('signatoriesFor')->willReturnCallback(fn ($report) => $resolver->signatoriesFor($report));
        $renderer->method('buildPdf')->willReturnCallback(fn ($report): array => [
            'bytes' => "%PDF-1.4\n".($report->snapshot['branding']['hospital_name'] ?? '')."\n%%EOF",
            'meta' => [],
        ]);
        $this->app->instance(ReportService::class, $renderer);

        foreach ([
            ['menu_calendar', ['menu_cycle_id' => $menu->id]],
            ['patient_menu_plan', ['meal_plan_id' => $mealPlan->id]],
        ] as [$type, $parameters]) {
            $first = app(PrepareSavedReport::class)->execute($actor, $type, $parameters, freeze: false);
            if ($type === 'menu_calendar') {
                $this->assertSame($menu->week_start_date->toDateString(), $first->report_covered_from?->toDateString());
                $this->assertSame($menu->week_start_date->copy()->addDays(6)->toDateString(), $first->report_covered_until?->toDateString());
            }
            $this->assertSame('Hospital at creation', $first->snapshot['branding']['hospital_name']);
            $this->assertSame('Original Officer', $first->snapshot['signatories'][0]['name']);
            $this->assertStringContainsString('Hospital at creation', Storage::disk('report_cache')->get($first->cache_path));
        }
    }

    public function test_period_report_uses_configuration_active_at_start_when_span_crosses_a_revision(): void
    {
        Storage::fake('report_cache');
        $actor = User::factory()->rnd()->create();
        $branding = ReportBranding::singleton();
        $branding->update(['hospital_name' => 'Hospital at period start']);
        $template = ReportTemplate::create([
            'type' => 'accomplishment_report',
            'name' => 'Accomplishment Report',
            'blade_view' => 'reports.accomplishment-report',
            'is_active' => true,
            'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'Officer at start', 'title' => 'Director']],
        ]);
        $initialTemplateRevision = ReportConfigurationRevision::query()
            ->where('kind', 'template')->where('report_template_id', $template->id)->latest('id')->firstOrFail();
        $initialBrandingRevision = ReportConfigurationRevision::query()
            ->where('kind', 'branding')->latest('id')->firstOrFail();
        DB::table('report_configuration_revisions')->where('id', $initialTemplateRevision->id)
            ->update(['created_at' => '2026-06-01 09:00:00']);
        DB::table('report_configuration_revisions')->where('id', $initialBrandingRevision->id)
            ->update(['created_at' => '2026-06-01 09:00:00']);

        $template->update([
            'signatories' => [['role' => 'approved', 'label' => 'Approved by', 'name' => 'Officer after start', 'title' => 'Director']],
        ]);
        $branding->update(['hospital_name' => 'Hospital after period start']);
        $latestTemplateRevision = ReportConfigurationRevision::query()
            ->where('kind', 'template')->where('report_template_id', $template->id)->latest('id')->firstOrFail();
        $latestBrandingRevision = ReportConfigurationRevision::query()
            ->where('kind', 'branding')->latest('id')->firstOrFail();
        DB::table('report_configuration_revisions')->where('id', $latestTemplateRevision->id)
            ->update(['created_at' => '2026-06-05 09:00:00']);
        DB::table('report_configuration_revisions')->where('id', $latestBrandingRevision->id)
            ->update(['created_at' => '2026-06-05 09:00:00']);

        $renderer = $this->createMock(ReportService::class);
        $renderer->method('signatoriesFor')->willReturnCallback(
            fn ($report): array => $report->snapshot['template_signatories'] ?? [],
        );
        $renderer->method('buildPdf')->willReturn(['bytes' => "%PDF-1.4\nperiod\n%%EOF", 'meta' => []]);
        $this->app->instance(ReportService::class, $renderer);

        $report = app(PrepareSavedReport::class)->execute($actor, 'accomplishment_report', [
            'start' => '2026-06-03', 'end' => '2026-06-08',
        ], freeze: false);

        $this->assertSame('Hospital at period start', $report->snapshot['branding']['hospital_name']);
        $this->assertSame('Officer at start', $report->snapshot['signatories'][0]['name']);
        $this->assertSame($initialTemplateRevision->uuid, $report->snapshot['configuration_revisions']['templates']['accomplishment_report']);
        $this->assertSame($initialBrandingRevision->uuid, $report->snapshot['configuration_revisions']['branding']);
        $this->assertSame(hash('sha256', $initialTemplateRevision->uuid), $report->template_version);
    }
}
