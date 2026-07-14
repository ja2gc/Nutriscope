<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'vendor_group_id', 'fs_item_id', 'description',
        'qty', 'unit', 'unit_price', 'total_value',
        'purchase_qty', 'purchase_unit', 'purchase_price',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_value' => 'decimal:2',
        'purchase_qty' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendorGroup()
    {
        return $this->belongsTo(PurchaseOrderVendorGroup::class, 'vendor_group_id');
    }

    public function fsItem()
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }

    public function corrections()
    {
        return $this->hasMany(PurchaseOrderItemCorrection::class, 'purchase_order_item_id');
    }
}
