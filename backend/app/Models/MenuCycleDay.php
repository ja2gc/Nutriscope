<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuCycleDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_cycle_id', 'day_of_week', 'meal_type',
        'recipe_id', 'fs_item_id', 'quantity', 'servings_override',
        'estimate_population', 'is_event', 'event_allocation',
    ];

    protected $casts = [
        'quantity'            => 'decimal:2',
        'servings_override'   => 'integer',
        'estimate_population' => 'integer',
        'is_event'            => 'boolean',
        'event_allocation'    => 'decimal:2',
    ];

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    /** Food-service recipe (NOT the NCP recipe). */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'recipe_id');
    }

    /** Single ready-to-serve catalog item (alternative to a recipe). */
    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }
}
