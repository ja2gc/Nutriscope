<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['fiscal_year', 'allocated_amount', 'per_head_day_limit'];

    protected $casts = [
        'fiscal_year'        => 'integer',
        'allocated_amount'   => 'decimal:2',
        'per_head_day_limit' => 'decimal:2',
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(BudgetLedger::class, 'fiscal_year', 'fiscal_year');
    }

    public function remainingBalance(): float
    {
        $entries   = $this->ledgerEntries()->get();
        $additions = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $deductions = (float) $entries->whereIn('type', ['po_deduction', 'manual_deduction'])->sum('amount');

        return round((float) $this->allocated_amount + $additions - $deductions, 2);
    }

    public static function forYear(int $year): ?self
    {
        return static::where('fiscal_year', $year)->first();
    }
}
