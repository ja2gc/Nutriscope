<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\Diagnosis\PesEvidenceBuilder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PesEvidenceBuilderTest extends TestCase
{
    public function test_bmi_classification_uses_asia_pacific_bands_without_screening_or_ibw_overrides(): void
    {
        foreach ([[15, 'Underweight'], [18.49, 'Underweight'], [18.5, 'Normal'], [22.99, 'Normal'], [23, 'Overweight'], [24.99, 'Overweight'], [25, 'Obese Class I'], [29.99, 'Obese Class I'], [30, 'Obese Class II'], [35, 'Obese Class II']] as [$bmi, $label]) {
            $this->assertSame($label, Assessment::classifyBmi((float) $bmi, 35), "BMI {$bmi}");
        }
        foreach ([null, 0, -1, INF, NAN] as $bmi) {
            $this->assertNull(Assessment::classifyBmi($bmi, 35));
        }
        $this->assertNull(Assessment::classifyBmi(27, null));
        $this->assertNull(Assessment::classifyBmi(27, 17));
        $this->assertNull(Assessment::classifyBmi(14, 2));
        $this->assertNull(Assessment::classifyBmi(27, 18));
        $this->assertNull(Assessment::classifyBmi(27, 35, true));
        $this->assertNull(Assessment::classifyBmi(27, 35, false, 'pregnant_t2'));
        $this->assertNull(Assessment::classifyBmi(27, 35, false, 'lactating'));
    }

    public function test_who_monthly_thresholds_use_sex_exact_boundaries_and_supported_ages(): void
    {
        foreach ([[12.117, 'Severe thinness'], [12.118, 'Thinness'], [13.031, 'Normal'], [16.645, 'Normal'], [16.646, 'Overweight'], [18.259, 'Overweight'], [18.260, 'Obesity']] as [$bmi, $label]) {
            $this->assertSame($label, Assessment::classifyBmi($bmi, 5, false, 'none', 'Male', 61));
        }
        $this->assertSame('Normal', Assessment::classifyBmi(16.8, 5, false, 'none', 'Female', 61));
        $this->assertNull(Assessment::classifyBmi(16.8, 5, false, 'none', null, 61));
        $this->assertNull(Assessment::classifyBmi(16.8, 5, false, 'none', 'Male', 60));
        $this->assertSame('Normal', Assessment::classifyBmi(24, 19, false, 'none', 'Male', 228));
        $this->assertSame('Overweight', Assessment::classifyBmi(24, 19, false, 'none', 'Male', 229));
        $this->assertNull((new Assessment(['weight' => 14, 'height' => 90]))->calculateBmi(4));
    }

    public function test_no_answers_remain_counterevidence_and_yes_answers_send_only_the_optional_detail(): void
    {
        $assessment = new Assessment([
            'chewing_swallowing_difficulties' => 'No',
            'constipation' => 'No',
            'diarrhea_notes' => 'Yes',
            'diarrhea_details' => 'Three loose stools since morning',
        ]);
        $record = new NcpRecord;
        $assessment->setRelation('biochemicalData', null);
        $record->setRelation('patient', new Patient);
        $record->setRelation('assessment', $assessment);
        $record->setRelation('diagnoses', collect());

        $evidence = app(PesEvidenceBuilder::class)->build($record);

        $this->assertSame('No', $evidence['chewing_swallowing_difficulties']);
        $this->assertSame(['constipation' => 'No', 'diarrhea' => 'Three loose stools since morning'], $evidence['gi_symptoms']);
    }

    public function test_age_evidence_uses_dob_and_census_boundaries_instead_of_legacy_category(): void
    {
        foreach ([
            ['dob' => '2009-01-01', 'legacy' => 'adult', 'expected' => 'pediatric'],
            ['dob' => '2007-01-01', 'legacy' => 'pediatric', 'expected' => 'adult'],
            ['dob' => '1966-01-01', 'legacy' => 'adult', 'expected' => 'elderly'],
        ] as $case) {
            $patient = new Patient(['dob' => $case['dob']]);
            $patient->setAttribute('age_group_category', $case['legacy']);
            $record = new NcpRecord;
            $record->created_at = Carbon::parse('2026-05-01');
            $record->setRelation('patient', $patient);
            $assessment = new Assessment;
            $assessment->setRelation('biochemicalData', null);
            $record->setRelation('assessment', $assessment);
            $record->setRelation('diagnoses', collect());

            $this->assertSame($case['expected'], app(PesEvidenceBuilder::class)->build($record)['age_group']);
        }
    }
}
