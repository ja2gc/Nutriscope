<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rates = DB::table('ai_usage_limits')->first([
            'input_cost_per_1m_tokens_usd',
            'output_cost_per_1m_tokens_usd',
        ]);

        DB::update(
            'UPDATE ai_usage_logs SET estimated_cost_usd = (tokens_input * ? + tokens_output * ?) / 1000000 WHERE estimated_cost_usd IS NULL',
            [
                $rates?->input_cost_per_1m_tokens_usd ?? 1,
                $rates?->output_cost_per_1m_tokens_usd ?? 5,
            ],
        );

        Cache::forget('admin_dashboard');
    }

    public function down(): void
    {
        // Historical estimates cannot be reconstructed after rates change.
    }
};
