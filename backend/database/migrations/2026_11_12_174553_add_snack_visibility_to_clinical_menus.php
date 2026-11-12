<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store clinical snack visibility independently of saved snack items.
     */
    public function up(): void
    {
        foreach (['meal_plans', 'meal_plan_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('snacks_hidden')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['meal_plans', 'meal_plan_templates'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('snacks_hidden');
            });
        }
    }
};
