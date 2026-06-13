<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPrepLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_cycle_id', 'service_date', 'status',
        'completed_by', 'completed_at', 'total_value', 'has_shortfall',
    ];

    protected $casts = [
        'service_date'  => 'date',
        'completed_at'  => 'datetime',
        'total_value'   => 'decimal:2',
        'has_shortfall' => 'boolean',
    ];

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MealPrepLogLine::class);
    }
}
