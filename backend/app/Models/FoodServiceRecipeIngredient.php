<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodServiceRecipeIngredient extends Model
{
    protected $table = 'food_service_recipe_ingredients';

    public $timestamps = false;

    protected $fillable = [
        'food_service_recipe_id', 'fs_item_id', 'quantity', 'unit',
    ];

    /** The food-service catalog item this line uses. */
    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'food_service_recipe_id');
    }
}
