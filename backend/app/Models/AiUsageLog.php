<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'model', 'tokens_input', 'tokens_output',
        'tokens_total', 'endpoint',
    ];

    protected function casts(): array
    {
        return ['estimated_cost_usd' => 'decimal:10'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $usage): void {
            $rates = AiUsageLimit::current();
            $usage->estimated_cost_usd = (
                $usage->tokens_input * $rates->input_cost_per_1m_tokens_usd
                + $usage->tokens_output * $rates->output_cost_per_1m_tokens_usd
            ) / 1_000_000;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
