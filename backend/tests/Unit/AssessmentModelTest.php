<?php

namespace Tests\Unit;

use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\Patient;
use App\Support\PrimaryDiagnosisCategory;
use App\Support\WeightChangePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

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
        $assessment = new Assessment;
        $this->assertContains('physical_activity_level', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_muac_mm(): void
    {
        $assessment = new Assessment;
        $this->assertContains('muac_mm', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_waist_cm(): void
    {
        $assessment = new Assessment;
        $this->assertContains('waist_cm', $assessment->getFillable());
    }

    public function test_assessment_fillable_includes_hip_cm(): void
    {
        $assessment = new Assessment;
        $this->assertContains('hip_cm', $assessment->getFillable());
    }

    /** Numeric fields must be cast to float */
    public function test_muac_mm_is_cast_to_float(): void
    {
        $casts = (new Assessment)->getCasts();
        $this->assertArrayHasKey('muac_mm', $casts);
        $this->assertEquals('float', $casts['muac_mm']);
    }

    public function test_waist_cm_is_cast_to_float(): void
    {
        $casts = (new Assessment)->getCasts();
        $this->assertArrayHasKey('waist_cm', $casts);
        $this->assertEquals('float', $casts['waist_cm']);
    }

    public function test_hip_cm_is_cast_to_float(): void
    {
        $casts = (new Assessment)->getCasts();
        $this->assertArrayHasKey('hip_cm', $casts);
        $this->assertEquals('float', $casts['hip_cm']);
    }

    /** Validation rules must accept the new fields */
    public function test_store_request_validates_physical_activity_level(): void
    {
        $request = new StoreAssessmentRequest;
        $rules = $request->rules();
        $this->assertArrayHasKey('physical_activity_level', $rules);
        $this->assertContains('required', $rules['physical_activity_level']);
        $this->assertContains('string', $rules['physical_activity_level']);
    }

    public function test_store_request_validates_muac_mm(): void
    {
        $request = new StoreAssessmentRequest;
        $rules = $request->rules();
        $this->assertArrayHasKey('muac_mm', $rules);
        $this->assertContains('nullable', $rules['muac_mm']);
        $this->assertContains('numeric', $rules['muac_mm']);
    }

    public function test_store_request_validates_waist_cm(): void
    {
        $request = new StoreAssessmentRequest;
        $rules = $request->rules();
        $this->assertArrayHasKey('waist_cm', $rules);
        $this->assertContains('nullable', $rules['waist_cm']);
        $this->assertContains('numeric', $rules['waist_cm']);
    }

    public function test_store_request_validates_hip_cm(): void
    {
        $request = new StoreAssessmentRequest;
        $rules = $request->rules();
        $this->assertArrayHasKey('hip_cm', $rules);
        $this->assertContains('nullable', $rules['hip_cm']);
        $this->assertContains('numeric', $rules['hip_cm']);
    }

    /** Migration must have added the columns to assessments table */
    public function test_assessments_table_has_physical_activity_level_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('assessments', 'physical_activity_level'),
            'assessments table must have physical_activity_level column'
        );
    }

    public function test_assessments_table_has_muac_mm_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('assessments', 'muac_mm'),
            'assessments table must have muac_mm column'
        );
    }

    public function test_assessments_table_has_waist_cm_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('assessments', 'waist_cm'),
            'assessments table must have waist_cm column'
        );
    }

    public function test_assessments_table_has_hip_cm_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('assessments', 'hip_cm'),
            'assessments table must have hip_cm column'
        );
    }

    public function test_primary_diagnosis_category_exposes_exact_allowed_values(): void
    {
        $this->assertSame([
            'Cardiovascular',
            'Renal',
            'Diabetes',
            'Obesity',
            'Malnutrition',
            'Surgery / Trauma',
            'Liver',
            'Cancer',
            'Pregnancy / Lactation',
            'Other',
        ], PrimaryDiagnosisCategory::values());
        $this->assertTrue(PrimaryDiagnosisCategory::isAllowed('Cancer'));
        $this->assertFalse(PrimaryDiagnosisCategory::isAllowed('Oncology'));
    }

    public function test_weight_change_period_parses_only_explicit_legacy_values(): void
    {
        $this->assertSame(['value' => 3, 'unit' => 'weeks'], WeightChangePeriod::parseLegacy('3 weeks'));
        $this->assertSame(['value' => 1, 'unit' => 'months'], WeightChangePeriod::parseLegacy('1 month'));
        $this->assertSame(['value' => 6, 'unit' => 'months'], WeightChangePeriod::parseLegacy(' 6 months '));
        $this->assertNull(WeightChangePeriod::parseLegacy('about three months'));
        $this->assertNull(WeightChangePeriod::parseLegacy('0 weeks'));
    }

    public function test_weight_change_period_formats_natural_singular_and_plural_labels(): void
    {
        $this->assertSame('1 week', WeightChangePeriod::format(1, 'weeks'));
        $this->assertSame('3 months', WeightChangePeriod::format(3, 'months'));
        $this->assertNull(WeightChangePeriod::format(null, null));
        $this->assertNull(WeightChangePeriod::format(3, null));
    }

    public function test_new_clinical_fields_are_fillable_cast_and_present_in_schema(): void
    {
        $assessment = new Assessment;

        foreach ([
            'weight_change_period_value',
            'weight_change_period_unit',
            'primary_diagnosis_category',
            'primary_diagnosis_other',
        ] as $field) {
            $this->assertContains($field, $assessment->getFillable());
            $this->assertTrue(Schema::hasColumn('assessments', $field), "Missing assessments.{$field}");
        }

        $this->assertSame('integer', $assessment->getCasts()['weight_change_period_value']);
        $this->assertFalse(Schema::hasColumn('patients', 'is_demo'));
        $this->assertArrayNotHasKey('is_demo', (new Patient)->getCasts());
        $this->assertNotContains('is_demo', (new Patient)->getFillable());
    }
}
