<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClinicalFoodDatasetTest extends TestCase
{
    private function foods(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/database/seeders/data/clinical-foods.json'), true, flags: JSON_THROW_ON_ERROR)['foods'];
    }

    public function test_carb_names_and_usda_identities_are_unambiguous(): void
    {
        $definitions = $this->foods();
        $foods = array_column($definitions, null, 'name');
        $ids = array_column($definitions, 'usda_fdc_id');
        $this->assertCount(count($definitions), $foods);
        $this->assertCount(count($ids), array_unique($ids));
        foreach (['Cooked White Rice' => 169708, 'Cooked Brown Rice' => 169704, 'Boiled Sweet Potato' => 168484,
            'Boiled Corn' => 169999, 'Cooked Cassava' => 2709564, 'Malunggay, Raw' => 168416, 'Sitaw, Raw' => 169222] as $name => $fdcId) {
            $this->assertArrayHasKey($name, $foods);
            $this->assertSame($fdcId, $foods[$name]['usda_fdc_id']);
        }
        $this->assertArrayNotHasKey('Steamed White Rice', $foods);
        $this->assertArrayNotHasKey('Steamed Brown Rice', $foods);
    }

    public function test_usda_definitions_contain_only_identifiers_and_configuration_edits(): void
    {
        foreach ($this->foods() as $food) {
            if (! isset($food['usda_fdc_id'])) {
                continue;
            }
            $this->assertIsInt($food['usda_fdc_id']);
            $this->assertGreaterThan(0, $food['usda_fdc_id']);
            $this->assertSame([], array_diff(array_keys($food), ['usda_fdc_id', 'name', 'category', 'allergens']), $food['name']);
        }
    }

    public function test_water_is_the_only_manual_food_and_recipes_use_the_selected_foods(): void
    {
        $foods = $this->foods();
        $manual = array_values(array_filter($foods, fn (array $food): bool => ! isset($food['usda_fdc_id'])));
        $this->assertSame(['Water'], array_column($manual, 'name'));
        $this->assertSame('mL', $manual[0]['serving_unit']);
        $this->assertSame(100, $manual[0]['water_g']);
        $catalog = json_decode(file_get_contents(dirname(__DIR__, 2).'/database/seeders/data/clinical-recipes.json'), true, flags: JSON_THROW_ON_ERROR);
        $names = array_column($foods, 'name');
        foreach ($catalog['recipes'] as $recipe) {
            foreach ($recipe['ingredients'] as [$name, $quantity, $unit]) {
                $this->assertContains($name, $names, $recipe['name']);
                $this->assertGreaterThan(0, $quantity);
                $this->assertSame($name === 'Water' ? 'mL' : 'g', $unit);
            }
        }
        $this->assertNotContains('Cheddar Cheese', $names);
        $this->assertNotContains('Pork Chorizo, Raw', $names);
        $this->assertContains('Raisins', $names);
    }
}
