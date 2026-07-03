<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HasPublicId;

    protected $fillable = [
        'shopping_list_id', 'fs_item_id', 'ingredient_name',
        'qty', 'unit', 'supplier_id', 'unit_price', 'total',
        'purchase_qty', 'purchase_unit', 'purchase_price',
        'vendor_locked_at', 'vendor_locked_by',
        'baseline_servings', 'baseline_quantity', 'scaled_quantity', 'scaled_unit',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'purchase_qty' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'vendor_locked_at' => 'datetime',
        'baseline_servings' => 'integer',
        'baseline_quantity' => 'decimal:2',
        'scaled_quantity' => 'decimal:2',
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

