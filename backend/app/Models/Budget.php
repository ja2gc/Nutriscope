<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    protected $fillable = [
        'rnd_user_id', 'scope', 'name', 'allocated_amount', 'actual_amount',
        'period_start', 'period_end', 'cost_per_person', 'population',
        'budget_per_head_day', 'budget_per_head_month', 'budget_per_head_year',
    ];

    protected $casts = [
        'allocated_amount'      => 'decimal:2',
        'actual_amount'         => 'decimal:2',
        'cost_per_person'       => 'decimal:2',
        'population'            => 'integer',
        'budget_per_head_day'   => 'decimal:2',
        'budget_per_head_month' => 'decimal:2',
        'budget_per_head_year'  => 'decimal:2',
        'period_start'          => 'date',
        'period_end'            => 'date',
    ];

    public function rnd()
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    /**
     * The budget whose period covers a given date — the owner of the per-head/day cap
     * for that date. Prefers the most specific (shortest) overlapping period, so a
     * custom/weekly budget wins over a broad monthly/yearly one for the same day.
     */
    public static function coveringDate(\Carbon\Carbon|string $date): ?self
    {
        $d = $date instanceof \Carbon\Carbon ? $date->toDateString() : (string) $date;

        return static::query()
            ->whereDate('period_start', '<=', $d)
            ->whereDate('period_end', '>=', $d)
            ->orderByRaw('DATEDIFF(period_end, period_start) ASC')
            ->first();
    }

    public function dailyLogs()
    {
        return $this->hasMany(BudgetDailyLog::class);
    }
}
