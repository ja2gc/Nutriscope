<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row for the ONLY edit allowed during open execution: correcting a vendor
 * line's unit cost / purchase price. Each correction records the before/after
 * values, who made it, and when.
 */
class PurchaseOrderItemCorrection extends Model
{
    protected $fillable = [
        'purchase_order_item_id',
        'old_unit_price', 'new_unit_price',
        'old_purchase_price', 'new_purchase_price',
        'corrected_by', 'corrected_at', 'reason',
    ];

    protected $casts = [
        'old_unit_price' => 'decimal:2',
        'new_unit_price' => 'decimal:2',
        'old_purchase_price' => 'decimal:2',
        'new_purchase_price' => 'decimal:2',
        'corrected_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
