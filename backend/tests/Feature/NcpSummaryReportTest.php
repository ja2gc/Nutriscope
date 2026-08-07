<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ScreeningDocument;
use App\Models\User;
use App\Services\Reports\Generators\NcpSummaryGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $patient = Patient::factory()->create([
            'name' => 'LEGACY NCP PATIENT',
            'first_name' => 'Ana Marie',
            'last_name' => 'Santos Cruz',
        ]);
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
            'risk_score' => 2,
        ]);
        Assessment::factory()->create(['ncp_record_id' => $ncp->id]);
        // DiagnosisFactory is stale (writes non-existent `signs`/`priority` columns) —
        // create directly with the real schema fields.
        Diagnosis::create([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NI',
            'problem' => 'Inadequate energy intake',
            'etiology' => 'poor appetite',
            'signs_symptoms' => 'unintentional weight loss',
            'pes_statement' => 'Inadequate energy intake related to poor appetite as evidenced by weight loss',
        ]);
        // Intervention/Monitoring factories are also stale vs the schema — create directly.
        Intervention::create([
            'ncp_record_id' => $ncp->id,
            'energy_kcal' => 1800, 'protein_g' => 70, 'carbs_g' => 250, 'fat_g' => 50, 'fluid_ml' => 2000,
            'education_notes' => 'Low-salt, high-protein diet education.',
        ]);
        Monitoring::create([
            'ncp_record_id' => $ncp->id,
            'weight' => 60,
            'bmi' => 22,
            'intake_notes' => 'Adequate oral intake.',
            'symptoms' => 'None noted.',
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
        $this->assertTrue(collect($data['instances'])->every(
            fn (array $instance): bool => str_contains($instance['label'], 'Ana Marie Santos Cruz')
                && ! str_contains($instance['label'], 'LEGACY NCP PATIENT')
        ));
    }

    public function test_clinical_instances_include_other_rnd_records_and_use_display_labels(): void
    {
        $owned = $this->makeRecord();
        $other = User::factory()->rnd()->create();
        $otherPatient = Patient::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Patient',
            'name' => 'OTHER-PATIENT-LEGACY-SENTINEL',
        ]);
        $shared = NcpRecord::factory()->create(['patient_id' => $otherPatient->id, 'rnd_user_id' => $other->id]);

        $response = $this->actingAs($this->rnd, 'sanctum')
            ->getJson('/api/rnd/reports/ncp_summary/instances')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [(string) $shared->id, (string) $owned->id],
            collect($response->json('data.instances'))->pluck('key')->all(),
        );
        $this->assertStringContainsString('Other Patient', $response->getContent());
        $this->assertStringNotContainsString('OTHER-PATIENT-LEGACY-SENTINEL', $response->getContent());
    }

    public function test_fss_cannot_browse_ncp_summary(): void
    {
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($fss)
            ->getJson('/api/fss/reports/ncp_summary/instances')
            ->assertForbidden();
    }

    public function test_prepare_saves_report_and_preview_streams_pdf(): void
    {
        $ncp = $this->makeRecord();
        $before = Report::count();

        $prepared = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports/ncp_summary/prepare', ['ncp_record_id' => $ncp->id])
            ->assertOk();
        $res = $this->get('/api/rnd/reports/'.$prepared->json('data.id').'/view');

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->streamedContent());
        $this->assertSame($before + 1, Report::count());
    }

    public function test_render_unknown_record_is_404(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports/ncp_summary/prepare', ['ncp_record_id' => 99999])
            ->assertNotFound();
    }

    public function test_data_includes_supporting_document_attachments(): void
    {
        $ncp = $this->makeRecord();

        ScreeningDocument::create([
            'patient_id' => $ncp->patient_id,
            'ncp_record_id' => $ncp->id,
            'assessment_id' => $ncp->assessment->id,
            'type' => 'referral',
            'file_path' => 'documents/ncp/ref.pdf',
            'original_name' => 'ref.pdf',
        ]);

        $report = new Report;
        $report->type = 'ncp_summary';
        $report->parameters = ['ncp_record_id' => $ncp->id];

        $data = app(NcpSummaryGenerator::class)->data($report);

        $this->assertCount(1, $data['attachments']);
        $this->assertSame('ref.pdf', $data['attachments']->first()->original_name);
    }

    public function test_data_uses_current_patient_display_name(): void
    {
        $ncp = $this->makeRecord();
        $report = new Report([
            'type' => 'ncp_summary',
            'parameters' => ['ncp_record_id' => $ncp->id],
        ]);

        $data = app(NcpSummaryGenerator::class)->data($report);

        $this->assertSame('Ana Marie Santos Cruz', $data['patient']['name']);
        $this->assertNotSame('LEGACY NCP PATIENT', $data['patient']['name']);
    }

    public function test_report_query_count_does_not_grow_with_more_clinical_rows(): void
    {
        $ncp = $this->makeRecord();
        $report = new Report([
            'type' => 'ncp_summary',
            'parameters' => ['ncp_record_id' => $ncp->id],
        ]);

        DB::enableQueryLog();
        app(NcpSummaryGenerator::class)->data($report);
        $baseQueries = count(DB::getQueryLog());

        Diagnosis::create([
            'ncp_record_id' => $ncp->id,
            'domain' => 'NC',
            'problem' => 'Additional diagnosis',
            'etiology' => 'additional cause',
            'signs_symptoms' => 'additional evidence',
            'pes_statement' => 'Additional diagnosis related to cause as evidenced by evidence',
        ]);
        Monitoring::create([
            'ncp_record_id' => $ncp->id,
            'weight' => 61,
            'bmi' => 22.5,
            'clinical_summary' => 'Additional monitoring.',
        ]);
        DB::flushQueryLog();
        app(NcpSummaryGenerator::class)->data($report);

        $this->assertSame($baseQueries, count(DB::getQueryLog()));
    }

    public function test_data_flags_completion_stage_and_links_meal_plan(): void
    {
        $ncp = $this->makeRecord();
        // makeRecord's intervention has no goal_type → still incomplete initial ADI.
        $ncp->intervention->update(['goal_type' => 'renal_diet']);
        $plan = MealPlan::create([
            'intervention_id' => $ncp->intervention->id,
            'patient_id' => $ncp->patient_id,
            'week_start_date' => '2026-06-15',
            'generation_type' => 'auto',
            'status' => 'draft',
        ]);

        $report = new Report;
        $report->type = 'ncp_summary';
        $report->parameters = ['ncp_record_id' => $ncp->id];
        $data = app(NcpSummaryGenerator::class)->data($report->fresh() ?? $report);

        $this->assertTrue($data['is_complete']);
        $this->assertSame('Full ADIME', $data['completion_stage']); // has monitoring too
        $this->assertSame($plan->id, $data['meal_plan']['id']);
    }

    public function test_data_marks_incomplete_when_prescription_missing(): void
    {
        $ncp = $this->makeRecord();
        $ncp->intervention->update(['goal_type' => null, 'energy_kcal' => null]);

        $report = new Report;
        $report->type = 'ncp_summary';
        $report->parameters = ['ncp_record_id' => $ncp->id];
        $data = app(NcpSummaryGenerator::class)->data($report);

        $this->assertFalse($data['is_complete']);
        $this->assertNotEmpty($data['incomplete_items']);
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
