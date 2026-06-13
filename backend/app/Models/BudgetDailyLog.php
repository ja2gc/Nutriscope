<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetDailyLog extends Model
{
    use HasFactory;
    
    protected $fillable = ['budget_id', 'log_date', 'spent', 'notes'];

    protected $casts = [
        'log_date' => 'date',
        'spent'    => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

}

