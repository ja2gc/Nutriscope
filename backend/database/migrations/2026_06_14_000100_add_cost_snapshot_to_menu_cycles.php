<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 6 #1 — freeze menu cost on activation. A menu cycle's costed figures are
 * snapshotted when it is activated (its plan is committed) so PPA / Menu Calendar
 * reports for a past cycle keep their original cost instead of re-pricing at today's
 * catalog price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->json('cost_snapshot')->nullable()->after('activation_date');
            $table->timestamp('cost_snapshot_at')->nullable()->after('cost_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->dropColumn(['cost_snapshot', 'cost_snapshot_at']);
        });
    }
};
