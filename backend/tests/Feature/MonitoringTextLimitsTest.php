<?php

namespace Tests\Feature;

use App\Http\Requests\RND\StoreMonitoringRequest;
use App\Http\Requests\RND\UpdateMonitoringRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MonitoringTextLimitsTest extends TestCase
{
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
