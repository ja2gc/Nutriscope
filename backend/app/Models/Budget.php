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

    public function dailyLogs()
    {
        return $this->hasMany(BudgetDailyLog::class);
    }
}
