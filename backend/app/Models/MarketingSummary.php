<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_statement_id', 'date_purchased', 'inclusive_start',
        'inclusive_end', 'total_amount', 'certified_by', 'certified_by_title',
    ];

    protected $casts = [
        'date_purchased' => 'date',
        'inclusive_start' => 'date',
        'inclusive_end' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function marketingStatement()
    {
        return $this->belongsTo(MarketingStatement::class);
    }
}
