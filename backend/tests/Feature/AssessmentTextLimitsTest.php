<?php

namespace Tests\Feature;

use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Http\Requests\RND\UpdateAssessmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AssessmentTextLimitsTest extends TestCase
{
    public function test_create_and_update_accept_text_at_each_limit_and_reject_one_character_over(): void
    {
        $limits = [
            'present_diet' => 250, 'dietary_intake' => 250, 'dietary_restrictions' => 100,
            'supplements' => 100, 'knowledge_notes' => 250, 'nutrient_drug_interaction' => 100,
            'chewing_swallowing_details' => 150, 'constipation_details' => 150, 'diarrhea_details' => 150,
            'primary_diagnosis_other' => 150, 'medical_history' => 250, 'social_history' => 250,
            'religion' => 100, 'food_intolerance' => 100, 'rnd_summary' => 3500,
        ];

        foreach ([StoreAssessmentRequest::class, UpdateAssessmentRequest::class] as $requestClass) {
            $rules = (new $requestClass)->rules();
            foreach ($limits as $field => $limit) {
                $valid = Validator::make([$field => str_repeat('x', $limit)], [$field => $rules[$field]]);
                $this->assertTrue($valid->passes(), "{$requestClass}: {$field} at {$limit}");
                $unicode = Validator::make([$field => str_repeat('😀', $limit)], [$field => $rules[$field]]);
                $this->assertTrue($unicode->passes(), "{$requestClass}: {$field} Unicode at {$limit}");
                $invalid = Validator::make([$field => str_repeat('x', $limit + 1)], [$field => $rules[$field]]);
                $this->assertTrue($invalid->fails(), "{$requestClass}: {$field} over {$limit}");
            }
        }
    }

    public function test_create_and_update_enforce_list_sizes_entry_lengths_and_abg_precision(): void
    {
        foreach ([StoreAssessmentRequest::class, UpdateAssessmentRequest::class] as $requestClass) {
            $rules = array_intersect_key((new $requestClass)->rules(), array_flip([
                'food_dislikes', 'food_dislikes.*', 'medications', 'medications.*', 'biochemical_data.abg',
            ]));
            $valid = [
                'food_dislikes' => array_fill(0, 5, str_repeat('x', 50)),
                'medications' => [str_repeat('x', 100)],
                'biochemical_data' => ['abg' => '123.45'],
            ];
            $this->assertTrue(Validator::make($valid, $rules)->passes(), $requestClass);

            foreach ([
                ['food_dislikes' => array_fill(0, 6, 'Rice')],
                ['food_dislikes' => [str_repeat('x', 51)]],
                ['food_dislikes' => [123]],
                ['medications' => ['Aspirin', 'Metformin']],
                ['medications' => [str_repeat('x', 101)]],
                ['medications' => [123]],
                ['biochemical_data' => ['abg' => '1234.56']],
                ['biochemical_data' => ['abg' => '7.456']],
                ['biochemical_data' => ['abg' => '1e2']],
            ] as $invalid) {
                $this->assertTrue(Validator::make($invalid, $rules)->fails(), $requestClass.' '.json_encode($invalid));
            }
        }
    }
}
