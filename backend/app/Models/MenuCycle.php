<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCycle extends Model
{
    use HasFactory;
    use \App\Models\Concerns\AuditsChanges;

    protected $fillable = [
        'rnd_user_id', 'name', 'population', 'budget_per_head_per_day',
        'cycle_days', 'is_active', 'week_start_date', 'status', 'activation_date',
    ];

    protected $casts = [
        'population'              => 'integer',
        'budget_per_head_per_day' => 'decimal:2',
        'week_start_date'         => 'date',
        'activation_date'         => 'date',
        'is_active'               => 'boolean',
    ];

    public function rnd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(MenuCycleDay::class);
    }
}
