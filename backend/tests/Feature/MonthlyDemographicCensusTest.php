<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DemographicCensusPeriod;
use App\Models\NcpAppointment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\Generators\DemographicCensusGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonthlyDemographicCensusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_cycle_enters_census_only_after_assessment_work_and_visit_finish(): void
    {
        $patient = Patient::factory()->create(['dob' => '1990-01-01']);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-10-02 08:00:00',
        ]);
        $generator = app(DemographicCensusGenerator::class);
        $start = Carbon::parse('2026-10-01');
        $end = Carbon::parse('2026-10-31');

        $this->assertSame(0, $generator->currentCensus($start, $end)['total']);

        Assessment::factory()->create(['ncp_record_id' => $cycle->id]);
        $visit = NcpAppointment::factory()->create([
            'patient_id' => $patient->id,
            'ncp_record_id' => $cycle->id,
            'status' => 'in_progress',
            'worked_on' => ['assessment'],
        ]);
        $this->assertSame(0, $generator->currentCensus($start, $end)['total']);

        $visit->update(['status' => 'completed', 'worked_on' => []]);
        $this->assertSame(0, $generator->currentCensus($start, $end)['total']);

        $visit->update(['worked_on' => ['assessment']]);
        $this->assertSame(1, $generator->currentCensus($start, $end)['total']);
    }

    public function test_assessed_legacy_cycle_without_visit_remains_in_census(): void
    {
        $patient = Patient::factory()->create(['dob' => '1990-01-01']);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-08-12 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $cycle->id]);

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31'),
        );

        $this->assertSame(1, $census['total']);
    }

    public function test_catch_up_starts_at_earliest_cycle_and_counts_each_cycle_once(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-01-12']);
        $april = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'completed',
            'created_at' => '2026-04-09 08:00:00',
            'updated_at' => '2026-04-30 08:00:00',
        ]);
        $june = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
            'created_at' => '2026-06-12 08:00:00',
            'updated_at' => '2026-06-12 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $april->id]);
        Assessment::factory()->create(['ncp_record_id' => $june->id]);

        $this->artisan('reports:demographic-census-catch-up')
            ->assertSuccessful();

        $this->assertSame(
            ['2026-04-01', '2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01'],
            DemographicCensusPeriod::query()->orderBy('period_start')->pluck('period_start')->map->toDateString()->all(),
        );
        $this->assertSame(1, DemographicCensusPeriod::query()->whereDate('period_start', '2026-04-01')->firstOrFail()->census['total']);
        $this->assertSame(1, DemographicCensusPeriod::query()->whereDate('period_start', '2026-06-01')->firstOrFail()->census['total']);
        $this->assertSame(0, DemographicCensusPeriod::query()->whereDate('period_start', '2026-07-01')->firstOrFail()->census['total']);
        $this->assertFalse(DemographicCensusPeriod::query()->whereDate('period_start', '2026-09-01')->exists());
        $this->assertFalse(DemographicCensusPeriod::query()->where('period_start', '<', '2026-04-01')->exists());
    }

    public function test_catch_up_stores_nothing_before_the_first_patient_exists(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');

        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();

        $this->assertDatabaseCount('demographic_census_periods', 0);
    }

    public function test_catch_up_is_idempotent_and_never_rewrites_a_current_definition_frozen_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-06-12']);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-06-12 08:00:00',
            'updated_at' => '2026-06-12 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $cycle->id]);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $june = DemographicCensusPeriod::query()->whereDate('period_start', '2026-06-01')->firstOrFail();
        $frozenAt = $june->frozen_at->copy();

        $patient->update(['admission_date' => '2026-07-12']);
        $cycle->update(['risk_score' => 5]);
        Carbon::setTestNow('2026-10-02 12:00:00');
        $this->artisan('reports:demographic-census-catch-up')
            ->assertSuccessful();

        $this->assertDatabaseCount('demographic_census_periods', 4);
        $this->assertSame(1, $june->fresh()->census['total']);
        $this->assertTrue($june->fresh()->frozen_at->equalTo($frozenAt));
    }

    public function test_screen_uses_stored_zero_month_snapshot(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-05-12']);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-12 08:00:00',
            'updated_at' => '2026-05-12 08:00:00',
        ]);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/demographic_census/summary?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.by_risk.Low', 0)
            ->assertJsonPath('data.by_risk.Moderate', 0)
            ->assertJsonPath('data.by_risk.High', 0)
            ->assertJsonPath('data.status', 'frozen')
            ->assertJsonPath('data.available_months.0.month', 5);

        $this->assertSame(0, DemographicCensusPeriod::query()->whereDate('period_start', '2026-06-01')->firstOrFail()->census['total']);
    }

    public function test_completed_month_cannot_be_edited_after_it_is_frozen(): void
    {
        Carbon::setTestNow('2026-06-02 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-05-12']);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-12 08:00:00',
            'updated_at' => '2026-05-12 08:00:00',
        ]);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $period = DemographicCensusPeriod::query()->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Completed demographic census periods are immutable.');
        $period->update(['source_count' => 99]);
    }

    public function test_generator_uses_values_from_each_cycle_instead_of_the_patients_latest_cycle(): void
    {
        $patient = Patient::factory()->create([
            'dob' => '1990-01-01',
            'sex' => 'Female',
            'admission_date' => '2025-12-01',
            'ward' => 'Ward A',
            'medical_diagnosis' => 'Diagnosis A',
        ]);
        $may = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 1,
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $may->id,
            'weight' => 56.32,
            'height' => 160,
            'bmi' => 22,
            'ibw_percentage' => 100,
            'nutritional_status' => 'Normal',
            'primary_diagnosis_category' => 'Diabetes',
        ]);
        $june = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 5,
            'created_at' => '2026-06-10 08:00:00',
            'updated_at' => '2026-06-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $june->id,
            'weight' => 61.44,
            'height' => 160,
            'bmi' => 24,
            'ibw_percentage' => 110,
            'nutritional_status' => 'Severe Malnutrition',
            'primary_diagnosis_category' => 'Malnutrition',
        ]);

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(1, $census['total']);
        $this->assertSame([
            'Severe Malnutrition' => 0,
            'Moderate Malnutrition' => 0,
            'Mild Malnutrition / Underweight' => 0,
            'Normal' => 1,
            'Overweight' => 0,
            'Obese Class I' => 0,
            'Obese Class II' => 0,
            'Obese Class II (Severe)' => 0,
            'Unspecified' => 0,
        ], $census['by_status']);
        $this->assertSame(['Low' => 1, 'Moderate' => 0, 'High' => 0], $census['by_risk']);
        $this->assertSame(['Diabetes' => 1], $census['by_primary_diagnosis_category']);
        $this->assertArrayNotHasKey('Diagnosis A', $census['by_primary_diagnosis_category']);
    }

    public function test_generator_groups_legacy_null_category_as_unclassified_without_using_physician_text(): void
    {
        $patient = Patient::factory()->create([
            'medical_diagnosis' => 'PRIVATE FREE-TEXT DIAGNOSIS',
        ]);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $cycle->id,
            'primary_diagnosis_category' => null,
        ]);

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(['Unclassified' => 1], $census['by_primary_diagnosis_category']);
        $this->assertStringNotContainsString('PRIVATE FREE-TEXT DIAGNOSIS', json_encode($census, JSON_THROW_ON_ERROR));
    }

    public function test_census_uses_adult_anthropometric_status_and_keeps_risk_separate(): void
    {
        $adult = Patient::factory()->create(['dob' => '1990-01-01']);
        $adultCycle = NcpRecord::factory()->create([
            'patient_id' => $adult->id,
            'risk_score' => 5,
            'created_at' => '2026-05-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $adultCycle->id,
            'weight' => 61.44,
            'height' => 160,
            'bmi' => 24,
            'ibw_percentage' => 110,
            'nutritional_status' => 'Severe Malnutrition',
        ]);

        $child = Patient::factory()->create(['dob' => '2016-01-01']);
        $childCycle = NcpRecord::factory()->create([
            'patient_id' => $child->id,
            'risk_score' => 1,
            'created_at' => '2026-05-11 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $childCycle->id,
            'weight' => 61.44,
            'height' => 160,
            'bmi' => 24,
            'ibw_percentage' => 110,
        ]);

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(2, $census['total']);
        $this->assertSame([
            'Severe Malnutrition' => 0,
            'Moderate Malnutrition' => 0,
            'Mild Malnutrition / Underweight' => 0,
            'Normal' => 0,
            'Overweight' => 1,
            'Obese Class I' => 0,
            'Obese Class II' => 0,
            'Obese Class II (Severe)' => 0,
            'Unspecified' => 1,
        ], $census['by_status']);
        $this->assertSame(['Low' => 1, 'Moderate' => 0, 'High' => 1], $census['by_risk']);
    }

    public function test_selected_census_view_downloads_directly_without_creating_a_report_identity(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $patient = Patient::factory()->create(['dob' => '1990-01-01', 'sex' => 'Female']);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 1,
            'created_at' => '2026-06-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $cycle->id,
            'nutritional_status' => 'Normal',
            'primary_diagnosis_category' => 'Diabetes',
        ]);
        $actor = User::factory()->rnd()->create();

        $response = $this->actingAs($actor, 'sanctum')
            ->get('/api/rnd/reports/demographic_census/export?year=2026&month=6');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_census_pdf_orders_status_first_and_separates_unclassified_cycles(): void
    {
        $summary = DemographicCensusGenerator::aggregate([
            ['age' => 35, 'sex' => 'F', 'nutritional_status' => 'Unspecified', 'risk_level' => 'Low'],
        ]);
        $summary['label'] = 'October 2026';
        $summary['status'] = 'live';
        $summary['age_groups'] = DemographicCensusGenerator::AGE_GROUPS;
        $summary['unknown_sex'] = 0;
        $summary['by_nutritional_status'] = $summary['by_status'];
        $summary['by_risk'] = ['Low' => 1, 'High' => 0, 'Moderate' => 0];

        $html = view('reports.demographic-census-download', [
            'summary' => $summary,
            'branding' => (object) ['hospital_name' => 'Test Hospital', 'address' => '', 'accreditation' => '', 'service_name' => '', 'logo_left_path' => null, 'logo_right_path' => null],
            'report' => new Report(['title' => 'Demographic Census']),
            'paper_orientation' => 'landscape',
        ])->render();

        $this->assertLessThan(strpos($html, 'By risk level'), strpos($html, 'By nutritional status'));
        $this->assertStringNotContainsString('<td>Unspecified</td>', $html);
        $this->assertStringContainsString('Not classified: 1', $html);
        $this->assertStringNotContainsString('Includes current data', $html);
        $this->assertLessThan(strpos($html, '>High</td>'), strpos($html, '>Moderate</td>'));
    }

    public function test_current_month_is_browsable_live_but_not_stored_as_frozen(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-04-09']);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-04-09 08:00:00',
            'updated_at' => '2026-04-09 08:00:00',
        ]);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/demographic_census/summary?year=2026&month=9')
            ->assertOk()
            ->assertJsonPath('data.status', 'live');

        $this->assertFalse(DemographicCensusPeriod::query()->whereDate('period_start', '2026-09-01')->exists());
    }

    public function test_all_existing_cycle_statuses_are_counted_and_deleted_cycle_is_not(): void
    {
        $patient = Patient::factory()->create(['admission_date' => '2026-05-01']);
        foreach (['draft', 'active', 'completed', 'discontinued', 'discharged'] as $day => $status) {
            $cycle = NcpRecord::factory()->create([
                'patient_id' => $patient->id,
                'status' => $status,
                'created_at' => Carbon::parse('2026-05-10 08:00:00')->addDays($day),
                'updated_at' => Carbon::parse('2026-05-10 08:00:00')->addDays($day),
            ]);
            Assessment::factory()->create(['ncp_record_id' => $cycle->id]);
        }
        $deleted = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-20 08:00:00',
            'updated_at' => '2026-05-20 08:00:00',
        ]);
        $deleted->delete();

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(5, $census['total']);
    }

    public function test_catch_up_rebuilds_previous_basis_snapshots_once(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-05-01']);
        $cycle = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $cycle->id]);
        DemographicCensusPeriod::query()->create([
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'census' => DemographicCensusGenerator::aggregate([]),
            'source_count' => 0,
            'basis_version' => 4,
            'frozen_at' => '2026-06-01 00:05:00',
        ]);

        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $may = DemographicCensusPeriod::query()->whereDate('period_start', '2026-05-01')->firstOrFail();

        $this->assertSame(1, $may->source_count);
        $this->assertSame(1, $may->census['total']);
        $this->assertSame(DemographicCensusGenerator::BASIS_VERSION, $may->basis_version);

        $frozenAt = $may->frozen_at->copy();
        Carbon::setTestNow('2026-07-03 12:00:00');
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $this->assertTrue($may->fresh()->frozen_at->equalTo($frozenAt));
    }

    public function test_census_screen_counts_cycle_start_month_and_sums_frozen_and_live_months_for_the_year(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $patient = Patient::factory()->create(['dob' => '1990-01-01', 'sex' => 'Female', 'medical_diagnosis' => 'PRIVATE DIAGNOSIS']);
        $january = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 1,
            'created_at' => '2026-01-10 08:00:00',
            'updated_at' => '2026-06-01 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $january->id, 'primary_diagnosis_category' => 'Diabetes']);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $june = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 5,
            'created_at' => '2026-06-10 08:00:00',
        ]);
        Assessment::factory()->create(['ncp_record_id' => $june->id, 'primary_diagnosis_category' => 'Renal']);
        $january->update(['risk_score' => 5]);
        $actor = User::factory()->rnd()->create();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/rnd/reports/demographic_census/summary?year=2026&month=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.by_risk.Low', 1)
            ->assertJsonPath('data.by_primary_diagnosis_category.Diabetes', 1)
            ->assertJsonPath('data.status', 'frozen');
        $this->getJson('/api/rnd/reports/demographic_census/summary?year=2026&month=6')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.by_risk.High', 1)
            ->assertJsonPath('data.by_primary_diagnosis_category.Renal', 1)
            ->assertJsonPath('data.status', 'live');
        $annual = $this->getJson('/api/rnd/reports/demographic_census/summary?year=2026')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.by_risk.Low', 1)
            ->assertJsonPath('data.by_risk.High', 1)
            ->assertJsonPath('data.by_primary_diagnosis_category.Diabetes', 1)
            ->assertJsonPath('data.by_primary_diagnosis_category.Renal', 1)
            ->assertJsonPath('data.age_sex.30-39.F', 2)
            ->assertJsonPath('data.status', 'live');
        $this->assertStringNotContainsString('PRIVATE DIAGNOSIS', $annual->getContent());
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_census_screen_is_read_only_for_rnd_and_admin_and_rejects_other_roles_and_invalid_periods(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $cycle = NcpRecord::factory()->create(['created_at' => '2026-05-10 08:00:00']);
        Assessment::factory()->create(['ncp_record_id' => $cycle->id]);
        $admin = User::factory()->create(['role' => 'Admin']);
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/demographic_census/summary?year=2026&month=5')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
        $this->getJson('/api/admin/reports/demographic_census/summary?year=2027')->assertNotFound();
        $this->getJson('/api/admin/reports/demographic_census/summary?year=2026&month=13')->assertUnprocessable();
        $this->actingAs($fss, 'sanctum')
            ->getJson('/api/fss/reports/demographic_census/summary?year=2026')->assertForbidden();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_census_prepared_pdf_routes_and_legacy_report_ids_are_no_longer_available(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');
        $actor = User::factory()->rnd()->create();
        NcpRecord::factory()->create(['created_at' => '2026-06-10 08:00:00']);
        $legacy = Report::factory()->create([
            'user_id' => $actor->id,
            'type' => 'demographic_census',
            'status' => 'archived',
        ]);
        $base = '/api/rnd/reports/demographic_census';
        $period = '?start=2026-06-01&end=2026-06-30';

        $this->actingAs($actor, 'sanctum')->postJson($base.'/prepare'.$period)->assertGone();
        $this->postJson($base.'/archive'.$period)->assertGone();
        $this->getJson($base.'/render'.$period)->assertGone();
        $this->getJson($base.'/instances')->assertGone();
        $this->getJson("/api/rnd/reports/{$legacy->uuid}")->assertGone();
        $this->getJson("/api/rnd/reports/{$legacy->uuid}/view")->assertGone();
        $this->getJson("/api/rnd/reports/{$legacy->uuid}/download")->assertGone();
        $this->getJson('/api/rnd/reports?type=demographic_census')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('reports', 1);
    }
}
