<?php

namespace Tests\Unit;

use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Models\Assessment;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AssessmentDryWeightTest extends TestCase
{
    public function test_assessment_model_accepts_dry_weight(): void
    {
        $assessment = new Assessment;
        $casts = $assessment->getCasts();

        $this->assertContains('dry_weight_kg', $assessment->getFillable());
        $this->assertArrayHasKey('dry_weight_kg', $casts);
        $this->assertSame('decimal:2', $casts['dry_weight_kg']);
    }

    public function test_dry_weight_is_required_when_edema_is_present(): void
    {
        $validator = $this->storeAssessmentValidator([
            'weight' => 75,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'edema_present' => true,
            'dry_weight_kg' => null,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dry_weight_kg', $validator->errors()->toArray());
    }

    public function test_dry_weight_is_not_required_when_edema_is_absent(): void
    {
        $validator = $this->storeAssessmentValidator([
            'weight' => 75,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'edema_present' => false,
            'dry_weight_kg' => null,
        ]);

        $this->assertFalse($validator->fails(), implode(' ', $validator->errors()->all()));
    }

    public function test_dry_weight_uses_assessment_weight_bounds(): void
    {
        $validator = $this->storeAssessmentValidator([
            'weight' => 75,
            'usual_weight' => 72,
            'height' => 170,
            'physical_activity_level' => 'sedentary',
            'edema_present' => true,
            'dry_weight_kg' => 700,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dry_weight_kg', $validator->errors()->toArray());
    }

    private function storeAssessmentValidator(array $data): \Illuminate\Validation\Validator
    {
        $request = StoreAssessmentRequest::create('/assessment', 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        if (method_exists($request, 'after')) {
            foreach ($request->after() as $callback) {
                $validator->after($callback);
            }
        }

        return $validator;
    }
}
