<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DemographicCensusPeriod;
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

    public function test_catch_up_starts_at_earliest_cycle_and_counts_each_cycle_once(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-01-12']);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'completed',
            'created_at' => '2026-04-09 08:00:00',
            'updated_at' => '2026-04-30 08:00:00',
        ]);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
            'created_at' => '2026-06-12 08:00:00',
            'updated_at' => '2026-06-12 08:00:00',
        ]);

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

    public function test_browser_and_generator_use_stored_zero_month_snapshot(): void
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
            ->getJson('/api/admin/reports/demographic_census/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.key', '2026-09')
            ->assertJsonPath('data.instances.4.key', '2026-05');

        $report = new Report([
            'type' => 'demographic_census',
            'parameters' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
        ]);
        $data = app(DemographicCensusGenerator::class)->data($report);

        $this->assertSame(0, $data['census']['total']);
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
            'nutritional_status' => 'Normal',
        ]);
        $june = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'risk_score' => 5,
            'created_at' => '2026-06-10 08:00:00',
            'updated_at' => '2026-06-10 08:00:00',
        ]);
        Assessment::factory()->create([
            'ncp_record_id' => $june->id,
            'nutritional_status' => 'Severe Malnutrition',
        ]);

        $census = app(DemographicCensusGenerator::class)->currentCensus(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
        );

        $this->assertSame(1, $census['total']);
        $this->assertSame(['Normal' => 1], $census['by_status']);
        $this->assertSame(['Low' => 1], $census['by_risk']);
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
            ->getJson('/api/admin/reports/demographic_census/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.key', '2026-09');

        $this->assertFalse(DemographicCensusPeriod::query()->whereDate('period_start', '2026-09-01')->exists());
    }

    public function test_all_existing_cycle_statuses_are_counted_and_deleted_cycle_is_not(): void
    {
        $patient = Patient::factory()->create(['admission_date' => '2026-05-01']);
        foreach (['draft', 'active', 'completed', 'discontinued', 'discharged'] as $day => $status) {
            NcpRecord::factory()->create([
                'patient_id' => $patient->id,
                'status' => $status,
                'created_at' => Carbon::parse('2026-05-10 08:00:00')->addDays($day),
                'updated_at' => Carbon::parse('2026-05-10 08:00:00')->addDays($day),
            ]);
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

    public function test_catch_up_rebuilds_legacy_patient_based_snapshots_once(): void
    {
        Carbon::setTestNow('2026-07-02 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-05-01']);
        NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'created_at' => '2026-05-10 08:00:00',
            'updated_at' => '2026-05-10 08:00:00',
        ]);
        DemographicCensusPeriod::query()->create([
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'census' => DemographicCensusGenerator::aggregate([]),
            'source_count' => 0,
            'basis_version' => 1,
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
}
