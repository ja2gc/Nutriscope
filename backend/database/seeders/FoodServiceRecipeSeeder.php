<?php

namespace Database\Seeders;

use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds 10 Filipino food service recipes.
 * Ingredients reference inventory rows (looked up by food_item name).
 * Idempotent: firstOrCreate by name + wasRecentlyCreated guard.
 * Runs AFTER InventorySeeder.
 */
class FoodServiceRecipeSeeder extends Seeder
{
    public function run(): void
    {
        $rnd = User::where('role', 'RND')->first();
        if (! $rnd) {
            $this->command->error('FoodServiceRecipeSeeder: No RND user found. Run AdminUserSeeder first.');
            return;
        }

        $recipes = [
            [
                'name'        => 'Kare-Kare Vegetables (No Bagoong)',
                'category'    => 'Vegetable',
                'prep_notes'  => 'Pampanga peanut vegetable stew. Peanut allergen — screen before serving.',
                'servings'    => 1,
                'ingredients' => [
                    ['Squash / Kalabasa (Cooked)', 100],
                    ['String Beans / Sitaw',        80],
                    ['Eggplant / Talong (Cooked)',   80],
                    ['Peanut Butter (Unsalted)',     30],
                    ['Steamed White Rice',          150],
                ],
            ],
            [
                'name'        => 'Sisig Tofu (Hospital Low-Fat)',
                'category'    => 'Vegetarian',
                'prep_notes'  => 'Kapampangan sisig adapted with tofu. Low fat, suitable for most diets.',
                'servings'    => 1,
                'ingredients' => [
                    ['Firm Tofu (Tokwa)',       150],
                    ['Onion (Raw)',              40],
                    ['Calamansi / Lime Juice',  15],
                    ['Steamed White Rice',      160],
                ],
            ],
            [
                'name'        => 'Caldereta-Style Vegetable Stew',
                'category'    => 'Vegetable',
                'prep_notes'  => 'Pampanga tomato-based stew. Hospital version — vegetables only.',
                'servings'    => 1,
                'ingredients' => [
                    ['Carrots (Cooked)',              100],
                    ['Cabbage / Repolyo (Cooked)',     80],
                    ['Tomato (Cooked)',               100],
                    ['Squash / Kalabasa (Cooked)',     80],
                    ['Steamed White Rice',            150],
                ],
            ],
            [
                'name'        => 'Bringhe (Glutinous Rice in Coconut)',
                'category'    => 'Staple',
                'prep_notes'  => 'Traditional Pampanga festival dish. High energy — weight gain and general diets.',
                'servings'    => 1,
                'ingredients' => [
                    ['Glutinous Rice (Cooked)', 180],
                    ['Coconut Milk (Canned)',    60],
                ],
            ],
            [
                'name'        => 'Chicken Adobo with Rice',
                'category'    => 'Regular Diet',
                'prep_notes'  => 'Filipino adobo braised in vinegar and garlic. Moderate sodium.',
                'servings'    => 1,
                'ingredients' => [
                    ['Chicken Thigh (Cooked)', 100],
                    ['Garlic (Raw)',              8],
                    ['Steamed White Rice',      180],
                ],
            ],
            [
                'name'        => 'Sinigang na Bangus (Simplified)',
                'category'    => 'Regular Diet',
                'prep_notes'  => 'Milkfish in tamarind-style broth. Hospital version — no commercial sinigang mix.',
                'servings'    => 1,
                'ingredients' => [
                    ['Milkfish / Bangus (Cooked)', 100],
                    ['Water Spinach (Kangkong)',    80],
                    ['Tomato (Raw)',                40],
                    ['Steamed White Rice',         160],
                ],
            ],
            [
                'name'        => 'Tinolang Manok (Chicken Ginger Soup)',
                'category'    => 'Regular Diet',
                'prep_notes'  => 'Chicken ginger soup with chayote and pechay. Suitable for most therapeutic diets.',
                'servings'    => 1,
                'ingredients' => [
                    ['Chicken Thigh (Cooked)',      80],
                    ['Chayote / Sayote (Cooked)',  100],
                    ['Ginger Root (Raw)',             8],
                    ['Bok Choy / Pechay (Cooked)',  60],
                    ['Steamed White Rice',          160],
                ],
            ],
            [
                'name'        => 'Paksiw na Bangus with Rice',
                'category'    => 'Regular Diet',
                'prep_notes'  => 'Bangus braised in vinegar. Moderate sodium — monitor in strict low-sodium orders.',
                'servings'    => 1,
                'ingredients' => [
                    ['Milkfish / Bangus (Cooked)', 100],
                    ['Bitter Melon (Ampalaya)',     40],
                    ['Ginger Root (Raw)',            5],
                    ['Steamed White Rice',         180],
                ],
            ],
            [
                'name'        => 'Pinakbet (Hospital Version)',
                'category'    => 'Vegetable',
                'prep_notes'  => 'Mixed vegetable stew without bagoong. Low sodium — cardiac and hypertension diets.',
                'servings'    => 1,
                'ingredients' => [
                    ['Squash / Kalabasa (Cooked)',  100],
                    ['String Beans / Sitaw',         80],
                    ['Eggplant / Talong (Cooked)',   80],
                    ['Bitter Melon (Ampalaya)',       60],
                    ['Tomato (Raw)',                  30],
                    ['Steamed White Rice',           150],
                ],
            ],
            [
                'name'        => 'Mongo Guisado with Rice (Mung Bean Stew)',
                'category'    => 'High Fiber',
                'prep_notes'  => 'Mung bean stew with kangkong. High fiber — diabetic and cardiac diets.',
                'servings'    => 1,
                'ingredients' => [
                    ['Mung Beans (Cooked)',         150],
                    ['Water Spinach (Kangkong)',     60],
                    ['Garlic (Raw)',                  5],
                    ['Onion (Raw)',                  20],
                    ['Steamed White Rice',          150],
                ],
            ],
        ];

        foreach ($recipes as $data) {
            $recipe = FoodServiceRecipe::where('name', $data['name'])->first();
            $created = false;

            if (! $recipe) {
                $recipe = FoodServiceRecipe::create([
                    'rnd_user_id' => $rnd->id,
                    'name'        => $data['name'],
                    'category'    => $data['category'],
                    'prep_notes'  => $data['prep_notes'],
                    'servings'    => $data['servings'],
                ]);
                $created = true;
            }

            if ($created) {
                foreach ($data['ingredients'] as [$foodItemName, $qty]) {
                    $inv = $this->resolveInventory($foodItemName);
                    FoodServiceRecipeIngredient::create([
                        'food_service_recipe_id' => $recipe->id,
                        'inventory_id'           => $inv->id,
                        'quantity'               => $qty,
                        'unit'                   => 'g',
                    ]);
                }
                $this->command->info("  ✓ FSS Recipe: {$data['name']}");
            } else {
                $this->command->line("  – Already seeded: {$data['name']}");
            }

            // Always recalculate (idempotent)
            $recipe->recalculateCost();
        }
    }

    private function resolveInventory(string $foodItemName): \App\Models\Inventory
    {
        $inv = Inventory::whereHas('foodItem', fn ($q) => $q->where('name', $foodItemName))->first();
        if (! $inv) {
            throw new \RuntimeException(
                "FoodServiceRecipeSeeder: no inventory row for food_item.name='{$foodItemName}'. Run InventorySeeder first."
            );
        }
        return $inv;
    }
}
