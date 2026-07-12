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
        Schema::table('budget_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('po_deduction_guard')->nullable()->after('purchase_order_id');
            $table->unique('po_deduction_guard', 'budget_ledger_po_deduction_guard_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_ledger', function (Blueprint $table) {
            $table->dropUnique('budget_ledger_po_deduction_guard_unique');
            $table->dropColumn('po_deduction_guard');
        });
    }
};
