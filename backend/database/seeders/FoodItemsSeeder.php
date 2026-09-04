<?php

namespace Database\Seeders;

use App\Models\FoodItem;
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
    /**
     * Direct FDC ID fallbacks used when USDA search is rate-limited or returns no results.
     * All IDs are SR Legacy / Foundation — verified to return full micronutrient data.
     * These 14 entries cover the items most likely to hit rate limits.
     */
    private const FALLBACK_FDC_IDS = [
        'Milkfish / Bangus (Cooked)' => 171995, // SR Legacy — milkfish cooked, dry heat
        'Egg (Hard Boiled)' => 173424, // SR Legacy — egg whole, cooked, hard-boiled
        'Banana (Raw)' => 173944, // SR Legacy — bananas raw
        'Pork Loin (Cooked)' => 174480, // SR Legacy — pork loin roasted
        'Sweet Potato / Kamote' => 168482, // SR Legacy — sweet potato cooked baked
        // ── Former manual items — now USDA-imported for full micronutrient data ──
        'Onion (Raw)' => 170000, // SR Legacy — onions raw
        'Ginger Root (Raw)' => 169231, // SR Legacy — ginger root raw
        'Tomato (Cooked)' => 168522, // SR Legacy — tomatoes red ripe cooked
        'Coconut Milk (Canned)' => 175194, // SR Legacy — coconut milk canned
        'Peanut Butter (Unsalted)' => 172470, // SR Legacy — peanut butter smooth no salt
        'Cocoa Powder (Unsweetened)' => 169593, // SR Legacy — cocoa dry powder unsweetened
        'Pineapple (Raw)' => 169124, // SR Legacy — pineapple raw all varieties
        'Guava (Raw)' => 173044, // SR Legacy — guavas common raw
        'Jackfruit (Raw)' => 174687, // SR Legacy — jackfruit raw
        'Calamansi / Lime Juice' => 167951, // SR Legacy — lime juice raw
        'Glutinous Rice (Cooked)' => 168879, // SR Legacy — rice white glutinous cooked
        'Corn Grits (Cooked)' => 170293, // SR Legacy — cornmeal white cooked regular
        'Rice Porridge (Lugaw)' => 2708418, // Survey (FNDDS) — rice congee
    ];

    private const INGREDIENTS = [
        // ── Grains / Carbs ────────────────────────────────────────────────────
        'Steamed White Rice' => 'rice white long-grain cooked enriched',
        'Steamed Brown Rice' => 'rice brown long-grain cooked',
        'Rice Porridge (Lugaw)' => 'rice congee',
        'Oatmeal (Plain, Cooked)' => 'oatmeal cooked water',
        'White Bread' => 'bread white commercially prepared',
        'Wheat Crackers' => 'crackers',
        'Sweet Corn (Cooked)' => 'corn yellow cooked boiled',

        // ── Proteins ──────────────────────────────────────────────────────────
        'Chicken Breast (Cooked)' => 'chicken breast meat cooked roasted',
        'Chicken Thigh (Cooked)' => 'chicken thigh meat cooked roasted',
        'Pork Loin (Cooked)' => 'pork loin',
        'Pork Belly (Cooked)' => 'pork belly',
        'Milkfish / Bangus (Cooked)' => 'milkfish cooked',
        'Tilapia (Cooked)' => 'tilapia cooked',
        'Mackerel (Cooked)' => 'mackerel cooked',
        'Sardines (Canned in Water)' => 'sardines canned',
        'Egg (Hard Boiled)' => 'egg whole cooked hard boiled',
        'Firm Tofu (Tokwa)' => 'tofu firm raw',
        'Mung Beans (Cooked)' => 'mung beans cooked boiled',

        // ── Vegetables ────────────────────────────────────────────────────────
        'Water Spinach (Kangkong)' => 'water spinach cooked',
        'Bok Choy / Pechay (Cooked)' => 'bok choy cooked boiled',
        'Eggplant / Talong (Cooked)' => 'eggplant cooked boiled',
        'Bitter Melon (Ampalaya)' => 'balsam pear bitter melon cooked',
        'Chayote / Sayote (Cooked)' => 'chayote vegetable pear',
        'Carrots (Cooked)' => 'carrots cooked',
        'Cabbage / Repolyo (Cooked)' => 'cabbage cooked',
        'String Beans / Sitaw' => 'green beans cooked',
        'Sweet Potato / Kamote' => 'sweet potato',
        'Squash / Kalabasa (Cooked)' => 'squash butternut cooked',
        'Tomato (Raw)' => 'tomato raw',

        // ── Fruits ────────────────────────────────────────────────────────────
        'Banana (Raw)' => 'banana raw',
        'Papaya (Raw)' => 'papaya raw',
        'Mango (Raw)' => 'mango raw',
        'Watermelon (Raw)' => 'watermelon raw',

        // ── Dairy / Other ─────────────────────────────────────────────────────
        'Low-fat Milk (1%)' => 'milk lowfat 1% fat',
        'Peanuts (Roasted)' => 'peanuts roasted',

        // ── Common Aromatics / Condiments ────────────────────────────────────
        'Garlic (Raw)' => 'garlic raw',
        'Onion (Raw)' => 'onions raw',
        'Ginger Root (Raw)' => 'ginger root raw',
        'Tomato (Cooked)' => 'tomato red cooked',
        'Coconut Milk (Canned)' => 'coconut milk canned',
        'Peanut Butter (Unsalted)' => 'peanut butter smooth unsalted',
        'Brown Sugar' => 'sugars brown',
        'Cocoa Powder (Unsweetened)' => 'cocoa powder unsweetened',

        // ── More Fruits ───────────────────────────────────────────────────────
        'Pineapple (Raw)' => 'pineapple raw',
        'Guava (Raw)' => 'guava raw',
        'Jackfruit (Raw)' => 'jackfruit raw',
        'Calamansi / Lime Juice' => 'lime juice raw',

        // ── More Carb Sources ────────────────────────────────────────────────
        'Glutinous Rice (Cooked)' => 'rice glutinous cooked',
        'Cassava / Kamoteng Kahoy' => 'yuca cassava cooked',
        'Corn Grits (Cooked)' => 'corn grits cooked regular',
        'Pandesal (Filipino Bread Roll)' => 'bread roll pan de sal',
    ];

    /** Preferred data type order — Foundation has the most complete nutrient data */
    private const DATA_TYPE_PRIORITY = ['Foundation', 'SR Legacy', 'Survey (FNDDS)'];

    /**
     * Snack-suitability curation for meal-plan auto-generation.
     *
     * `FoodItem::isReadyToEat()` treats every fruit/vegetable as a standalone snack by
     * default, but aromatics, condiments and cooked side-dish vegetables are not sensible
     * on their own — they only belong inside a composed recipe. Pin those to false here.
     * (Map value is the explicit `ready_to_eat` flag; true would force-include an item
     * whose category wouldn't otherwise qualify.)
     */
    private const SNACK_CURATION = [
        'Onion (Raw)' => false, // aromatic
        'Ginger Root (Raw)' => false, // aromatic
        'Calamansi / Lime Juice' => false, // condiment / juice
        'Tomato (Cooked)' => false, // cooked side dish
        'Water Spinach (Kangkong)' => false, // leafy side dish
        'Eggplant / Talong (Cooked)' => false, // cooked side dish
        'Bok Choy / Pechay (Cooked)' => false, // cooked side dish
        'Chayote / Sayote (Cooked)' => false, // cooked side dish
        'Squash / Kalabasa (Cooked)' => false, // cooked side dish
        // Left eligible by category default: Guava, Jackfruit, Papaya, Pineapple,
        // Sweet Corn — sensible single ready-to-eat snacks.
    ];

    /**
     * Category overrides applied after USDA import.
     *
     * Reasons items need overrides:
     *  - FNDDS (Survey) items return no foodCategory → mapCategory() returns null.
     *  - USDA "Dairy and Egg Products" maps to 'dairy' but eggs belong in 'protein'.
     *  - USDA "Vegetables and Vegetable Products" captures starchy roots (cassava, corn)
     *    that belong in 'carbs' for NCP meal planning purposes.
     *  - Condiments/aromatics may return null or unexpected categories.
     *
     * Applied idempotently after every import run (safe for re-runs).
     */
    private const CATEGORY_OVERRIDE = [
        // ── Items with null category from FNDDS (no USDA foodCategory) ──────────
        'Rice Porridge (Lugaw)' => 'carbs',
        'Pandesal (Filipino Bread Roll)' => 'carbs',

        // ── Eggs misclassified as dairy (USDA lumps "Dairy and Egg Products") ───
        'Egg (Hard Boiled)' => 'protein',

        // ── Starchy roots/grains misclassified as vegetable in USDA ─────────────
        'Cassava / Kamoteng Kahoy' => 'carbs',
        'Sweet Corn (Cooked)' => 'carbs',

        // ── Ensure aromatics/condiments have correct category ────────────────────
        'Garlic (Raw)' => 'vegetable',
        'Onion (Raw)' => 'vegetable',
        'Ginger Root (Raw)' => 'vegetable',
        'Tomato (Raw)' => 'vegetable',
        'Tomato (Cooked)' => 'vegetable',
        'Coconut Milk (Canned)' => 'fat',
        'Peanut Butter (Unsalted)' => 'fat',
        'Peanuts (Roasted)' => 'fat',
        'Cocoa Powder (Unsweetened)' => 'carbs',
        'Brown Sugar' => 'carbs',
        'Calamansi / Lime Juice' => 'fruit',

        // ── Dairy — confirm not overridden by Dairy-and-Egg→dairy mapping ────────
        'Low-fat Milk (1%)' => 'dairy',
    ];

    public function run(): void
    {
        $usda = app(UsdaService::class);

        // Remove manually-seeded foods (no USDA ID) and their dependents.
        // USDA-imported foods are kept so re-runs can resume without re-fetching.
        $manualIds = FoodItem::whereNull('usda_fdc_id')->pluck('id');
        if ($manualIds->isNotEmpty()) {
            DB::table('recipe_ingredients')->whereIn('food_item_id', $manualIds)->delete();
            FoodItem::whereNull('usda_fdc_id')->delete();
            $this->command->info('Cleared '.$manualIds->count().' manually-seeded food items.');
        }

        $this->command->info('Importing '.count(self::INGREDIENTS).' Filipino ingredients from USDA...');

        $pending = self::INGREDIENTS;
        $maxRetries = 3;
        $retryCount = 0;

        // Loop until all items are successfully seeded or max retries exhausted
        while (! empty($pending) && $retryCount < $maxRetries) {
            if ($retryCount > 0) {
                $this->command->info("\n✓ Retry #{$retryCount} — retrying ".count($pending).' pending items...');
                usleep(2000000); // 2s between retry loops — let rate limit cool down
            }

            $stillPending = [];

            foreach ($pending as $friendlyName => $query) {
                // Skip if already in DB — avoids burning API calls on re-runs
                if (FoodItem::where('name', $friendlyName)->exists()) {
                    $this->command->line("  – Already imported: {$friendlyName}");

                    continue;
                }

                try {
                    $results = $usda->search($query, 10);

                    if (empty($results)) {
                        $this->command->warn("  ✗ No results for: {$friendlyName}");
                        $stillPending[$friendlyName] = $query; // Mark for retry

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
                                $existing = FoodItem::where('usda_fdc_id', $candidate['fdc_id'])->first();
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
                        $stillPending[$friendlyName] = $query; // Mark for retry
                    }

                } catch (\Exception $e) {
                    // Check if it's a rate limit error (429 or similar)
                    if (str_contains($e->getMessage(), 'rate') || str_contains($e->getMessage(), '429')) {
                        $this->command->warn("  ⏱ Rate limited: {$friendlyName} — will retry");
                        $stillPending[$friendlyName] = $query;

                        continue;
                    }

                    // Try direct FDC ID fallback
                    if (isset(self::FALLBACK_FDC_IDS[$friendlyName])) {
                        $fallbackId = self::FALLBACK_FDC_IDS[$friendlyName];
                        try {
                            $food = $usda->import($fallbackId);
                            $food->update(['name' => $friendlyName]);
                            $this->command->info("  ✓ {$friendlyName} [fallback FDC #{$fallbackId}]");
                        } catch (\Exception $fe) {
                            $this->command->warn("  ✗ Fallback failed for {$friendlyName}: {$fe->getMessage()}");
                            $stillPending[$friendlyName] = $query; // Mark for retry
                        }
                    } else {
                        $this->command->warn("  ✗ {$friendlyName}: {$e->getMessage()}");
                        $stillPending[$friendlyName] = $query; // Mark for retry
                    }
                }

                usleep(1000000); // 1s between foods — stays within USDA rate limit
            }

            $pending = $stillPending;
            $retryCount++;
        }

        // Final status
        if (empty($pending)) {
            $this->command->info('✓ All items successfully imported. All items use USDA import (macros + micros + water_g).');
        } else {
            $this->command->error('⚠ Max retries exhausted. '.count($pending).' item(s) still pending:');
            foreach ($pending as $name => $query) {
                $this->command->line("  - {$name}");
            }

            throw new \RuntimeException('USDA food import incomplete. Rerun FoodItemsSeeder to resume pending items.');
        }

        // Snack-suitability curation (idempotent — safe on re-runs).
        $curated = 0;
        foreach (self::SNACK_CURATION as $name => $readyToEat) {
            $curated += FoodItem::where('name', $name)->update(['ready_to_eat' => $readyToEat]);
        }
        $this->command->info("Snack curation: pinned ready_to_eat on {$curated} item(s).");

        // Category overrides — correct null/wrong categories after USDA import (idempotent).
        foreach (self::CATEGORY_OVERRIDE as $name => $category) {
            FoodItem::where('name', $name)->update(['category' => $category]);
        }
        $this->command->info('Category overrides: corrected '.count(self::CATEGORY_OVERRIDE).' item(s) — null/USDA-mismatch categories fixed.');
    }
}
