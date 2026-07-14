<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringAiReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_review_sends_only_tracked_indicators_and_returns_course_of_action(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'Albumin improving toward target; continue high-protein renal plan and recheck in 2 weeks.']],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
            ], 200),
        ]);

        $rnd = User::factory()->create(['role' => 'RND']);
        $patient = Patient::factory()->create(['sex' => 'Male', 'dob' => '1980-01-01']);
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'weight' => 50, 'height' => 170, 'bmi' => 17.3]);
        $assessment->biochemicalData()->create(['albumin' => 2.8]); // abnormal → tracked
        Intervention::create(['ncp_record_id' => $ncp->id, 'goal_type' => 'renal_diet', 'energy_kcal' => 1800]);
        Monitoring::create(['ncp_record_id' => $ncp->id, 'weight' => 52, 'bmi' => 18.0, 'lab_values' => ['albumin' => 3.0]]);

        $res = $this->actingAs($rnd, 'sanctum')
            ->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings/ai-review")
            ->assertOk();

        $this->assertNotEmpty($res->json('data.narrative'));

        Http::assertSent(function (Request $request) {
            $body = json_encode($request->data());

            return str_contains($body, 'Albumin')        // tracked (flagged + renal goal)
                && str_contains($body, 'pes_statements')  // PES context included
                && ! str_contains($body, 'Sodium');       // untracked lab excluded
        });
    }
}
