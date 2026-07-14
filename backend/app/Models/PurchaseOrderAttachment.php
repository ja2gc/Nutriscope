<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAttachment extends Model
{
    use HasPublicId;

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
