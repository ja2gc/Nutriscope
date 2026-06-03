<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'fss_user_id', 'allocated_amount', 'actual_amount',
        'period_start', 'period_end', 'cost_per_person',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'actual_amount'    => 'decimal:2',
        'cost_per_person'  => 'decimal:2',
        'period_start'     => 'date',
        'period_end'       => 'date',
    ];

    public function fss()
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function dailyLogs()
    {
        return $this->hasMany(BudgetDailyLog::class);
    }
}
