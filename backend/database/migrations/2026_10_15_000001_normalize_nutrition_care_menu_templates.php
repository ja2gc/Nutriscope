<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const FOOD_CATEGORIES = [
        'Steamed White Rice' => 'carb',
        'Steamed Brown Rice' => 'carb',
        'Sweet Potato / Kamote' => 'carb',
        'Sweet Corn (Cooked)' => 'carb',
        'Bok Choy / Pechay (Cooked)' => 'meal',
        'Carrots (Cooked)' => 'meal',
        'Squash / Kalabasa (Cooked)' => 'meal',
        'String Beans / Sitaw' => 'meal',
        'Eggplant / Talong (Cooked)' => 'meal',
        'Water Spinach (Kangkong)' => 'meal',
        'Low-fat Milk (1%)' => 'snack',
        'Banana (Raw)' => 'snack',
        'Papaya (Raw)' => 'snack',
    ];

    private const LEGACY_CATEGORIES = [
        'breakfast' => 'meal',
        'diabetic-friendly' => 'meal',
        'high fiber' => 'meal',
        'high protein' => 'meal',
        'regular diet' => 'meal',
        'soft diet' => 'meal',
        'staple' => 'carb',
        'vegetable' => 'meal',
        'vegetarian' => 'meal',
    ];

    private const LEGACY_SNACK_CARBS = [
        'Boiled Corn (Mais)',
        'Boiled Kamote (Sweet Potato)',
        'Boiled Cassava (Kamoteng Kahoy)',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $recipes = DB::table('recipes')->orderBy('id')->get();
            $recipesById = $recipes->keyBy('id');
            $categoryUpdates = [];

            foreach ($recipes as $recipe) {
                $category = strtolower(trim((string) $recipe->category));
                $target = in_array($category, ['meal', 'carb', 'snack'], true)
                    ? $category
                    : (self::LEGACY_CATEGORIES[$category] ?? null);

                if ($category === 'snack' && in_array($recipe->name, self::LEGACY_SNACK_CARBS, true)) {
                    $target = 'carb';
                }
                if ($target === null) {
                    throw new RuntimeException("Unresolved legacy recipe category '{$recipe->category}' for '{$recipe->name}'.");
                }
                if ($recipe->category !== $target) {
                    $categoryUpdates[$recipe->id] = $target;
                }
            }

            $items = DB::table('meal_plan_template_items as item')
                ->join('meal_plan_template_days as day', 'day.id', '=', 'item.template_day_id')
                ->join('meal_plan_templates as template', 'template.id', '=', 'day.template_id')
                ->select('item.*', 'template.rnd_user_id')
                ->orderBy('item.template_day_id')
                ->orderBy('item.line_order')
                ->get();
            $itemCount = $items->count();
            $recipeCount = $recipes->count();
            $snapshots = $items->mapWithKeys(fn ($item): array => [$item->id => $this->decodeJson($item->nutrient_snapshot)])->all();
            $lineOrders = $items->mapWithKeys(fn ($item): array => [$item->id => $item->line_order])->all();
            $foodsById = [];
            $wrapperSpecs = [];
            $itemUpdates = [];

            foreach ($items as $item) {
                if ($item->nutrient_snapshot === null) {
                    throw new RuntimeException("Template item {$item->id} has no saved nutrient snapshot.");
                }
                $itemUpdates[$item->id] = $this->convertLine(
                    (int) $item->rnd_user_id,
                    $item->food_item_id === null ? null : (int) $item->food_item_id,
                    $item->recipe_id === null ? null : (int) $item->recipe_id,
                    (float) $item->quantity,
                    (string) $item->unit,
                    $recipesById,
                    $foodsById,
                    $wrapperSpecs,
                );
            }

            $days = DB::table('meal_plan_template_days as day')
                ->join('meal_plan_templates as template', 'template.id', '=', 'day.template_id')
                ->select('day.*', 'template.rnd_user_id')
                ->orderBy('day.id')
                ->get();
            $itemsByDay = $items->groupBy('template_day_id');
            $parentOnlyUpdates = [];

            foreach ($days as $day) {
                if ($itemsByDay->has($day->id)) {
                    continue;
                }
                if ($day->food_item_id === null && $day->recipe_id === null) {
                    continue;
                }
                $parentOnlyUpdates[$day->id] = $this->convertLine(
                    (int) $day->rnd_user_id,
                    $day->food_item_id === null ? null : (int) $day->food_item_id,
                    $day->recipe_id === null ? null : (int) $day->recipe_id,
                    (float) $day->quantity,
                    (string) $day->unit,
                    $recipesById,
                    $foodsById,
                    $wrapperSpecs,
                );
            }

            foreach ($wrapperSpecs as &$spec) {
                $existing = DB::table('recipes')
                    ->where('rnd_user_id', $spec['owner_id'])
                    ->where('name', $spec['food']->name)
                    ->get();
                if ($existing->count() > 1) {
                    throw new RuntimeException("Duplicate Menu Items exist for '{$spec['food']->name}' and RND {$spec['owner_id']}.");
                }
                if ($existing->isEmpty()) {
                    continue;
                }

                $wrapper = $existing->first();
                $ingredient = DB::table('recipe_ingredients')->where('recipe_id', $wrapper->id)->get();
                if ($ingredient->count() !== 1
                    || (int) $ingredient->first()->food_item_id !== (int) $spec['food']->id
                    || (float) $ingredient->first()->quantity !== (float) $spec['food']->serving_size
                    || $ingredient->first()->unit !== $spec['food']->serving_unit
                    || (int) $wrapper->servings !== 1
                    || (float) $wrapper->prepared_portion_amount !== (float) $spec['food']->serving_size
                    || $wrapper->prepared_portion_unit !== $spec['food']->serving_unit
                ) {
                    throw new RuntimeException("Recipe '{$spec['food']->name}' exists but is not the verified one-Ingredient Menu Item.");
                }
                $spec['existing_id'] = (int) $wrapper->id;
            }
            unset($spec);

            foreach ($categoryUpdates as $id => $category) {
                DB::table('recipes')->where('id', $id)->update(['category' => $category]);
            }

            $wrapperIds = [];
            foreach ($wrapperSpecs as $key => $spec) {
                $food = $spec['food'];
                $attributes = [
                    'name' => $food->name,
                    'rnd_user_id' => $spec['owner_id'],
                    'prep_notes' => "Single-ingredient Menu Item using the source Ingredient's {$food->serving_size} {$food->serving_unit} nutrient basis.",
                    'cost' => $food->unit_price,
                    'total_calories' => $food->calories,
                    'total_protein' => $food->protein,
                    'total_carbs' => $food->carbs,
                    'total_fat' => $food->fat,
                    'total_water_g' => $food->water_g ?? 0,
                    'micronutrients' => $food->micronutrients,
                    'category' => $spec['category'],
                    'meal_types' => json_encode($spec['category'] === 'snack' ? ['am_snack', 'pm_snack'] : ['breakfast', 'lunch', 'dinner']),
                    'component_type' => match ($spec['category']) {
                        'carb' => 'staple',
                        'snack' => 'other',
                        default => 'main_dish',
                    },
                    'servings' => 1,
                    'prepared_portion_amount' => $food->serving_size,
                    'prepared_portion_unit' => $food->serving_unit,
                    'updated_at' => now(),
                ];

                if (isset($spec['existing_id'])) {
                    $wrapperIds[$key] = $spec['existing_id'];
                    DB::table('recipes')->where('id', $spec['existing_id'])->update($attributes);
                } else {
                    $attributes['uuid'] = (string) Str::uuid();
                    $attributes['created_at'] = now();
                    $wrapperIds[$key] = DB::table('recipes')->insertGetId($attributes);
                    DB::table('recipe_ingredients')->insert([
                        'recipe_id' => $wrapperIds[$key],
                        'food_item_id' => $food->id,
                        'quantity' => $food->serving_size,
                        'unit' => $food->serving_unit,
                    ]);
                }
            }

            foreach ($itemUpdates as $id => $line) {
                $this->saveLine('meal_plan_template_items', $id, $line, $wrapperIds);
            }

            foreach ($days as $day) {
                $dayItems = $itemsByDay->get($day->id);
                if ($dayItems !== null) {
                    $first = $dayItems->first();
                    $this->saveLine('meal_plan_template_days', $day->id, $itemUpdates[$first->id], $wrapperIds);
                } elseif (isset($parentOnlyUpdates[$day->id])) {
                    $this->saveLine('meal_plan_template_days', $day->id, $parentOnlyUpdates[$day->id], $wrapperIds);
                }
            }

            $actualSnapshots = DB::table('meal_plan_template_items')->pluck('nutrient_snapshot', 'id');
            $actualOrders = DB::table('meal_plan_template_items')->pluck('line_order', 'id');
            foreach ($snapshots as $id => $snapshot) {
                if ($snapshot !== $this->decodeJson($actualSnapshots[$id] ?? null)
                    || (int) ($lineOrders[$id] ?? -1) !== (int) ($actualOrders[$id] ?? -2)
                ) {
                    throw new RuntimeException("Template item {$id} snapshot or line order changed unexpectedly.");
                }
            }

            if (DB::table('meal_plan_template_items')->count() !== $itemCount
                || DB::table('recipes')->count() !== $recipeCount + count(array_filter($wrapperSpecs, fn (array $spec): bool => ! isset($spec['existing_id'])))
                || DB::table('meal_plan_template_items')->where(function ($query): void {
                    $query->whereNull('recipe_id')->orWhereNotNull('food_item_id')->orWhere('unit', '!=', 'serving');
                })->exists()
                || DB::table('meal_plan_template_days')->where(function ($query): void {
                    $query->whereNotNull('food_item_id')->orWhereNotNull('recipe_id');
                })->where(function ($query): void {
                    $query->whereNull('recipe_id')->orWhereNotNull('food_item_id')->orWhere('unit', '!=', 'serving');
                })->exists()
            ) {
                throw new RuntimeException('Nutrition menu template migration failed its post-write invariants.');
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Nutrition menu data normalization is intentionally irreversible; preserve this forward migration.');
    }

    private function convertLine(
        int $ownerId,
        ?int $foodId,
        ?int $recipeId,
        float $quantity,
        string $unit,
        $recipesById,
        array &$foodsById,
        array &$wrapperSpecs,
    ): array {
        if (($foodId === null) === ($recipeId === null) || $quantity <= 0) {
            throw new RuntimeException('A template line must have exactly one source and a positive quantity.');
        }

        if ($foodId !== null) {
            $food = $foodsById[$foodId] ??= DB::table('food_items')->where('id', $foodId)->first();
            $category = $food === null ? null : (self::FOOD_CATEGORIES[$food->name] ?? null);
            if ($food === null || $category === null || $food->usda_fdc_id === null
                || (float) $food->serving_size !== 100.0
                || strtolower(trim((string) $food->serving_unit)) !== 'g'
                || strtolower(trim($unit)) !== 'g'
            ) {
                throw new RuntimeException("Direct template Ingredient {$foodId} lacks a verified 100 g baseline or taxonomy mapping.");
            }
            $servings = round($quantity / 100, 2);
            if ($servings <= 0 || $servings > 999999.99) {
                throw new RuntimeException("Direct template Ingredient {$foodId} cannot be represented as a positive two-decimal serving quantity.");
            }

            $key = $ownerId.':'.$foodId;
            $wrapperSpecs[$key] ??= ['owner_id' => $ownerId, 'food' => $food, 'category' => $category];

            return ['food_item_id' => null, 'recipe_id' => $key, 'quantity' => $servings, 'unit' => 'serving'];
        }

        $recipe = $recipesById->get($recipeId);
        if ($recipe === null || (int) $recipe->rnd_user_id !== $ownerId) {
            throw new RuntimeException("Template recipe {$recipeId} is missing or belongs to a different RND owner.");
        }
        if (strtolower(trim($unit)) === 'serving') {
            return ['food_item_id' => null, 'recipe_id' => $recipeId, 'quantity' => round($quantity, 2), 'unit' => 'serving'];
        }
        if ((float) $recipe->prepared_portion_amount <= 0
            || strtolower(trim($unit)) !== strtolower(trim((string) $recipe->prepared_portion_unit))
        ) {
            throw new RuntimeException("Template line for '{$recipe->name}' has no matching prepared-portion basis.");
        }

        $servings = round($quantity / (float) $recipe->prepared_portion_amount, 2);
        if ($servings <= 0 || $servings > 999999.99) {
            throw new RuntimeException("Template line for '{$recipe->name}' cannot be represented as a positive two-decimal serving quantity.");
        }

        return ['food_item_id' => null, 'recipe_id' => $recipeId, 'quantity' => $servings, 'unit' => 'serving'];
    }

    private function saveLine(string $table, int $id, array $line, array $wrapperIds): void
    {
        $line['recipe_id'] = $wrapperIds[$line['recipe_id']] ?? $line['recipe_id'];
        if ($line['recipe_id'] === null) {
            throw new RuntimeException("Template line {$id} could not resolve a Menu Item Recipe.");
        }
        DB::table($table)->where('id', $id)->update($line + ['updated_at' => now()]);
    }

    private function decodeJson(?string $json): mixed
    {
        return $json === null ? null : json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
};
