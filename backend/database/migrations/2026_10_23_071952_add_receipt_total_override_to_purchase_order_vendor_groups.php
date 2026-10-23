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
        Schema::table('purchase_order_vendor_groups', function (Blueprint $table) {
            $table->decimal('receipt_total_override', 10, 2)->nullable()->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_vendor_groups', function (Blueprint $table) {
            $table->dropColumn('receipt_total_override');
        });
    }
};
