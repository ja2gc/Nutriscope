<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->rnd()->create();
    }

    public function test_deleted_ncp_archived_report_keeps_safe_root_and_owner_authorization(): void
    {
        Storage::fake('public');
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        Storage::disk('public')->put('reports/deleted-ncp.pdf', '%PDF-safe');
        $report = Report::factory()->create([
            'user_id' => $this->rnd->id,
            'type' => 'ncp_summary',
            'parameters' => [],
            'file_path' => 'reports/deleted-ncp.pdf',
            'audit_patient_id' => $ncp->patient_id,
            'audit_ncp_record_id' => $ncp->id,
            'audit_owner_id' => $this->rnd->id,
        ]);
        $patientId = $ncp->patient_id;
        $ncpId = $ncp->id;
        $ncp->delete();

        $this->actingAs($this->rnd, 'sanctum')
            ->get("/api/rnd/reports/{$report->uuid}/download")
            ->assertOk();
        $this->get("/api/rnd/reports/{$report->uuid}/download")->assertOk();

        $activity = AuditActivity::query()->where('event', 'downloaded')->latest('id')->firstOrFail();
        $this->assertSame($patientId, $activity->properties['details']['root_patient_id']);
        $this->assertSame($ncpId, $activity->properties['details']['ncp_record_id']);
        $this->assertSame(2, AuditActivity::query()->where('event', 'downloaded')->count());
    }

    public function test_rnd_can_list_own_reports_with_creator_attribution(): void
    {
        Report::factory(3)->create(['user_id' => $this->rnd->id, 'status' => 'completed']);

        $this->actingAs($this->rnd, 'sanctum')->getJson('/api/rnd/reports')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.created_by.id', $this->rnd->uuid);
    }

    public function test_rnd_can_show_owned_report(): void
    {
        $report = Report::factory()->create([
            'user_id' => $this->rnd->id,
            'status' => 'completed',
            'type' => 'procurement_pack',
        ]);

        $this->actingAs($this->rnd, 'sanctum')
            ->getJson("/api/rnd/reports/{$report->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $report->uuid);
    }

    public function test_rnd_cannot_see_another_users_report(): void
    {
        $report = Report::factory()->create(['user_id' => User::factory()->rnd()->create()->id]);

        $this->actingAs($this->rnd, 'sanctum')
            ->getJson("/api/rnd/reports/{$report->uuid}")
            ->assertForbidden();
    }
}
