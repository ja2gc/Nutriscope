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
        Schema::table('ncp_records', function (Blueprint $table): void {
            $table->string('nutrition_support', 30)->nullable();
        });
        Schema::table('menu_cycle_days', fn (Blueprint $table) => $table->json('recipe_original')->nullable());
        foreach (['interventions', 'meal_plan_templates'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('nutrition_support', 30)->nullable();
                $table->json('formula_items')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ncp_records', fn (Blueprint $table) => $table->dropColumn('nutrition_support'));
        Schema::table('menu_cycle_days', fn (Blueprint $table) => $table->dropColumn('recipe_original'));
        foreach (['interventions', 'meal_plan_templates'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['nutrition_support', 'formula_items']));
        }
    }
};
