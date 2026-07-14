<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\ClinicalRule;
use App\Models\Intervention;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\RecommendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RecommendServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create([
            'role' => 'RND',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeNcpRecord(array $assessmentData = []): NcpRecord
    {
        $patient = Patient::factory()->create();
        $ncpRecord = NcpRecord::factory()->create([
            'patient_id' => $patient->id,
            'rnd_user_id' => $this->rnd->id,
        ]);
        Assessment::factory()->create(array_merge(
            ['ncp_record_id' => $ncpRecord->id],
            $assessmentData
        ));

        return $ncpRecord;
    }

    private function seedRules(): void
    {
        ClinicalRule::insert([
            ['condition' => 'CKD', 'stage' => 'all',  'nutrient_or_food_tag' => 'phosphorus', 'rule_type' => 'limit',    'threshold' => 800,  'unit' => 'mg',  'reason' => 'CKD limits phosphorus.', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'CKD', 'stage' => 'all',  'nutrient_or_food_tag' => 'potassium',  'rule_type' => 'limit',    'threshold' => 2000, 'unit' => 'mg',  'reason' => 'CKD limits potassium.',  'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'DM',  'stage' => 'all',  'nutrient_or_food_tag' => 'sugar',      'rule_type' => 'avoid',    'threshold' => 0,    'unit' => 'g',   'reason' => 'DM avoids sugar.',        'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'DM',  'stage' => 'all',  'nutrient_or_food_tag' => 'whole_grain', 'rule_type' => 'recommend', 'threshold' => 0,   'unit' => '',    'reason' => 'DM recommends whole grain.', 'created_at' => now(), 'updated_at' => now()],
            ['condition' => 'HTN', 'stage' => 'all',  'nutrient_or_food_tag' => 'sodium',     'rule_type' => 'limit',    'threshold' => 1500, 'unit' => 'mg',  'reason' => 'HTN limits sodium.',      'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_recommend_service_returns_recommendations_for_conditions(): void
    {
        $this->seedRules();
        $service = app(RecommendService::class);

        $result = $service->getRecommendations(['CKD', 'DM'], null);

        $this->assertArrayHasKey('recommend', $result);
        $this->assertArrayHasKey('avoid', $result);
        $this->assertArrayHasKey('limits', $result);
        $this->assertNotEmpty($result['limits']);
    }

    public function test_recommend_service_correctly_separates_rule_types(): void
    {
        $this->seedRules();
        $service = app(RecommendService::class);

        $result = $service->getRecommendations(['DM'], null);

        $avoidTags = array_column($result['avoid'], 'tag');
        $recommendTags = array_column($result['recommend'], 'tag');

        $this->assertContains('sugar', $avoidTags);
        $this->assertContains('whole_grain', $recommendTags);
    }

    public function test_recommend_endpoint_returns_structured_response(): void
    {
        $this->seedRules();
        $ncpRecord = $this->makeNcpRecord();

        Intervention::factory()->create(['ncp_record_id' => $ncpRecord->id]);

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/intervention/recommend", [
                'conditions' => ['CKD', 'HTN'],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['recommend', 'avoid', 'limits']]);
    }

    public function test_recommend_endpoint_requires_conditions(): void
    {
        $ncpRecord = $this->makeNcpRecord();

        $response = $this->actingAs($this->rnd)
            ->postJson("/api/rnd/ncp-records/{$ncpRecord->uuid}/intervention/recommend", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['conditions']);
    }
}
