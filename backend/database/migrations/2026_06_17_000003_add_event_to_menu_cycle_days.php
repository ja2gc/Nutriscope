<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event/exception days. An event day (fiesta, Christmas meal) is funded apart from
 * the daily per-head subsistence cap, so it carries its own lump allocation and is
 * excluded from standard-cap variance.
 *
 * Columns only this pass — the BudgetActualService wiring (use event_allocation as
 * that day's cap, flag it separately in the series) is deferred until the budget
 * consumption scoping (F3) is resolved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->boolean('is_event')->default(false)->after('estimate_population');
            $table->decimal('event_allocation', 10, 2)->nullable()->after('is_event');
        });
    }

    public function down(): void
    {
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropColumn(['is_event', 'event_allocation']);
        });
    }
};
