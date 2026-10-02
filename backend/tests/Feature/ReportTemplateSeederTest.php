<?php

namespace Tests\Feature;

use App\Models\ReportTemplate;
use App\Models\User;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_uses_names_printed_on_the_approved_hospital_forms(): void
    {
        $this->seed(ReportTemplateSeeder::class);
        $this->seed(ReportTemplateSeeder::class);

        $this->assertSame(10, ReportTemplate::count());

        $inspection = collect(ReportTemplate::where('type', 'inspection_report')->firstOrFail()->signatories);
        $statement = collect(ReportTemplate::where('type', 'marketing_statement')->firstOrFail()->signatories);
        $summary = collect(ReportTemplate::where('type', 'marketing_summary')->firstOrFail()->signatories);
        $accomplishment = collect(ReportTemplate::where('type', 'accomplishment_report')->firstOrFail()->signatories);

        $this->assertSame('FRANCIS V. MASLOG', $inspection->firstWhere('role', 'inspected_by')['name']);
        $this->assertSame('MA. CONCEPCION D. LUGTU, MPA', $inspection->firstWhere('role', 'verified_by')['name']);
        $this->assertSame('ELAINE JUSTINA L. ABRIOL', $inspection->firstWhere('role', 'conforme')['name']);
        $this->assertSame('ETHEL REYES, MD, CFP', $inspection->firstWhere('role', 'approved_by')['name']);
        $this->assertSame('ELAINE JUSTINA L. ABRIOL', $statement->firstWhere('role', 'buyer')['name']);
        $this->assertSame('ETHEL REYES, MD, CFP', $statement->firstWhere('role', 'examined_by')['name']);
        $this->assertSame('ELAINE JUSTINA L. ABRIOL', $summary->firstWhere('role', 'certified_correct')['name']);
        $this->assertSame('ELAINE JUSTINA L. ABRIOL', $accomplishment->firstWhere('role', 'noted_by')['name']);
        $this->assertSame('MA. CONCEPCION D. LUGTU, MPA', $accomplishment->firstWhere('role', 'approved_by')['name']);
        $this->assertSame(
            'Monthly ADIME cycle census by age, sex, and risk level.',
            ReportTemplate::where('type', 'demographic_census')->firstOrFail()->description,
        );
    }

    public function test_clinical_auto_filled_signatories_cannot_be_changed_through_template_api(): void
    {
        $this->seed(ReportTemplateSeeder::class);
        $rnd = User::factory()->rnd()->create();
        $template = ReportTemplate::where('type', 'ncp_summary')->firstOrFail();
        $original = $template->signatories;

        $this->actingAs($rnd, 'sanctum')->patchJson("/api/rnd/report-templates/{$template->uuid}", [
            'signatories' => [
                ['role' => 'prepared_by', 'label' => 'Prepared by:', 'name' => 'Wrong RND', 'title' => 'Wrong title'],
                ['role' => 'conforme', 'label' => 'Conforme:', 'name' => 'Wrong Physician', 'title' => 'Wrong title'],
            ],
        ])->assertUnprocessable();

        $this->assertSame($original, $template->fresh()->signatories);
    }

    public function test_admin_can_edit_report_template_signatories(): void
    {
        $this->seed(ReportTemplateSeeder::class);
        $admin = User::factory()->create(['role' => 'Admin']);
        $template = ReportTemplate::where('type', 'inspection_report')->firstOrFail();
        $signatories = $template->signatories;
        $signatories[0]['name'] = 'Fictional Inspector';

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/report-templates')
            ->assertOk();
        $this->patchJson("/api/admin/report-templates/{$template->uuid}", [
            'signatories' => $signatories,
        ])->assertOk();

        $this->assertSame('Fictional Inspector', $template->fresh()->signatories[0]['name']);
    }

    public function test_census_pdf_template_is_not_offered_or_editable(): void
    {
        $this->seed(ReportTemplateSeeder::class);
        $admin = User::factory()->create(['role' => 'Admin']);
        $template = ReportTemplate::where('type', 'demographic_census')->firstOrFail();

        $data = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/report-templates')->assertOk()->json('data');
        $this->assertNotContains('demographic_census', array_column($data, 'type'));
        $this->patchJson("/api/admin/report-templates/{$template->uuid}", ['name' => 'Legacy PDF'])
            ->assertGone();
    }
}
