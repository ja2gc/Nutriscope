<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopping lists become multi-cycle: a single date-range list spans whatever menu
 * cycles cover its dates (resolved per-date via MenuCycle::coveringDate), so the
 * single menu_cycle_id FK is removed. Coverage metadata records dates the span could
 * not be planned for (partial lists), surfaced to the user as a dismissible warning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_cycle_id');
            $table->enum('coverage_status', ['full', 'partial'])->default('full')->after('list_type');
            $table->json('uncovered_dates')->nullable()->after('coverage_status');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropColumn(['coverage_status', 'uncovered_dates']);
            $table->foreignId('menu_cycle_id')->nullable()->after('rnd_user_id')->constrained('menu_cycles')->nullOnDelete();
        });
    }
};
