<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FoodItem;

class FoodItemsSeeder extends Seeder
{
    public function run(): void
    {
        $foods = [
            // Staples
            ['name' => 'Steamed White Rice', 'category' => 'Grains', 'calories' => 130, 'protein' => 2.7, 'carbs' => 28.2, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 2.50, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 1, 'potassium' => 35, 'phosphate' => 43]],
            ['name' => 'Steamed Brown Rice', 'category' => 'Grains', 'calories' => 112, 'protein' => 2.6, 'carbs' => 23.5, 'fat' => 0.9, 'allergens' => [], 'unit_price' => 3.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 1, 'potassium' => 79, 'phosphate' => 83, 'fiber' => 1.8]],
            ['name' => 'Pandesal', 'category' => 'Breads', 'calories' => 290, 'protein' => 8.5, 'carbs' => 55.0, 'fat' => 4.0, 'allergens' => ['gluten', 'eggs', 'dairy'], 'unit_price' => 5.00, 'serving_unit' => 'piece', 'serving_size' => 60, 'micronutrients' => ['sodium' => 350, 'potassium' => 80]],
            ['name' => 'Lugaw (Rice Porridge)', 'category' => 'Grains', 'calories' => 65, 'protein' => 1.5, 'carbs' => 13.5, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 1.50, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 180, 'potassium' => 20]],

            // Protein
            ['name' => 'Chicken Breast (Boiled)', 'category' => 'Poultry', 'calories' => 165, 'protein' => 31.0, 'carbs' => 0.0, 'fat' => 3.6, 'allergens' => ['chicken'], 'unit_price' => 18.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 74, 'potassium' => 256, 'phosphate' => 220]],
            ['name' => 'Chicken Breast (Adobo)', 'category' => 'Poultry', 'calories' => 185, 'protein' => 28.0, 'carbs' => 2.0, 'fat' => 7.0, 'allergens' => ['chicken', 'soy'], 'unit_price' => 22.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 580, 'potassium' => 240, 'phosphate' => 210]],
            ['name' => 'Bangus (Milkfish, Steamed)', 'category' => 'Fish', 'calories' => 148, 'protein' => 20.5, 'carbs' => 0.0, 'fat' => 7.0, 'allergens' => ['fish'], 'unit_price' => 20.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 65, 'potassium' => 300, 'phosphate' => 190, 'omega3' => 1.2]],
            ['name' => 'Tinola (Chicken Ginger Soup)', 'category' => 'Soups', 'calories' => 95, 'protein' => 12.0, 'carbs' => 4.5, 'fat' => 3.5, 'allergens' => ['chicken'], 'unit_price' => 25.00, 'serving_unit' => 'g', 'serving_size' => 250, 'micronutrients' => ['sodium' => 320, 'potassium' => 280, 'phosphate' => 150]],
            ['name' => 'Scrambled Egg', 'category' => 'Eggs', 'calories' => 149, 'protein' => 10.0, 'carbs' => 1.6, 'fat' => 11.5, 'allergens' => ['eggs'], 'unit_price' => 8.00, 'serving_unit' => 'piece', 'serving_size' => 70, 'micronutrients' => ['sodium' => 180, 'potassium' => 120, 'phosphate' => 150, 'cholesterol' => 210]],
            ['name' => 'Tokwa (Firm Tofu)', 'category' => 'Legumes', 'calories' => 76, 'protein' => 8.1, 'carbs' => 1.9, 'fat' => 4.2, 'allergens' => ['soy'], 'unit_price' => 10.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 7, 'potassium' => 121, 'phosphate' => 97, 'calcium' => 350]],
            ['name' => 'Monggo (Mung Bean Soup)', 'category' => 'Legumes', 'calories' => 105, 'protein' => 7.0, 'carbs' => 19.0, 'fat' => 0.4, 'allergens' => [], 'unit_price' => 8.00, 'serving_unit' => 'g', 'serving_size' => 200, 'micronutrients' => ['sodium' => 240, 'potassium' => 266, 'phosphate' => 99, 'fiber' => 3.5]],

            // Vegetables
            ['name' => 'Kangkong (Water Spinach, Sauteed)', 'category' => 'Vegetables', 'calories' => 35, 'protein' => 2.6, 'carbs' => 5.4, 'fat' => 0.5, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 45, 'potassium' => 312, 'calcium' => 67, 'fiber' => 2.1]],
            ['name' => 'Ampalaya (Bitter Gourd, Sauteed)', 'category' => 'Vegetables', 'calories' => 25, 'protein' => 1.0, 'carbs' => 4.3, 'fat' => 0.4, 'allergens' => [], 'unit_price' => 6.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 30, 'potassium' => 290, 'fiber' => 2.6]],
            ['name' => 'Sayote (Chayote, Boiled)', 'category' => 'Vegetables', 'calories' => 19, 'protein' => 0.8, 'carbs' => 4.5, 'fat' => 0.1, 'allergens' => [], 'unit_price' => 4.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 2, 'potassium' => 125, 'phosphate' => 18, 'fiber' => 1.7]],
            ['name' => 'Pechay (Bok Choy, Boiled)', 'category' => 'Vegetables', 'calories' => 13, 'protein' => 1.5, 'carbs' => 2.2, 'fat' => 0.2, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 65, 'potassium' => 252, 'calcium' => 105, 'fiber' => 1.0]],
            ['name' => 'Carrots (Boiled)', 'category' => 'Vegetables', 'calories' => 35, 'protein' => 0.8, 'carbs' => 8.2, 'fat' => 0.2, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 58, 'potassium' => 235, 'fiber' => 2.4]],

            // Fruits / Snacks
            ['name' => 'Banana (Lakatan)', 'category' => 'Fruits', 'calories' => 89, 'protein' => 1.1, 'carbs' => 23.0, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 7.00, 'serving_unit' => 'piece', 'serving_size' => 100, 'micronutrients' => ['sodium' => 1, 'potassium' => 358, 'fiber' => 2.6]],
            ['name' => 'Papaya (Ripe)', 'category' => 'Fruits', 'calories' => 43, 'protein' => 0.5, 'carbs' => 11.0, 'fat' => 0.3, 'allergens' => [], 'unit_price' => 5.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 8, 'potassium' => 182, 'fiber' => 1.7]],
            ['name' => 'Skyflakes Crackers', 'category' => 'Snacks', 'calories' => 130, 'protein' => 2.5, 'carbs' => 22.0, 'fat' => 3.5, 'allergens' => ['gluten'], 'unit_price' => 8.00, 'serving_unit' => 'pack', 'serving_size' => 33, 'micronutrients' => ['sodium' => 200, 'potassium' => 30]],
            ['name' => 'Unsweetened Oatmeal', 'category' => 'Grains', 'calories' => 71, 'protein' => 2.5, 'carbs' => 12.0, 'fat' => 1.5, 'allergens' => ['gluten'], 'unit_price' => 6.00, 'serving_unit' => 'g', 'serving_size' => 100, 'micronutrients' => ['sodium' => 49, 'potassium' => 61, 'fiber' => 1.7]],
            ['name' => 'Low-fat Milk', 'category' => 'Dairy', 'calories' => 42, 'protein' => 3.4, 'carbs' => 5.0, 'fat' => 1.0, 'allergens' => ['dairy'], 'unit_price' => 12.00, 'serving_unit' => 'ml', 'serving_size' => 100, 'micronutrients' => ['sodium' => 44, 'potassium' => 150, 'calcium' => 120, 'phosphate' => 95]],
        ];

        foreach ($foods as $food) {
            FoodItem::firstOrCreate(
                ['name' => $food['name']],
                array_merge($food, [
                    'allergens' => $food['allergens'],
                    'micronutrients' => $food['micronutrients'],
                ])
            );
        }
    }
}
