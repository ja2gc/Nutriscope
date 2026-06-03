<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingStatement extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'purchase_order_id', 'date', 'period_start', 'period_end',
        'subtotal_forwarded', 'grand_total', 'buyer_name', 'buyer_title',
        'certified_by', 'certified_by_title', 'examined_by', 'examined_by_title',
        'verified_by', 'verified_by_title', 'file_path', 'extracted_data'
    ];

    protected $casts = [
        'date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'subtotal_forwarded' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'extracted_data' => 'array',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(MarketingStatementItem::class);
    }

}

