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

    /**
     * The cycle that owns a given calendar date — the single source of truth for
     * date→cycle resolution. A cycle covers [week_start_date, week_start_date +
     * (cycle_days-1)] (default 7-day span). On overlap an active cycle wins, else the
     * latest-starting one. Returns null when no cycle covers the date (an uncovered day).
     *
     * Candidate filtering is done in SQL (week_start_date <= date) and the window-end
     * check in PHP so the date math stays portable across sqlite (tests) and the prod DB.
     */
    public static function coveringDate(\Carbon\Carbon|string $date): ?self
    {
        $day = ($date instanceof \Carbon\Carbon ? $date->copy() : \Carbon\Carbon::parse($date))->startOfDay();

        return self::query()
            ->whereNotNull('week_start_date')
            ->whereDate('week_start_date', '<=', $day->toDateString())
            ->orderByDesc('is_active')
            ->orderByDesc('week_start_date')
            ->get()
            ->first(function (self $cycle) use ($day) {
                $span = (int) ($cycle->cycle_days ?: 7);
                $end  = $cycle->week_start_date->copy()->startOfDay()->addDays($span - 1);

                return $day->gte($cycle->week_start_date->copy()->startOfDay()) && $day->lte($end);
            });
    }
}
