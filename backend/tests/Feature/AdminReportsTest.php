<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\ReportBranding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin role-restricted report access.
 *
 * Allowlist: demographic_census, budget_report, procurement_pack.
 * Blocked:   ncp_summary, patient_menu_plan (PHI — 403 even via direct API).
 */
class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'Admin']);
        ReportBranding::singleton(); // ensure a branding row exists
    }

    // ── Allowed types → 200 ────────────────────────────────────────────────

    public function test_admin_can_browse_demographic_census_instances(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/demographic_census/instances')
            ->assertOk();
    }

    public function test_admin_retired_budget_report_is_not_found(): void
    {
        Budget::factory()->create();

        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/budget_report/instances')
            ->assertNotFound();
    }

    public function test_admin_can_browse_procurement_pack_instances(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/procurement_pack/instances')
            ->assertOk();
    }

    // ── Blocked types → 403 (PHI guard — enforced on the backend) ──────────

    public function test_admin_cannot_browse_ncp_summary_instances(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/ncp_summary/instances')
            ->assertForbidden();
    }

    public function test_admin_cannot_browse_patient_menu_plan_instances(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/patient_menu_plan/instances')
            ->assertForbidden();
    }

    public function test_admin_cannot_render_ncp_summary(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/ncp_summary/render')
            ->assertForbidden();
    }

    public function test_admin_cannot_render_patient_menu_plan(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/patient_menu_plan/render')
            ->assertForbidden();
    }

    public function test_admin_cannot_archive_ncp_summary(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/reports/ncp_summary/archive')
            ->assertForbidden();
    }

    // ── Index must not return clinical rows for Admin ────────────────────────

    public function test_admin_index_returns_only_allowed_type_rows(): void
    {
        // Create reports of various types owned by admin
        \App\Models\Report::factory()->create([
            'user_id' => $this->admin->id,
            'type'    => 'demographic_census',
            'status'  => 'archived',
        ]);
        \App\Models\Report::factory()->create([
            'user_id' => $this->admin->id,
            'type'    => 'ncp_summary',
            'status'  => 'archived',
        ]);
        \App\Models\Report::factory()->create([
            'user_id' => $this->admin->id,
            'type'    => 'patient_menu_plan',
            'status'  => 'archived',
        ]);

        $data = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports')
            ->assertOk()
            ->json('data');

        $types = collect($data)->pluck('type')->all();
        $this->assertContains('demographic_census', $types);
        $this->assertNotContains('ncp_summary', $types);
        $this->assertNotContains('patient_menu_plan', $types);
    }

    // ── Non-admin (RND) must not reach admin prefix ─────────────────────────

    public function test_rnd_cannot_access_admin_reports(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);

        $this->actingAs($rnd)
            ->getJson('/api/admin/reports/demographic_census/instances')
            ->assertForbidden();
    }

    // ── Types not in the allowlist are 403 (clinical PHI guard) ────────────

    public function test_admin_blocked_from_clinical_types_not_in_allowlist(): void
    {
        // ncp_summary is clinical PHI — never allowed for Admin.
        $this->actingAs($this->admin)
            ->getJson('/api/admin/reports/ncp_summary/instances')
            ->assertForbidden();
    }
}
