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
        'rnd_user_id', 'name',
        'cycle_days', 'is_active', 'week_start_date', 'status', 'activation_date',
        'cost_snapshot', 'cost_snapshot_at',
    ];

    protected $casts = [
        'week_start_date'         => 'date',
        'activation_date'         => 'date',
        'is_active'               => 'boolean',
        'cost_snapshot'           => 'array',
        'cost_snapshot_at'        => 'datetime',
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
