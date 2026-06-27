<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLedger extends Model
{
    protected $table = 'budget_ledger';

    protected $fillable = [
        'fiscal_year', 'type', 'amount', 'reason', 'reference',
        'purchase_order_id', 'procurement_span', 'created_by',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'amount'      => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Additions positive, deductions negative. */
    public function signedAmount(): float
    {
        return $this->type === 'manual_addition'
            ? (float) $this->amount
            : -(float) $this->amount;
    }
}
