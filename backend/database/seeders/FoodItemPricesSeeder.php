<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

/**
 * Sets realistic Philippine market unit_price (₱ per 100 g) on food items,
 * then triggers Recipe::recalculateTotals() so recipe costs are auto-computed
 * from their ingredient quantities × food item prices.
 */
class FoodItemPricesSeeder extends Seeder
{
    // Prices are ₱ per 100 g (rough Philippine wet-market / supermarket rates)
    private const PRICES = [
        // Grains / Carbs
        'Cooked White Rice' => 4.50,
        'Cooked Brown Rice' => 6.00,
        'Rice Porridge (Lugaw)' => 3.50,
        'Oatmeal (Plain, Cooked)' => 8.00,
        'White Bread' => 12.00,
        'Wheat Crackers' => 15.00,
        'Boiled Corn' => 6.00,
        'Glutinous Rice (Cooked)' => 5.50,
        'Cooked Cassava' => 5.00,

        // Proteins
        'Chicken Breast (Cooked)' => 28.00,
        'Chicken Thigh (Cooked)' => 22.00,
        'Pork Loin (Cooked)' => 28.00,
        'Pork Belly (Cooked)' => 32.00,
        'Milkfish / Bangus (Cooked)' => 20.00,
        'Tilapia (Cooked)' => 16.00,
        'Mackerel (Cooked)' => 18.00,
        'Sardines (Canned in Water)' => 12.00,
        'Egg (Hard Boiled)' => 15.00,
        'Firm Tofu (Tokwa)' => 10.00,
        'Mung Beans (Cooked)' => 7.00,

        // Vegetables
        'Water Spinach (Kangkong)' => 4.00,
        'Bok Choy / Pechay (Cooked)' => 5.00,
        'Eggplant / Talong (Cooked)' => 4.50,
        'Bitter Melon (Ampalaya)' => 8.00,
        'Chayote / Sayote (Cooked)' => 4.00,
        'Carrots (Cooked)' => 6.00,
        'Cabbage / Repolyo (Cooked)' => 4.00,
        'String Beans / Sitaw' => 5.00,
        'Sweet Potato, Raw' => 6.00,
        'Squash / Kalabasa (Cooked)' => 5.00,
        'Tomato (Raw)' => 5.00,
        'Tomato (Cooked)' => 5.00,

        // Fruits
        'Banana (Raw)' => 5.00,
        'Papaya (Raw)' => 4.00,
        'Mango (Raw)' => 12.00,
        'Watermelon (Raw)' => 4.50,
        'Pineapple (Raw)' => 6.00,
        'Guava (Raw)' => 7.00,
        'Jackfruit (Raw)' => 10.00,
        'Calamansi / Lime Juice' => 10.00,

        // Dairy / Other
        'Low-fat Milk (1%)' => 8.00,
        'Peanuts (Roasted)' => 15.00,

        // Aromatics / Condiments
        'Garlic (Raw)' => 20.00,
        'Onion (Raw)' => 10.00,
        'Ginger Root (Raw)' => 12.00,
        'Coconut Milk (Canned)' => 15.00,
        'Peanut Butter (Unsalted)' => 18.00,
        'Brown Sugar' => 6.00,
        'Cocoa Powder (Unsweetened)' => 30.00,
    ];

    public function run(): void
    {
        $updated = 0;
        $dataset = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);
        $fdcIds = collect($dataset['foods'])->pluck('usda_fdc_id', 'name');
        foreach (self::PRICES as $name => $price) {
            $name = match ($name) {
                'Low-fat Milk (1%)' => 'Low-fat Milk',
                'Coconut Milk (Canned)' => 'Coconut Milk',
                'Egg (Hard Boiled)' => 'Chicken Egg, Hard-boiled',
                'Sardines (Canned in Water)' => 'Sardines, Canned',
                'String Beans / Sitaw' => 'Green Beans, Boiled',
                'Calamansi / Lime Juice' => 'Lime Juice, Raw',
                'Chayote / Sayote (Cooked)' => 'Sayote, Boiled with Salt',
                default => $name,
            };
            if (! $fdcIds->has($name)) {
                continue;
            }
            $rows = FoodItem::where('usda_fdc_id', $fdcIds[$name])->update(['unit_price' => $price]);
            $updated += $rows;
        }

        $this->command->info("Updated unit_price on {$updated} food item rows.");

        // Recalculate cost for every recipe that has ingredients
        $recipes = Recipe::with('ingredients')->get();
        $recalced = 0;
        foreach ($recipes as $recipe) {
            if ($recipe->ingredients->isNotEmpty()) {
                $recipe->recalculateTotals();
                $recalced++;
            }
        }

        $this->command->info("Recalculated cost for {$recalced} recipes.");
    }
}
