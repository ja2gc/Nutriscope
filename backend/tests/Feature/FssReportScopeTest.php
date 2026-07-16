<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FSS role-restricted report access — fss.md §8.
 *
 * Allowlist: accomplishment_report only.
 * Blocked:   all other report types (403).
 * RND:       unrestricted by this guard.
 */
class FssReportScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS']);
        $this->rnd = User::factory()->create(['role' => 'RND']);
        ReportBranding::singleton(); // ensure a branding row exists
    }

    /** Seed a DietListCount so accomplishment_report has data. */
    private function seedAccomplishment(string $date = '2026-06-10'): DietListCount
    {
        return DietListCount::factory()->create([
            'fss_user_id' => $this->fss->id,
            'service_date' => $date,
            'population' => 30,
        ]);
    }

    // ── FSS — accomplishment_report allowed ───────────────────────────────

    public function test_fss_can_browse_accomplishment_report_instances(): void
    {
        $this->seedAccomplishment();

        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/accomplishment_report/instances')
            ->assertOk();
    }

    public function test_fss_can_render_accomplishment_report(): void
    {
        $this->seedAccomplishment('2026-06-10');

        $this->actingAs($this->fss)
            ->get('/api/fss/reports/accomplishment_report/render?start=2026-06-10&end=2026-06-10')
            ->assertOk();
    }

    public function test_fss_cannot_open_mutate_or_trail_disallowed_report_rows_even_if_attributed(): void
    {
        Storage::fake('public');
        $this->actingAs($this->fss, 'sanctum');
        foreach (['ncp_summary', 'procurement_pack'] as $type) {
            $path = "reports/{$type}.pdf";
            Storage::disk('public')->put($path, '%PDF-private');
            $report = Report::factory()->create([
                'user_id' => $this->fss->id,
                'type' => $type,
                'status' => 'archived',
                'file_path' => $path,
            ]);

            foreach (['', '/view', '/download'] as $suffix) {
                $this->getJson("/api/fss/reports/{$report->uuid}{$suffix}")->assertForbidden();
            }
            $this->deleteJson("/api/fss/reports/{$report->uuid}")->assertForbidden();
            $this->getJson("/api/rnd/reports/{$report->uuid}/activity")->assertForbidden();
            $this->assertModelExists($report);
        }
    }

    // ── Retired report types are gone (404 for all roles) ─────────────────

    public function test_fss_retired_dietary_cash_book_is_not_found(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/dietary_cash_book/instances')
            ->assertNotFound();
    }

    public function test_fss_retired_inventory_report_is_not_found(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/inventory_report/instances')
            ->assertNotFound();
    }

    public function test_fss_retired_budget_report_is_not_found(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/budget_report/instances')
            ->assertNotFound();
    }

    public function test_fss_retired_dietary_cash_book_render_is_not_found(): void
    {
        $this->actingAs($this->fss)
            ->get('/api/fss/reports/dietary_cash_book/render')
            ->assertNotFound();
    }

    public function test_fss_retired_budget_report_archive_is_not_found(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/reports/budget_report/archive')
            ->assertNotFound();
    }

    // ── RND — retired reports are also gone ───────────────────────────────

    public function test_rnd_retired_dietary_cash_book_is_not_found(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/dietary_cash_book/instances')
            ->assertNotFound();
    }

    public function test_rnd_retired_inventory_report_is_not_found(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/inventory_report/instances')
            ->assertNotFound();
    }

    public function test_rnd_can_browse_accomplishment_report_instances(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/accomplishment_report/instances')
            ->assertOk();
    }
}
