<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;
    protected $fillable = [
        'rnd_user_id', 'name', 'category', 'prep_notes', 'cost',
        'total_calories', 'total_protein', 'total_carbs', 'total_fat',
        'micronutrients', 'servings',
    ];

    protected $casts = [
        'micronutrients' => 'array',
        'cost'           => 'decimal:2',
        'total_calories' => 'decimal:2',
        'total_protein'  => 'decimal:2',
        'total_carbs'    => 'decimal:2',
        'total_fat'      => 'decimal:2',
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
     * Recalculate and persist nutrient totals from ingredients.
     */
    public function recalculateTotals(): void
    {
        $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
        $micros = [];

        foreach ($this->ingredients()->with('foodItem')->get() as $ing) {
            $food   = $ing->foodItem;
            $factor = $ing->quantity / ($food->serving_size ?: 100);

            $totals['calories'] += (float) $food->calories * $factor;
            $totals['protein']  += (float) $food->protein  * $factor;
            $totals['carbs']    += (float) $food->carbs    * $factor;
            $totals['fat']      += (float) $food->fat      * $factor;

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
            'micronutrients' => array_map(fn($v) => round($v, 3), $micros),
        ]);
    }
}

