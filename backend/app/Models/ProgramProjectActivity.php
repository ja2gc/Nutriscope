<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramProjectActivity extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'activity',
        'menu_snapshot',
        'target_date_range',
        'period_start',
        'period_end',
        'estimated_total_cost',
        'estimated_output_patients',
        'actual_total_cost',
        'actual_output_patients',
        'execution_frozen_at',
        'planning_payload',
        'execution_payload',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'estimated_total_cost' => 'decimal:2',
        'actual_total_cost' => 'decimal:2',
        'estimated_output_patients' => 'integer',
        'actual_output_patients' => 'integer',
        'execution_frozen_at' => 'datetime',
        'planning_payload' => 'array',
        'execution_payload' => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
