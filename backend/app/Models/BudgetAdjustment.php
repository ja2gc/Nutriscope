<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id', 'type', 'amount', 'reason_category', 'reason', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed value: additions add to the allocation, deductions subtract. */
    public function signedAmount(): float
    {
        return $this->type === 'deduction' ? -(float) $this->amount : (float) $this->amount;
    }
}
