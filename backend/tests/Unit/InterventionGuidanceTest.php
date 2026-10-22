<?php

namespace Tests\Unit;

use App\Support\InterventionGoalCatalog;
use App\Support\InterventionGuidance;
use Tests\TestCase;

class InterventionGuidanceTest extends TestCase
{
    public function test_every_composed_goal_type_fits_the_shared_guidance_budget(): void
    {
        foreach (InterventionGoalCatalog::stages() as $goal => $types) {
            foreach ($types ?: [null] as $type) {
                $notes = InterventionGuidance::for($goal, $type, [
                    'nutrition_status' => 'malnutrition_or_muscle_loss', 'refeeding_risk' => true,
                    'pediatric' => true, 'maternal' => true, 'fluid_mode' => 'restricted',
                    'energy_adjustment_mode' => 'surplus', 'energy_adjustment_kcal' => 2147483647,
                ]);
                $this->assertLessThanOrEqual(700, InterventionGuidance::characterCount($notes));
                foreach (InterventionGuidance::FIELDS as $field) {
                    $this->assertLessThanOrEqual(1200, mb_strlen($notes[$field] ?? ''));
                }
                foreach (['education_notes' => 300, 'counseling_goals' => 120, 'strategies' => 300] as $field => $budget) {
                    $this->assertLessThanOrEqual($budget, mb_strlen($notes[$field]), $field.' composition budget');
                    $this->assertLessThanOrEqual(2, substr_count($notes[$field], '.'), $field.' sentence count');
                }
                $this->assertNotEmpty($notes['foods']['choose']);
                $this->assertStringNotContainsString('restore albumin', $notes['education_notes']);
            }
        }
    }
}
