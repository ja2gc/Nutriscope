<?php

namespace Tests\Unit;

use App\Models\Diagnosis;
use App\Models\Intervention;
use App\Models\Monitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the clinical model factories against schema drift — each must insert
 * cleanly under sqlite (matching the real columns + enum constraints).
 */
class ClinicalFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnosis_factory_creates(): void
    {
        $diagnosis = Diagnosis::factory()->create();

        $this->assertTrue($diagnosis->exists);
        $this->assertContains($diagnosis->domain, ['NI', 'NC', 'NB']);
        $this->assertDatabaseHas('diagnoses', ['id' => $diagnosis->id]);
    }

    public function test_monitoring_factory_creates(): void
    {
        $monitoring = Monitoring::factory()->create();

        $this->assertTrue($monitoring->exists);
        $this->assertIsArray($monitoring->lab_values);
        $this->assertDatabaseHas('monitorings', ['id' => $monitoring->id]);
    }

    public function test_intervention_factory_creates(): void
    {
        $intervention = Intervention::factory()->create();

        $this->assertTrue($intervention->exists);
        $this->assertIsArray($intervention->displayed_nutrients);
        $this->assertDatabaseHas('interventions', ['id' => $intervention->id]);
    }
}
