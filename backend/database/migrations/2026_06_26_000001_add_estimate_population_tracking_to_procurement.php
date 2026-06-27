<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->unsignedInteger('estimate_population')->nullable()->after('total_served_population');
            $table->timestamp('estimate_population_updated_at')->nullable()->after('estimate_population');
        });

        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->timestamp('estimate_population_updated_at')->nullable()->after('estimate_population');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_lists', function (Blueprint $table) {
            $table->dropColumn(['estimate_population', 'estimate_population_updated_at']);
        });

        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropColumn('estimate_population_updated_at');
        });
    }
};
