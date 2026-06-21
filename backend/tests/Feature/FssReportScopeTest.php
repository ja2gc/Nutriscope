<?php

namespace Tests\Feature;

use App\Models\DietListCount;
use App\Models\ReportBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'fss_user_id'  => $this->fss->id,
            'service_date' => $date,
            'population'   => 30,
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

    // ── FSS — other report types blocked (403) ────────────────────────────

    public function test_fss_cannot_browse_instances_for_dietary_cash_book(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/dietary_cash_book/instances')
            ->assertForbidden();
    }

    public function test_fss_cannot_browse_instances_for_inventory_report(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/inventory_report/instances')
            ->assertForbidden();
    }

    public function test_fss_cannot_browse_instances_for_budget_report(): void
    {
        $this->actingAs($this->fss)
            ->getJson('/api/fss/reports/budget_report/instances')
            ->assertForbidden();
    }

    public function test_fss_cannot_render_dietary_cash_book(): void
    {
        $this->actingAs($this->fss)
            ->get('/api/fss/reports/dietary_cash_book/render')
            ->assertForbidden();
    }

    public function test_fss_cannot_archive_budget_report(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/reports/budget_report/archive')
            ->assertForbidden();
    }

    // ── RND — unrestricted by FSS guard ──────────────────────────────────

    public function test_rnd_can_browse_instances_for_dietary_cash_book(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/dietary_cash_book/instances')
            ->assertOk();
    }

    public function test_rnd_can_browse_instances_for_inventory_report(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/inventory_report/instances')
            ->assertOk();
    }

    public function test_rnd_can_browse_accomplishment_report_instances(): void
    {
        $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports/accomplishment_report/instances')
            ->assertOk();
    }
}
