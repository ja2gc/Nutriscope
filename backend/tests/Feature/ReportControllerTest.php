<?php

namespace Tests\Feature;

use App\Models\AuditActivity;
use App\Models\DietListCount;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportService;
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
        $this->rnd = User::factory()->rnd()->create([
            'name' => 'LEGACY REPORT OWNER',
            'first_name' => 'Rosa Maria',
            'last_name' => 'Dela Peña',
        ]);
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
        $this->assertSame($patientId, $activity->root_patient_id);
        $this->assertSame($ncpId, $activity->ncp_record_id);
        $this->assertMatchesRegularExpression('/^NCP-[A-F0-9]{16}$/D', $activity->properties['details']['ncp_reference']);
        $this->assertArrayNotHasKey('root_patient_id', $activity->properties['details']);
        $this->assertArrayNotHasKey('ncp_record_id', $activity->properties['details']);
        $this->assertSame(2, AuditActivity::query()->where('event', 'downloaded')->count());
    }

    public function test_rnd_can_list_own_reports_with_creator_attribution(): void
    {
        Report::factory(3)->create(['user_id' => $this->rnd->id, 'status' => 'completed']);

        $this->actingAs($this->rnd, 'sanctum')->getJson('/api/rnd/reports')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.created_by.id', $this->rnd->uuid)
            ->assertJsonPath('data.0.created_by.name', 'Rosa Maria Dela Peña');
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

    public function test_historical_report_name_snapshots_are_not_rewritten(): void
    {
        $report = Report::factory()->create([
            'user_id' => $this->rnd->id,
            'status' => 'completed',
            'type' => 'procurement_pack',
            'parameters' => ['prepared_by_name' => 'Historical Prepared By'],
            'snapshot' => ['staff' => [['name' => 'Historical Staff']]],
        ]);

        $this->actingAs($this->rnd, 'sanctum')
            ->getJson("/api/rnd/reports/{$report->uuid}")
            ->assertOk()
            ->assertJsonPath('data.parameters.prepared_by_name', 'Historical Prepared By')
            ->assertJsonPath('data.snapshot.staff.0.name', 'Historical Staff')
            ->assertJsonPath('data.created_by.name', 'Rosa Maria Dela Peña');
    }

    public function test_live_render_uses_current_prepared_by_display_name(): void
    {
        DietListCount::factory()->create(['service_date' => '2026-06-10']);
        $reports = $this->createMock(ReportService::class);
        $reports->method('supports')->willReturn(true);
        $reports->expects($this->once())
            ->method('streamBytes')
            ->willReturnCallback(function (string $type, array $params): string {
                $this->assertSame('accomplishment_report', $type);
                $this->assertSame('Rosa Maria Dela Peña', $params['prepared_by_name']);

                return '%PDF-current-name';
            });
        $this->app->instance(ReportService::class, $reports);

        $this->actingAs($this->rnd, 'sanctum')
            ->get('/api/rnd/reports/accomplishment_report/render?start=2026-06-10&end=2026-06-10')
            ->assertOk();
    }
}
