<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FoodItem;

class FoodItemsSeeder extends Seeder
{
    public function run(): void
    {
        $foods = [
            // ── Staples / Grains → carbs ──────────────────────────────────────
            ['name' => 'Steamed White Rice',    'category' => 'carbs',     'calories' => 130, 'protein' => 2.7,  'carbs' => 28.2, 'fat' => 0.3,  'allergens' => [],                         'unit_price' => 2.50,  'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 1,   'potassium' => 35,  'phosphate' => 43]],
            ['name' => 'Steamed Brown Rice',    'category' => 'carbs',     'calories' => 112, 'protein' => 2.6,  'carbs' => 23.5, 'fat' => 0.9,  'allergens' => [],                         'unit_price' => 3.00,  'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 1,   'potassium' => 79,  'phosphate' => 83, 'fiber' => 1.8]],
            ['name' => 'Pandesal',              'category' => 'carbs',     'calories' => 290, 'protein' => 8.5,  'carbs' => 55.0, 'fat' => 4.0,  'allergens' => ['wheat', 'eggs', 'milk'],  'unit_price' => 5.00,  'serving_unit' => 'piece', 'serving_size' => 60,  'micronutrients' => ['sodium' => 350, 'potassium' => 80]],
            ['name' => 'Lugaw (Rice Porridge)', 'category' => 'carbs',     'calories' => 65,  'protein' => 1.5,  'carbs' => 13.5, 'fat' => 0.3,  'allergens' => [],                         'unit_price' => 1.50,  'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 180, 'potassium' => 20]],
            ['name' => 'Unsweetened Oatmeal',   'category' => 'carbs',     'calories' => 71,  'protein' => 2.5,  'carbs' => 12.0, 'fat' => 1.5,  'allergens' => ['wheat'],                  'unit_price' => 6.00,  'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 49,  'potassium' => 61,  'fiber' => 1.7]],
            ['name' => 'Skyflakes Crackers',    'category' => 'carbs',     'calories' => 130, 'protein' => 2.5,  'carbs' => 22.0, 'fat' => 3.5,  'allergens' => ['wheat'],                  'unit_price' => 8.00,  'serving_unit' => 'pack',  'serving_size' => 33,  'micronutrients' => ['sodium' => 200, 'potassium' => 30]],

            // ── Proteins ──────────────────────────────────────────────────────
            ['name' => 'Chicken Breast (Boiled)',       'category' => 'protein',   'calories' => 165, 'protein' => 31.0, 'carbs' => 0.0,  'fat' => 3.6,  'allergens' => [],                'unit_price' => 18.00, 'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 74,  'potassium' => 256, 'phosphate' => 220]],
            ['name' => 'Chicken Breast (Adobo)',        'category' => 'protein',   'calories' => 185, 'protein' => 28.0, 'carbs' => 2.0,  'fat' => 7.0,  'allergens' => ['soybeans'],      'unit_price' => 22.00, 'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 580, 'potassium' => 240, 'phosphate' => 210]],
            ['name' => 'Bangus (Milkfish, Steamed)',    'category' => 'protein',   'calories' => 148, 'protein' => 20.5, 'carbs' => 0.0,  'fat' => 7.0,  'allergens' => ['fish'],          'unit_price' => 20.00, 'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 65,  'potassium' => 300, 'phosphate' => 190, 'omega3' => 1.2]],
            ['name' => 'Tinola (Chicken Ginger Soup)', 'category' => null,        'calories' => 95,  'protein' => 12.0, 'carbs' => 4.5,  'fat' => 3.5,  'allergens' => [],                'unit_price' => 25.00, 'serving_unit' => 'g',     'serving_size' => 250, 'micronutrients' => ['sodium' => 320, 'potassium' => 280, 'phosphate' => 150]],
            ['name' => 'Scrambled Egg',                'category' => 'protein',   'calories' => 149, 'protein' => 10.0, 'carbs' => 1.6,  'fat' => 11.5, 'allergens' => ['eggs'],          'unit_price' => 8.00,  'serving_unit' => 'piece', 'serving_size' => 70,  'micronutrients' => ['sodium' => 180, 'potassium' => 120, 'phosphate' => 150, 'cholesterol' => 210]],
            ['name' => 'Tokwa (Firm Tofu)',             'category' => 'protein',   'calories' => 76,  'protein' => 8.1,  'carbs' => 1.9,  'fat' => 4.2,  'allergens' => ['soybeans'],      'unit_price' => 10.00, 'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 7,   'potassium' => 121, 'phosphate' => 97,  'calcium' => 350]],
            ['name' => 'Monggo (Mung Bean Soup)',       'category' => 'protein',   'calories' => 105, 'protein' => 7.0,  'carbs' => 19.0, 'fat' => 0.4,  'allergens' => [],                'unit_price' => 8.00,  'serving_unit' => 'g',     'serving_size' => 200, 'micronutrients' => ['sodium' => 240, 'potassium' => 266, 'phosphate' => 99,  'fiber' => 3.5]],

            // ── Vegetables ────────────────────────────────────────────────────
            ['name' => 'Kangkong (Water Spinach, Sauteed)', 'category' => 'vegetable', 'calories' => 35, 'protein' => 2.6, 'carbs' => 5.4, 'fat' => 0.5, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 45,  'potassium' => 312, 'calcium' => 67,  'fiber' => 2.1]],
            ['name' => 'Ampalaya (Bitter Gourd, Sauteed)', 'category' => 'vegetable', 'calories' => 25, 'protein' => 1.0, 'carbs' => 4.3, 'fat' => 0.4, 'allergens' => [], 'unit_price' => 6.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 30,  'potassium' => 290, 'fiber' => 2.6]],
            ['name' => 'Sayote (Chayote, Boiled)',         'category' => 'vegetable', 'calories' => 19, 'protein' => 0.8, 'carbs' => 4.5, 'fat' => 0.1, 'allergens' => [], 'unit_price' => 4.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 2,   'potassium' => 125, 'phosphate' => 18, 'fiber' => 1.7]],
            ['name' => 'Pechay (Bok Choy, Boiled)',        'category' => 'vegetable', 'calories' => 13, 'protein' => 1.5, 'carbs' => 2.2, 'fat' => 0.2, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 65,  'potassium' => 252, 'calcium' => 105, 'fiber' => 1.0]],
            ['name' => 'Carrots (Boiled)',                 'category' => 'vegetable', 'calories' => 35, 'protein' => 0.8, 'carbs' => 8.2, 'fat' => 0.2, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 58,  'potassium' => 235, 'fiber' => 2.4]],

            // ── Fruits ────────────────────────────────────────────────────────
            ['name' => 'Banana (Lakatan)', 'category' => 'fruit', 'calories' => 89, 'protein' => 1.1, 'carbs' => 23.0, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 7.00, 'serving_unit' => 'piece', 'serving_size' => 100, 'micronutrients' => ['sodium' => 1, 'potassium' => 358, 'fiber' => 2.6]],
            ['name' => 'Papaya (Ripe)',    'category' => 'fruit', 'calories' => 43, 'protein' => 0.5, 'carbs' => 11.0, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g',     'serving_size' => 100, 'micronutrients' => ['sodium' => 8, 'potassium' => 182, 'fiber' => 1.7]],

            // ── Dairy ─────────────────────────────────────────────────────────
            ['name' => 'Low-fat Milk', 'category' => 'dairy', 'calories' => 42, 'protein' => 3.4, 'carbs' => 5.0, 'fat' => 1.0, 'allergens' => ['milk'], 'unit_price' => 12.00, 'serving_unit' => 'ml', 'serving_size' => 100, 'micronutrients' => ['sodium' => 44, 'potassium' => 150, 'calcium' => 120, 'phosphate' => 95]],
        ];

        foreach ($foods as $food) {
            FoodItem::updateOrCreate(
                ['name' => $food['name']],
                $food
            );
        }
    }
}
