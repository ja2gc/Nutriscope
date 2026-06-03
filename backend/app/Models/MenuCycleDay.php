<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuCycleDay extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'menu_cycle_id', 'day_of_week', 'meal_type', 'recipe_id',
        'food_item_id', 'quantity'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function menuCycle()
    {
        return $this->belongsTo(MenuCycle::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }

}

