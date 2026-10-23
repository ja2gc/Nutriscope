<?php

namespace Tests\Unit;

use App\Services\FoodRecallService;
use PHPUnit\Framework\TestCase;

class FoodRecallEvidenceTest extends TestCase
{
    public function test_recall_uses_saved_portions_and_recorded_day_average_with_explicit_units(): void
    {
        $snapshot = ['calories' => 200, 'protein' => 10, 'carbs' => 20, 'fat' => 5,
            'serving_size' => 100, 'serving_unit' => 'g', 'micronutrients' => ['sodium' => 100]];
        $item = ['quantity' => 100, 'unit' => 'g', 'recipe_id' => null, 'nutrient_snapshot' => $snapshot];
        $summary = (new FoodRecallService)->evidenceSummary(['days_count' => 2, 'displayed_micros' => ['sodium'], 'days' => [
            ['kind' => 'weekday', 'items' => [$item]], ['kind' => 'weekend', 'items' => [[...$item, 'quantity' => 200]]],
        ]]);
        $this->assertSame(2, $summary['days_count']);
        $this->assertSame(['weekday' => 1, 'weekend' => 1], $summary['day_types']);
        $this->assertSame(['value' => 300.0, 'unit' => 'kcal'], $summary['average_daily']['energy_kcal']);
        $this->assertSame(['value' => 150.0, 'unit' => 'mg'], $summary['average_daily']['micro_sodium']);
        $this->assertArrayNotHasKey('fluid_ml', $summary['average_daily']);
    }

    public function test_missing_nutrients_remain_unknown_and_incompatible_units_are_not_converted(): void
    {
        $item = ['quantity' => 100, 'unit' => 'g', 'recipe_id' => null,
            'nutrient_snapshot' => ['calories' => 200, 'protein' => 10, 'serving_size' => 100, 'serving_unit' => 'g']];
        $unknown = $item;
        unset($unknown['nutrient_snapshot']['protein']);
        $plan = ['days_count' => 1, 'days' => [['items' => [$item, $unknown]]]];
        $service = new FoodRecallService;
        $this->assertArrayNotHasKey('protein_g', $service->evidenceSummary($plan)['average_daily']);
        $plan['days'][0]['items'][0]['unit'] = 'mL';
        $this->assertNull($service->evidenceSummary($plan));
        $this->assertNull($service->evidenceSummary(['days_count' => 1, 'days' => [['items' => []]]]));
    }

    public function test_entered_intake_preserves_zero_without_inventing_unknown_micronutrient_units(): void
    {
        $values = FoodRecallService::intakeEvidence(['energy_kcal' => 0, 'protein_g' => null, 'micro_sodium' => 1500, 'micro_unknown' => 7]);
        $this->assertSame(['value' => 0.0, 'unit' => 'kcal'], $values['energy_kcal']);
        $this->assertSame(['value' => 1500.0, 'unit' => 'mg'], $values['micro_sodium']);
        $this->assertArrayNotHasKey('protein_g', $values);
        $this->assertArrayNotHasKey('micro_unknown', $values);
    }
}
