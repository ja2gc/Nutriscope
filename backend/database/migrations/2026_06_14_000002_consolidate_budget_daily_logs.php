<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapse the split-brain budget_daily_logs: it carried two parallel sets
     * (date+planned/actual/variance AND log_date+spent). Back-fill the survivors
     * from the legacy columns, then drop the legacy set. log_date+spent+notes win.
     */
    public function up(): void
    {
        // Back-fill survivors from legacy rows (e.g. previously-seeded data).
        DB::table('budget_daily_logs')->whereNull('log_date')->whereNotNull('date')
            ->update(['log_date' => DB::raw('date')]);
        DB::table('budget_daily_logs')->whereNull('spent')->whereNotNull('actual')
            ->update(['spent' => DB::raw('actual')]);

        Schema::table('budget_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['date', 'planned', 'actual', 'variance']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_daily_logs', function (Blueprint $table) {
            $table->date('date')->nullable();
            $table->decimal('planned', 10, 2)->default(0);
            $table->decimal('actual', 10, 2)->default(0);
            $table->decimal('variance', 10, 2)->default(0);
        });
    }
};
