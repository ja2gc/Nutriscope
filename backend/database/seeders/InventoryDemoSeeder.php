<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Inventory;
use App\Models\Recipe;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        Inventory::truncate();

        $today = Carbon::today();

        // ── FOOD ITEMS ────────────────────────────────────────────────────────
        // [name, qty, unit, threshold, expiry_offset_days|null, unit_price, notes]
        $foodSeeds = [
            // OK — healthy stock
            ['Steamed White Rice',         80.00, 'kg',   10.00, +60,  220.00, null],
            ['Steamed Brown Rice',         45.00, 'kg',   10.00, +55,  290.00, null],
            ['Chicken Breast (Cooked)',    30.00, 'kg',    5.00, +14, 1400.00, null],
            ['Tilapia (Cooked)',           25.00, 'kg',    5.00, +10,  800.00, null],
            ['Egg (Hard Boiled)',         120.00, 'pcs',  30.00, +20,    7.50, null],
            ['Firm Tofu (Tokwa)',          15.00, 'kg',    3.00, +7,   500.00, null],

            // LOW STOCK
            ['Rice Porridge (Lugaw)',       3.00, 'kg',    8.00, +20,  175.00, 'Reorder needed'],
            ['Chicken Thigh (Cooked)',      2.00, 'kg',    5.00, +12, 1100.00, 'Low — reorder'],
            ['Mung Beans (Cooked)',         1.50, 'kg',    4.00, +30,  350.00, 'Running low'],

            // NO STOCK
            ['Oatmeal (Plain, Cooked)',     0.00, 'kg',    5.00, null, 400.00, 'Out of stock'],
            ['Pork Belly (Cooked)',         0.00, 'kg',    3.00, null, 1600.00, 'Out of stock'],

            // EXPIRING SOON
            ['Wheat Crackers',            18.00, 'kg',    5.00, +3,   750.00, 'Consume before expiry'],
            ['Sardines (Canned in Water)', 40.00, 'cans', 10.00, +5,   600.00, 'Expires soon'],
            ['Milkfish / Bangus (Cooked)', 12.00, 'kg',    4.00, +2,  1000.00, 'Use today or tomorrow'],

            // Additional OK items
            ['Carrots (Cooked)',           20.00, 'kg',    3.00, +30,  300.00, null],
            ['Cabbage / Repolyo (Cooked)', 15.00, 'kg',    3.00, +14,  200.00, null],
            ['Banana (Raw)',               50.00, 'pcs',  20.00, +5,    10.00, null],
            ['Tomato (Raw)',               10.00, 'kg',    2.00, +7,   250.00, null],
        ];

        foreach ($foodSeeds as [$name, $qty, $unit, $threshold, $expiryDays, $price, $notes]) {
            $foodItem = FoodItem::where('name', $name)->first();
            if (! $foodItem) {
                $this->command->warn("FoodItem not found: {$name}");
                continue;
            }

            Inventory::create([
                'item_type'               => 'food_item',
                'food_item_id'            => $foodItem->id,
                'quantity_in_stock'       => $qty,
                'unit'                    => $unit,
                'minimum_stock_threshold' => $threshold,
                'expiry_date'             => $expiryDays !== null ? $today->copy()->addDays($expiryDays) : null,
                'unit_price'              => $price,
                'usage_rate'              => null,
                'notes'                   => $notes,
            ]);
        }

        // ── RECIPES ───────────────────────────────────────────────────────────
        // [name, qty, unit, threshold, expiry_offset_days|null, notes]
        $recipeSeeds = [
            // OK
            ['Plain White Rice Meal',              50.00, 'servings', 10.00, +2,   null],
            ['Boiled Chicken Breast with White Rice', 20.00, 'servings', 5.00, +1,   null],
            ['Steamed Tilapia with Rice',          15.00, 'servings',  5.00, +1,   null],

            // LOW STOCK
            ['Plain Brown Rice Meal',               4.00, 'servings',  8.00, +3,   'Running low'],
            ['Lugaw with Chicken (Soft Diet)',       2.00, 'servings',  5.00, +1,   'Low stock'],

            // NO STOCK
            ['Chicken Breast with Brown Rice',      0.00, 'servings',  5.00, null, 'Out of stock'],

            // EXPIRING SOON
            ['Lugaw with Egg (Soft Diet)',          12.00, 'servings',  3.00, +1,   'Serve today'],
        ];

        foreach ($recipeSeeds as [$name, $qty, $unit, $threshold, $expiryDays, $notes]) {
            $recipe = Recipe::where('name', $name)->first();
            if (! $recipe) {
                $this->command->warn("Recipe not found: {$name}");
                continue;
            }

            Inventory::create([
                'item_type'               => 'recipe',
                'recipe_id'               => $recipe->id,
                'quantity_in_stock'       => $qty,
                'unit'                    => $unit,
                'minimum_stock_threshold' => $threshold,
                'expiry_date'             => $expiryDays !== null ? $today->copy()->addDays($expiryDays) : null,
                'unit_price'              => null,
                'usage_rate'              => null,
                'notes'                   => $notes,
            ]);
        }
    }
}
