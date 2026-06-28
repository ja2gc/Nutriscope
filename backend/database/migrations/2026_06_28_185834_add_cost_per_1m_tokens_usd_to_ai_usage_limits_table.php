<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_usage_limits', function (Blueprint $table) {
            $table->decimal('cost_per_1m_tokens_usd', 10, 4)->default(1.92)->after('monthly_token_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_usage_limits', function (Blueprint $table) {
            $table->dropColumn('cost_per_1m_tokens_usd');
        });
    }
};
