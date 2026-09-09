<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanTemplateItem extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'template_day_id', 'food_item_id', 'recipe_id', 'fdc_id',
        'quantity', 'unit', 'nutrient_snapshot', 'ai_suggested', 'line_order',
    ];

    protected $casts = [
        'nutrient_snapshot' => 'array',
        'ai_suggested' => 'boolean',
        'quantity' => 'decimal:2',
        'line_order' => 'integer',
    ];

    public function templateDay(): BelongsTo
    {
        return $this->belongsTo(MealPlanTemplateDay::class, 'template_day_id');
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
