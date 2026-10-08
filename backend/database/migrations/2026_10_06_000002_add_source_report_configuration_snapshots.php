<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_orders', 'menu_cycles', 'meal_plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('report_configuration_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'menu_cycles', 'meal_plans'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('report_configuration_snapshot');
            });
        }
    }
};
