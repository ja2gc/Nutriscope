<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single manual headcount for the whole procurement span — total patients served
 * across the assigned shopping-list date range, entered from the census list by
 * RND or FSS. Denominator for the calculated actual budget-per-head (total
 * procurement cost ÷ total served). NOT summed from meal-prep logs (that would
 * triple-count breakfast/lunch/dinner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->unsignedInteger('total_served_population')->nullable()->after('days_span');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropColumn('total_served_population');
        });
    }
};
