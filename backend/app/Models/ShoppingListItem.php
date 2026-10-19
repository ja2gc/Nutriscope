<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'shopping_list_id', 'fs_item_id', 'ingredient_name', 'source',
        'included_in_po', 'exclusion_note',
        'qty', 'unit', 'supplier_id', 'unit_price', 'total',
        'purchase_qty', 'purchase_unit', 'purchase_price',
        'quantity_mode',
        'vendor_locked_at', 'vendor_locked_by',
        'baseline_servings', 'baseline_quantity', 'scaled_quantity', 'scaled_unit',
        'generation_baseline',
    ];

    protected $casts = [
        'qty' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'total' => 'decimal:2',
        'purchase_qty' => 'decimal:6',
        'purchase_price' => 'decimal:6',
        'vendor_locked_at' => 'datetime',
        'baseline_servings' => 'integer',
        'baseline_quantity' => 'decimal:6',
        'scaled_quantity' => 'decimal:6',
        'included_in_po' => 'boolean',
        'generation_baseline' => 'array',
    ];

    public function vendorLocked(): bool
    {
        return $this->vendor_locked_at !== null;
    }

    public function shoppingList()
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function fsItem()
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
