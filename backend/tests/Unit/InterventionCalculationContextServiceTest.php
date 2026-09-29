<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\Monitoring;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Services\InterventionCalculationContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InterventionCalculationContextServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_assessment_is_explicit_calculation_source(): void
    {
        Carbon::setTestNow('2026-09-29 12:00:00');
        $patient = Patient::factory()->create([
            'dob' => '1996-09-29',
            'sex' => 'Female',
        ]);
        $ncpRecord = NcpRecord::factory()->create(['patient_id' => $patient->id]);
        Assessment::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 62,
            'height' => 160,
            'edema_present' => false,
            'dry_weight_kg' => null,
            'physical_activity_level' => 'moderate',
            'pregnancy_lactation_status' => 'lactating',
            'allergies' => ['shellfish'],
            'dietary_restrictions' => 'Low sodium',
            'food_dislikes' => ['liver'],
        ]);

        $context = app(InterventionCalculationContextService::class)->for($ncpRecord);

        $this->assertSame('assessment', $context['source_type']);
        $this->assertNull($context['source_monitoring_id']);
        $this->assertSame('62.00', $context['weight']);
        $this->assertSame('160.00', $context['height']);
        $this->assertSame('moderate', $context['physical_activity_level']);
        $this->assertSame('lactating', $context['pregnancy_lactation_status']);
        $this->assertSame(['shellfish'], $context['allergies']);
        $this->assertSame(30, $context['age_years']);
        $this->assertSame('Female', $context['sex']);
    }

    public function test_latest_monitoring_wins_with_per_field_assessment_fallback_without_mutation(): void
    {
        $patient = Patient::factory()->create(['sex' => 'Male']);
        $ncpRecord = NcpRecord::factory()->create(['patient_id' => $patient->id]);
        $assessment = Assessment::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'weight' => 70,
            'height' => 170,
            'edema_present' => false,
            'physical_activity_level' => 'light',
            'pregnancy_lactation_status' => 'none',
            'allergies' => ['peanut'],
            'dietary_restrictions' => 'Soft diet',
            'food_dislikes' => ['okra'],
        ]);
        app(InterventionCalculationContextService::class);
        Monitoring::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'observed_at' => '2026-09-20',
            'weight' => 68,
            'height' => 168,
            'created_at' => '2026-09-20 09:00:00',
        ]);
        $latest = Monitoring::factory()->create([
            'ncp_record_id' => $ncpRecord->id,
            'observed_at' => '2026-09-25',
            'weight' => 65,
            'height' => null,
            'allergies' => null,
            'food_dislikes' => ['ampalaya'],
            'created_at' => '2026-09-25 09:00:00',
        ]);
        $before = $assessment->refresh()->getAttributes();

        $context = app(InterventionCalculationContextService::class)->prefillForMonitoring($ncpRecord);

        $this->assertSame('monitoring', $context['source_type']);
        $this->assertSame($latest->uuid, $context['source_monitoring_id']);
        $this->assertSame('2026-09-25', $context['source_monitoring_date']);
        $this->assertSame('65.00', $context['weight']);
        $this->assertSame('170.00', $context['height']);
        $this->assertSame(['peanut'], $context['allergies']);
        $this->assertSame(['ampalaya'], $context['food_dislikes']);
        $this->assertSame($before, $assessment->fresh()->getAttributes());
    }
}
