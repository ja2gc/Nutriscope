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
        return $this->belongsTo(FoodItem::class)->withTrashed();
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealPlanTemplateItem::class, 'template_day_id')->orderBy('line_order');
    }

    public static function removeSource(string $column, int $id): void
    {
        static::where($column, $id)->orWhereHas('items', fn ($query) => $query->where($column, $id))
            ->get()->each(function (self $day) use ($column, $id): void {
                $day->items()->where($column, $id)->delete();
                $first = $day->items()->first();
                if ($first) {
                    $day->update($first->only(['food_item_id', 'recipe_id', 'quantity', 'unit']));
                } else {
                    $day->delete();
                }
            });
    }
}
