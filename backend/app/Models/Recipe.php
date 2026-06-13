<?php

namespace App\Models;

use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;
    protected $fillable = [
        'rnd_user_id', 'name', 'category', 'meal_types', 'prep_notes', 'cost',
        'total_calories', 'total_protein', 'total_carbs', 'total_fat', 'total_water_g',
        'micronutrients', 'servings',
    ];

    protected $casts = [
        'micronutrients' => 'array',
        'meal_types'     => 'array',
        'cost'           => 'decimal:2',
        'total_calories' => 'decimal:2',
        'total_protein'  => 'decimal:2',
        'total_carbs'    => 'decimal:2',
        'total_fat'      => 'decimal:2',
        'total_water_g'  => 'decimal:2',
    ];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * Unit-correct scaling factor for one ingredient against its food's serving.
     * A missing ingredient unit defaults to the food's serving unit (legacy data).
     *
     * Physical units (mass/volume) are converted exactly. When either side is a
     * non-convertible COUNT unit (e.g. 'piece', 'serving', 'slice') — or the units
     * already match — we use a direct numeric ratio quantity/serving_size: there is
     * no defined gram-per-piece, so the recorded numbers are taken as comparable
     * rather than throwing. (Mass↔volume crossings still convert via density.)
     */
    private function ingredientFactor(RecipeIngredient $ing, FoodItem $food): float
    {
        $servingUnit    = $food->serving_unit ?: 'g';
        $ingredientUnit = $ing->unit ?: $servingUnit;
        $size           = (float) $food->serving_size ?: 100.0;

        $bothConvertible = UnitConverter::isKnown($ingredientUnit) && UnitConverter::isKnown($servingUnit);
        $sameUnit        = UnitConverter::normalize($ingredientUnit) === UnitConverter::normalize($servingUnit);

        if (! $bothConvertible || $sameUnit) {
            return (float) $ing->quantity / $size;
        }

        return UnitConverter::scalingFactor(
            (float) $ing->quantity,
            $ingredientUnit,
            $size,
            $servingUnit,
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
                if (!$food) continue;
                $total += (float) ($food->water_g ?? 0) * $this->ingredientFactor($ing, $food);
            }
            return round($total, 2);
        }
        return (float) ($this->total_water_g ?? 0);
    }

    /**
     * Recalculate and persist nutrient totals from ingredients, including water.
     */
    public function recalculateTotals(): void
    {
        $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'water' => 0];
        $micros = [];

        foreach ($this->ingredients()->with('foodItem')->get() as $ing) {
            $food   = $ing->foodItem;
            if (! $food) continue;
            $factor = $this->ingredientFactor($ing, $food);

            $totals['calories'] += (float) $food->calories * $factor;
            $totals['protein']  += (float) $food->protein  * $factor;
            $totals['carbs']    += (float) $food->carbs    * $factor;
            $totals['fat']      += (float) $food->fat      * $factor;
            $totals['water']    += (float) ($food->water_g ?? 0) * $factor;

            // Aggregate micronutrients weighted by ingredient proportion
            foreach ($food->micronutrients ?? [] as $key => $value) {
                $micros[$key] = ($micros[$key] ?? 0) + ((float) $value * $factor);
            }
        }

        $this->update([
            'total_calories' => round($totals['calories'], 2),
            'total_protein'  => round($totals['protein'], 2),
            'total_carbs'    => round($totals['carbs'], 2),
            'total_fat'      => round($totals['fat'], 2),
            'total_water_g'  => round($totals['water'], 2),
            'micronutrients' => array_map(fn($v) => round($v, 3), $micros),
        ]);
    }
}

