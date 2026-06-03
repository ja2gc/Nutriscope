<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;
    
    protected $table = 'inventory';
    protected $fillable = [
        'food_item_id', 'quantity_in_stock', 'unit', 'expiry_date',
        'usage_rate', 'minimum_stock_threshold', 'notes'
    ];

    protected $casts = [
        'quantity_in_stock' => 'decimal:2',
        'usage_rate' => 'decimal:2',
        'minimum_stock_threshold' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }

}

