<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAttachment extends Model
{
    protected $fillable = ['purchase_order_id', 'vendor_group_id', 'type', 'path', 'caption'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendorGroup(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderVendorGroup::class, 'vendor_group_id');
    }
}
