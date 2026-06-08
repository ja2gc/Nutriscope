<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodServiceRecipe extends Model
{
    protected $table = 'food_service_recipes';

    protected $fillable = [
        'rnd_user_id', 'name', 'category', 'prep_notes', 'servings',
        'cost', 'total_calories', 'total_protein', 'total_carbs', 'total_fat',
    ];

    protected $casts = [
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
        return $this->hasMany(FoodServiceRecipeIngredient::class);
    }

    /**
     * Cost = Σ (ingredient.quantity / 100) × inventory.unit_price
     * unit_price in inventory is ₱/100g.
     */
    public function recalculateCost(): void
    {
        $cost = 0.0;
        foreach ($this->ingredients()->with('inventoryItem')->get() as $ing) {
            $inv = $ing->inventoryItem;
            $cost += (float) ($inv?->unit_price ?? 0) * ($ing->quantity / 100);
        }
        $this->update(['cost' => round($cost, 2)]);
    }
}
