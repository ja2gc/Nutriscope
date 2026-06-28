<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\Generators\DemographicCensusGenerator;
use PHPUnit\Framework\TestCase;

class DemographicCensusTest extends TestCase
{
    public function test_buckets_ages_into_the_census_age_groups(): void
    {
        $this->assertSame('0-4', DemographicCensusGenerator::ageGroup(0));
        $this->assertSame('0-4', DemographicCensusGenerator::ageGroup(4));
        $this->assertSame('5-9', DemographicCensusGenerator::ageGroup(5));
        $this->assertSame('15-18', DemographicCensusGenerator::ageGroup(18));
        $this->assertSame('19-29', DemographicCensusGenerator::ageGroup(19));
        $this->assertSame('40-59', DemographicCensusGenerator::ageGroup(59));
        $this->assertSame('60+', DemographicCensusGenerator::ageGroup(60));
        $this->assertSame('60+', DemographicCensusGenerator::ageGroup(92));
    }

    public function test_maps_risk_score_to_category(): void
    {
        $this->assertNull(DemographicCensusGenerator::riskLevel(null));
        $this->assertSame('Low', DemographicCensusGenerator::riskLevel(0.0));
        $this->assertSame('Low', DemographicCensusGenerator::riskLevel(1.0));
        $this->assertSame('Moderate', DemographicCensusGenerator::riskLevel(2.0));
        $this->assertSame('Moderate', DemographicCensusGenerator::riskLevel(3.0));
        $this->assertSame('High', DemographicCensusGenerator::riskLevel(4.0));
    }

    public function test_aggregates_age_by_sex_matrix_with_totals(): void
    {
        $patients = [
            ['age' => 3, 'sex' => 'M', 'ward' => 'Pedia', 'diagnosis' => 'PEM', 'nutritional_status' => 'underweight', 'risk_level' => 'high'],
            ['age' => 4, 'sex' => 'F', 'ward' => 'Pedia', 'diagnosis' => 'PEM', 'nutritional_status' => 'underweight', 'risk_level' => 'high'],
            ['age' => 70, 'sex' => 'M', 'ward' => 'Medicine', 'diagnosis' => 'DM', 'nutritional_status' => 'normal', 'risk_level' => 'low'],
        ];

        $out = DemographicCensusGenerator::aggregate($patients);

        $this->assertSame(3, $out['total']);

        // age × sex matrix
        $this->assertSame(1, $out['age_sex']['0-4']['M']);
        $this->assertSame(1, $out['age_sex']['0-4']['F']);
        $this->assertSame(2, $out['age_sex']['0-4']['total']);
        $this->assertSame(1, $out['age_sex']['60+']['M']);
        $this->assertSame(0, $out['age_sex']['60+']['F']);

        // sex totals
        $this->assertSame(2, $out['by_sex']['M']);
        $this->assertSame(1, $out['by_sex']['F']);
    }

    public function test_breaks_down_by_ward_diagnosis_status_and_risk(): void
    {
        $patients = [
            ['age' => 30, 'sex' => 'F', 'ward' => 'OB', 'diagnosis' => 'Anemia', 'nutritional_status' => 'normal', 'risk_level' => 'moderate'],
            ['age' => 31, 'sex' => 'F', 'ward' => 'OB', 'diagnosis' => 'Anemia', 'nutritional_status' => 'normal', 'risk_level' => 'low'],
            ['age' => 45, 'sex' => 'M', 'ward' => 'Surgery', 'diagnosis' => 'Post-op', 'nutritional_status' => 'at_risk', 'risk_level' => 'high'],
        ];

        $out = DemographicCensusGenerator::aggregate($patients);

        $this->assertSame(2, $out['by_ward']['OB']);
        $this->assertSame(1, $out['by_ward']['Surgery']);
        $this->assertSame(2, $out['by_diagnosis']['Anemia']);
        $this->assertSame(2, $out['by_status']['normal']);
        $this->assertSame(1, $out['by_status']['at_risk']);
        $this->assertSame(1, $out['by_risk']['high']);
        $this->assertSame(1, $out['by_risk']['moderate']);
        $this->assertSame(1, $out['by_risk']['low']);
    }

    public function test_handles_unknowns_without_crashing(): void
    {
        $out = DemographicCensusGenerator::aggregate([
            ['age' => null, 'sex' => null, 'ward' => null, 'diagnosis' => null, 'nutritional_status' => null, 'risk_level' => null],
        ]);

        $this->assertSame(1, $out['total']);
        $this->assertSame(1, $out['by_ward']['Unspecified']);
        $this->assertSame(1, $out['by_sex']['Unknown']);
    }
}
