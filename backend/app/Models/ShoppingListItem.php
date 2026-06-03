<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListItem extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'shopping_list_id', 'food_item_id', 'ingredient_name',
        'qty', 'unit', 'supplier_id', 'unit_price', 'total'
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function shoppingList()
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

}

