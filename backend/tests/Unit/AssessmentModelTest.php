<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Assessment;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * TDD: Assessment model must accept new clinical measurement fields.
 *
 * Part 2 of plan — new fields required for PAL-based TEE calculation and
 * body composition measurements (MUAC, waist, hip for WHR).
 */
class AssessmentModelTest extends TestCase
{
    use RefreshDatabase;

    /** New fields must be in $fillable */
    public function test_assessment_fillable_includes_physical_activity_level(): void
    {
        $assessment = new Assessment();
        $this->assertContains('physical_activity_level', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_muac_mm(): void
    {
        $assessment = new Assessment();
        $this->assertContains('muac_mm', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_waist_cm(): void
    {
        $assessment = new Assessment();
        $this->assertContains('waist_cm', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_hip_cm(): void
    {
        $assessment = new Assessment();
        $this->assertContains('hip_cm', $assessment->getFillable());
    }

    /** Numeric fields must be cast to float */
    public function test_muac_mm_is_cast_to_float(): void
    {
        $casts = (new Assessment())->getCasts();
        $this->assertArrayHasKey('muac_mm', $casts);
        $this->assertEquals('float', $casts['muac_mm']);
    }

    public function test_waist_cm_is_cast_to_float(): void
    {
        $casts = (new Assessment())->getCasts();
        $this->assertArrayHasKey('waist_cm', $casts);
        $this->assertEquals('float', $casts['waist_cm']);
    }

    public function test_hip_cm_is_cast_to_float(): void
    {
        $casts = (new Assessment())->getCasts();
        $this->assertArrayHasKey('hip_cm', $casts);
        $this->assertEquals('float', $casts['hip_cm']);
    }

    /** Validation rules must accept the new fields */
    public function test_store_request_validates_physical_activity_level(): void
    {
        $request = new \App\Http\Requests\RND\StoreAssessmentRequest();
        $rules = $request->rules();
        $this->assertArrayHasKey('physical_activity_level', $rules);
        $this->assertContains('required', $rules['physical_activity_level']);
        $this->assertContains('string', $rules['physical_activity_level']);
    }

    public function test_store_request_validates_muac_mm(): void
    {
        $request = new \App\Http\Requests\RND\StoreAssessmentRequest();
        $rules = $request->rules();
        $this->assertArrayHasKey('muac_mm', $rules);
        $this->assertContains('nullable', $rules['muac_mm']);
        $this->assertContains('numeric', $rules['muac_mm']);
    }

    public function test_store_request_validates_waist_cm(): void
    {
        $request = new \App\Http\Requests\RND\StoreAssessmentRequest();
        $rules = $request->rules();
        $this->assertArrayHasKey('waist_cm', $rules);
        $this->assertContains('nullable', $rules['waist_cm']);
        $this->assertContains('numeric', $rules['waist_cm']);
    }

    public function test_store_request_validates_hip_cm(): void
    {
        $request = new \App\Http\Requests\RND\StoreAssessmentRequest();
        $rules = $request->rules();
        $this->assertArrayHasKey('hip_cm', $rules);
        $this->assertContains('nullable', $rules['hip_cm']);
        $this->assertContains('numeric', $rules['hip_cm']);
    }

    /** Migration must have added the columns to assessments table */
    public function test_assessments_table_has_physical_activity_level_column(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('assessments', 'physical_activity_level'),
            'assessments table must have physical_activity_level column'
        );
    }

    public function test_assessments_table_has_muac_mm_column(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('assessments', 'muac_mm'),
            'assessments table must have muac_mm column'
        );
    }

    public function test_assessments_table_has_waist_cm_column(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('assessments', 'waist_cm'),
            'assessments table must have waist_cm column'
        );
    }

    public function test_assessments_table_has_hip_cm_column(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('assessments', 'hip_cm'),
            'assessments table must have hip_cm column'
        );
    }
}
