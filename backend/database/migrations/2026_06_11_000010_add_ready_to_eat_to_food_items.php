<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-item override for meal-plan auto-generation eligibility.
     *
     * NULL  → fall back to category allowlist (fruit / vegetable are ready-to-eat).
     * true  → force-include this item as a standalone ready-to-eat snack.
     * false → force-exclude (e.g. a raw 'vegetable' that must be cooked).
     */
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->boolean('ready_to_eat')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropColumn('ready_to_eat');
        });
    }
};
