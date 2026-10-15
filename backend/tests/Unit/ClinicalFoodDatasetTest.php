<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClinicalFoodDatasetTest extends TestCase
{
    public function test_approved_sources_have_their_own_per_100_gram_nutrients(): void
    {
        $dataset = json_decode(file_get_contents(dirname(__DIR__, 2).'/database/seeders/data/clinical-foods.json'), true, flags: JSON_THROW_ON_ERROR);
        $foods = array_column($dataset['foods'], null, 'name');

        foreach ([
            'Pork Loin (Cooked)' => [209, 28.62, 0, 9.63, 61.02],
            'Tomato (Cooked)' => [18, 0.95, 4.01, 0.11, 94.34],
            'Coconut Milk (Canned)' => [197, 2.02, 2.81, 21.33, 72.88],
            'Calamansi / Lime Juice' => [25, 0.42, 8.42, 0.07, 90.79],
            'Glutinous Rice (Cooked)' => [97, 2.02, 21.09, 0.19, 76.63],
            'Corn Grits (Cooked)' => [71, 1.71, 14.76, 0.46, 82.93],
        ] as $name => $expected) {
            foreach (array_combine(['calories', 'protein', 'carbs', 'fat', 'water_g'], $expected) as $key => $amount) {
                $this->assertEquals($amount, $foods[$name][$key], "$name $key");
            }
        }
    }

    public function test_curated_foods_use_the_approved_food_and_preparation_sources(): void
    {
        $dataset = json_decode(file_get_contents(dirname(__DIR__, 2).'/database/seeders/data/clinical-foods.json'), true, flags: JSON_THROW_ON_ERROR);
        $foods = array_column($dataset['foods'], null, 'name');
        foreach ([
            'Pork Loin (Cooked)' => [168233, 'Pork, fresh, loin, whole, separable lean only, cooked, roasted'],
            'Tomato (Cooked)' => [170050, 'Tomatoes, red, ripe, cooked'],
            'Coconut Milk (Canned)' => [170173, 'Nuts, coconut milk, canned (liquid expressed from grated meat and water)'],
            'Calamansi / Lime Juice' => [168156, 'Lime juice, raw'],
            'Glutinous Rice (Cooked)' => [169711, 'Rice, white, glutinous, unenriched, cooked'],
            'Corn Grits (Cooked)' => [171655, 'Cereals, corn grits, white, regular and quick, enriched, cooked with water, without salt'],
        ] as $name => [$fdcId, $description]) {
            $this->assertSame($fdcId, $foods[$name]['usda_fdc_id'], $name);
            $this->assertSame($fdcId, $foods[$name]['source']['fdc_id'], $name);
            $this->assertSame($description, $foods[$name]['source']['description'], $name);
            $this->assertEquals(100, $foods[$name]['serving_size'], $name);
            $this->assertSame('g', $foods[$name]['serving_unit'], $name);
        }
    }
}
