<?php

namespace App\Models;

use App\Support\RecipeScaler;
use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class FoodServiceRecipe extends Model
{
    use \App\Models\Concerns\AuditsChanges;
    use \App\Models\Concerns\HasPublicId;

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

    /**
     * Recompute the cached cost of every recipe that uses any of the given
     * fs_items. One bad recipe is logged, not allowed to abort the batch.
     *
     * @param array<int,int> $fsItemIds
     */
    public static function recalculateForItems(array $fsItemIds): void
    {
        if (! $fsItemIds) {
            return;
        }

        $recipeIds = FoodServiceRecipeIngredient::whereIn('fs_item_id', $fsItemIds)
            ->pluck('food_service_recipe_id')->unique();

        foreach (static::whereIn('id', $recipeIds)->get() as $recipe) {
            try {
                $recipe->recalculateCost();
            } catch (\Throwable $e) {
                Log::warning('recalculateForItems: recipe recompute failed', [
                    'recipe' => $recipe->id, 'error' => $e->getMessage(),
                ]);
            }
        }
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
