<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PesCitedDraftTest extends TestCase
{
    use RefreshDatabase;

    private function record(array $assessment): NcpRecord
    {
        $user = User::factory()->rnd()->create();
        $this->actingAs($user, 'sanctum');
        $record = NcpRecord::factory()->create(['patient_id' => Patient::factory()->create(['dob' => '1990-01-01'])->id, 'rnd_user_id' => $user->id, 'status' => 'draft']);
        Assessment::factory()->create([...$assessment, 'ncp_record_id' => $record->id]);
        config()->set('services.anthropic.key', 'test-key');
        Http::preventStrayRequests();

        return $record;
    }

    private function provider(string $domain, array $draft, ?string $invalidRole = null): void
    {
        Http::fake(['api.anthropic.com/*' => function ($request) use ($domain, $draft, $invalidRole) {
            $payload = json_decode(substr($request['messages'][0]['content'], strlen("input_json:\n")), true);
            $id = collect($payload['candidates'])->search(fn ($candidate) => $candidate[0] === $domain && strcasecmp($candidate[1], $draft['candidate'] ?? 'Other') === 0);
            unset($draft['candidate'], $draft['pk']);
            $refs = array_flip(array_column($payload['evidence'], 0));
            foreach ($payload['evidence'] as $ref => $field) {
                $refs[$field[0]] = $ref;
            }
            foreach (['ek', 'sk'] as $role) {
                $draft[$role] = array_map(fn ($field) => $refs[$field] ?? 'e999', $draft[$role]);
            }
            if ($invalidRole !== null) {
                unset($draft[$invalidRole]);
            }

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['suggestions' => [['id' => $id, ...$draft]]])]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50]]);
        }]);
    }

    #[DataProvider('citedCases')]
    public function test_cited_standard_custom_and_predicted_drafts_remain_available_for_rnd_review(string $domain, array $assessment, array $draft): void
    {
        $record = $this->record($assessment);
        $draft += ['n' => 'The cited assessment pattern supports this problem for review.',
            'en' => 'The cited history explains the proposed barrier.', 'sn' => 'The cited finding is the proposed indicator to follow.'];
        $this->provider($domain, $draft);
        $url = "/api/rnd/ncp-records/{$record->uuid}/diagnoses";
        $response = $this->postJson($url.'/ai-suggest')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $issued = $response->json('data.0');
        $this->assertNotEmpty($issued['evidence_refs']);
        $this->postJson($url.'/ai-approve', ['draft_id' => $issued['draft_id']])->assertCreated();
        $this->assertSame($issued['evidence_refs'], Diagnosis::query()->sole()->ai_provenance['evidence_refs']);
        $this->postJson($url.'/ai-suggest')->assertOk()->assertJsonCount(0, 'data');
        Http::assertSentCount(1);
    }

    public static function citedCases(): array
    {
        return [
            'recorded activity barrier can be a cited cause for review' => ['NC', ['weight' => 80, 'usual_weight' => 80, 'height' => 175.5,
                'edema_present' => false, 'pregnancy_lactation_status' => 'none', 'physical_activity_level' => 'sedentary', 'medical_history' => 'Back pain limits physical activity.'],
                ['candidate' => 'Overweight/Obesity', 'p' => 'Overweight/obesity', 'e' => 'Back pain limits physical activity', 'ek' => ['medical_history'],
                    's' => 'BMI 25.97', 'sk' => ['bmi']]],
            'percentage derived from both cited weights' => ['NC', ['weight' => 61.2, 'usual_weight' => 64.4, 'height' => 167.6,
                'weight_change_period_value' => 2, 'weight_change_period_unit' => 'months', 'medical_history' => 'Dyspnea reduces appetite; weight loss was not intentional.'],
                ['candidate' => 'Unintended Weight Loss', 'p' => 'Unintended weight loss', 'e' => 'Dyspnea reduces appetite', 'ek' => ['medical_history'],
                    's' => 'Weight loss 4.97% over 2 months', 'sk' => ['weight_kg', 'usual_weight_kg', 'weight_change_period']]],
            'adult classification cited without repeating raw BMI' => ['NC', ['weight' => 80, 'usual_weight' => 80, 'height' => 175.5,
                'edema_present' => false, 'pregnancy_lactation_status' => 'none', 'medical_history' => 'Limited mobility reduces activity.'],
                ['candidate' => 'Overweight/Obesity', 'p' => 'Overweight/obesity', 'e' => 'Limited mobility reduces activity', 'ek' => ['medical_history'],
                    's' => 'BMI classification: Obese Class I', 'sk' => ['bmi_classification']]],
            'qualitative prediction without a numeric target' => ['NI', ['medical_history' => 'Back injury restricts activity during recovery.', 'present_diet' => 'Usual portions expected to continue during inactivity.'],
                ['p' => 'Predicted excessive energy intake', 'pk' => ['present_diet', 'medical_history'], 'e' => 'Back injury restricts activity during recovery', 'ek' => ['medical_history'], 's' => 'Usual portions expected to continue during inactivity', 'sk' => ['present_diet']]],
            'custom nutrient with documented inadequacy' => ['NI', ['dietary_intake' => 'Choline intake below documented requirements.', 'social_history' => 'Cultural food practices exclude eggs and several protein foods.'],
                ['p' => 'Inadequate choline intake', 'e' => 'Cultural food practices exclude eggs and several protein foods', 'ek' => ['social_history'], 's' => 'Choline intake below documented requirements', 'sk' => ['dietary_intake']]],
            'swallowing with a neurological history' => ['NC', ['medical_history' => 'Recent stroke affects swallowing.', 'chewing_swallowing_difficulties' => 'Yes', 'chewing_swallowing_details' => 'Coughs with thin liquids.'],
                ['p' => 'Swallowing difficulty', 'pk' => ['chewing_swallowing_difficulties'], 'e' => 'Recent stroke affects swallowing', 'ek' => ['medical_history'], 's' => 'Coughs with thin liquids', 'sk' => ['chewing_swallowing_difficulties']]],
            'GI function with treatment context' => ['NC', ['medical_history' => 'Loose stools began after a new treatment.', 'diarrhea_notes' => 'Yes', 'diarrhea_details' => 'Loose stools after meals.'],
                ['p' => 'Altered GI function', 'pk' => ['gi_symptoms'], 'e' => 'Loose stools began after a new treatment', 'ek' => ['medical_history'], 's' => 'Loose stools after meals', 'sk' => ['gi_symptoms']]],
            'malnutrition with separate documented criteria' => ['NI', ['physical_assessment' => 'Reduced muscle mass observed on examination.', 'medical_history' => 'Active inflammation during chemotherapy.', 'dietary_intake' => 'Poor intake during treatment.'],
                ['p' => 'Malnutrition', 'e' => 'Active inflammation during chemotherapy', 'ek' => ['medical_history'], 's' => 'Reduced muscle mass observed on examination', 'sk' => ['physical_assessment']]],
            'physical inactivity from a physical barrier' => ['NB', ['physical_activity_level' => 'sedentary', 'medical_history' => 'Lower back pain prevents usual walks.', 'social_history' => 'Stopped usual nightly walks.'],
                ['candidate' => 'Physical Inactivity', 'p' => 'Physical inactivity', 'e' => 'Lower back pain prevents usual walks', 'ek' => ['medical_history'], 's' => 'Stopped usual nightly walks', 'sk' => ['social_history', 'physical_activity_level']]],
            'access with a recorded barrier' => ['NB', ['social_history' => 'Limited access to food due to unavailable grocery transport.'],
                ['p' => 'Limited access to food or water', 'pk' => ['social_history'], 'e' => 'Unavailable grocery transport', 'ek' => ['social_history'], 's' => 'Food shopping limited by unavailable transport', 'sk' => ['social_history']]],
            'knowledge with an educational barrier' => ['NB', ['knowledge_notes' => 'Cannot explain food labels or carbohydrate sources.', 'social_history' => 'Never received formal nutrition education.'],
                ['p' => 'Food- and nutrition-related knowledge deficit', 'pk' => ['knowledge_notes'], 'e' => 'Never received formal nutrition education', 'ek' => ['social_history'], 's' => 'Unable to interpret labels or identify carbohydrate sources', 'sk' => ['knowledge_notes']]],
            'predicted interaction with planned treatment' => ['NC', ['medical_history' => 'Warfarin is planned; variable vitamin K intake needs review.', 'present_diet' => 'Vitamin K intake varies between days.'],
                ['p' => 'Predicted food–medication interaction', 'pk' => ['medical_history', 'present_diet'], 'e' => 'Warfarin is planned; variable vitamin K intake needs review', 'ek' => ['medical_history'], 's' => 'Variable vitamin K intake before planned warfarin therapy', 'sk' => ['medical_history', 'present_diet']]],
            'weight loss citing both weights instead of a calculated percentage' => ['NC', ['weight' => 61.2, 'usual_weight' => 64.4, 'height' => 167.6,
                'weight_change_period_value' => 2, 'weight_change_period_unit' => 'months', 'medical_history' => 'Dyspnea reduces appetite; weight loss was not intentional.'],
                ['candidate' => 'Unintended Weight Loss', 'p' => 'Unintended weight loss', 'e' => 'Dyspnea reduces appetite', 'ek' => ['medical_history'],
                    's' => 'Weight fell from 64.4 kg to 61.2 kg over 2 months', 'sk' => ['weight_kg', 'usual_weight_kg', 'weight_change_period']]],
        ];
    }

    public function test_compact_provider_output_must_cite_every_component(): void
    {
        foreach (['ek', 'sk'] as $missing) {
            $record = $this->record(['knowledge_notes' => 'Cannot explain food labels.', 'social_history' => 'Limited nutrition education.']);
            $this->provider('NB', ['p' => 'Food- and nutrition-related knowledge deficit', 'pk' => ['knowledge_notes'],
                'e' => 'Limited nutrition education', 'ek' => ['social_history'], 's' => 'Cannot explain food labels', 'sk' => ['knowledge_notes']], $missing);
            $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_one_weight_alone_does_not_establish_a_weight_change(): void
    {
        $record = $this->record(['weight' => 61.2, 'usual_weight' => 64.4, 'height' => 167.6,
            'weight_change_period_value' => 2, 'weight_change_period_unit' => 'months',
            'medical_history' => 'Dyspnea reduces appetite; weight loss was not intentional.']);
        $this->provider('NC', ['candidate' => 'Unintended Weight Loss', 'p' => 'Unintended weight loss',
            'e' => 'Dyspnea reduces appetite', 'ek' => ['medical_history'],
            's' => 'Weight 61.2 kg', 'sk' => ['weight_kg']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_known_problem_with_missing_citations_stays_retryable(): void
    {
        $record = $this->record(['knowledge_notes' => 'Cannot explain food labels.', 'social_history' => 'Limited nutrition education.']);
        $this->provider('NB', ['candidate' => 'Food- and nutrition-related knowledge deficit', 'p' => 'Food- and nutrition-related knowledge deficit',
            'e' => 'Limited nutrition education', 'ek' => ['social_history'], 's' => 'Cannot explain food labels', 'sk' => ['knowledge_notes']], 'sk');
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.has_retryable_rejections', true);
    }

    public function test_unverified_cause_is_withheld_instead_of_substituting_another_clause(): void
    {
        $record = $this->record(['dietary_intake' => 'Estimated energy intake 1200 kcal/day versus estimated needs 1600 kcal/day (75%). Dyspnea reduces appetite and food intake.',
            'medical_history' => 'Congestive heart failure diagnosed 2 months ago. Dyspnea reduces appetite; weight loss was not intentional.']);
        $this->provider('NI', ['candidate' => 'Inadequate energy intake', 'p' => 'Inadequate energy intake',
            'e' => 'Breathlessness limits eating', 'ek' => ['medical_history', 'dietary_intake'],
            's' => 'Energy intake 1200 kcal/day vs needs 1600 kcal/day (75%)', 'sk' => ['dietary_intake']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_normal_activity_cannot_be_cited_as_an_inactivity_cause(): void
    {
        $record = $this->record(['weight' => 80, 'usual_weight' => 80, 'height' => 175.5,
            'edema_present' => false, 'pregnancy_lactation_status' => 'none', 'physical_activity_level' => 'lightly_active']);
        $this->provider('NC', ['candidate' => 'Overweight/Obesity', 'p' => 'Overweight/obesity',
            'e' => 'Physical inactivity', 'ek' => ['physical_activity_level'], 's' => 'BMI 25.97', 'sk' => ['bmi']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_derived_weight_loss_does_not_allow_a_different_percentage(): void
    {
        $record = $this->record(['weight' => 61.2, 'usual_weight' => 64.4, 'height' => 167.6,
            'weight_change_period_value' => 2, 'weight_change_period_unit' => 'months',
            'medical_history' => 'Dyspnea reduces appetite; weight loss was not intentional.']);
        $this->provider('NC', ['candidate' => 'Unintended Weight Loss', 'p' => 'Unintended weight loss',
            'e' => 'Dyspnea reduces appetite', 'ek' => ['medical_history'],
            's' => 'Weight loss 5% over 2 months', 'sk' => ['weight_kg', 'usual_weight_kg', 'weight_change_period']]);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_optional_notes_do_not_infer_excess_intake_from_food_choices_or_bmi(): void
    {
        $record = $this->record(['weight' => 80, 'usual_weight' => 80, 'height' => 175.5,
            'edema_present' => false, 'pregnancy_lactation_status' => 'none', 'physical_activity_level' => 'sedentary',
            'medical_history' => 'Limited mobility reduces activity.',
            'dietary_intake' => 'Frequent processed foods. Energy intake has not been quantified against individual targets.']);
        $this->provider('NC', ['candidate' => 'Overweight/Obesity', 'p' => 'Overweight/obesity',
            'e' => 'Limited mobility reduces activity', 'ek' => ['medical_history'], 's' => 'BMI 25.97', 'sk' => ['bmi'],
            'en' => 'Sedentary activity and frequent processed foods support excess energy intake']);
        $this->postJson("/api/rnd/ncp-records/{$record->uuid}/diagnoses/ai-suggest")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.etiology_notes', '');
        Http::assertSentCount(1);
    }
}
