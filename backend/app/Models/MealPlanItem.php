<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanItem extends Model
{
    protected $fillable = [
        'meal_plan_day_id', 'food_item_id', 'recipe_id',
        'quantity', 'unit', 'nutrient_snapshot', 'ai_suggested',
    ];

    protected $casts = [
        'nutrient_snapshot' => 'array',
        'ai_suggested'      => 'boolean',
        'quantity'          => 'decimal:2',
    ];

    public function mealPlanDay(): BelongsTo
    {
        return $this->belongsTo(MealPlanDay::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }
}
