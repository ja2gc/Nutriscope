<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds FSS inventory with common Filipino grocery ingredients.
 * unit_price = ₱/100g (cost formula: qty_g / 100 × unit_price).
 * Idempotent: updateOrCreate by food_item_id.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        // Columns: [food_item_name, qty, unit, min_threshold, usage_rate/day,
        //           expiry_offset_days, unit_price (₱/100g), notes]
        $foodSeeds = [
            // ── Proteins ──────────────────────────────────────────────────────
            ['Chicken Breast (Cooked)',      30.00, 'kg',      5.00,   8.50,  14,  28.00, null],
            ['Chicken Thigh (Cooked)',       20.00, 'kg',      4.00,   6.00,  12,  22.00, null],
            ['Pork Loin (Cooked)',           15.00, 'kg',      3.00,   4.50,  10,  28.00, null],
            ['Pork Belly (Cooked)',          10.00, 'kg',      3.00,   3.00,  10,  32.00, null],
            ['Milkfish / Bangus (Cooked)',   20.00, 'kg',      4.00,   7.00,   7,  20.00, 'Monitor expiry closely — seafood'],
            ['Tilapia (Cooked)',             18.00, 'kg',      4.00,   6.50,   7,  16.00, 'Monitor expiry closely — seafood'],
            ['Mackerel (Cooked)',            12.00, 'kg',      3.00,   4.00,   7,  18.00, 'Monitor expiry — omega-3 source'],
            ['Sardines (Canned in Water)',   60.00, 'cans',   12.00,   5.00,  90,  12.00, null],
            ['Egg (Hard Boiled)',           200.00, 'pcs',    50.00,  30.00,  20,  15.00, null],
            ['Firm Tofu (Tokwa)',            15.00, 'kg',      3.00,   5.00,   5,  10.00, 'Refrigerate — short shelf life'],
            ['Mung Beans (Cooked)',          20.00, 'kg',      4.00,   5.50,  30,   7.00, null],

            // ── Grains & Starches ─────────────────────────────────────────────
            ['Steamed White Rice',          100.00, 'kg',     20.00,  25.00,  60,   4.50, 'Main staple — maintain high stock'],
            ['Steamed Brown Rice',           50.00, 'kg',     10.00,  10.00,  55,   6.00, 'For diabetic and cardiac diets'],
            ['Rice Porridge (Lugaw)',         30.00, 'kg',     8.00,   8.00,  20,   3.50, 'Soft diet and post-op orders'],
            ['Oatmeal (Plain, Cooked)',       25.00, 'kg',     5.00,   5.00, 120,   8.00, 'Diabetic and cardiac diet staple'],
            ['Glutinous Rice (Cooked)',       15.00, 'kg',     3.00,   3.00,  60,   5.50, 'For Bringhe and Ginataan'],
            ['Sweet Potato / Kamote',         20.00, 'kg',     4.00,   5.00,  14,   6.00, 'Low GI — diabetic-friendly snack'],
            ['Cassava / Kamoteng Kahoy',      15.00, 'kg',     3.00,   3.00,  14,   5.00, 'High-energy snack'],

            // ── Aromatics & Seasonings ────────────────────────────────────────
            ['Garlic (Raw)',                   5.00, 'kg',     1.00,   1.50,  30,  20.00, 'Base for all savory dishes'],
            ['Onion (Raw)',                    8.00, 'kg',     2.00,   2.50,  30,  10.00, 'Base for all savory dishes'],
            ['Ginger Root (Raw)',              3.00, 'kg',     0.50,   1.00,  20,  12.00, 'Tinola and ginger-based dishes'],
            ['Tomato (Raw)',                  10.00, 'kg',     2.00,   4.00,   7,   5.00, 'Sautéed base and salads'],
            ['Tomato (Cooked)',               10.00, 'kg',     2.00,   4.00,   5,   5.00, 'Caldereta and stew base'],
            ['Coconut Milk (Canned)',          30.00, 'cans',  6.00,   5.00, 365,  11.25, 'Bringhe, Ginataan, Kare-Kare'],
            ['Calamansi / Lime Juice',         10.00, 'liters', 2.00,  3.00,  14,  10.00, 'Sisig, Tinola, sour dishes'],
            ['Peanut Butter (Unsalted)',        5.00, 'kg',     1.00,   1.50, 180,  18.00, 'Kare-Kare base — peanut allergen'],
            ['Brown Sugar',                   10.00, 'kg',     2.00,   2.00, 365,   6.00, 'Moderate use — desserts only'],

            // ── Vegetables ────────────────────────────────────────────────────
            ['Water Spinach (Kangkong)',       10.00, 'kg',    2.00,   4.00,   3,   4.00, 'High-turnover leafy green'],
            ['Bok Choy / Pechay (Cooked)',      8.00, 'kg',    2.00,   3.50,   4,   5.00, 'Tinola and stir-fry'],
            ['Bitter Melon (Ampalaya)',          8.00, 'kg',    2.00,   3.00,   5,   8.00, 'Diabetic-friendly — high demand'],
            ['Eggplant / Talong (Cooked)',      10.00, 'kg',    2.00,   4.00,   5,   4.50, 'Pinakbet, salads'],
            ['Chayote / Sayote (Cooked)',       12.00, 'kg',    2.00,   4.00,   7,   4.00, 'Tinola base — renal-friendly'],
            ['Carrots (Cooked)',                12.00, 'kg',    2.00,   4.00,  14,   6.00, 'Caldereta, mixed vegetables'],
            ['Cabbage / Repolyo (Cooked)',      15.00, 'kg',    3.00,   5.00,  10,   4.00, 'Renal-friendly low-potassium veg'],
            ['String Beans / Sitaw',             8.00, 'kg',    2.00,   3.00,   5,   5.00, 'Pinakbet and Kare-Kare'],
            ['Squash / Kalabasa (Cooked)',      15.00, 'kg',    3.00,   5.00,  14,   5.00, 'Pinakbet, Kare-Kare, soups'],

            // ── Fruits ────────────────────────────────────────────────────────
            ['Banana (Raw)',                   80.00, 'pcs',   20.00,  20.00,   5,   5.00, 'Standard snack — cardiac and renal diets monitor potassium'],
            ['Papaya (Raw)',                    8.00, 'kg',     2.00,   3.00,   5,   4.00, 'Vitamin C — wound healing support'],
            ['Mango (Raw)',                    10.00, 'kg',     2.00,   4.00,   5,  12.00, 'Philippine pride — monitor potassium in renal'],
            ['Watermelon (Raw)',               20.00, 'kg',     4.00,   6.00,   5,   4.50, 'Hydrating low-calorie snack'],
            ['Pineapple (Raw)',                10.00, 'kg',     2.00,   3.00,   7,   6.00, 'Monitor potassium in renal diet'],
            ['Guava (Raw)',                     8.00, 'kg',     2.00,   3.00,   5,   7.00, 'Highest vitamin C per 100 g'],
            ['Jackfruit (Raw)',                10.00, 'kg',     2.00,   3.00,   5,  10.00, 'Energy-dense for weight gain diets'],

            // ── Dairy & Misc ─────────────────────────────────────────────────
            ['Low-fat Milk (1%)',              20.00, 'liters',  5.00,  7.00,  14,   8.00, 'Supplement drinks and cereals'],
            ['Peanuts (Roasted)',               5.00, 'kg',      1.00,  1.50, 180,  15.00, 'Energy-dense — peanut allergen'],
        ];

        $foodCount = 0;
        foreach ($foodSeeds as [$name, $qty, $unit, $threshold, $usageRate, $expiryDays, $price, $notes]) {
            $foodItem = FoodItem::where('name', $name)->first();
            if (! $foodItem) {
                $this->command->warn("  InventorySeeder: FoodItem not found → {$name}");
                continue;
            }

            Inventory::updateOrCreate(
                ['food_item_id' => $foodItem->id],
                [
                    'item_type'               => 'food_item',
                    'quantity_in_stock'       => $qty,
                    'unit'                    => $unit,
                    'minimum_stock_threshold' => $threshold,
                    'usage_rate'              => $usageRate,
                    'expiry_date'             => $today->copy()->addDays($expiryDays),
                    'unit_price'              => $price,
                    'notes'                   => $notes,
                ]
            );
            $foodCount++;
        }

        $this->command->info("  InventorySeeder: {$foodCount} food item inventory rows seeded.");
    }
}
