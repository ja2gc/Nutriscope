<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringDraftSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_sends_current_bmi_classification_and_preserves_weight_comparisons_without_risk(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $ncp->patient()->update(['dob' => '1990-01-01']);
        $ncp->assessment()->update(['nutritional_status' => 'Severe Malnutrition']);
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(function ($request): bool {
            $payload = json_decode($request['messages'][0]['content'], true);
            $this->assertSame('Normal', $payload['bmi_classification']);
            $weight = collect($payload['indicators'])->firstWhere('key', 'weight');
            $this->assertSame(60, $weight['previous']);
            $this->assertSame(61, $weight['current']);
            $this->assertArrayNotHasKey('nutritional_status', $payload);
            $this->assertArrayNotHasKey('risk_score', $payload);

            return true;
        });
    }

    private function context(bool $available = true, string $narrative = 'Intake has improved; protein remains below target.'): array
    {
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $narrative]],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 15],
        ], $available ? 200 : 503)]);
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = NcpRecord::factory()->create(['status' => 'active', 'rnd_user_id' => $rnd->id, 'patient_id' => Patient::factory()->create()->id]);
        $assessment = Assessment::factory()->create(['ncp_record_id' => $ncp->id, 'weight' => 60, 'height' => 170, 'bmi' => 20.76]);
        $assessment->biochemicalData()->create(['albumin' => 2.8]);
        Intervention::create(['ncp_record_id' => $ncp->id, 'goal_type' => 'high_protein', 'protein_g' => 80,
            'micronutrient_limits' => ['fiber' => ['min' => 25, 'unit' => 'g']]]);
        $this->actingAs($rnd, 'sanctum');
        $url = "/api/rnd/ncp-records/{$ncp->uuid}/monitorings/ai-review";
        $draftId = (string) Str::uuid();
        $entryKey = $this->getJson($url.'?draft_id='.$draftId)->assertOk()->json('data.entry_key');

        return [$ncp, $url, [
            'entry_key' => $entryKey, 'draft_id' => $draftId, 'observed_at' => now()->toDateString(), 'visit_type' => 'scheduled_follow_up',
            'weight' => 61, 'height' => 170, 'edema_present' => false, 'physical_activity_level' => 'light',
            'pregnancy_lactation_status' => 'none', 'allergies' => [], 'food_dislikes' => [], 'dietary_restrictions' => null,
            'lab_values' => ['albumin' => 3.1, 'micro_fiber' => 20], 'symptoms' => 'Nausea persists.',
        ]];
    }

    public function test_unsaved_values_compare_with_assessment_and_only_populated_indicators_are_sent(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 9);
        $this->assertDatabaseCount('monitorings', 0);
        Http::assertSent(function ($request) {
            $payload = json_decode($request['messages'][0]['content'], true);
            $indicators = collect($payload['indicators'])->keyBy('key');

            return $indicators['albumin']['current'] === 3.1
                && $indicators['albumin']['previous'] === 2.8
                && $indicators['micro_fiber']['current'] === 20
                && ! $indicators->has('protein_g')
                && ! str_contains(json_encode($payload), 'patient_id');
        });
    }

    public function test_summary_prompt_identifies_care_focus_without_prescribing_steps(): void
    {
        $narrative = 'Intake improved but remains below the recorded protein goal. Protein adequacy remains the priority care focus.';
        [$ncp, $url, $draft] = $this->context(true, $narrative);
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.narrative', $narrative);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_starts_with($request['system'], '# CONSTRAINTS & LOGIC')
            && strlen($request['system']) <= 2400
            && str_contains($request['system'], 'One continuous paragraph; <=80 words')
            && str_contains($request['system'], 'Omit exact previous/current values')
            && str_contains($request['system'], 'Referral/medications/guidance are context')
            && str_contains($request['system'], 'Name what needs attention')
            && str_contains($request['system'], 'No prescribed steps')
            && str_contains($request['system'], 'No per-indicator value lists')
            && str_contains($request['system'], 'No target: normality/goal attainment unknown')
            && str_contains($request['system'], 'Overshoot != improvement'));
    }

    public function test_edit_summary_excludes_its_saved_values_and_includes_entry_context(): void
    {
        [$ncp, $url, $draft] = $this->context(true, str_repeat('x', 700));
        $entry = Monitoring::factory()->create(['ncp_record_id' => $ncp->id, 'observed_at' => $draft['observed_at'], 'lab_values' => ['albumin' => 4.5]]);
        $draft['entry_key'] = $this->getJson($url.'?draft_id='.$draft['draft_id'])->json('data.entry_key');
        $draft['monitoring_id'] = $entry->uuid;
        $draft['intake_notes'] = 'Smaller meals reported.';
        $draft['intake_nutrients'] = ['energy_kcal' => 1200];
        $draft['lab_values']['bp'] = '130/80';
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(function ($request) {
            $payload = json_decode($request['messages'][0]['content'], true);
            $albumin = collect($payload['indicators'])->firstWhere('key', 'albumin');

            return $albumin['previous'] === 2.8
                && $payload['intake_notes'] === 'Smaller meals reported.'
                && $payload['symptoms'] === 'Nausea persists.'
                && $payload['recall_intake_estimate']['energy_kcal'] === 1200
                && $payload['additional_findings']['bp'] === '130/80';
        });
    }

    public function test_ten_requests_are_shared_across_refreshes_and_changed_values(): void
    {
        [$ncp, $url, $draft] = $this->context();
        foreach (range(9, 0) as $remaining) {
            $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', $remaining);
            $draft['weight']++;
            $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', $remaining);
        }
        $this->postJson($url, $draft)->assertStatus(429);
        Http::assertSentCount(10);
        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_a_new_draft_shares_the_rnd_allowance_and_reuses_unchanged_inputs(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 9);
        $nextDraftId = (string) Str::uuid();
        $next = $this->getJson($url.'?draft_id='.$nextDraftId)->assertOk()
            ->assertJsonPath('data.remaining', 9)->json('data');
        $this->assertNotSame($draft['entry_key'], $next['entry_key']);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 9);
        $this->postJson($url, [...$draft, 'draft_id' => $nextDraftId, 'entry_key' => $next['entry_key']])
            ->assertOk()->assertJsonPath('data.remaining', 9)->assertJsonPath('data.cached', true);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_allowance_requires_a_valid_draft_id_and_rejects_a_key_from_a_different_draft(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->getJson($url.'?draft_id=invalid')->assertUnprocessable();
        $this->postJson($url, [...$draft, 'draft_id' => (string) Str::uuid()])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_backdated_draft_uses_last_earlier_value_and_not_future_visits(): void
    {
        [$ncp, $url, $draft] = $this->context();
        Monitoring::create(['ncp_record_id' => $ncp->id, 'observed_at' => now()->subDays(3), 'lab_values' => ['albumin' => 2.9]]);
        Monitoring::create(['ncp_record_id' => $ncp->id, 'observed_at' => now(), 'lab_values' => ['albumin' => 4.0]]);
        $draft['entry_key'] = $this->getJson($url.'?draft_id='.$draft['draft_id'])->json('data.entry_key');
        $draft['observed_at'] = now()->subDay()->toDateString();
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(fn ($request) => collect(json_decode($request['messages'][0]['content'], true)['indicators'])->firstWhere('key', 'albumin')['previous'] === 2.9);
        $this->assertDatabaseCount('monitorings', 2);
    }

    public function test_blank_visits_use_last_nonblank_field_then_assessment_or_unknown(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $ncp->assessment->biochemicalData->update(['sodium' => 140]);
        Monitoring::create(['ncp_record_id' => $ncp->id, 'observed_at' => now()->subDays(3), 'lab_values' => ['albumin' => 2.9]]);
        Monitoring::create(['ncp_record_id' => $ncp->id, 'observed_at' => now()->subDay(), 'lab_values' => []]);
        $draft['entry_key'] = $this->getJson($url.'?draft_id='.$draft['draft_id'])->json('data.entry_key');
        $draft['lab_values'] = ['albumin' => 3.1, 'sodium' => 142, 'micro_fiber' => 20, 'potassium' => null];
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(function ($request): bool {
            $indicators = collect(json_decode($request['messages'][0]['content'], true)['indicators'])->keyBy('key');

            return $indicators['albumin']['previous'] === 2.9
                && $indicators['albumin']['previous_visit'] === 'Follow-up 1'
                && $indicators['sodium']['previous'] === 140
                && $indicators['sodium']['previous_visit'] === 'Visit 1'
                && $indicators['micro_fiber']['previous'] === null
                && $indicators['micro_fiber']['gap_before'] === null
                && ! $indicators->has('potassium');
        });
        Http::assertSentCount(1);
    }

    public function test_failed_generation_uses_an_attempt_but_invalid_inputs_never_reach_ai(): void
    {
        [$ncp, $url, $draft] = $this->context(false);
        $this->postJson($url, array_replace($draft, ['weight' => -2]))->assertUnprocessable();
        Http::assertNothingSent();
        $this->postJson($url, $draft)->assertStatus(503);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 9);
    }

    public function test_other_role_cannot_generate_and_stale_entry_keys_are_rejected(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, array_replace($draft, ['entry_key' => str_repeat('a', 64)]))->assertStatus(409);
        $this->actingAs(User::factory()->create(['role' => 'FSS']), 'sanctum')->postJson($url, $draft)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_parallel_generation_is_blocked_and_a_saved_visit_keeps_the_allowance(): void
    {
        [$ncp, $url, $draft] = $this->context();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => function () use ($url, $draft) {
            $this->postJson($url, $draft)->assertStatus(429);

            return Http::response(['content' => [['type' => 'text', 'text' => 'Recorded intake remains below target.']], 'usage' => ['input_tokens' => 50, 'output_tokens' => 10]]);
        }]);
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 9);
        Http::assertSentCount(1);
        $save = $draft;
        unset($save['entry_key']);
        $save['clinical_summary'] = 'Reviewed summary.';
        $save['goal_achievement'] = ['continuation_decision' => 'continue'];
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $save)->assertCreated();
        $budget = $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 9)->json('data');
        $this->assertNotSame($draft['entry_key'], $budget['entry_key']);
    }

    public function test_oversized_ai_output_is_rejected_without_truncating_and_counts_the_attempt(): void
    {
        [$ncp, $url, $draft] = $this->context(true, str_repeat('x', 701));
        $this->postJson($url, $draft)->assertStatus(503);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 9);
        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_recall_gets_only_relevant_prescription_guides_without_merging_entered_intake(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $ncp->intervention()->first()->update(['energy_kcal' => 1800, 'fat_g' => 60]);
        $draft['intake_nutrients'] = ['protein_g' => 40, 'energy_kcal' => 1200, 'micro_fiber' => 20];
        $draft['lab_values']['protein_g'] = 90;
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(function ($request) {
            $payload = json_decode($request['messages'][0]['content'], true);
            $this->assertSame(40, $payload['recall_intake_estimate']['protein_g']);
            $this->assertSame(90, collect($payload['indicators'])->firstWhere('key', 'protein_g')['current']);
            $this->assertSame(80, $payload['recall_targets']['protein_g']['target']);
            $this->assertSame('g', $payload['recall_targets']['protein_g']['unit']);
            $this->assertSame(1800, $payload['recall_targets']['energy_kcal']['target']);
            $this->assertSame(25, $payload['recall_targets']['micro_fiber']['reference']['min']);
            $this->assertArrayNotHasKey('fat_g', $payload['recall_targets']);

            return true;
        });
    }
}
