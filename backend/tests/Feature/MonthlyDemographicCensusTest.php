<?php

namespace Tests\Feature;

use App\Models\DemographicCensusPeriod;
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

    public function test_catch_up_stores_every_completed_month_from_site_launch_including_zero_counts(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        Patient::factory()->create(['admission_date' => '2026-06-12']);

        $this->artisan('reports:demographic-census-catch-up')
            ->assertSuccessful();

        $this->assertSame(
            ['2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01'],
            DemographicCensusPeriod::query()->orderBy('period_start')->pluck('period_start')->map->toDateString()->all(),
        );
        $this->assertSame(0, DemographicCensusPeriod::query()->whereDate('period_start', '2026-05-01')->firstOrFail()->census['total']);
        $this->assertSame(1, DemographicCensusPeriod::query()->whereDate('period_start', '2026-06-01')->firstOrFail()->census['total']);
        $this->assertFalse(DemographicCensusPeriod::query()->whereDate('period_start', '2026-09-01')->exists());
        $this->assertFalse(DemographicCensusPeriod::query()->where('period_start', '<', '2026-05-01')->exists());
    }

    public function test_catch_up_is_idempotent_and_never_rewrites_a_frozen_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $patient = Patient::factory()->create(['admission_date' => '2026-06-12']);
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $june = DemographicCensusPeriod::query()->whereDate('period_start', '2026-06-01')->firstOrFail();
        $frozenAt = $june->frozen_at->copy();

        $patient->update(['admission_date' => '2026-07-12']);
        Carbon::setTestNow('2026-10-02 12:00:00');
        $this->artisan('reports:demographic-census-catch-up')
            ->assertSuccessful();

        $this->assertDatabaseCount('demographic_census_periods', 5);
        $this->assertSame(1, $june->fresh()->census['total']);
        $this->assertTrue($june->fresh()->frozen_at->equalTo($frozenAt));
    }

    public function test_browser_and_generator_use_stored_zero_month_snapshot(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports/demographic_census/instances')
            ->assertOk()
            ->assertJsonPath('data.instances.0.key', '2026-08')
            ->assertJsonPath('data.instances.3.key', '2026-05');

        $report = new Report([
            'type' => 'demographic_census',
            'parameters' => ['start' => '2026-05-01', 'end' => '2026-05-31'],
        ]);
        $data = app(DemographicCensusGenerator::class)->data($report);

        $this->assertSame(0, $data['census']['total']);
    }

    public function test_completed_month_cannot_be_edited_after_it_is_frozen(): void
    {
        Carbon::setTestNow('2026-06-02 12:00:00');
        $this->artisan('reports:demographic-census-catch-up')->assertSuccessful();
        $period = DemographicCensusPeriod::query()->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Completed demographic census periods are immutable.');
        $period->update(['source_count' => 99]);
    }
}
