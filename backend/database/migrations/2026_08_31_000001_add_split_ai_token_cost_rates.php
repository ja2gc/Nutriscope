<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_limits', function (Blueprint $table): void {
            $table->decimal('input_cost_per_1m_tokens_usd', 10, 4)->default(1.0000);
            $table->decimal('output_cost_per_1m_tokens_usd', 10, 4)->default(5.0000);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_limits', function (Blueprint $table): void {
            $table->dropColumn([
                'input_cost_per_1m_tokens_usd',
                'output_cost_per_1m_tokens_usd',
            ]);
        });
    }
};
