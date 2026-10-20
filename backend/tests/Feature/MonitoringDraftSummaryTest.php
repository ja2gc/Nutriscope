<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Intervention;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringDraftSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function context(bool $available = true, string $narrative = 'Intake has improved; protein remains below target.'): array
    {
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $narrative]],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 15],
        ], $available ? 200 : 503)]);
        $rnd = User::factory()->create(['role' => 'RND']);
        $ncp = NcpRecord::factory()->create(['rnd_user_id' => $rnd->id, 'patient_id' => Patient::factory()->create()->id]);
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
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 2);
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

    public function test_summary_prompt_prioritizes_two_sentences_and_excludes_recommendations(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, $draft)->assertOk();
        Http::assertSent(fn ($request) => str_contains($request['system'], 'Write two sentences')
            && str_contains($request['system'], 'Use at most 60 words')
            && str_contains($request['system'], 'Do not add a closing recommendation')
            && str_contains($request['system'], 'Do not repeat a value for each indicator'));
    }

    public function test_three_generations_are_shared_across_refreshes_and_changed_values(): void
    {
        [$ncp, $url, $draft] = $this->context();
        foreach ([2, 1, 0] as $remaining) {
            $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', $remaining);
            $draft['weight']++;
            $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', $remaining);
        }
        $this->postJson($url, $draft)->assertStatus(429);
        Http::assertSentCount(3);
        $this->assertDatabaseCount('monitorings', 0);
    }

    public function test_a_new_draft_has_its_own_allowance_after_an_unsaved_draft_is_abandoned(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 2);
        $nextDraftId = (string) Str::uuid();
        $next = $this->getJson($url.'?draft_id='.$nextDraftId)->assertOk()
            ->assertJsonPath('data.remaining', 3)->json('data');
        $this->assertNotSame($draft['entry_key'], $next['entry_key']);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 2);
        $this->postJson($url, [...$draft, 'draft_id' => $nextDraftId, 'entry_key' => $next['entry_key']])
            ->assertOk()->assertJsonPath('data.remaining', 2);
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

    public function test_failed_generation_does_not_use_allowance_and_invalid_inputs_never_reach_ai(): void
    {
        [$ncp, $url, $draft] = $this->context(false);
        $this->postJson($url, array_replace($draft, ['weight' => -2]))->assertUnprocessable();
        Http::assertNothingSent();
        $this->postJson($url, $draft)->assertStatus(503);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 3);
    }

    public function test_other_role_cannot_generate_and_stale_entry_keys_are_rejected(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $this->postJson($url, array_replace($draft, ['entry_key' => str_repeat('a', 64)]))->assertStatus(409);
        $this->actingAs(User::factory()->create(['role' => 'FSS']), 'sanctum')->postJson($url, $draft)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_parallel_generation_is_blocked_and_a_saved_visit_starts_a_new_allowance(): void
    {
        [$ncp, $url, $draft] = $this->context();
        $lock = Cache::lock('monitoring-summary:'.$draft['entry_key'].':lock', 60);
        $this->assertTrue($lock->get());
        $this->postJson($url, $draft)->assertStatus(429);
        Http::assertNothingSent();
        $lock->release();
        $this->postJson($url, $draft)->assertOk()->assertJsonPath('data.remaining', 2);
        $save = $draft;
        unset($save['entry_key']);
        $save['clinical_summary'] = 'Reviewed summary.';
        $save['goal_achievement'] = ['continuation_decision' => 'continue'];
        $this->postJson("/api/rnd/ncp-records/{$ncp->uuid}/monitorings", $save)->assertCreated();
        $budget = $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 3)->json('data');
        $this->assertNotSame($draft['entry_key'], $budget['entry_key']);
    }

    public function test_oversized_ai_output_is_rejected_without_truncating_or_using_allowance(): void
    {
        [$ncp, $url, $draft] = $this->context(true, str_repeat('x', 601));
        $this->postJson($url, $draft)->assertStatus(503);
        $this->getJson($url.'?draft_id='.$draft['draft_id'])->assertOk()->assertJsonPath('data.remaining', 3);
        $this->assertDatabaseCount('monitorings', 0);
    }
}
