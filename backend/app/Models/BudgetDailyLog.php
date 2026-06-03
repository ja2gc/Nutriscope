<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetDailyLog extends Model
{
    use HasFactory;
    
    protected $fillable = ['budget_id', 'date', 'planned', 'actual', 'variance', 'log_date', 'spent', 'notes'];

    protected $casts = [
        'planned'  => 'decimal:2',
        'actual'   => 'decimal:2',
        'variance' => 'decimal:2',
        'date'     => 'date',
        'log_date' => 'date',
        'spent'    => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

}

