<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Recipe extends Model
{
    use HasFactory;
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'rnd_user_id', 'name', 'category', 'meal_types', 'component_type', 'prep_notes', 'cost',
        'total_calories', 'total_protein', 'total_carbs', 'total_fat', 'total_water_g',
        'micronutrients', 'servings', 'prepared_portion_amount', 'prepared_portion_unit',
    ];

    protected $casts = [
        'micronutrients' => 'array',
        'meal_types' => 'array',
        'cost' => 'decimal:2',
        'total_calories' => 'decimal:2',
        'total_protein' => 'decimal:2',
        'total_carbs' => 'decimal:2',
        'total_fat' => 'decimal:2',
        'total_water_g' => 'decimal:2',
        'prepared_portion_amount' => 'decimal:2',
    ];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function hasValidPortionData(): bool
    {
        if (! is_numeric($this->servings) || (float) $this->servings <= 0 || $this->ingredients->isEmpty()) {
            return false;
        }

        foreach ($this->ingredients as $ingredient) {
            if (! $ingredient->foodItem) {
                return false;
            }
            try {
                $this->ingredientFactor($ingredient, $ingredient->foodItem);
            } catch (InvalidArgumentException) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scale only within a physical family or the same measured count unit.
     */
    private function ingredientFactor(RecipeIngredient $ing, FoodItem $food): float
    {
        return UnitConverter::scalingFactor(
            (float) $ing->quantity,
            (string) $ing->unit,
            (float) $food->serving_size,
            (string) $food->serving_unit,
        );
    }

    /**
     * Computed water accessor: sum water_g from all ingredients using the same
     * factor logic as other nutrients. Falls back to the persisted total_water_g
     * column if ingredients are not loaded.
     *
     * Phase 3.2: unblocks frontend water display and meal-plan day totals (4.3).
     */
    public function getTotalWaterAttribute(): float
    {
        if ($this->relationLoaded('ingredients')) {
            $total = 0.0;
            foreach ($this->ingredients as $ing) {
                $food = $ing->foodItem;
                if (! $food) {
                    continue;
                }
                $total += (float) ($food->water_g ?? 0) * $this->ingredientFactor($ing, $food);
            }

            return round($total, 2);
        }

        return (float) ($this->total_water_g ?? 0);
    }

    /** Build a measured per-serving snapshot without changing the library Recipe. */
    public function planSnapshot(array $overrides = [], ?array $baseline = null, ?array $ingredients = null): array
    {
        if ($ingredients !== null) {
            $foods = FoodItem::withTrashed()->whereIn('uuid', array_column($ingredients, 'food_item_id'))->get()->keyBy('uuid');
            $frozenFoods = collect($baseline['recipe_original_ingredients'] ?? [])->keyBy('food_item.id');
            $rows = [];
            foreach ($ingredients as $index => $ingredient) {
                $food = $foods->get($ingredient['food_item_id']);
                $frozenFood = $frozenFoods->get($ingredient['food_item_id'])['food_item'] ?? null;
                if (! $food || ($food->trashed() && ! $frozenFood)) {
                    throw new InvalidArgumentException('Choose an existing Ingredient.');
                }
                $rows[] = [
                    'id' => $index + 1, 'food_item_id' => $food->id,
                    'name' => $frozenFood['name'] ?? $food->name,
                    'quantity' => $ingredient['quantity'], 'unit' => $ingredient['unit'],
                    'food_item' => $frozenFood ?? ['id' => $food->uuid, 'name' => $food->name,
                        ...array_intersect_key($food->toArray(), array_flip(['calories', 'protein', 'carbs', 'fat', 'water_g', 'micronutrients', 'serving_size', 'serving_unit']))],
                ];
            }
            $baseline = [...($baseline ?? []), 'recipe_original_ingredients' => $rows, 'recipe_ingredients' => [], 'recipe_yield' => $baseline['recipe_yield'] ?? $this->servings];
        }
        $originalIngredients = $baseline['recipe_original_ingredients'] ?? [];
        $frozen = (float) ($baseline['recipe_yield'] ?? 0) > 0 && $originalIngredients !== []
            && collect($originalIngredients)->every(fn ($ingredient) => isset($ingredient['id'], $ingredient['food_item']['serving_size'], $ingredient['food_item']['serving_unit']));
        if (! $frozen) {
            if ($baseline !== null) {
                throw new InvalidArgumentException('Repair the saved recipe details before editing this portion.');
            }
            if (! $this->hasValidPortionData()) {
                throw new InvalidArgumentException('Correct the Menu Item yield and measured Ingredient units before using this portion.');
            }
            $originalIngredients = $this->ingredients->map(fn ($ingredient) => [
                'id' => $ingredient->id,
                'food_item_id' => $ingredient->food_item_id,
                'name' => $ingredient->foodItem->name,
                'quantity' => (float) $ingredient->quantity,
                'unit' => $ingredient->unit,
                'food_item' => [
                    'id' => $ingredient->foodItem->uuid,
                    'name' => $ingredient->foodItem->name,
                    ...array_intersect_key($ingredient->foodItem->toArray(), array_flip([
                        'calories', 'protein', 'carbs', 'fat', 'water_g', 'micronutrients', 'serving_size', 'serving_unit',
                    ])),
                ],
            ])->all();
        }
        $servings = (float) ($frozen ? $baseline['recipe_yield'] : $this->servings);
        $planned = collect($baseline['recipe_ingredients'] ?? [])->keyBy('id');
        $overrideQty = collect($overrides)->keyBy('id');
        if ($overrideQty->count() !== count($overrides) || $overrideQty->keys()->diff(array_column($originalIngredients, 'id'))->isNotEmpty()) {
            throw new InvalidArgumentException('Choose only ingredients belonging to this Menu Item.');
        }
        $cal = $prot = $carb = $fat = $water = 0.0;
        $micros = [];
        $planIngredients = [];

        foreach ($originalIngredients as $ingredient) {
            $food = $ingredient['food_item'];
            $quantity = (float) ($overrideQty[$ingredient['id']]['quantity'] ?? $planned[$ingredient['id']]['quantity'] ?? $ingredient['quantity']);
            $planIngredients[] = [
                'id' => $ingredient['id'],
                'name' => $ingredient['name'],
                'quantity' => $quantity,
                'unit' => $ingredient['unit'],
            ];
            $factor = UnitConverter::scalingFactor($quantity, (string) $ingredient['unit'], (float) $food['serving_size'], (string) $food['serving_unit']);
            $cal += (float) $food['calories'] * $factor;
            $prot += (float) ($food['protein'] ?? 0) * $factor;
            $carb += (float) ($food['carbs'] ?? 0) * $factor;
            $fat += (float) ($food['fat'] ?? 0) * $factor;
            $water += (float) ($food['water_g'] ?? 0) * $factor;
            foreach (($food['micronutrients'] ?? []) as $key => $value) {
                $micros[$key] = ($micros[$key] ?? 0) + (float) $value * $factor;
            }
        }

        return [
            'name' => $baseline['name'] ?? $this->name,
            'prep_notes' => $frozen ? ($baseline['prep_notes'] ?? null) : $this->prep_notes,
            'calories' => round($cal / $servings, 2),
            'protein' => round($prot / $servings, 2),
            'carbs' => round($carb / $servings, 2),
            'fat' => round($fat / $servings, 2),
            'water_g' => round($water / $servings, 2),
            'serving_size' => 1,
            'serving_unit' => 'serving',
            'micronutrients' => array_map(fn ($value) => round($value / $servings, 3), $micros),
            'recipe_ingredients' => $planIngredients,
            'recipe_original_ingredients' => $originalIngredients,
            'recipe_yield' => $servings,
            ...(isset($baseline['recipe_snapshot_source']) ? ['recipe_snapshot_source' => $baseline['recipe_snapshot_source']] : []),
        ];
    }

    /**
     * Recalculate and persist nutrient totals from ingredients, including water.
     */
    public function recalculateTotals(): void
    {
        $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'water' => 0, 'cost' => 0];
        $micros = [];

        foreach ($this->ingredients()->with('foodItem')->get() as $ing) {
            $food = $ing->foodItem;
            if (! $food) {
                continue;
            }
            $factor = $this->ingredientFactor($ing, $food);

            $totals['calories'] += (float) $food->calories * $factor;
            $totals['protein'] += (float) $food->protein * $factor;
            $totals['carbs'] += (float) $food->carbs * $factor;
            $totals['fat'] += (float) $food->fat * $factor;
            $totals['water'] += (float) ($food->water_g ?? 0) * $factor;
            $totals['cost'] += (float) $food->unit_price * $factor;

            // Aggregate micronutrients weighted by ingredient proportion
            foreach ($food->micronutrients ?? [] as $key => $value) {
                $micros[$key] = ($micros[$key] ?? 0) + ((float) $value * $factor);
            }
        }

        $this->update([
            'total_calories' => round($totals['calories'], 2),
            'total_protein' => round($totals['protein'], 2),
            'total_carbs' => round($totals['carbs'], 2),
            'total_fat' => round($totals['fat'], 2),
            'total_water_g' => round($totals['water'], 2),
            'cost' => round($totals['cost'], 2),
            'micronutrients' => array_map(fn ($v) => round($v, 3), $micros),
        ]);
    }
}
