<?php

namespace Tests\Feature;

use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MonitoringTextLimitsTest extends TestCase
{
    public function test_care_decision_is_required_and_accepts_only_the_three_existing_choices(): void
    {
        foreach ([StoreMonitoringRequest::class, UpdateMonitoringRequest::class] as $requestClass) {
            $rules = array_filter((new $requestClass)->rules(), fn ($key) => str_starts_with($key, 'goal_achievement'), ARRAY_FILTER_USE_KEY);
            foreach ([[], ['goal_achievement' => null], ['goal_achievement' => []], ['goal_achievement' => ['continuation_decision' => null]], ['goal_achievement' => ['continuation_decision' => '']], ['goal_achievement' => ['continuation_decision' => 'unknown']]] as $invalid) {
                $this->assertTrue(Validator::make($invalid, $rules)->fails());
            }
            foreach (['continue', 'modify', 'discontinue'] as $decision) {
                $this->assertTrue(Validator::make(['goal_achievement' => ['continuation_decision' => $decision]], $rules)->passes());
            }
        }
    }

    public function test_progress_assessment_is_required_and_limited_to_600_characters(): void
    {
        foreach ([StoreMonitoringRequest::class, UpdateMonitoringRequest::class] as $requestClass) {
            $rules = ['clinical_summary' => (new $requestClass)->rules()['clinical_summary']];
            foreach (['' => false, '   ' => false, str_repeat('x', 600) => true, str_repeat('x', 601) => false] as $text => $valid) {
                $this->assertSame($valid, Validator::make(['clinical_summary' => $text], $rules)->passes());
            }
        }
    }

    public function test_shared_fields_match_assessment_limits(): void
    {
        foreach ([StoreMonitoringRequest::class, UpdateMonitoringRequest::class] as $requestClass) {
            $rules = array_intersect_key((new $requestClass)->rules(), array_flip(['dietary_restrictions', 'food_dislikes', 'food_dislikes.*']));
            $data = ['dietary_restrictions' => str_repeat('x', 100), 'food_dislikes' => array_fill(0, 5, str_repeat('x', 50))];
            $this->assertTrue(Validator::make($data, $rules)->passes());
            foreach ([['dietary_restrictions' => str_repeat('x', 101)], ['food_dislikes' => array_fill(0, 6, 'rice')], ['food_dislikes' => [str_repeat('x', 51)]]] as $invalid) {
                $this->assertTrue(Validator::make(array_replace($data, $invalid), $rules)->fails());
            }
        }
    }

    public function test_text_labs_keep_the_same_length_and_decimal_rules_as_assessment(): void
    {
        foreach ([StoreMonitoringRequest::class, UpdateMonitoringRequest::class] as $requestClass) {
            foreach (['123.45' => true, '7.45' => true, '7.456' => false, '1234.56' => false, '1e2' => false] as $abg => $valid) {
                $request = new $requestClass;
                $request->merge(['lab_values' => ['abg' => $abg]]);
                $validator = Validator::make(['lab_values' => ['abg' => $abg]], ['lab_values' => ['array']]);
                foreach ($request->after() as $callback) {
                    $validator->after($callback);
                }
                $this->assertSame($valid, $validator->passes(), $abg);
            }
        }
    }
}
