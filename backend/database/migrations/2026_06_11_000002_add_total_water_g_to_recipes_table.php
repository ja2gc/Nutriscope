<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the computed water total on the recipe row so it can be read
     * without loading ingredients. Populated by Recipe::recalculateTotals().
     *
     * Phase 3.2 — unblocks water display in meal-plan day totals and recipe cards.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->decimal('total_water_g', 8, 2)->default(0)->after('total_fat');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('total_water_g');
        });
    }
};
