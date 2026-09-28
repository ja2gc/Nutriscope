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
        Schema::table('interventions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->unique('intervention_id', 'meal_plans_intervention_unique');
        });

        if (Schema::hasIndex('meal_plans', 'meal_plans_intervention_lookup')) {
            Schema::table('meal_plans', function (Blueprint $table) {
                $table->dropIndex('meal_plans_intervention_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->change();
        });

        if (! Schema::hasIndex('meal_plans', 'meal_plans_intervention_lookup')) {
            Schema::table('meal_plans', function (Blueprint $table) {
                $table->index('intervention_id', 'meal_plans_intervention_lookup');
            });
        }

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropUnique('meal_plans_intervention_unique');
        });
    }
};
