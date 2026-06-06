<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodItem extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'category', 'usda_fdc_id', 'calories', 'protein', 'carbs', 'fat', 'water_g',
        'micronutrients', 'allergens', 'unit_price', 'serving_unit', 'serving_size',
    ];

    protected $casts = [
        'micronutrients' => 'array',
        'allergens'      => 'array',
        'calories'       => 'decimal:2',
        'protein'        => 'decimal:2',
        'carbs'          => 'decimal:2',
        'fat'            => 'decimal:2',
        'water_g'        => 'float',
        'unit_price'     => 'decimal:2',
        'serving_size'   => 'decimal:2',
    ];

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

