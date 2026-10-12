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
    public function test_no_answers_are_omitted_and_yes_answers_send_only_the_optional_detail(): void
    {
        $assessment = new Assessment([
            'chewing_swallowing_difficulties' => 'No',
            'constipation' => 'No',
            'diarrhea_notes' => 'Yes',
            'diarrhea_details' => 'Three loose stools since morning',
        ]);
        $record = new NcpRecord;
        $record->setRelation('patient', new Patient);
        $record->setRelation('assessment', $assessment);
        $record->setRelation('diagnoses', collect());

        $evidence = app(PesEvidenceBuilder::class)->build($record);

        $this->assertArrayNotHasKey('chewing_swallowing_difficulties', $evidence);
        $this->assertSame(['diarrhea' => 'Three loose stools since morning'], $evidence['gi_symptoms']);
    }

    public function test_age_evidence_uses_dob_and_census_boundaries_instead_of_legacy_category(): void
    {
        foreach ([
            ['dob' => '2008-01-01', 'legacy' => 'adult', 'expected' => 'pediatric'],
            ['dob' => '2007-01-01', 'legacy' => 'pediatric', 'expected' => 'adult'],
            ['dob' => '1966-01-01', 'legacy' => 'adult', 'expected' => 'elderly'],
        ] as $case) {
            $patient = new Patient(['dob' => $case['dob']]);
            $patient->setAttribute('age_group_category', $case['legacy']);
            $record = new NcpRecord;
            $record->created_at = Carbon::parse('2026-05-01');
            $record->setRelation('patient', $patient);
            $record->setRelation('assessment', new Assessment);
            $record->setRelation('diagnoses', collect());

            $this->assertSame($case['expected'], app(PesEvidenceBuilder::class)->build($record)['age_group']);
        }
    }
}
