<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'item_type', 'fs_item_id', 'recipe_id',
        'quantity_in_stock', 'unit',
        'usage_rate', 'minimum_stock_threshold', 'unit_price', 'notes',
    ];

    protected $casts = [
        'quantity_in_stock'       => 'decimal:2',
        'usage_rate'              => 'decimal:2',
        'minimum_stock_threshold' => 'decimal:2',
        'unit_price'              => 'decimal:2',
    ];

    /** Stock of a catalog item (ingredient or supply). */
    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }

    /** Stock of a prepared food-service recipe. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(FoodServiceRecipe::class, 'recipe_id');
    }
}
