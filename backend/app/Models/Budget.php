<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['fiscal_year', 'allocated_amount', 'per_head_day_limit', 'created_by'];

    protected $casts = [
        'fiscal_year' => 'integer',
        'allocated_amount' => 'decimal:2',
        'per_head_day_limit' => 'decimal:2',
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(BudgetLedger::class, 'fiscal_year', 'fiscal_year');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWithLedgerTotals(Builder $query): Builder
    {
        return $query
            ->withSum(['ledgerEntries as manual_additions_sum' => fn (Builder $ledger) => $ledger->where('type', 'manual_addition')], 'amount')
            ->withSum(['ledgerEntries as manual_deductions_sum' => fn (Builder $ledger) => $ledger->where('type', 'manual_deduction')], 'amount')
            ->withSum(['ledgerEntries as po_deductions_sum' => fn (Builder $ledger) => $ledger->where('type', 'po_deduction')], 'amount');
    }

    public function remainingBalance(): float
    {
        $entries = $this->ledgerEntries()->get();
        $additions = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $deductions = (float) $entries->whereIn('type', ['po_deduction', 'manual_deduction'])->sum('amount');

        return round((float) $this->allocated_amount + $additions - $deductions, 2);
    }

    public static function forYear(int $year): ?self
    {
        return static::where('fiscal_year', $year)->first();
    }
}
