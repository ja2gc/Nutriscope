<?php

use App\Models\FoodItem;
use App\Models\MealPlanTemplateItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Support\UnitConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Verified USDA SR Legacy replacements, approved on 2026-10-15; nutrients are per 100 g. */
    private const SOURCE_CORRECTIONS = [
        'Pork Loin (Cooked)' => [
            'old_fdc_id' => 174480,
            'old_macros' => [
                'calories' => 152.0,
                'protein' => 29.1,
                'carbs' => 0.0,
                'fat' => 3.13,
            ],
            'new' => [
                'usda_fdc_id' => 168233,
                'calories' => 209,
                'protein' => 28.62,
                'carbs' => 0,
                'fat' => 9.63,
                'water_g' => 61.02,
                'micronutrients' => [
                    'sodium' => 58,
                    'potassium' => 425,
                    'phosphate' => 249,
                    'calcium' => 18,
                    'iron' => 1.09,
                    'magnesium' => 28,
                    'zinc' => 2.53,
                    'copper' => 0.059,
                    'manganese' => 0.016,
                    'selenium' => 35.1,
                    'fiber' => 0,
                    'cholesterol' => 81,
                    'vitamin_a' => 2,
                    'vitamin_c' => 0.6,
                    'vitamin_d' => 0.7,
                    'vitamin_e' => 0.2,
                    'vitamin_k' => 0,
                    'vitamin_b1' => 1.017,
                    'vitamin_b2' => 0.329,
                    'vitamin_b3' => 5.893,
                    'vitamin_b6' => 0.552,
                    'vitamin_b12' => 0.73,
                    'folate' => 1,
                ],
            ],
        ],
        'Tomato (Cooked)' => [
            'old_fdc_id' => 168522,
            'old_macros' => [
                'calories' => 20.0,
                'protein' => 1.64,
                'carbs' => 4.31,
                'fat' => 0.09,
            ],
            'new' => [
                'usda_fdc_id' => 170050,
                'calories' => 18,
                'protein' => 0.95,
                'carbs' => 4.01,
                'fat' => 0.11,
                'water_g' => 94.34,
                'micronutrients' => [
                    'sodium' => 11,
                    'potassium' => 218,
                    'phosphate' => 28,
                    'calcium' => 11,
                    'iron' => 0.68,
                    'magnesium' => 9,
                    'zinc' => 0.14,
                    'copper' => 0.075,
                    'manganese' => 0.105,
                    'selenium' => 0.5,
                    'fiber' => 0.7,
                    'cholesterol' => 0,
                    'vitamin_a' => 24,
                    'vitamin_c' => 22.8,
                    'vitamin_d' => 0,
                    'vitamin_e' => 0.56,
                    'vitamin_k' => 2.8,
                    'vitamin_b1' => 0.036,
                    'vitamin_b2' => 0.022,
                    'vitamin_b3' => 0.532,
                    'vitamin_b6' => 0.079,
                    'vitamin_b12' => 0,
                    'folate' => 13,
                ],
            ],
        ],
        'Coconut Milk (Canned)' => [
            'old_fdc_id' => 175194,
            'old_macros' => [
                'calories' => 127.0,
                'protein' => 8.67,
                'carbs' => 22.8,
                'fat' => 0.5,
            ],
            'new' => [
                'usda_fdc_id' => 170173,
                'calories' => 197,
                'protein' => 2.02,
                'carbs' => 2.81,
                'fat' => 21.33,
                'water_g' => 72.88,
                'micronutrients' => [
                    'sodium' => 13,
                    'potassium' => 220,
                    'phosphate' => 96,
                    'calcium' => 18,
                    'iron' => 3.3,
                    'magnesium' => 46,
                    'zinc' => 0.56,
                    'copper' => 0.223,
                    'manganese' => 0.768,
                    'cholesterol' => 0,
                    'vitamin_a' => 0,
                    'vitamin_c' => 1,
                    'vitamin_d' => 0,
                    'vitamin_b1' => 0.022,
                    'vitamin_b2' => 0,
                    'vitamin_b3' => 0.637,
                    'vitamin_b6' => 0.028,
                    'vitamin_b12' => 0,
                    'folate' => 14,
                ],
            ],
        ],
        'Calamansi / Lime Juice' => [
            'old_fdc_id' => 167951,
            'old_macros' => [
                'calories' => 404.0,
                'protein' => 5.1,
                'carbs' => 73.0,
                'fat' => 13.5,
            ],
            'new' => [
                'usda_fdc_id' => 168156,
                'calories' => 25,
                'protein' => 0.42,
                'carbs' => 8.42,
                'fat' => 0.07,
                'water_g' => 90.79,
                'micronutrients' => [
                    'sodium' => 2,
                    'potassium' => 117,
                    'phosphate' => 14,
                    'calcium' => 14,
                    'iron' => 0.09,
                    'magnesium' => 8,
                    'zinc' => 0.08,
                    'copper' => 0.027,
                    'manganese' => 0.018,
                    'selenium' => 0.1,
                    'fiber' => 0.4,
                    'cholesterol' => 0,
                    'vitamin_a' => 2,
                    'vitamin_c' => 30,
                    'vitamin_d' => 0,
                    'vitamin_e' => 0.22,
                    'vitamin_k' => 0.6,
                    'vitamin_b1' => 0.025,
                    'vitamin_b2' => 0.015,
                    'vitamin_b3' => 0.142,
                    'vitamin_b6' => 0.038,
                    'vitamin_b12' => 0,
                    'folate' => 10,
                ],
            ],
        ],
        'Glutinous Rice (Cooked)' => [
            'old_fdc_id' => 168879,
            'old_macros' => [
                'calories' => 360.0,
                'protein' => 6.61,
                'carbs' => 79.3,
                'fat' => 0.58,
            ],
            'new' => [
                'usda_fdc_id' => 169711,
                'calories' => 97,
                'protein' => 2.02,
                'carbs' => 21.09,
                'fat' => 0.19,
                'water_g' => 76.63,
                'micronutrients' => [
                    'sodium' => 5,
                    'potassium' => 10,
                    'phosphate' => 8,
                    'calcium' => 2,
                    'iron' => 0.14,
                    'magnesium' => 5,
                    'zinc' => 0.41,
                    'copper' => 0.049,
                    'manganese' => 0.262,
                    'selenium' => 5.6,
                    'fiber' => 1,
                    'cholesterol' => 0,
                    'vitamin_a' => 0,
                    'vitamin_c' => 0,
                    'vitamin_d' => 0,
                    'vitamin_e' => 0.04,
                    'vitamin_k' => 0,
                    'vitamin_b1' => 0.02,
                    'vitamin_b2' => 0.013,
                    'vitamin_b3' => 0.29,
                    'vitamin_b6' => 0.026,
                    'vitamin_b12' => 0,
                    'folate' => 1,
                ],
            ],
        ],
        'Corn Grits (Cooked)' => [
            'old_fdc_id' => 170293,
            'old_macros' => [
                'calories' => 282.0,
                'protein' => 16.2,
                'carbs' => 18.0,
                'fat' => 16.2,
            ],
            'new' => [
                'usda_fdc_id' => 171655,
                'calories' => 71,
                'protein' => 1.71,
                'carbs' => 14.76,
                'fat' => 0.46,
                'water_g' => 82.93,
                'micronutrients' => [
                    'sodium' => 2,
                    'potassium' => 27,
                    'phosphate' => 20,
                    'calcium' => 1,
                    'iron' => 0.57,
                    'magnesium' => 7,
                    'zinc' => 0.18,
                    'copper' => 0,
                    'manganese' => 0.037,
                    'selenium' => 3.1,
                    'fiber' => 0.8,
                    'cholesterol' => 0,
                    'vitamin_a' => 0,
                    'vitamin_c' => 0,
                    'vitamin_d' => 0,
                    'vitamin_e' => 0.03,
                    'vitamin_k' => 0,
                    'vitamin_b1' => 0.086,
                    'vitamin_b2' => 0.058,
                    'vitamin_b3' => 0.799,
                    'vitamin_b6' => 0.046,
                    'vitamin_b12' => 0,
                    'folate' => 38,
                ],
            ],
        ],
    ];

    /** Food-specific USDA cup weights scaled by the specified 236.588 mL cup, persisted to six decimals. */
    private const MEASURE_CORRECTIONS = [
        'Low-fat Milk (1%)' => ['recipe' => 'Crackers and Milk Snack', 'source_ids' => [170872], 'old_quantity' => 150, 'quantity_g' => 154.699309],
        'Coconut Milk (Canned)' => ['recipe' => 'Ginataan (Coconut Fruit Dessert)', 'source_ids' => [175194, 170173], 'old_quantity' => 50, 'quantity_g' => 47.762355],
        'Calamansi / Lime Juice' => ['recipe' => 'Papaya with Calamansi', 'source_ids' => [167951, 168156], 'old_quantity' => 15, 'quantity_g' => 15.343128],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $foodCount = FoodItem::query()->count();
            $recipeCount = Recipe::query()->count();
            $lineCount = MealPlanTemplateItem::query()->count();
            $foodUpdates = [];
            $sourceFoodIds = [];

            foreach (self::SOURCE_CORRECTIONS as $name => $correction) {
                $matches = FoodItem::query()->where('name', $name)->whereNotNull('usda_fdc_id')->get();
                if ($matches->count() > 1) {
                    throw new RuntimeException("Ambiguous canonical Ingredient '{$name}'; review its source before migration.");
                }
                $food = $matches->first();
                if ($food === null) {
                    continue;
                }
                if ((float) $food->serving_size !== 100.0 || UnitConverter::normalize((string) $food->serving_unit) !== 'g') {
                    throw new RuntimeException("Ingredient '{$name}' no longer has the audited 100 g baseline.");
                }
                $sourceFoodIds[] = $food->id;
                if ((string) $food->usda_fdc_id === (string) $correction['new']['usda_fdc_id']) {
                    continue;
                }
                if ((string) $food->usda_fdc_id !== (string) $correction['old_fdc_id']) {
                    throw new RuntimeException("Ingredient '{$name}' has an unexpected USDA source.");
                }
                foreach ($correction['old_macros'] as $field => $value) {
                    if ((float) $food->{$field} !== (float) $value) {
                        throw new RuntimeException("Ingredient '{$name}' has changed nutrient values; review before migration.");
                    }
                }
                if (FoodItem::query()->where('usda_fdc_id', $correction['new']['usda_fdc_id'])->whereKeyNot($food->id)->exists()) {
                    throw new RuntimeException("The approved USDA source for '{$name}' already belongs to another Ingredient.");
                }
                $foodUpdates[] = [$food, $correction['new']];
            }

            $ingredients = RecipeIngredient::query()->with(['foodItem', 'recipe'])->orderBy('id')->get();
            $rowUpdates = [];
            $affectedRecipeIds = [];
            foreach ($ingredients as $ingredient) {
                $food = $ingredient->foodItem;
                if ($food === null) {
                    throw new RuntimeException('A Menu Item has no measured Ingredient source.');
                }
                $quantity = (float) $ingredient->quantity;
                $unit = (string) $ingredient->unit;
                $measure = self::MEASURE_CORRECTIONS[$food->name] ?? null;
                if (! UnitConverter::isCompatible($unit, (string) $food->serving_unit)) {
                    if ($measure === null || $ingredient->recipe?->name !== $measure['recipe']
                        || ! in_array((int) $food->usda_fdc_id, $measure['source_ids'], true)
                        || UnitConverter::normalize($unit) !== 'ml'
                        || UnitConverter::normalize((string) $food->serving_unit) !== 'g'
                        || (float) $food->serving_size !== 100.0
                        || $quantity !== (float) $measure['old_quantity']
                    ) {
                        throw new RuntimeException("Unverified measured quantity/unit for Menu Item ingredient {$ingredient->id}; no data changed.");
                    }
                    $quantity = $measure['quantity_g'];
                    $unit = 'g';
                    $rowUpdates[] = [$ingredient, $quantity];
                }
                try {
                    UnitConverter::scalingFactor($quantity, $unit, (float) $food->serving_size, (string) $food->serving_unit);
                } catch (InvalidArgumentException $exception) {
                    throw new RuntimeException('A Menu Item requires a corrected measured Ingredient baseline before migration.', previous: $exception);
                }
                if (in_array($food->id, $sourceFoodIds, true) || $measure !== null) {
                    $affectedRecipeIds[] = $ingredient->recipe_id;
                }
            }
            $affectedRecipeIds = array_values(array_unique($affectedRecipeIds));
            $lines = MealPlanTemplateItem::query()->whereIn('recipe_id', $affectedRecipeIds)->get();
            foreach ($lines as $line) {
                if ($line->food_item_id !== null || $line->unit !== 'serving' || (float) $line->quantity <= 0 || $line->nutrient_snapshot === null) {
                    throw new RuntimeException('Normalize and verify reusable template serving lines before correcting their nutrients.');
                }
            }

            foreach ($foodUpdates as [$food, $attributes]) {
                $food->update($attributes);
            }
            foreach ($rowUpdates as [$ingredient, $quantity]) {
                $ingredient->update(['quantity' => $quantity, 'unit' => 'g']);
            }
            $recipes = Recipe::query()->whereIn('id', $affectedRecipeIds)->with('ingredients.foodItem')->get()->keyBy('id');
            foreach ($recipes as $recipe) {
                $recipe->recalculateTotals();
            }
            foreach ($lines as $line) {
                $recipe = $recipes[$line->recipe_id];
                $yield = (float) $recipe->servings;
                if ($yield <= 0) {
                    throw new RuntimeException('Correct the Menu Item batch yield before refreshing reusable template nutrients.');
                }
                $snapshot = $line->nutrient_snapshot;
                foreach (['calories', 'protein', 'carbs', 'fat'] as $nutrient) {
                    $snapshot[$nutrient] = round((float) $recipe->{'total_'.$nutrient} / $yield, 2);
                }
                $snapshot['water_g'] = round((float) $recipe->total_water_g / $yield, 2);
                $snapshot['micronutrients'] = array_map(fn ($value): float => round((float) $value / $yield, 3), $recipe->micronutrients ?? []);
                $snapshot['serving_size'] = 1;
                $snapshot['serving_unit'] = 'serving';
                $snapshot['recipe_yield'] = $yield;
                $snapshot['recipe_ingredients'] = $recipe->ingredients->map(fn ($ingredient): array => [
                    'name' => $ingredient->foodItem->name,
                    'quantity' => (float) $ingredient->quantity,
                    'unit' => $ingredient->unit,
                ])->all();
                $line->update(['nutrient_snapshot' => $snapshot]);
            }

            if (FoodItem::query()->count() !== $foodCount || Recipe::query()->count() !== $recipeCount || MealPlanTemplateItem::query()->count() !== $lineCount) {
                throw new RuntimeException('Catalog correction unexpectedly changed record counts.');
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Verified clinical source corrections require a forward fix; incorrect nutrient data must not be restored.');
    }
};
