<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuCycleTemplateDay extends Model
{
    protected $fillable = [
        'template_id', 'day_of_week', 'meal_type', 'recipe_id', 'fs_item_id', 'quantity',
    ];

    protected $casts = ['quantity' => 'decimal:2'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuCycleTemplate::class, 'template_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'recipe_id');
    }

    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }
}
