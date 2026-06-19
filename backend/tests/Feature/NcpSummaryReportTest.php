<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\NcpSummaryGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** NCP Summary report — per-record Nutrition Care Plan, RND-only. */
class NcpSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
    }

    private function makeRecord(): NcpRecord
    {
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id,
            'risk_score'  => 2,
        ]);
        Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        // DiagnosisFactory is stale (writes non-existent `signs`/`priority` columns) —
        // create directly with the real schema fields.
        Diagnosis::create([
            'ncp_record_id'  => $ncp->id,
            'domain'         => 'NI',
            'problem'        => 'Inadequate energy intake',
            'etiology'       => 'poor appetite',
            'signs_symptoms' => 'unintentional weight loss',
            'pes_statement'  => 'Inadequate energy intake related to poor appetite as evidenced by weight loss',
        ]);
        // Intervention/Monitoring factories are also stale vs the schema — create directly.
        Intervention::create([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800, 'protein_g' => 70, 'carbs_g' => 250, 'fat_g' => 50, 'fluid_ml' => 2000,
            'education_notes' => 'Low-salt, high-protein diet education.',
        ]);
        Monitoring::create([
            'ncp_record_id'    => $ncp->id,
            'weight'           => 60,
            'bmi'              => 22,
            'intake_notes'     => 'Adequate oral intake.',
            'symptoms'         => 'None noted.',
            'clinical_summary' => 'Tolerating diet, weight stable.',
        ]);

        return $ncp;
    }

    public function test_lists_one_instance_per_ncp_record(): void
    {
        $this->makeRecord();
        $this->makeRecord();

        $data = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/ncp_summary/instances')
            ->assertOk()
            ->assertJsonPath('data.axis', 'entity')
            ->json('data');

        $this->assertCount(2, $data['instances']);
        $this->assertArrayHasKey('ncp_record_id', $data['instances'][0]['params']);
    }

    public function test_fss_cannot_browse_ncp_summary(): void
    {
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($fss)
            ->getJson('/api/fss/reports/ncp_summary/instances')
            ->assertForbidden();
    }

    public function test_render_streams_pdf(): void
    {
        $ncp = $this->makeRecord();
        $before = Report::count();

        $res = $this->actingAs($this->rnd)
            ->get("/api/rnd/reports/ncp_summary/render?ncp_record_id={$ncp->id}");

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame($before, Report::count(), 'render must not persist');
    }

    public function test_render_unknown_record_is_404(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/ncp_summary/render?ncp_record_id=99999')
            ->assertNotFound();
    }

    public function test_data_includes_supporting_document_attachments(): void
    {
        $ncp = $this->makeRecord();

        \App\Models\ScreeningDocument::create([
            'patient_id'    => $ncp->patient_id,
            'assessment_id' => $ncp->assessment->id,
            'type'          => 'referral',
            'file_path'     => 'documents/ncp/ref.pdf',
            'original_name' => 'ref.pdf',
        ]);

        $report = new Report();
        $report->type = 'ncp_summary';
        $report->parameters = ['ncp_record_id' => $ncp->id];

        $data = (new NcpSummaryGenerator())->data($report);

        $this->assertCount(1, $data['attachments']);
        $this->assertSame('ref.pdf', $data['attachments']->first()->original_name);
    }

    public function test_age_and_risk_helpers(): void
    {
        $dob = Carbon::parse('2000-01-01');
        $this->assertSame(20, NcpSummaryGenerator::ageFrom($dob, Carbon::parse('2020-06-01')));
        $this->assertNull(NcpSummaryGenerator::ageFrom(null));

        $this->assertSame('Low Risk', NcpSummaryGenerator::riskBand(1.0));
        $this->assertSame('Moderate Risk', NcpSummaryGenerator::riskBand(3.0));
        $this->assertSame('High Risk', NcpSummaryGenerator::riskBand(4.0));
        $this->assertSame('—', NcpSummaryGenerator::riskBand(null));
    }
}
