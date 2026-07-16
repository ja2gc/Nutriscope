<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) {
            return;
        }

        $recipes = [
            // ── Staples ───────────────────────────────────────────────────────
            [
                'name' => 'Plain White Rice Meal',
                'category' => 'Staple',
                'prep_notes' => 'Steamed white rice. Standard ward portion.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 200, 'g']],
            ],
            [
                'name' => 'Plain Brown Rice Meal',
                'category' => 'Staple',
                'prep_notes' => 'Steamed brown rice. Higher fiber alternative for diabetic and cardiac diets.',
                'servings' => 1,
                'ingredients' => [['Steamed Brown Rice', 200, 'g']],
            ],

            // ── Soft / Liquid Diet ────────────────────────────────────────────
            [
                'name' => 'Lugaw with Egg (Soft Diet)',
                'category' => 'Soft Diet',
                'prep_notes' => 'Rice porridge with hard-boiled egg. Suitable for post-op, soft diet, and swallowing difficulty orders.',
                'servings' => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 300, 'g'], ['Egg (Hard Boiled)', 50, 'g']],
            ],
            [
                'name' => 'Lugaw with Chicken (Soft Diet)',
                'category' => 'Soft Diet',
                'prep_notes' => 'Rice porridge with shredded chicken breast. High-protein soft diet option.',
                'servings' => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 300, 'g'], ['Chicken Breast (Cooked)', 60, 'g']],
            ],

            // ── High Protein ──────────────────────────────────────────────────
            [
                'name' => 'Boiled Chicken Breast with White Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Unseasoned boiled chicken breast. Low sodium, high protein. Suitable for most therapeutic diets.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Chicken Breast (Cooked)', 120, 'g']],
            ],
            [
                'name' => 'Chicken Breast with Brown Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Boiled chicken with high-fiber brown rice. Good for DM and cardiac patients.',
                'servings' => 1,
                'ingredients' => [['Steamed Brown Rice', 180, 'g'], ['Chicken Breast (Cooked)', 120, 'g']],
            ],
            [
                'name' => 'Steamed Tilapia with Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Lean white fish. Low sodium, good phosphate source. Suitable for most diets except CKD (monitor phosphate).',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Tilapia (Cooked)', 100, 'g']],
            ],
            [
                'name' => 'Milkfish (Bangus) with Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Steamed milkfish. Rich in omega-3. Avoid in fish allergy patients.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Milkfish / Bangus (Cooked)', 100, 'g']],
            ],
            [
                'name' => 'Sardines with Rice',
                'category' => 'High Protein',
                'prep_notes' => 'Canned sardines in water (drained). Cost-effective protein. Note: moderate sodium.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Sardines (Canned in Water)', 90, 'g']],
            ],
            [
                'name' => 'Egg and Rice Breakfast',
                'category' => 'Breakfast',
                'prep_notes' => 'Hard-boiled egg with steamed white rice. Standard ward breakfast.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Egg (Hard Boiled)', 50, 'g']],
            ],

            // ── Vegetarian / Plant-based ──────────────────────────────────────
            [
                'name' => 'Tokwa with Kangkong and Rice',
                'category' => 'Vegetarian',
                'prep_notes' => 'Firm tofu with water spinach and white rice. Plant-based protein. Contains soy — avoid for soybean allergy.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Firm Tofu (Tokwa)', 100, 'g'], ['Water Spinach (Kangkong)', 80, 'g']],
            ],
            [
                'name' => 'Monggo with Brown Rice',
                'category' => 'High Fiber',
                'prep_notes' => 'Mung beans with brown rice. High fiber, suitable for DM and constipation patients.',
                'servings' => 1,
                'ingredients' => [['Steamed Brown Rice', 180, 'g'], ['Mung Beans (Cooked)', 150, 'g']],
            ],

            // ── Diabetic-Friendly ─────────────────────────────────────────────
            [
                'name' => 'Ampalaya with Tokwa and Brown Rice',
                'category' => 'Diabetic-Friendly',
                'prep_notes' => 'Bitter melon with firm tofu and brown rice. Low GI, high fiber. Traditional diabetic meal in Filipino diet.',
                'servings' => 1,
                'ingredients' => [['Steamed Brown Rice', 150, 'g'], ['Bitter Melon (Ampalaya)', 100, 'g'], ['Firm Tofu (Tokwa)', 80, 'g']],
            ],
            [
                'name' => 'Oatmeal with Banana',
                'category' => 'Breakfast',
                'prep_notes' => 'Plain oatmeal with banana. No added sugar. Suitable for DM breakfast — monitor potassium if renal diet.',
                'servings' => 1,
                'ingredients' => [['Oatmeal (Plain, Cooked)', 150, 'g'], ['Banana (Raw)', 80, 'g']],
            ],
            [
                'name' => 'Sweet Potato with Chicken',
                'category' => 'Diabetic-Friendly',
                'prep_notes' => 'Baked sweet potato with boiled chicken breast. Low GI carbohydrate alternative.',
                'servings' => 1,
                'ingredients' => [['Sweet Potato / Kamote', 150, 'g'], ['Chicken Breast (Cooked)', 100, 'g']],
            ],

            // ── Vegetable Sides ───────────────────────────────────────────────
            [
                'name' => 'Mixed Vegetable Plate',
                'category' => 'Vegetarian',
                'prep_notes' => 'Boiled mixed vegetables. Low calorie, high fiber side dish suitable for all therapeutic diets.',
                'servings' => 1,
                'ingredients' => [['Bok Choy / Pechay (Cooked)', 100, 'g'], ['Carrots (Cooked)', 80, 'g'], ['Chayote / Sayote (Cooked)', 80, 'g']],
            ],

            // ── Snacks ────────────────────────────────────────────────────────
            [
                'name' => 'Banana Snack',
                'category' => 'Snack',
                'prep_notes' => 'One banana. Quick, potassium-rich snack — avoid in high-K and renal-restricted diets.',
                'servings' => 1,
                'ingredients' => [['Banana (Raw)', 100, 'g']],
            ],
            [
                'name' => 'Crackers and Milk Snack',
                'category' => 'Snack',
                'prep_notes' => 'Wheat crackers with low-fat milk. Between-meal nourishment. Contains wheat and milk.',
                'servings' => 1,
                'ingredients' => [['Wheat Crackers', 33, 'g'], ['Low-fat Milk (1%)', 150, 'ml']],
            ],
            [
                'name' => 'Papaya Snack',
                'category' => 'Snack',
                'prep_notes' => 'Fresh papaya. Rich in vitamin C, fiber. Suitable for most diets.',
                'servings' => 1,
                'ingredients' => [['Papaya (Raw)', 150, 'g']],
            ],

            // ── Pork Dishes ───────────────────────────────────────────────────
            [
                'name' => 'Pork Loin with Rice',
                'category' => 'Regular Diet',
                'prep_notes' => 'Boiled pork loin with white rice. Moderate fat. Not recommended for low-fat or low-sodium diets without modification.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 180, 'g'], ['Pork Loin (Cooked)', 100, 'g']],
            ],

            // ── Carb-Dominant Breakfasts ──────────────────────────────────────
            [
                'name' => 'Champorado (Chocolate Rice Porridge)',
                'category' => 'Breakfast',
                'prep_notes' => 'Filipino chocolate rice porridge made with glutinous rice and cocoa. Very carb-heavy, low protein. Good for weight gain and high-energy needs.',
                'servings' => 1,
                'ingredients' => [['Glutinous Rice (Cooked)', 200, 'g'], ['Cocoa Powder (Unsweetened)', 15, 'g'], ['Brown Sugar', 20, 'g']],
            ],
            [
                'name' => 'Sinangag (Garlic Fried Rice)',
                'category' => 'Breakfast',
                'prep_notes' => 'Filipino garlic fried rice. Mostly carbohydrates. Common Filipino breakfast staple. Low sodium version without soy sauce.',
                'servings' => 1,
                'ingredients' => [['Steamed White Rice', 200, 'g'], ['Garlic (Raw)', 10, 'g']],
            ],
            [
                'name' => 'Arroz Caldo (Chicken Rice Soup)',
                'category' => 'Breakfast',
                'prep_notes' => 'Filipino chicken congee with ginger. Balanced protein and carbs. Excellent for sick patients and soft diet.',
                'servings' => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 250, 'g'], ['Chicken Breast (Cooked)', 60, 'g'], ['Ginger Root (Raw)', 5, 'g']],
            ],
            [
                'name' => 'Plain Oatmeal with Brown Sugar',
                'category' => 'Breakfast',
                'prep_notes' => 'Plain cooked oatmeal lightly sweetened. High fiber, low protein. Suitable for diabetic and cardiac diets (reduce or omit sugar for diabetic patients).',
                'servings' => 1,
                'ingredients' => [['Oatmeal (Plain, Cooked)', 200, 'g'], ['Brown Sugar', 8, 'g']],
            ],
            [
                'name' => 'Pandesal with Egg',
                'category' => 'Breakfast',
                'prep_notes' => 'Filipino bread roll with boiled egg. Standard Filipino breakfast. Contains wheat.',
                'servings' => 1,
                'ingredients' => [['Pandesal (Filipino Bread Roll)', 80, 'g'], ['Egg (Hard Boiled)', 50, 'g']],
            ],

            // ── Carb-Only / Fruit Snacks ─────────────────────────────────────
            [
                'name' => 'Fresh Mango Serving',
                'category' => 'Snack',
                'prep_notes' => 'Fresh ripe Philippine mango. Naturally sweet, rich in vitamin C and A. Monitor potassium in renal diets.',
                'servings' => 1,
                'ingredients' => [['Mango (Raw)', 200, 'g']],
            ],
            [
                'name' => 'Fresh Watermelon Snack',
                'category' => 'Snack',
                'prep_notes' => 'Fresh watermelon. Very low calorie, hydrating. Good for general diet. Limit in dialysis/fluid restriction.',
                'servings' => 1,
                'ingredients' => [['Watermelon (Raw)', 250, 'g']],
            ],
            [
                'name' => 'Papaya with Calamansi',
                'category' => 'Snack',
                'prep_notes' => 'Fresh papaya with calamansi squeeze. Rich in vitamin C, fiber, and beta-carotene. Suitable for all diets.',
                'servings' => 1,
                'ingredients' => [['Papaya (Raw)', 200, 'g'], ['Calamansi / Lime Juice', 15, 'ml']],
            ],
            [
                'name' => 'Guava Snack',
                'category' => 'Snack',
                'prep_notes' => 'Fresh guava. Very high in vitamin C. High fiber. Low calorie. Suitable for diabetic and weight loss diets.',
                'servings' => 1,
                'ingredients' => [['Guava (Raw)', 150, 'g']],
            ],
            [
                'name' => 'Boiled Corn (Mais)',
                'category' => 'Snack',
                'prep_notes' => 'Plain boiled corn on the cob or kernels. Filipino street food staple. Good carbohydrate source, moderate fiber.',
                'servings' => 1,
                'ingredients' => [['Sweet Corn (Cooked)', 150, 'g']],
            ],
            [
                'name' => 'Boiled Kamote (Sweet Potato)',
                'category' => 'Snack',
                'prep_notes' => 'Plain boiled sweet potato. Low GI, high in beta-carotene and fiber. Excellent for diabetic and weight management diets.',
                'servings' => 1,
                'ingredients' => [['Sweet Potato / Kamote', 200, 'g']],
            ],
            [
                'name' => 'Pineapple Snack',
                'category' => 'Snack',
                'prep_notes' => 'Fresh pineapple. Contains bromelain, good for digestion. Monitor potassium in renal diet.',
                'servings' => 1,
                'ingredients' => [['Pineapple (Raw)', 150, 'g']],
            ],
            [
                'name' => 'Jackfruit (Langka) Snack',
                'category' => 'Snack',
                'prep_notes' => 'Fresh jackfruit. Naturally sweet, high fiber, moderate carbohydrates. Rich in B vitamins.',
                'servings' => 1,
                'ingredients' => [['Jackfruit (Raw)', 150, 'g']],
            ],

            // ── Vegetable-Dominant Dishes ─────────────────────────────────────
            [
                'name' => 'Pinakbet (Hospital Version)',
                'category' => 'Vegetable',
                'prep_notes' => 'Mixed vegetable stew without bagoong (shrimp paste) for low-sodium hospital version. Suitable for cardiac and hypertension diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Squash / Kalabasa (Cooked)', 100, 'g'], ['String Beans / Sitaw', 80, 'g'],
                    ['Eggplant / Talong (Cooked)', 80, 'g'], ['Bitter Melon (Ampalaya)', 60, 'g'],
                    ['Tomato (Raw)', 30, 'g'], ['Steamed White Rice', 150, 'g'],
                ],
            ],
            [
                'name' => 'Chopsuey with Rice',
                'category' => 'Vegetable',
                'prep_notes' => 'Chinese-Filipino mixed vegetable stir-fry with white rice. Lightly seasoned. Good for all therapeutic diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Carrots (Cooked)', 80, 'g'], ['Cabbage / Repolyo (Cooked)', 80, 'g'],
                    ['String Beans / Sitaw', 60, 'g'], ['Chayote / Sayote (Cooked)', 60, 'g'],
                    ['Steamed White Rice', 160, 'g'],
                ],
            ],
            [
                'name' => 'Bulanglang (Vegetable Soup)',
                'category' => 'Vegetable',
                'prep_notes' => 'Tagalog-Visayan mixed vegetable soup. Very low calorie, high micronutrients. Suitable for all diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Squash / Kalabasa (Cooked)', 100, 'g'], ['String Beans / Sitaw', 80, 'g'],
                    ['Water Spinach (Kangkong)', 60, 'g'], ['Steamed White Rice', 150, 'g'],
                ],
            ],
            [
                'name' => 'Ginisang Sayote (Sautéed Chayote)',
                'category' => 'Vegetable',
                'prep_notes' => 'Lightly sautéed chayote with garlic and onion. Low calorie, good for renal diet (low potassium), suitable as side dish.',
                'servings' => 1,
                'ingredients' => [
                    ['Chayote / Sayote (Cooked)', 200, 'g'], ['Garlic (Raw)', 5, 'g'],
                    ['Onion (Raw)', 20, 'g'], ['Steamed White Rice', 150, 'g'],
                ],
            ],
            [
                'name' => 'Ensaladang Talong (Eggplant Salad)',
                'category' => 'Vegetable',
                'prep_notes' => 'Roasted eggplant salad with tomato and onion. Low calorie Filipino salad. Good for cardiac and weight loss diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Eggplant / Talong (Cooked)', 150, 'g'], ['Tomato (Raw)', 50, 'g'],
                    ['Onion (Raw)', 30, 'g'],
                ],
            ],
            [
                'name' => 'Ampalaya Salad with Tomato',
                'category' => 'Diabetic-Friendly',
                'prep_notes' => 'Bitter melon salad with tomato. Low calorie, high fiber. Traditional diabetes management food in Filipino culture.',
                'servings' => 1,
                'ingredients' => [
                    ['Bitter Melon (Ampalaya)', 120, 'g'], ['Tomato (Raw)', 60, 'g'],
                    ['Onion (Raw)', 20, 'g'], ['Egg (Hard Boiled)', 50, 'g'],
                ],
            ],

            // ── Balanced Filipino Mains ───────────────────────────────────────
            [
                'name' => 'Mongo Guisado with Rice (Mung Bean Stew)',
                'category' => 'High Fiber',
                'prep_notes' => 'Sautéed mung bean stew with kangkong and garlic. High fiber, moderate protein, very Filipino. Good for diabetic and cardiac diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Mung Beans (Cooked)', 150, 'g'], ['Water Spinach (Kangkong)', 60, 'g'],
                    ['Garlic (Raw)', 5, 'g'], ['Onion (Raw)', 20, 'g'],
                    ['Steamed White Rice', 150, 'g'],
                ],
            ],
            [
                'name' => 'Paksiw na Bangus with Rice',
                'category' => 'Regular Diet',
                'prep_notes' => 'Milkfish braised in vinegar and spices. Filipino fish dish. Moderate sodium from vinegar braising. Monitor in strict low-sodium diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Milkfish / Bangus (Cooked)', 100, 'g'], ['Bitter Melon (Ampalaya)', 40, 'g'],
                    ['Ginger Root (Raw)', 5, 'g'], ['Steamed White Rice', 180, 'g'],
                ],
            ],
            [
                'name' => 'Sinigang na Bangus (Simplified)',
                'category' => 'Regular Diet',
                'prep_notes' => 'Milkfish in tamarind-style sour broth with vegetables. Hospital version without commercial sinigang mix (lower sodium). High in omega-3.',
                'servings' => 1,
                'ingredients' => [
                    ['Milkfish / Bangus (Cooked)', 100, 'g'], ['Water Spinach (Kangkong)', 80, 'g'],
                    ['Tomato (Raw)', 40, 'g'], ['Steamed White Rice', 160, 'g'],
                ],
            ],
            [
                'name' => 'Tinolang Manok (Chicken Ginger Soup)',
                'category' => 'Regular Diet',
                'prep_notes' => 'Filipino chicken soup with ginger, chayote, and malunggay. Balanced, low fat. Suitable for most therapeutic diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Chicken Thigh (Cooked)', 80, 'g'], ['Chayote / Sayote (Cooked)', 100, 'g'],
                    ['Ginger Root (Raw)', 8, 'g'], ['Bok Choy / Pechay (Cooked)', 60, 'g'],
                    ['Steamed White Rice', 160, 'g'],
                ],
            ],
            [
                'name' => 'Ginisang Monggo with Sardines',
                'category' => 'High Fiber',
                'prep_notes' => 'Mung bean soup with canned sardines and kangkong. Budget-friendly, high fiber, good omega-3.',
                'servings' => 1,
                'ingredients' => [
                    ['Mung Beans (Cooked)', 120, 'g'], ['Sardines (Canned in Water)', 50, 'g'],
                    ['Water Spinach (Kangkong)', 60, 'g'], ['Garlic (Raw)', 5, 'g'],
                    ['Steamed White Rice', 150, 'g'],
                ],
            ],
            [
                'name' => 'Tilapia Sinigang (Simplified)',
                'category' => 'High Protein',
                'prep_notes' => 'Tilapia in light sour broth with vegetables. Low fat, lean protein. Hospital version without commercial sinigang mix.',
                'servings' => 1,
                'ingredients' => [
                    ['Tilapia (Cooked)', 120, 'g'], ['String Beans / Sitaw', 60, 'g'],
                    ['Eggplant / Talong (Cooked)', 60, 'g'], ['Tomato (Raw)', 40, 'g'],
                    ['Steamed White Rice', 160, 'g'],
                ],
            ],

            // ── Regular Diet Mains ────────────────────────────────────────────
            [
                'name' => 'Mackerel Adobo Flakes with Rice',
                'category' => 'Regular Diet',
                'prep_notes' => 'Mackerel flaked in light adobo sauce with garlic. Good omega-3, moderate protein. Suitable for most diets.',
                'servings' => 1,
                'ingredients' => [
                    ['Mackerel (Cooked)', 80, 'g'], ['Garlic (Raw)', 5, 'g'],
                    ['Steamed White Rice', 180, 'g'],
                ],
            ],

            // ── Cassava / Root Dishes ──────────────────────────────────────────
            [
                'name' => 'Boiled Cassava (Kamoteng Kahoy)',
                'category' => 'Snack',
                'prep_notes' => 'Plain boiled cassava. Very high carbohydrate, low protein. Traditional Filipino root crop snack. Good for weight gain diets.',
                'servings' => 1,
                'ingredients' => [['Cassava / Kamoteng Kahoy', 200, 'g']],
            ],
            [
                'name' => 'Ginataan (Coconut Fruit Dessert)',
                'category' => 'Snack',
                'prep_notes' => 'Filipino dessert with sweet potato and banana in coconut milk. High energy, moderate fat. Good for weight gain and high-energy needs.',
                'servings' => 1,
                'ingredients' => [
                    ['Sweet Potato / Kamote', 100, 'g'], ['Banana (Raw)', 80, 'g'],
                    ['Coconut Milk (Canned)', 50, 'ml'],
                ],
            ],
            [
                'name' => 'Lugaw (Plain Rice Porridge)',
                'category' => 'Soft Diet',
                'prep_notes' => 'Plain rice porridge without any topping. Very low protein, mostly carbohydrates. Suitable for strict renal diets, post-op, and low-residue orders.',
                'servings' => 1,
                'ingredients' => [['Rice Porridge (Lugaw)', 350, 'g']],
            ],
        ];

        foreach ($recipes as $recipeData) {
            $ingredientRows = [];

            foreach ($recipeData['ingredients'] as [$foodName, $qty, $unit]) {
                $food = FoodItem::where('name', $foodName)->first();
                if (! $food) {
                    $this->command->warn("  Recipe '{$recipeData['name']}': ingredient '{$foodName}' not found — skipping.");

                    continue;
                }

                $ingredientRows[] = ['food' => $food, 'qty' => $qty, 'unit' => $unit];
            }

            if (empty($ingredientRows)) {
                continue;
            }

            $created = DB::transaction(function () use ($recipeData, $rnd, $ingredientRows): bool {
                // Update only this canonical demo recipe; unrelated RND recipes survive reruns.
                $recipe = Recipe::updateOrCreate(
                    ['name' => $recipeData['name']],
                    [
                        'rnd_user_id' => $rnd->id,
                        'category' => $recipeData['category'],
                        'prep_notes' => $recipeData['prep_notes'],
                        'servings' => $recipeData['servings'] ?? 1,
                    ],
                );

                $created = $recipe->wasRecentlyCreated;
                $recipe->ingredients()->delete();
                foreach ($ingredientRows as $row) {
                    $recipe->ingredients()->create([
                        'food_item_id' => $row['food']->id,
                        'quantity' => $row['qty'],
                        'unit' => $row['unit'],
                    ]);
                }

                // Aggregate macros, water, and micronutrients from the synchronized ingredients.
                $recipe->recalculateTotals();

                return $created;
            });
            if ($created) {
                $this->command->info("  ✓ Recipe: {$recipeData['name']}");
            } else {
                $this->command->line("  – Synchronized: {$recipeData['name']}");
            }
        }
    }
}
