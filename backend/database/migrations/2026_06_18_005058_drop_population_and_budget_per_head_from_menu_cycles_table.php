<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->dropColumn(['population', 'budget_per_head_per_day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->unsignedInteger('population')->default(0)->after('name');
            $table->decimal('budget_per_head_per_day', 8, 2)->nullable()->after('population');
        });
    }
};
