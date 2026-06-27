<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('procurement_track', ['food', 'supplies'])->default('food')->after('lifecycle_status');
            $table->index(['procurement_track', 'lifecycle_status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['procurement_track', 'lifecycle_status']);
            $table->dropColumn('procurement_track');
        });
    }
};
