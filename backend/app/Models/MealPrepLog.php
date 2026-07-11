<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPrepLog extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;
    use \App\Models\Concerns\HasPublicId;

    protected $fillable = [
        'menu_cycle_id', 'service_date', 'population', 'served_population',
        'population_variance', 'status', 'completed_by', 'completed_at',
        'total_value', 'has_shortfall', 'served_locked_at', 'served_locked_by',
    ];

    protected $casts = [
        'service_date'        => 'date',
        'population'          => 'integer',
        'served_population'   => 'integer',
        'population_variance' => 'integer',
        'completed_at'        => 'datetime',
        'total_value'         => 'decimal:2',
        'has_shortfall'       => 'boolean',
        'served_locked_at'    => 'datetime',
    ];

    protected function auditAttributes(): array
    {
        return [
            'menu_cycle_id', 'service_date', 'population', 'served_population',
            'population_variance', 'status', 'completed_by', 'completed_at', 'total_value',
            'has_shortfall', 'served_locked_at', 'served_locked_by',
        ];
    }

    public function servedLocked(): bool
    {
        return $this->served_locked_at !== null;
    }

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
