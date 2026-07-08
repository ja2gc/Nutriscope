<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringPlanEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function ncpFor(User $rnd): NcpRecord
    {
        $patient = Patient::factory()->create(['sex' => 'Male', 'dob' => '1980-01-01']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'weight' => 50, 'height' => 170, 'bmi' => 17.3]);
        $assessment->biochemicalData()->create(['albumin' => 2.8]);

        return $ncp;
    }

    public function test_rnd_can_fetch_monitoring_plan(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = $this->ncpFor($rnd);

        $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitoring-plan")
            ->assertOk()
            ->assertJsonStructure(['data' => ['visits', 'indicators', 'pes_statements']]);
    }

    public function test_non_rnd_is_forbidden(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = $this->ncpFor($rnd);
        $fss = User::factory()->create(['role' => 'FSS']);

        $this->actingAs($fss, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitoring-plan")
            ->assertForbidden();
    }

    public function test_renal_monitoring_plan_tracks_phosphate_goal_lab(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = $this->ncpFor($rnd);
        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'renal_diet',
            'disease_stage' => 'stage_4',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitoring-plan")
            ->assertOk();

        $keys = collect($response->json('data.indicators'))->pluck('key')->all();

        $this->assertContains('potassium', $keys);
        $this->assertContains('phosphate', $keys);
    }

    public function test_custom_monitoring_plan_tracks_all_supported_electrolyte_labs(): void
    {
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = $this->ncpFor($rnd);
        Intervention::forceCreate([
            'ncp_record_id' => $ncp->id,
            'goal_type' => 'custom',
        ]);

        $response = $this->actingAs($rnd, 'sanctum')
            ->getJson("/api/rnd/ncp-records/{$ncp->uuid}/monitoring-plan")
            ->assertOk();

        $keys = collect($response->json('data.indicators'))->pluck('key')->all();

        $this->assertContains('phosphate', $keys);
        $this->assertContains('magnesium', $keys);
    }
}
