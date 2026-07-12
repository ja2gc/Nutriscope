<?php

namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\AuditActivity;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
        $this->fss = User::factory()->create([
            'role' => 'FSS',
            'password' => Hash::make('password'),
        ]);

        // Seed required report templates
        ReportTemplate::insert(array_map(fn (array $row) => $row + ['uuid' => (string) Str::uuid()], [
            ['type' => 'adime_individual',    'name' => 'ADIME Individual',       'blade_view' => 'reports.adime_individual',    'description' => 'Individual ADIME Note', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'adime_aggregate',     'name' => 'ADIME Aggregate',        'blade_view' => 'reports.adime_aggregate',     'description' => 'Aggregate ADIME',       'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ncp_census',          'name' => 'NCP Census',             'blade_view' => 'reports.ncp_census',          'description' => 'Monthly NCP Census',    'created_at' => now(), 'updated_at' => now()],
            ['type' => 'inventory_report',    'name' => 'Inventory Report',       'blade_view' => 'reports.inventory',           'description' => 'Stock levels',          'created_at' => now(), 'updated_at' => now()],
            ['type' => 'budget_report',       'name' => 'Budget Report',          'blade_view' => 'reports.budget',              'description' => 'Budget summary',        'created_at' => now(), 'updated_at' => now()],
            ['type' => 'menu_cycle_report',   'name' => 'Menu Cycle Report',      'blade_view' => 'reports.menu_cycle',          'description' => 'Menu cycles',           'created_at' => now(), 'updated_at' => now()],
            ['type' => 'patient_menu_plan',   'name' => 'Patient Menu Plan',      'blade_view' => 'reports.patient_menu_plan',   'description' => 'Individual meal plan',  'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ncp_summary',         'name' => 'NCP Summary',            'blade_view' => 'reports.ncp_summary',          'description' => 'NCP summary',           'created_at' => now(), 'updated_at' => now()],
            ['type' => 'inspection_report',   'name' => 'Inspection Report',      'blade_view' => 'reports.inspection_report',   'description' => 'Delivery inspection',   'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marketing_statement', 'name' => 'Marketing Statement',    'blade_view' => 'reports.marketing_statement', 'description' => 'Marketing docs',        'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marketing_summary',   'name' => 'Marketing Summary',      'blade_view' => 'reports.marketing_summary',   'description' => 'Monthly summary',       'created_at' => now(), 'updated_at' => now()],
        ]));
    }

    private function makeNcpRecord(): NcpRecord
    {
        $patient = Patient::factory()->create();

        return NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
    }

    public function test_rnd_can_request_adime_individual_report(): void
    {
        Queue::fake();
        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports', [
                'template_code' => 'adime_individual',
                'parameters' => ['ncp_record_id' => $ncpRecord->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateReport::class);
        $this->assertDatabaseHas('reports', [
            'status' => 'pending',
            'audit_patient_id' => $ncpRecord->patient_id,
            'audit_ncp_record_id' => $ncpRecord->id,
            'audit_owner_id' => $this->rnd->id,
        ]);
    }

    public function test_each_ncp_report_type_rejects_a_context_owned_by_another_rnd(): void
    {
        Queue::fake();
        $foreign = NcpRecord::factory()->create(['rnd_user_id' => User::factory()->rnd()->create()->id]);

        foreach (['adime_individual', 'ncp_summary'] as $type) {
            $this->actingAs($this->rnd, 'sanctum')->postJson('/api/rnd/reports', [
                'template_code' => $type,
                'parameters' => ['ncp_record_id' => $foreign->id],
            ])->assertForbidden();
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_patient_menu_plan_rejects_owned_ncp_smuggled_with_foreign_meal_plan(): void
    {
        Queue::fake();
        $owned = $this->makeNcpRecord();
        $foreign = NcpRecord::factory()->create(['rnd_user_id' => User::factory()->rnd()->create()->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $foreign->id]);
        $foreignPlan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id' => $foreign->patient_id,
        ]);

        $this->actingAs($this->rnd, 'sanctum')->postJson('/api/rnd/reports', [
            'template_code' => 'patient_menu_plan',
            'parameters' => [
                'meal_plan_id' => $foreignPlan->id,
                'ncp_record_id' => $owned->id,
                'patient_id' => $owned->patient_id,
            ],
        ])->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_rejects_clinical_context_owned_by_another_rnd(): void
    {
        Queue::fake();
        $other = User::factory()->rnd()->create();
        $ncp = NcpRecord::factory()->create(['rnd_user_id' => $other->id]);

        $this->actingAs($this->rnd, 'sanctum')->postJson('/api/rnd/reports', [
            'template_code' => 'patient_menu_plan',
            'parameters' => ['ncp_record_id' => $ncp->id],
        ])->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_deleted_ncp_archived_report_keeps_safe_root_and_owner_authorization(): void
    {
        Storage::fake('public');
        $ncp = $this->makeNcpRecord();
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

    public function test_fss_cannot_request_non_accomplishment_report(): void
    {
        // Scope (fss.md §8): FSS's only report is accomplishment_report; other types are 403.
        Queue::fake();

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/reports', [
                'template_code' => 'inventory_report',
                'parameters' => [],
            ]);

        $response->assertForbidden();

        Queue::assertNotPushed(GenerateReport::class);
    }

    public function test_report_request_requires_valid_template_code(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports', [
                'template_code' => 'nonexistent_report',
                'parameters' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['template_code']);
    }

    public function test_rnd_can_list_own_reports(): void
    {
        Report::factory(3)->create(['user_id' => $this->rnd->id, 'status' => 'completed']);

        $response = $this->actingAs($this->rnd)
            ->getJson('/api/rnd/reports');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_rnd_can_show_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->rnd->id, 'status' => 'completed']);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/reports/{$report->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->uuid);
    }

    public function test_rnd_cannot_see_another_users_report(): void
    {
        $otherUser = User::factory()->create(['role' => 'RND']);
        $report = Report::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/reports/{$report->uuid}");

        $response->assertForbidden();
    }

    public function test_report_generate_requires_template_code(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['template_code']);
    }
}
