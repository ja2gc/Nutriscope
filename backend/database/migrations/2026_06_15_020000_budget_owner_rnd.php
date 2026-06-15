<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budgets are planning artifacts owned by the RND, not the FSS.
 * Rename the owner column fss_user_id -> rnd_user_id to match menu_cycles /
 * food_service_recipes and the RND-only write guard on /api/fss/budgets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('rnd_user_id', 'fss_user_id');
        });
    }
};
