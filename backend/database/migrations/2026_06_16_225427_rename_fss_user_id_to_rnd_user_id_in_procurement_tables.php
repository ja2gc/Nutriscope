<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shopping lists and purchase orders are RND planning artifacts, not FSS operations.
     * Rename fss_user_id → rnd_user_id to match budgets and menu_cycles ownership model.
     */
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
        });
    }
};
