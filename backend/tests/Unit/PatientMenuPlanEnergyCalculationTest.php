<?php

namespace Tests\Unit;

use App\Services\Reports\Generators\PatientMenuPlanGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PatientMenuPlanEnergyCalculationTest extends TestCase
{
    public function test_clinical_energy_equation_includes_maternal_addition_and_deficit(): void
    {
        $calculation = [
            'energy_per_kg' => 30,
            'weight_kg' => 60,
            'maternal_energy_kcal' => 300,
            'energy_adjustment_mode' => 'deficit',
            'signed_adjustment_kcal' => -250,
            'calculated' => ['energy_kcal' => 1850],
        ];

        $this->assertSame(
            '30 kcal/kg × 60 kg + 300 kcal maternal addition − 250 kcal deficit = 1850 kcal/day.',
            $this->format($calculation, 1850),
        );
    }

    public function test_mifflin_equation_includes_surplus_and_clinician_override(): void
    {
        $calculation = [
            'method' => 'Mifflin–St Jeor × confirmed adult activity estimate',
            'inputs' => [
                'weightKg' => 70,
                'heightCm' => 170,
                'ageYears' => 35,
                'sex' => 'Female',
                'activityFactor' => 1.4,
            ],
            'maternal_energy_kcal' => 500,
            'energy_adjustment_mode' => 'surplus',
            'signed_adjustment_kcal' => 300,
            'calculated' => ['energy_kcal' => 2210],
            'manual_overrides' => ['energy_kcal' => 2300],
        ];

        $this->assertSame(
            '(10 × 70 kg + 6.25 × 170 cm − 5 × 35 years − 161) × activity factor 1.4 + 500 kcal maternal addition + 300 kcal surplus = 2210 kcal/day. Clinician override: 2300 kcal/day.',
            $this->format($calculation, 2300),
        );
    }

    /** @param array<string, mixed> $calculation */
    private function format(array $calculation, float $prescribedEnergy): ?string
    {
        $method = new ReflectionMethod(PatientMenuPlanGenerator::class, 'energyCalculation');

        return $method->invoke(new PatientMenuPlanGenerator, $calculation, $prescribedEnergy);
    }
}
