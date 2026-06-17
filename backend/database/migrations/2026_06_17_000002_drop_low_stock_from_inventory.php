<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B: remove the low-stock concept. No editable minimum threshold and no
 * numeric low-stock state — stock is now a binary in/out indicator (green/red)
 * derived from quantity_in_stock. usage_rate (only ever fed the low-stock guess)
 * goes with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn(['minimum_stock_threshold', 'usage_rate']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->decimal('usage_rate', 8, 2)->nullable();
            $table->decimal('minimum_stock_threshold', 8, 2)->nullable();
        });
    }
};
