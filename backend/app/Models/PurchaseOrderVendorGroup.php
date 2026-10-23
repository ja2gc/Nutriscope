<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderVendorGroup extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'or_number',
        'status',
        'total_amount',
        'received_at',
        'stocked_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'receipt_total_override' => 'decimal:2',
        'received_at' => 'datetime',
        'stocked_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recalculateTotal(): void
    {
        $this->load('items');
        $this->total_amount = $this->receipt_total_override ?? round((float) $this->items->sum(
            fn ($item) => $item->actual_qty !== null && $item->actual_unit_price !== null
                ? (float) $item->actual_qty * (float) $item->actual_unit_price
                : (float) $item->total_value,
        ), 2);
        $this->save();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'vendor_group_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PurchaseOrderAttachment::class, 'vendor_group_id');
    }
}
