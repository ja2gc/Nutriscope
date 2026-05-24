<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\FoodItem;
use App\Models\User;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (!$rnd) return;

        $recipes = [
            [
                'name' => 'Plain Rice Meal',
                'category' => 'Staple',
                'prep_notes' => 'Steamed white rice, standard ward portion.',
                'ingredients' => [['Steamed White Rice', 200, 'g']],
            ],
            [
                'name' => 'Brown Rice Meal',
                'category' => 'Staple',
                'prep_notes' => 'Steamed brown rice, higher fiber alternative.',
                'ingredients' => [['Steamed Brown Rice', 200, 'g']],
            ],
            [
                'name' => 'Lugaw with Egg',
                'category' => 'Soft Diet',
                'prep_notes' => 'Soft rice porridge with boiled egg. Suitable for post-op or soft diet orders.',
                'ingredients' => [['Lugaw (Rice Porridge)', 300, 'g'], ['Scrambled Egg', 70, 'g']],
            ],
            [
                'name' => 'Boiled Chicken Breast with Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Unseasoned boiled chicken. Low sodium.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Chicken Breast (Boiled)', 120, 'g']],
            ],
            [
                'name' => 'Chicken Adobo with Brown Rice',
                'category' => 'Regular Diet',
                'prep_notes' => 'Standard chicken adobo. Moderate sodium — not for sodium-restricted diets.',
                'ingredients' => [['Steamed Brown Rice', 180, 'g'], ['Chicken Breast (Adobo)', 100, 'g']],
            ],
            [
                'name' => 'Tinola with Rice',
                'category' => 'Soups',
                'prep_notes' => 'Ginger-based chicken soup. Light, suitable for general diet.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Tinola (Chicken Ginger Soup)', 250, 'g']],
            ],
            [
                'name' => 'Steamed Bangus with Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Steamed milkfish. Good source of omega-3.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Bangus (Milkfish, Steamed)', 100, 'g']],
            ],
            [
                'name' => 'Tokwa with Kangkong and Rice',
                'category' => 'Vegetarian',
                'prep_notes' => 'Soy-based protein with leafy vegetable. Good for non-meat patients.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Tokwa (Firm Tofu)', 100, 'g'], ['Kangkong (Water Spinach, Sauteed)', 80, 'g']],
            ],
            [
                'name' => 'Monggo Soup with Rice',
                'category' => 'High Fiber',
                'prep_notes' => 'Mung bean soup. High fiber, good for diabetic and general patients.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Monggo (Mung Bean Soup)', 200, 'g']],
            ],
            [
                'name' => 'Oatmeal Breakfast',
                'category' => 'Breakfast',
                'prep_notes' => 'Plain oatmeal — no sugar. Add banana if allowed.',
                'ingredients' => [['Unsweetened Oatmeal', 150, 'g'], ['Banana (Lakatan)', 100, 'g']],
            ],
            [
                'name' => 'Egg and Rice Breakfast',
                'category' => 'Breakfast',
                'prep_notes' => 'Scrambled egg with white rice. Standard ward breakfast.',
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Scrambled Egg', 70, 'g']],
            ],
            [
                'name' => 'Vegetable Plate',
                'category' => 'Vegetarian',
                'prep_notes' => 'Mixed boiled vegetables. Low calorie, high fiber side dish.',
                'ingredients' => [['Pechay (Bok Choy, Boiled)', 100, 'g'], ['Carrots (Boiled)', 80, 'g'], ['Sayote (Chayote, Boiled)', 80, 'g']],
            ],
            [
                'name' => 'Banana Snack',
                'category' => 'Snack',
                'prep_notes' => 'One piece lakatan banana. Quick potassium-rich snack — avoid in high-K diets.',
                'ingredients' => [['Banana (Lakatan)', 100, 'g']],
            ],
            [
                'name' => 'Crackers and Milk Snack',
                'category' => 'Snack',
                'prep_notes' => 'Light snack for between-meal nourishment.',
                'ingredients' => [['Skyflakes Crackers', 33, 'g'], ['Low-fat Milk', 150, 'ml']],
            ],
            [
                'name' => 'Ampalaya with Tokwa and Brown Rice',
                'category' => 'Diabetic-Friendly',
                'prep_notes' => 'Bitter gourd with tofu. Good for DM patients — low GI, high fiber.',
                'ingredients' => [['Steamed Brown Rice', 150, 'g'], ['Ampalaya (Bitter Gourd, Sauteed)', 100, 'g'], ['Tokwa (Firm Tofu)', 80, 'g']],
            ],
            [
                'name' => 'Chicken and Vegetable Lugaw',
                'category' => 'Soft Diet',
                'prep_notes' => 'Soft diet with additional protein. Post-op suitable.',
                'ingredients' => [['Lugaw (Rice Porridge)', 300, 'g'], ['Chicken Breast (Boiled)', 80, 'g'], ['Sayote (Chayote, Boiled)', 60, 'g']],
            ],
        ];

        foreach ($recipes as $recipeData) {
            $existing = Recipe::where('name', $recipeData['name'])->first();
            if ($existing) continue;

            $totalCalories = 0;
            $totalProtein = 0;
            $totalCarbs = 0;
            $totalFat = 0;
            $totalCost = 0;
            $ingredientRows = [];

            foreach ($recipeData['ingredients'] as [$foodName, $qty, $unit]) {
                $food = FoodItem::where('name', $foodName)->first();
                if (!$food) continue;

                $factor = $qty / $food->serving_size;
                $totalCalories += $food->calories * $factor;
                $totalProtein  += $food->protein * $factor;
                $totalCarbs    += $food->carbs * $factor;
                $totalFat      += $food->fat * $factor;
                $totalCost     += $food->unit_price * ($qty / 100);

                $ingredientRows[] = ['food' => $food, 'qty' => $qty, 'unit' => $unit];
            }

            $recipe = Recipe::create([
                'rnd_user_id'    => $rnd->id,
                'name'           => $recipeData['name'],
                'category'       => $recipeData['category'],
                'prep_notes'     => $recipeData['prep_notes'],
                'cost'           => round($totalCost, 2),
                'total_calories' => round($totalCalories, 2),
                'total_protein'  => round($totalProtein, 2),
                'total_carbs'    => round($totalCarbs, 2),
                'total_fat'      => round($totalFat, 2),
                'servings'       => 1,
            ]);

            foreach ($ingredientRows as $row) {
                RecipeIngredient::create([
                    'recipe_id'    => $recipe->id,
                    'food_item_id' => $row['food']->id,
                    'quantity'     => $row['qty'],
                    'unit'         => $row['unit'],
                ]);
            }
        }
    }
}
