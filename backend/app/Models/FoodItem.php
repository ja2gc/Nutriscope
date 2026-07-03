<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodItem extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasPublicId;
    protected $fillable = [
        'name', 'category', 'ready_to_eat', 'usda_fdc_id', 'calories', 'protein', 'carbs', 'fat', 'water_g',
        'micronutrients', 'allergens', 'unit_price', 'serving_unit', 'serving_size',
    ];

    protected $casts = [
        'micronutrients' => 'array',
        'allergens'      => 'array',
        'ready_to_eat'   => 'boolean',
        'calories'       => 'decimal:2',
        'protein'        => 'decimal:2',
        'carbs'          => 'decimal:2',
        'fat'            => 'decimal:2',
        'water_g'        => 'float',
        'unit_price'     => 'decimal:2',
        'serving_size'   => 'decimal:2',
    ];

    /**
     * Categories whose items are ready-to-eat by default (no cooking required),
     * making them eligible as standalone snacks in meal-plan auto-generation.
     * The per-item `ready_to_eat` column overrides this when non-null.
     */
    public const READY_TO_EAT_CATEGORIES = ['fruit', 'vegetable'];

    /**
     * Whether this item may be placed as a standalone ready-to-eat snack.
     * Resolution: explicit `ready_to_eat` flag wins; otherwise the category allowlist.
     */
    public function isReadyToEat(): bool
    {
        if ($this->ready_to_eat !== null) {
            return (bool) $this->ready_to_eat;
        }
        return in_array($this->category, self::READY_TO_EAT_CATEGORIES, true);
    }

    /**
     * Scope: items eligible as standalone ready-to-eat snacks.
     * Eligible = ready_to_eat === true OR (ready_to_eat IS NULL AND category in allowlist).
     */
    public function scopeReadyToEat($query)
    {
        return $query->where(function ($q) {
            $q->where('ready_to_eat', true)
              ->orWhere(function ($q2) {
                  $q2->whereNull('ready_to_eat')
                     ->whereIn('category', self::READY_TO_EAT_CATEGORIES);
              });
        });
    }

    /**
     * ALWAYS use these scope methods â€” never raw JSON queries.
     * Enforces: food_items.allergens is JSON â€” always whereJsonContains / whereJsonDoesntContain
     */
    public function scopeWithoutAllergens($query, array $allergens)
    {
        foreach ($allergens as $allergen) {
            $query->whereJsonDoesntContain('allergens', $allergen);
        }
        return $query;
    }

    public function scopeWithAllergen($query, string $allergen)
    {
        return $query->whereJsonContains('allergens', $allergen);
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}

