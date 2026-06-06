<?php

namespace Database\Seeders;

use App\Services\UsdaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoodItemsSeeder extends Seeder
{
    /**
     * Filipino-relevant ingredients mapped to USDA search queries.
     * Search prefers Foundation > SR Legacy > Survey (FNDDS).
     * Friendly name is used in the DB; USDA provides all nutrients automatically.
     */
    /**
     * Direct FDC ID fallbacks for foods that consistently hit the rate limit on search.
     * Used only when search fails — avoids burning a search call for a known ID.
     */
    private const FALLBACK_FDC_IDS = [
        'Pork Loin (Cooked)'   => 174480, // SR Legacy — confirmed from previous run
        'Sweet Potato / Kamote' => 168482, // SR Legacy — sweet potato cooked baked
    ];

    private const INGREDIENTS = [
        // ── Grains / Carbs ────────────────────────────────────────────────────
        'Steamed White Rice'          => 'rice white long-grain cooked enriched',
        'Steamed Brown Rice'          => 'rice brown long-grain cooked',
        'Rice Porridge (Lugaw)'       => 'rice congee',
        'Oatmeal (Plain, Cooked)'     => 'oatmeal cooked water',
        'White Bread'                 => 'bread white commercially prepared',
        'Wheat Crackers'              => 'crackers',
        'Sweet Corn (Cooked)'         => 'corn yellow cooked boiled',

        // ── Proteins ──────────────────────────────────────────────────────────
        'Chicken Breast (Cooked)'     => 'chicken breast meat cooked roasted',
        'Chicken Thigh (Cooked)'      => 'chicken thigh meat cooked roasted',
        'Pork Loin (Cooked)'          => 'pork loin',
        'Pork Belly (Cooked)'         => 'pork belly',
        'Milkfish / Bangus (Cooked)'  => 'milkfish cooked',
        'Tilapia (Cooked)'            => 'tilapia cooked',
        'Mackerel (Cooked)'           => 'mackerel cooked',
        'Sardines (Canned in Water)'  => 'sardines canned',
        'Egg (Hard Boiled)'           => 'egg whole cooked hard boiled',
        'Firm Tofu (Tokwa)'           => 'tofu firm raw',
        'Mung Beans (Cooked)'         => 'mung beans cooked boiled',

        // ── Vegetables ────────────────────────────────────────────────────────
        'Water Spinach (Kangkong)'    => 'water spinach cooked',
        'Bok Choy / Pechay (Cooked)'  => 'bok choy cooked boiled',
        'Eggplant / Talong (Cooked)'  => 'eggplant cooked boiled',
        'Bitter Melon (Ampalaya)'     => 'balsam pear bitter melon cooked',
        'Chayote / Sayote (Cooked)'   => 'chayote vegetable pear',
        'Carrots (Cooked)'            => 'carrots cooked',
        'Cabbage / Repolyo (Cooked)'  => 'cabbage cooked',
        'String Beans / Sitaw'        => 'green beans cooked',
        'Sweet Potato / Kamote'       => 'sweet potato',
        'Squash / Kalabasa (Cooked)'  => 'squash butternut cooked',
        'Tomato (Raw)'                => 'tomato raw',

        // ── Fruits ────────────────────────────────────────────────────────────
        'Banana (Raw)'                => 'banana raw',
        'Papaya (Raw)'                => 'papaya raw',
        'Mango (Raw)'                 => 'mango raw',
        'Watermelon (Raw)'            => 'watermelon raw',

        // ── Dairy / Other ─────────────────────────────────────────────────────
        'Low-fat Milk (1%)'           => 'milk lowfat 1% fat',
        'Peanuts (Roasted)'           => 'peanuts roasted',

        // ── Common Aromatics / Condiments ────────────────────────────────────
        'Garlic (Raw)'                => 'garlic raw',
        'Onion (Raw)'                 => 'onions raw',
        'Ginger Root (Raw)'           => 'ginger root raw',
        'Tomato (Cooked)'             => 'tomato red cooked',
        'Coconut Milk (Canned)'       => 'coconut milk canned',
        'Peanut Butter (Unsalted)'    => 'peanut butter smooth unsalted',
        'Brown Sugar'                 => 'sugars brown',
        'Cocoa Powder (Unsweetened)'  => 'cocoa powder unsweetened',

        // ── More Fruits ───────────────────────────────────────────────────────
        'Pineapple (Raw)'             => 'pineapple raw',
        'Guava (Raw)'                 => 'guava raw',
        'Jackfruit (Raw)'             => 'jackfruit raw',
        'Calamansi / Lime Juice'      => 'lime juice raw',

        // ── More Carb Sources ────────────────────────────────────────────────
        'Glutinous Rice (Cooked)'     => 'rice glutinous cooked',
        'Cassava / Kamoteng Kahoy'    => 'yuca cassava cooked',
        'Corn Grits (Cooked)'         => 'corn grits cooked regular',
        'Pandesal (Filipino Bread Roll)' => 'bread roll pan de sal',
    ];

    /** Preferred data type order — Foundation has the most complete nutrient data */
    private const DATA_TYPE_PRIORITY = ['Foundation', 'SR Legacy', 'Survey (FNDDS)'];

    public function run(): void
    {
        $usda = app(UsdaService::class);

        // Remove manually-seeded foods (no USDA ID) and their dependents.
        // USDA-imported foods are kept so re-runs can resume without re-fetching.
        $manualIds = \App\Models\FoodItem::whereNull('usda_fdc_id')->pluck('id');
        if ($manualIds->isNotEmpty()) {
            DB::table('recipe_ingredients')->whereIn('food_item_id', $manualIds)->delete();
            DB::table('inventory')->whereIn('food_item_id', $manualIds)->delete();
            \App\Models\FoodItem::whereNull('usda_fdc_id')->delete();
            $this->command->info('Cleared ' . $manualIds->count() . ' manually-seeded food items.');
        }

        $this->command->info('Importing ' . count(self::INGREDIENTS) . ' Filipino ingredients from USDA...');

        foreach (self::INGREDIENTS as $friendlyName => $query) {
            // Skip if already in DB — avoids burning API calls on re-runs
            if (\App\Models\FoodItem::where('name', $friendlyName)->exists()) {
                $this->command->line("  – Already imported: {$friendlyName}");
                continue;
            }

            try {
                $results = $usda->search($query, 10);

                if (empty($results)) {
                    $this->command->warn("  ✗ No results for: {$friendlyName}");
                    continue;
                }

                // Pick best result by data type priority
                $best = null;
                foreach (self::DATA_TYPE_PRIORITY as $preferred) {
                    foreach ($results as $r) {
                        if ($r['data_type'] === $preferred) {
                            $best = $r;
                            break 2;
                        }
                    }
                }
                $best ??= $results[0];

                // Try each result until one imports successfully (some FDC IDs return 404 on fetch)
                $imported = false;
                foreach ($results as $candidate) {
                    try {
                        $food = $usda->import($candidate['fdc_id']);
                        $food->update(['name' => $friendlyName]);
                        $this->command->info("  ✓ {$friendlyName} [{$candidate['data_type']} #{$candidate['fdc_id']}]");
                        $imported = true;
                        break;
                    } catch (\RuntimeException $e) {
                        if (str_contains($e->getMessage(), 'already exists')) {
                            // A previous partial run imported this FDC ID under a different name — rename it
                            $existing = \App\Models\FoodItem::where('usda_fdc_id', $candidate['fdc_id'])->first();
                            if ($existing) {
                                $existing->update(['name' => $friendlyName]);
                                $this->command->info("  ✓ {$friendlyName} [renamed from existing #{$candidate['fdc_id']}]");
                                $imported = true;
                                break;
                            }
                        }
                        // fetch failed (404 etc.) — try next candidate
                        usleep(300000);
                        continue;
                    }
                }

                if (! $imported) {
                    $this->command->warn("  ✗ All candidates failed for: {$friendlyName}");
                }

            } catch (\Exception $e) {
                // Try direct FDC ID fallback before giving up
                if (isset(self::FALLBACK_FDC_IDS[$friendlyName])) {
                    $fallbackId = self::FALLBACK_FDC_IDS[$friendlyName];
                    try {
                        $food = $usda->import($fallbackId);
                        $food->update(['name' => $friendlyName]);
                        $this->command->info("  ✓ {$friendlyName} [fallback FDC #{$fallbackId}]");
                    } catch (\Exception $fe) {
                        $this->command->warn("  ✗ Fallback also failed for {$friendlyName}: {$fe->getMessage()}");
                    }
                } else {
                    $this->command->warn("  ✗ Skipped {$friendlyName}: {$e->getMessage()}");
                }
                usleep(1000000);
            }

            usleep(1000000); // 1s between foods — stays within USDA rate limit
        }

        $this->command->info('Done.');

        // Manual entries for items the USDA search fails on (rate limit / query sensitivity).
        // Nutrition values are USDA-verified per 100g. serving_size = 100g for all.
        // [name, kcal, protein, carbs, fat, water_g, category] — values per 100g, USDA-verified
        $manualItems = [
            ['Onion (Raw)',                  40,  1.1,  9.3,  0.1,  89.1, 'vegetable'],
            ['Ginger Root (Raw)',            80,  1.8, 17.8,  0.8,  78.9, 'spice'],
            ['Tomato (Cooked)',              18,  0.9,  3.9,  0.2,  94.0, 'vegetable'],
            ['Coconut Milk (Canned)',       197,  2.3,  2.8, 21.3,  67.6, 'dairy-alternative'],
            ['Peanut Butter (Unsalted)',    588, 25.0, 20.1, 50.4,   1.8, 'legume'],
            ['Cocoa Powder (Unsweetened)',  228, 19.6, 57.9, 13.7,   3.0, 'other'],
            ['Pineapple (Raw)',              50,  0.5, 13.1,  0.1,  86.0, 'fruit'],
            ['Guava (Raw)',                  68,  2.6, 14.3,  1.0,  80.8, 'fruit'],
            ['Jackfruit (Raw)',              95,  1.7, 23.2,  0.6,  73.5, 'fruit'],
            ['Calamansi / Lime Juice',       25,  0.4,  8.4,  0.1,  91.5, 'fruit'],
            ['Glutinous Rice (Cooked)',      97,  2.1, 21.7,  0.2,  72.0, 'grain'],
            ['Corn Grits (Cooked)',          71,  1.7, 15.5,  0.2,  82.5, 'grain'],
        ];

        foreach ($manualItems as [$name, $cal, $prot, $carb, $fat, $water, $cat]) {
            if (\App\Models\FoodItem::where('name', $name)->exists()) {
                // Backfill water_g on existing manual items that predate this field
                \App\Models\FoodItem::where('name', $name)->whereNull('water_g')->update(['water_g' => $water]);
                continue;
            }
            \App\Models\FoodItem::create([
                'name'         => $name,
                'calories'     => $cal,
                'protein'      => $prot,
                'carbs'        => $carb,
                'fat'          => $fat,
                'water_g'      => $water,
                'category'     => $cat,
                'serving_size' => 100,
                'serving_unit' => 'g',
            ]);
            $this->command->info("  ✓ Manual: {$name}");
        }
    }
}
