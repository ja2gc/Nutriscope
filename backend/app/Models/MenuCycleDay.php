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
        'estimate_population', 'estimate_population_updated_at',
        'is_event', 'event_allocation',
        'snapshot_purchase_order_id', 'po_snapshot', 'po_snapshot_at', 'po_snapshot_locked',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'servings_override' => 'integer',
        'estimate_population' => 'integer',
        'estimate_population_updated_at' => 'datetime',
        'is_event' => 'boolean',
        'event_allocation' => 'decimal:2',
        'po_snapshot' => 'array',
        'po_snapshot_at' => 'datetime',
        'po_snapshot_locked' => 'boolean',
    ];

    public function snapshotPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'snapshot_purchase_order_id');
    }

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    /** Food-service recipe (NOT the NCP recipe). */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'recipe_id');
    }

    /** Single catalog item (alternative to a recipe). */
    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }
}
