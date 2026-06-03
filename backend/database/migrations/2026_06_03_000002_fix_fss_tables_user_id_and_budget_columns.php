<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix FSS-owned tables:
 * - rename rnd_user_id → fss_user_id in shopping_lists, purchase_orders, menu_cycles, budgets
 * - rename budgets.planned_amount → allocated_amount
 * - add menu_cycles: name, cycle_days, is_active
 * - add purchase_orders: order_date
 * - add shopping_lists: period_start, period_end
 */
return new class extends Migration
{
    public function up(): void
    {
        // shopping_lists: rename + add period columns
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
            $table->date('period_start')->nullable()->after('list_date');
            $table->date('period_end')->nullable()->after('period_start');
        });

        // purchase_orders: rename + add order_date
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
            $table->date('order_date')->nullable()->after('po_number');
        });

        // menu_cycles: rename + add name, cycle_days, is_active
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
            $table->string('name')->nullable()->after('fss_user_id');
            $table->unsignedTinyInteger('cycle_days')->default(7)->after('name');
            $table->boolean('is_active')->default(false)->after('cycle_days');
        });

        // budgets: rename user + rename amount column
        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
            $table->renameColumn('planned_amount', 'allocated_amount');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
            $table->dropColumn(['period_start', 'period_end']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
            $table->dropColumn('order_date');
        });
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
            $table->dropColumn(['name', 'cycle_days', 'is_active']);
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
            $table->renameColumn('allocated_amount', 'planned_amount');
        });
    }
};
