<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'purchase_order_id', 'fs_item_id', 'description',
        'qty', 'unit', 'unit_price', 'total_value'
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function fsItem()
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }

}

