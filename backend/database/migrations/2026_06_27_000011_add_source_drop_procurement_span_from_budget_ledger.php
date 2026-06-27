<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_ledger', function (Blueprint $table) {
            $table->enum('source', ['system', 'manual'])->default('manual')->after('type');
        });

        // Backfill po_deduction as system, others as manual
        DB::table('budget_ledger')
            ->where('type', 'po_deduction')
            ->update(['source' => 'system']);

        if (Schema::hasColumn('budget_ledger', 'procurement_span')) {
            Schema::table('budget_ledger', function (Blueprint $table) {
                $table->dropColumn('procurement_span');
            });
        }
    }

    public function down(): void
    {
        Schema::table('budget_ledger', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('budget_ledger', function (Blueprint $table) {
            $table->string('procurement_span')->nullable();
        });
    }
};
