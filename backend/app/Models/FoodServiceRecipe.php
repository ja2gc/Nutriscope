<?php

namespace App\Models;

use App\Support\RecipeScaler;
use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodServiceRecipe extends Model
{
    protected $table = 'food_service_recipes';

    protected $fillable = [
        'rnd_user_id', 'name', 'category', 'prep_notes', 'servings', 'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(FoodServiceRecipeIngredient::class);
    }

    /**
     * Cost = Σ ingredient.quantity (in the item's base_unit) × fs_item.unit_cost.
     * Quantities entered in a different-but-known unit are converted first, so a
     * gram quantity always multiplies a ₱/gram cost. Catalog prices are live.
     */
    public function recalculateCost(): void
    {
        $cost = 0.0;
        foreach ($this->ingredients()->with('fsItem')->get() as $ing) {
            $item = $ing->fsItem;
            if (! $item) {
                continue;
            }
            $qty  = (float) $ing->quantity;
            $from = (string) $ing->unit;
            $base = (string) $item->base_unit;

            if ($from !== '' && $base !== ''
                && UnitConverter::normalize($from) !== UnitConverter::normalize($base)
                && UnitConverter::isKnown($from) && UnitConverter::isKnown($base)) {
                $qty = UnitConverter::convert($qty, $from, $base);
            }

            $cost += $qty * $item->unit_cost;
        }
        $this->update(['cost' => round($cost, 2)]);
    }

    /** Baseline → target scale factor for this recipe (target servings ÷ this recipe's yield). */
    public function scaleFactor(int $targetServings): float
    {
        return RecipeScaler::factor((int) $this->servings, $targetServings);
    }

    /** Total recipe cost when scaled to a target number of servings. */
    public function scaledCost(int $targetServings): float
    {
        return round(RecipeScaler::scale((float) $this->cost, (int) $this->servings, $targetServings), 2);
    }
}
