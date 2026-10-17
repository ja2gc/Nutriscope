<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\User;
use App\Services\FSS\AccomplishmentReportArchiveService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccomplishmentPeriodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_browses_staff_and_only_their_approved_reports_without_review_access(): void
    {
        $admin = User::factory()->admin()->create();
        $ada = User::factory()->fss()->create(['first_name' => 'Ada', 'last_name' => 'Santos']);
        $bea = User::factory()->fss()->create();
        $approved = Report::factory()->create(['user_id' => $ada->id, 'type' => 'accomplishment_report', 'review_state' => 'approved']);
        Report::factory()->create(['user_id' => $ada->id, 'type' => 'accomplishment_report', 'review_state' => 'pending']);
        Report::factory()->create(['user_id' => $bea->id, 'type' => 'accomplishment_report', 'review_state' => 'approved']);
        $this->actingAs($admin)->getJson('/api/admin/accomplishment-periods/staff?search=Ada%20Santos')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ada->uuid);
        $this->getJson("/api/admin/reports?type=accomplishment_report&fss_user_id={$ada->uuid}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved->uuid);
        $this->getJson('/api/rnd/accomplishment-periods/to-review')->assertForbidden();
        $this->actingAs($bea)->getJson('/api/admin/accomplishment-periods/staff')->assertForbidden();
    }

    public function test_rnd_selects_fss_by_name_before_filtering_the_review_queue_by_user_and_month(): void
    {
        $rnd = User::factory()->rnd()->create();
        $ada = User::factory()->fss()->create(['first_name' => 'Ada', 'last_name' => 'Santos']);
        $bea = User::factory()->fss()->create(['first_name' => 'Bea', 'last_name' => 'Reyes']);
        Report::factory()->create(['user_id' => $ada->id, 'type' => 'accomplishment_report', 'review_state' => 'pending', 'report_covered_from' => '2026-06-01', 'report_covered_until' => '2026-06-15', 'submitted_at' => now()]);
        Report::factory()->create(['user_id' => $ada->id, 'type' => 'accomplishment_report', 'review_state' => 'pending', 'report_covered_from' => '2026-07-01', 'report_covered_until' => '2026-07-15', 'submitted_at' => now()]);
        Report::factory()->create(['user_id' => $bea->id, 'type' => 'accomplishment_report', 'review_state' => 'pending', 'report_covered_from' => '2026-06-01', 'report_covered_until' => '2026-06-15', 'submitted_at' => now()]);

        $this->actingAs($rnd)->getJson('/api/rnd/accomplishment-periods/staff?search=Ada')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ada->uuid);
        $this->getJson('/api/rnd/accomplishment-periods/staff?search=Ada%20Santos')
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/rnd/accomplishment-periods/to-review?fss_user_id={$ada->uuid}&covered_month=2026-06")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.fss_user.id', $ada->uuid);
        $this->actingAs($bea)->getJson('/api/rnd/accomplishment-periods/staff')->assertForbidden();
    }

    public function test_progress_counts_unique_fss_dates_in_a_leap_year_without_a_menu_cycle(): void
    {
        $fss = User::factory()->fss()->create();
        $other = User::factory()->fss()->create();
        DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => '2028-02-16']);
        DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => '2028-02-29', 'off_duty' => true, 'population' => 0]);
        DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => '2028-02-29', 'off_duty' => true, 'population' => 0]);
        DietListCount::factory()->create(['fss_user_id' => $other->id, 'service_date' => '2028-02-20']);
        Carbon::setTestNow(Carbon::parse('2028-03-01 12:00:00', 'Asia/Manila'));
        try {
            $periods = $this->actingAs($fss)
                ->getJson('/api/fss/accomplishment-periods?year=2028')
                ->assertOk()->json('data');
        } finally {
            Carbon::setTestNow();
        }

        $period = collect($periods)->firstWhere('start', '2028-02-16');
        $this->assertSame('2028-02-29', $period['end']);
        $this->assertSame(14, $period['expected_days']);
        $this->assertSame(2, $period['saved_days']);
        $this->assertSame('draft', $period['status']);
        $this->assertCount(14, $period['days']);
    }

    public function test_progress_includes_empty_periods_between_the_first_log_and_today(): void
    {
        $fss = User::factory()->fss()->create();
        DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => '2026-06-10']);
        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'Asia/Manila'));
        try {
            $periods = $this->actingAs($fss)->getJson('/api/fss/accomplishment-periods?year=2026')
                ->assertOk()->json('data');
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(
            ['2026-08-01', '2026-07-16', '2026-07-01', '2026-06-16', '2026-06-01'],
            array_column($periods, 'start'),
        );
        $this->assertSame(0, collect($periods)->firstWhere('start', '2026-07-01')['saved_days']);
    }

    public function test_a_period_with_a_missing_date_cannot_be_submitted(): void
    {
        $fss = User::factory()->fss()->create();
        DietListCount::factory()->create(['fss_user_id' => $fss->id, 'service_date' => '2026-06-01']);

        $this->actingAs($fss)
            ->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertUnprocessable();
    }

    public function test_complete_current_period_can_be_submitted_as_soon_as_every_daily_log_is_saved(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $fss = User::factory()->fss()->create();
        $this->fillPeriod($fss, '2026-06-01', '2026-06-15');
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'Asia/Manila'));
        try {
            $this->actingAs($fss)->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
                ->assertOk()->assertJsonPath('data.status', 'pending')
                ->assertJsonPath('data.saved_days', 15)->assertJsonPath('data.expected_days', 15);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_a_new_log_is_not_disclosed_by_an_older_legacy_report_for_the_same_period(): void
    {
        $fss = User::factory()->fss()->create();
        $rnd = User::factory()->rnd()->create();
        Report::factory()->create([
            'user_id' => $fss->id,
            'type' => 'accomplishment_report',
            'status' => 'completed',
            'review_state' => null,
            'report_covered_from' => '2026-06-01',
            'report_covered_until' => '2026-06-15',
            'created_at' => '2026-06-16 12:00:00',
            'generated_at' => '2026-06-16 12:00:00',
        ]);
        DietListCount::factory()->create([
            'fss_user_id' => $fss->id,
            'service_date' => '2026-06-10',
            'created_at' => '2026-10-13 12:00:00',
        ]);

        $this->actingAs($rnd)->getJson('/api/rnd/reports/accomplishment_report/instances?covered_month=2026-06')
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_complete_period_submission_appears_only_in_rnd_review_and_locks_daily_edits(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $fss = User::factory()->fss()->create();
        $rnd = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        foreach (CarbonPeriod::create('2026-06-01', '2026-06-15') as $date) {
            DietListCount::factory()->create([
                'fss_user_id' => $fss->id,
                'service_date' => $date->toDateString(),
                'ward' => 'Accomplishment report',
                'population' => 0,
            ]);
        }

        $this->actingAs($rnd)->getJson('/api/rnd/reports/accomplishment_report/instances')
            ->assertOk()->assertJsonCount(0, 'data.instances');
        $this->getJson('/api/fss/diet-list-counts?from=2026-06-01&to=2026-06-15')
            ->assertOk()->assertJsonCount(0, 'data');

        $submitted = $this->actingAs($fss)
            ->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $reportId = $submitted->json('data.report_id');
        $report = Report::where('uuid', $reportId)->firstOrFail();
        $this->assertSame('pending', $report->review_state);
        $this->assertSame('completed', $report->status);
        $this->assertNotNull($report->official_file_stored_object_id);

        $this->actingAs($rnd)->getJson('/api/rnd/accomplishment-periods/to-review')
            ->assertOk()->assertJsonPath('data.0.report_id', $reportId)
            ->assertJsonPath('data.0.fss_user.id', $fss->uuid);
        $this->getJson("/api/rnd/reports/{$reportId}")->assertOk();
        $this->get("/api/rnd/reports/{$reportId}/view")->assertOk();
        $this->getJson('/api/rnd/reports?type=accomplishment_report')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/api/admin/reports?type=accomplishment_report')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/admin/reports/{$reportId}")->assertForbidden();
        $this->getJson('/api/rnd/accomplishment-periods/to-review')->assertForbidden();
        $this->getJson('/api/fss/diet-list-counts?from=2026-06-01&to=2026-06-15')
            ->assertForbidden();
        $this->actingAs($fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-01',
            'collected_ward_diet_lists' => 1,
            'apportioned_distributed_meals' => 1,
        ])->assertStatus(409);
    }

    public function test_withdraw_reject_resubmit_and_approval_preserve_the_reviewed_pdf(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $fss = User::factory()->fss()->create();
        $rnd = User::factory()->rnd()->create();
        $this->fillPeriod($fss, '2026-06-01', '2026-06-15');

        $first = $this->actingAs($fss)->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertOk()->json('data');
        $reportId = $first['report_id'];
        $this->postJson('/api/fss/accomplishment-periods/2026-06-01/withdraw')
            ->assertOk()->assertJsonPath('data.status', 'draft');
        $this->actingAs($rnd)->getJson('/api/rnd/accomplishment-periods/to-review')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/rnd/reports/{$reportId}")->assertForbidden();
        $this->postJson("/api/rnd/accomplishment-periods/{$reportId}/approve", [
            'submission_version' => 1,
        ])->assertStatus(409);

        $this->actingAs($fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-01',
            'collected_ward_diet_lists' => 2,
            'apportioned_distributed_meals' => 10,
        ])->assertOk();
        $second = $this->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertOk()->assertJsonPath('data.submission_version', 2)->json('data');
        $this->actingAs($rnd)->postJson("/api/rnd/accomplishment-periods/{$reportId}/reject", [
            'submission_version' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/rnd/accomplishment-periods/{$reportId}/reject", [
            'submission_version' => 2, 'reason' => str_repeat('x', 501),
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $reason = str_repeat('x', 500);
        $this->postJson("/api/rnd/accomplishment-periods/{$reportId}/reject", [
            'submission_version' => 2, 'reason' => $reason,
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->actingAs($fss)->getJson('/api/fss/accomplishment-periods/2026-06-01')
            ->assertOk()->assertJsonPath('data.rejection_reason', $reason);
        $this->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-01',
            'collected_ward_diet_lists' => 3,
            'apportioned_distributed_meals' => 10,
        ])->assertOk();
        $this->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertOk()->assertJsonPath('data.submission_version', 3);
        $this->actingAs($rnd)->postJson("/api/rnd/accomplishment-periods/{$reportId}/approve", [
            'submission_version' => 2,
        ])->assertStatus(409);
        $this->postJson("/api/rnd/accomplishment-periods/{$reportId}/approve", [
            'submission_version' => 3,
        ])->assertOk()->assertJsonPath('data.status', 'approved');

        $approved = Report::where('uuid', $reportId)->firstOrFail();
        $hash = $approved->content_hash;
        $fileId = $approved->official_file_stored_object_id;
        $this->actingAs($fss)->postJson('/api/fss/diet-list-counts', [
            'service_date' => '2026-06-01',
            'collected_ward_diet_lists' => 4,
            'apportioned_distributed_meals' => 10,
        ])->assertStatus(409);
        app(AccomplishmentReportArchiveService::class)->preparePeriod($fss, '2026-06-01');
        $this->assertSame($hash, $approved->fresh()->content_hash);
        $this->assertSame($fileId, $approved->fresh()->official_file_stored_object_id);
        Storage::disk('report_cache')->delete($approved->cache_path);
        $viewed = $this->actingAs($rnd)->get("/api/rnd/reports/{$reportId}/view")->assertOk()->streamedContent();
        $this->assertSame($hash, hash('sha256', $viewed));
        $this->actingAs($rnd)->getJson('/api/rnd/reports?type=accomplishment_report')
            ->assertOk()->assertJsonPath('data.0.id', $reportId);
    }

    public function test_generic_report_entry_points_cannot_publish_unsubmitted_logs_or_change_an_approved_report(): void
    {
        Storage::fake('report_cache');
        Storage::fake(config('filesystems.private_uploads'));
        $fss = User::factory()->fss()->create();
        $rnd = User::factory()->rnd()->create();
        $admin = User::factory()->admin()->create();
        $this->fillPeriod($fss, '2026-06-01', '2026-06-15');

        foreach ([$fss, $rnd] as $actor) {
            $this->actingAs($actor)->postJson('/api/'.strtolower($actor->role).'/reports/accomplishment_report/prepare', [
                'start' => '2026-06-01', 'end' => '2026-06-15', 'fss_user_id' => $fss->id,
            ])->assertStatus(409);
        }
        $this->actingAs($admin)->postJson('/api/admin/reports/accomplishment_report/prepare', [
            'start' => '2026-06-01', 'end' => '2026-06-15',
        ])->assertStatus(409);

        $reportId = $this->actingAs($fss)
            ->postJson('/api/fss/accomplishment-periods/2026-06-01/submit')
            ->assertOk()->json('data.report_id');
        $this->actingAs($rnd)->postJson("/api/rnd/accomplishment-periods/{$reportId}/approve", [
            'submission_version' => 1,
        ])->assertOk();

        $this->actingAs($fss)->postJson("/api/fss/reports/{$reportId}/archive")->assertStatus(409);
        $this->actingAs($rnd)->postJson("/api/rnd/reports/{$reportId}/archive")->assertStatus(409);
        $this->actingAs($admin)->getJson("/api/admin/reports/{$reportId}")->assertOk();
        $this->getJson('/api/admin/reports?type=accomplishment_report')
            ->assertOk()->assertJsonPath('data.0.id', $reportId);
    }

    private function fillPeriod(User $fss, string $start, string $end): void
    {
        foreach (CarbonPeriod::create($start, $end) as $date) {
            DietListCount::factory()->create([
                'fss_user_id' => $fss->id,
                'service_date' => $date->toDateString(),
                'ward' => 'Accomplishment report',
                'population' => 0,
            ]);
        }
    }
}
