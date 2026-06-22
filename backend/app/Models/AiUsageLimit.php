<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row table holding the global AI token-usage caps.
 * null = unlimited. Mirrors the ReportBranding singleton pattern.
 */
class AiUsageLimit extends Model
{
    protected $table = 'ai_usage_limits';

    protected $fillable = [
        'daily_token_limit',
        'monthly_token_limit',
    ];

    protected $casts = [
        'daily_token_limit'   => 'integer',
        'monthly_token_limit' => 'integer',
    ];

    /** The one-and-only limits row, created on demand if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
