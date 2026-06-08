<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodServiceRecipeIngredient extends Model
{
    protected $table = 'food_service_recipe_ingredients';

    public $timestamps = false;

    protected $fillable = [
        'food_service_recipe_id', 'inventory_id', 'quantity', 'unit',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'food_service_recipe_id');
    }
}
