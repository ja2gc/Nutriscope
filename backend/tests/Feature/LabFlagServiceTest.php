<?php

namespace Tests\Feature;

use App\Services\LabFlagService;
use Tests\TestCase;

class LabFlagServiceTest extends TestCase
{
    public function test_flags_low_high_and_normal_with_sex_ranges(): void
    {
        $svc = new LabFlagService;

        $flags = $svc->flag(['albumin' => 2.8, 'glucose' => 90, 'hemoglobin' => 13.0], 'Male');

        $this->assertSame('LOW', $flags['albumin']['status']);
        $this->assertSame(2.8, $flags['albumin']['value']);
        $this->assertArrayNotHasKey('glucose', $flags);           // in range → not flagged
        $this->assertSame('LOW', $flags['hemoglobin']['status']); // 13.0 < male 13.5
    }

    public function test_female_hemoglobin_range_differs(): void
    {
        $svc = new LabFlagService;

        $flags = $svc->flag(['hemoglobin' => 13.0], 'Female');

        $this->assertArrayNotHasKey('hemoglobin', $flags);        // 13.0 normal for female (>=12.0)
    }

    public function test_high_value_flagged(): void
    {
        $svc = new LabFlagService;

        $flags = $svc->flag(['glucose' => 140], 'Female');

        $this->assertSame('HIGH', $flags['glucose']['status']);
        $this->assertSame(140.0, $flags['glucose']['value']);
    }

    public function test_magnesium_range_is_flagged_for_severe_nutrition_monitoring(): void
    {
        $svc = new LabFlagService;

        $low = $svc->flag(['magnesium' => 1.4], 'Male');
        $high = $svc->flag(['magnesium' => 2.6], 'Male');
        $normal = $svc->flag(['magnesium' => 1.9], 'Male');

        $this->assertSame('LOW', $low['magnesium']['status']);
        $this->assertSame('HIGH', $high['magnesium']['status']);
        $this->assertArrayNotHasKey('magnesium', $normal);
    }
}
