<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAttachment extends Model
{
    use HasPublicId;

    protected $fillable = ['purchase_order_id', 'vendor_group_id', 'stored_object_id', 'type', 'path', 'caption'];

    protected $hidden = ['path', 'stored_object_id'];

    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => '/api/fss/purchase-order-attachments/'.$this->uuid.'/file');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendorGroup(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderVendorGroup::class, 'vendor_group_id');
    }

    public function storedObject(): BelongsTo
    {
        return $this->belongsTo(StoredObject::class);
    }
}
