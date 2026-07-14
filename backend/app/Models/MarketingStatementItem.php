<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingStatementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_statement_id', 'item_description', 'unit', 'qty',
        'unit_price', 'total_value', 'record_payment', 'food_item_id',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function marketingStatement()
    {
        return $this->belongsTo(MarketingStatement::class);
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }
}
