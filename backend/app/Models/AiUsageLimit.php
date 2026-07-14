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
        'cost_per_1m_tokens_usd',
    ];

    protected $casts = [
        'daily_token_limit' => 'integer',
        'monthly_token_limit' => 'integer',
        'cost_per_1m_tokens_usd' => 'float',
    ];

    protected $attributes = [
        'cost_per_1m_tokens_usd' => 1.92,
    ];

    /** The one-and-only limits row, created on demand if missing. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
