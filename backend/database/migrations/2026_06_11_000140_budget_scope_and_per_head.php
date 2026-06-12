<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget Phase 4: scope (monthly/quarterly/yearly/custom) + per-head rates so the
 * hospital can set a yearly budget and a budget per head per day/month/year.
 * Additive — existing budgets keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->enum('scope', ['monthly', 'quarterly', 'yearly', 'custom'])->default('custom')->after('fss_user_id');
            $table->unsignedInteger('population')->nullable()->after('cost_per_person');
            $table->decimal('budget_per_head_day', 10, 2)->nullable()->after('population');
            $table->decimal('budget_per_head_month', 10, 2)->nullable()->after('budget_per_head_day');
            $table->decimal('budget_per_head_year', 10, 2)->nullable()->after('budget_per_head_month');
            $table->string('name')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn([
                'scope', 'name', 'population',
                'budget_per_head_day', 'budget_per_head_month', 'budget_per_head_year',
            ]);
        });
    }
};
