<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuCycleDay extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'menu_cycle_id', 'day_of_week', 'meal_type', 'line_order',
        'recipe_id', 'fs_item_id', 'quantity', 'servings_override', 'recipe_override', 'recipe_original',
        'estimate_population', 'estimate_population_updated_at',
        'is_event', 'event_allocation',
        'snapshot_purchase_order_id', 'po_snapshot', 'po_snapshot_at', 'po_snapshot_locked',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'line_order' => 'integer',
        'servings_override' => 'integer',
        'recipe_override' => 'array',
        'recipe_original' => 'array',
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

    public static function recipeBaseline(?int $recipeId): ?array
    {
        $recipe = $recipeId ? FoodServiceRecipe::with('ingredients.fsItem')->find($recipeId) : null;
        if (! $recipe) {
            return null;
        }

        return ['name' => $recipe->name, 'reference_servings' => $recipe->servings, 'prep_notes' => $recipe->prep_notes,
            'portion_multiplier' => 1,
            'ingredients' => $recipe->ingredients->filter(fn ($row) => $row->fsItem !== null)->map(fn ($row) => [
                'fs_item_id' => $row->fs_item_id, 'name' => $row->fsItem->name, 'quantity' => $row->quantity,
                'unit' => $row->unit, 'adjust_to_taste' => (bool) $row->adjust_to_taste,
            ])->values()->all()];
    }

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            $line->recipe_original ??= $line->recipe_override ?? self::recipeBaseline($line->recipe_id);
        });
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
