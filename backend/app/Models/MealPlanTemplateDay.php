<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlanTemplateDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id', 'day_of_week', 'meal_type', 'food_item_id',
        'recipe_id', 'quantity', 'unit',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function template()
    {
        return $this->belongsTo(MealPlanTemplate::class, 'template_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanTemplateItem::class, 'template_day_id')->orderBy('line_order');
    }
}
