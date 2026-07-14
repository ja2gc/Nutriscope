<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPrepLogLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_prep_log_id', 'fs_item_id', 'qty_base', 'unit', 'unit_cost', 'line_value', 'shortfall_qty',
    ];

    protected $casts = [
        'qty_base' => 'decimal:2',
        'unit_cost' => 'decimal:6',
        'line_value' => 'decimal:2',
        'shortfall_qty' => 'decimal:2',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(MealPrepLog::class, 'meal_prep_log_id');
    }

    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }
}
