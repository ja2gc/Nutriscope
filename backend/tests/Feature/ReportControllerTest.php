<?php

namespace Tests\Feature;

use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Jobs\GenerateReport;
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
            'role'     => 'RND',
            'password' => Hash::make('password'),
        ]);
        $this->fss = User::factory()->create([
            'role'     => 'FSS',
            'password' => Hash::make('password'),
        ]);

        // Seed required report templates
        ReportTemplate::insert([
            ['type' => 'adime_individual',    'name' => 'ADIME Individual',       'blade_view' => 'reports.adime_individual',    'description' => 'Individual ADIME Note', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'adime_aggregate',     'name' => 'ADIME Aggregate',        'blade_view' => 'reports.adime_aggregate',     'description' => 'Aggregate ADIME',       'created_at' => now(), 'updated_at' => now()],
            ['type' => 'ncp_census',          'name' => 'NCP Census',             'blade_view' => 'reports.ncp_census',          'description' => 'Monthly NCP Census',    'created_at' => now(), 'updated_at' => now()],
            ['type' => 'inventory_report',    'name' => 'Inventory Report',       'blade_view' => 'reports.inventory',           'description' => 'Stock levels',          'created_at' => now(), 'updated_at' => now()],
            ['type' => 'budget_report',       'name' => 'Budget Report',          'blade_view' => 'reports.budget',              'description' => 'Budget summary',        'created_at' => now(), 'updated_at' => now()],
            ['type' => 'menu_cycle_report',   'name' => 'Menu Cycle Report',      'blade_view' => 'reports.menu_cycle',          'description' => 'Menu cycles',           'created_at' => now(), 'updated_at' => now()],
            ['type' => 'patient_menu_plan',   'name' => 'Patient Menu Plan',      'blade_view' => 'reports.patient_menu_plan',   'description' => 'Individual meal plan',  'created_at' => now(), 'updated_at' => now()],
            ['type' => 'inspection_report',   'name' => 'Inspection Report',      'blade_view' => 'reports.inspection_report',   'description' => 'Delivery inspection',   'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marketing_statement', 'name' => 'Marketing Statement',    'blade_view' => 'reports.marketing_statement', 'description' => 'Marketing docs',        'created_at' => now(), 'updated_at' => now()],
            ['type' => 'marketing_summary',   'name' => 'Marketing Summary',      'blade_view' => 'reports.marketing_summary',   'description' => 'Monthly summary',       'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeNcpRecord(): NcpRecord
    {
        $patient = Patient::factory()->create();
        return NcpRecord::factory()->create([
            'patient_id'  => $patient->id,
            'rnd_user_id' => $this->rnd->id
        ]);
    }

    public function test_rnd_can_request_adime_individual_report(): void
    {
        Queue::fake();
        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports', [
                'template_code' => 'adime_individual',
                'parameters'    => ['ncp_record_id' => $ncpRecord->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateReport::class);
        $this->assertDatabaseHas('reports', ['status' => 'pending']);
    }

    public function test_fss_can_request_inventory_report(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->fss)
            ->postJson('/api/fss/reports', [
                'template_code' => 'inventory_report',
                'parameters'    => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateReport::class);
    }

    public function test_report_request_requires_valid_template_code(): void
    {
        $response = $this->actingAs($this->rnd)
            ->postJson('/api/rnd/reports', [
                'template_code' => 'nonexistent_report',
                'parameters'    => [],
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
            ->getJson("/api/rnd/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id);
    }

    public function test_rnd_cannot_see_another_users_report(): void
    {
        $otherUser = User::factory()->create(['role' => 'RND']);
        $report    = Report::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->rnd)
            ->getJson("/api/rnd/reports/{$report->id}");

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
