<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlanDay extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = ['meal_plan_id', 'day_of_week', 'meal_type', 'flagged', 'variance'];

    protected $casts = [
        'flagged'  => 'boolean',
        'variance' => 'array',
    ];

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class);
    }

    /**
     * Sum nutrient totals for this meal slot.
     */
    public function nutrientTotals(): array
    {
        return $this->items->reduce(function ($carry, $item) {
            $snap = $item->nutrient_snapshot ?? [];
            $carry['calories'] = ($carry['calories'] ?? 0) + ($snap['calories'] ?? 0);
            $carry['protein']  = ($carry['protein'] ?? 0)  + ($snap['protein'] ?? 0);
            $carry['carbs']    = ($carry['carbs'] ?? 0)    + ($snap['carbs'] ?? 0);
            $carry['fat']      = ($carry['fat'] ?? 0)      + ($snap['fat'] ?? 0);
            return $carry;
        }, ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0]);
    }
}

