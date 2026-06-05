<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) return;

        $recipes = [
            // ── Staples ───────────────────────────────────────────────────────
            [
                'name'        => 'Plain White Rice Meal',
                'category'    => 'Staple',
                'prep_notes'  => 'Steamed white rice. Standard ward portion.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 200, 'g']],
            ],
            [
                'name'        => 'Plain Brown Rice Meal',
                'category'    => 'Staple',
                'prep_notes'  => 'Steamed brown rice. Higher fiber alternative for diabetic and cardiac diets.',
                'servings'    => 1,
                'ingredients' => [['Steamed Brown Rice', 200, 'g']],
            ],

            // ── Soft / Liquid Diet ────────────────────────────────────────────
            [
                'name'        => 'Lugaw with Egg (Soft Diet)',
                'category'    => 'Soft Diet',
                'prep_notes'  => 'Rice porridge with hard-boiled egg. Suitable for post-op, soft diet, and swallowing difficulty orders.',
                'servings'    => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 300, 'g'], ['Egg (Hard Boiled)', 50, 'g']],
            ],
            [
                'name'        => 'Lugaw with Chicken (Soft Diet)',
                'category'    => 'Soft Diet',
                'prep_notes'  => 'Rice porridge with shredded chicken breast. High-protein soft diet option.',
                'servings'    => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 300, 'g'], ['Chicken Breast (Cooked)', 60, 'g']],
            ],

            // ── High Protein ──────────────────────────────────────────────────
            [
                'name'        => 'Boiled Chicken Breast with White Rice',
                'category'    => 'High Protein',
                'prep_notes'  => 'Unseasoned boiled chicken breast. Low sodium, high protein. Suitable for most therapeutic diets.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Chicken Breast (Cooked)', 120, 'g']],
            ],
            [
                'name'        => 'Chicken Breast with Brown Rice',
                'category'    => 'High Protein',
                'prep_notes'  => 'Boiled chicken with high-fiber brown rice. Good for DM and cardiac patients.',
                'servings'    => 1,
                'ingredients' => [['Steamed Brown Rice', 180, 'g'], ['Chicken Breast (Cooked)', 120, 'g']],
            ],
            [
                'name'        => 'Steamed Tilapia with Rice',
                'category'    => 'High Protein',
                'prep_notes'  => 'Lean white fish. Low sodium, good phosphate source. Suitable for most diets except CKD (monitor phosphate).',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Tilapia (Cooked)', 100, 'g']],
            ],
            [
                'name'        => 'Milkfish (Bangus) with Rice',
                'category'    => 'High Protein',
                'prep_notes'  => 'Steamed milkfish. Rich in omega-3. Avoid in fish allergy patients.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Milkfish / Bangus (Cooked)', 100, 'g']],
            ],
            [
                'name'        => 'Sardines with Rice',
                'category'    => 'High Protein',
                'prep_notes'  => 'Canned sardines in water (drained). Cost-effective protein. Note: moderate sodium.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Sardines (Canned in Water)', 90, 'g']],
            ],
            [
                'name'        => 'Egg and Rice Breakfast',
                'category'    => 'Breakfast',
                'prep_notes'  => 'Hard-boiled egg with steamed white rice. Standard ward breakfast.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Egg (Hard Boiled)', 50, 'g']],
            ],

            // ── Vegetarian / Plant-based ──────────────────────────────────────
            [
                'name'        => 'Tokwa with Kangkong and Rice',
                'category'    => 'Vegetarian',
                'prep_notes'  => 'Firm tofu with water spinach and white rice. Plant-based protein. Contains soy — avoid for soybean allergy.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Firm Tofu (Tokwa)', 100, 'g'], ['Water Spinach (Kangkong)', 80, 'g']],
            ],
            [
                'name'        => 'Monggo with Brown Rice',
                'category'    => 'High Fiber',
                'prep_notes'  => 'Mung beans with brown rice. High fiber, suitable for DM and constipation patients.',
                'servings'    => 1,
                'ingredients' => [['Steamed Brown Rice', 180, 'g'], ['Mung Beans (Cooked)', 150, 'g']],
            ],

            // ── Diabetic-Friendly ─────────────────────────────────────────────
            [
                'name'        => 'Ampalaya with Tokwa and Brown Rice',
                'category'    => 'Diabetic-Friendly',
                'prep_notes'  => 'Bitter melon with firm tofu and brown rice. Low GI, high fiber. Traditional diabetic meal in Filipino diet.',
                'servings'    => 1,
                'ingredients' => [['Steamed Brown Rice', 150, 'g'], ['Bitter Melon (Ampalaya)', 100, 'g'], ['Firm Tofu (Tokwa)', 80, 'g']],
            ],
            [
                'name'        => 'Oatmeal with Banana',
                'category'    => 'Breakfast',
                'prep_notes'  => 'Plain oatmeal with banana. No added sugar. Suitable for DM breakfast — monitor potassium if renal diet.',
                'servings'    => 1,
                'ingredients' => [['Oatmeal (Plain, Cooked)', 150, 'g'], ['Banana (Raw)', 80, 'g']],
            ],
            [
                'name'        => 'Sweet Potato with Chicken',
                'category'    => 'Diabetic-Friendly',
                'prep_notes'  => 'Baked sweet potato with boiled chicken breast. Low GI carbohydrate alternative.',
                'servings'    => 1,
                'ingredients' => [['Sweet Potato / Kamote', 150, 'g'], ['Chicken Breast (Cooked)', 100, 'g']],
            ],

            // ── Vegetable Sides ───────────────────────────────────────────────
            [
                'name'        => 'Mixed Vegetable Plate',
                'category'    => 'Vegetarian',
                'prep_notes'  => 'Boiled mixed vegetables. Low calorie, high fiber side dish suitable for all therapeutic diets.',
                'servings'    => 1,
                'ingredients' => [['Bok Choy / Pechay (Cooked)', 100, 'g'], ['Carrots (Cooked)', 80, 'g'], ['Chayote / Sayote (Cooked)', 80, 'g']],
            ],

            // ── Snacks ────────────────────────────────────────────────────────
            [
                'name'        => 'Banana Snack',
                'category'    => 'Snack',
                'prep_notes'  => 'One banana. Quick, potassium-rich snack — avoid in high-K and renal-restricted diets.',
                'servings'    => 1,
                'ingredients' => [['Banana (Raw)', 100, 'g']],
            ],
            [
                'name'        => 'Crackers and Milk Snack',
                'category'    => 'Snack',
                'prep_notes'  => 'Wheat crackers with low-fat milk. Between-meal nourishment. Contains wheat and milk.',
                'servings'    => 1,
                'ingredients' => [['Wheat Crackers', 33, 'g'], ['Low-fat Milk (1%)', 150, 'ml']],
            ],
            [
                'name'        => 'Papaya Snack',
                'category'    => 'Snack',
                'prep_notes'  => 'Fresh papaya. Rich in vitamin C, fiber. Suitable for most diets.',
                'servings'    => 1,
                'ingredients' => [['Papaya (Raw)', 150, 'g']],
            ],

            // ── Pork Dishes ───────────────────────────────────────────────────
            [
                'name'        => 'Pork Loin with Rice',
                'category'    => 'Regular Diet',
                'prep_notes'  => 'Boiled pork loin with white rice. Moderate fat. Not recommended for low-fat or low-sodium diets without modification.',
                'servings'    => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Pork Loin (Cooked)', 100, 'g']],
            ],
        ];

        foreach ($recipes as $recipeData) {
            // Skip if already exists
            if (Recipe::where('name', $recipeData['name'])->exists()) continue;

            $totalCalories = 0.0;
            $totalProtein  = 0.0;
            $totalCarbs    = 0.0;
            $totalFat      = 0.0;
            $ingredientRows = [];

            foreach ($recipeData['ingredients'] as [$foodName, $qty, $unit]) {
                $food = FoodItem::where('name', $foodName)->first();
                if (! $food) {
                    $this->command->warn("  Recipe '{$recipeData['name']}': ingredient '{$foodName}' not found — skipping.");
                    continue;
                }

                $servingSize = (float) ($food->serving_size ?: 100);
                $factor = $qty / $servingSize;

                $totalCalories += (float) $food->calories * $factor;
                $totalProtein  += (float) $food->protein  * $factor;
                $totalCarbs    += (float) $food->carbs    * $factor;
                $totalFat      += (float) $food->fat      * $factor;

                $ingredientRows[] = ['food' => $food, 'qty' => $qty, 'unit' => $unit];
            }

            if (empty($ingredientRows)) continue;

            $recipe = Recipe::create([
                'rnd_user_id'    => $rnd->id,
                'name'           => $recipeData['name'],
                'category'       => $recipeData['category'],
                'prep_notes'     => $recipeData['prep_notes'],
                'servings'       => $recipeData['servings'] ?? 1,
                'total_calories' => round($totalCalories, 2),
                'total_protein'  => round($totalProtein, 2),
                'total_carbs'    => round($totalCarbs, 2),
                'total_fat'      => round($totalFat, 2),
            ]);

            foreach ($ingredientRows as $row) {
                RecipeIngredient::create([
                    'recipe_id'    => $recipe->id,
                    'food_item_id' => $row['food']->id,
                    'quantity'     => $row['qty'],
                    'unit'         => $row['unit'],
                ]);
            }

            $this->command->info("  ✓ Recipe: {$recipeData['name']}");
        }
    }
}
