<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderAttachment extends Model
{
    use HasPublicId;

    protected $fillable = ['purchase_order_id', 'vendor_group_id', 'type', 'path', 'caption'];

    protected $appends = ['url'];

    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('filesystems.uploads'))->url($this->path));
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendorGroup(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderVendorGroup::class, 'vendor_group_id');
    }
}
