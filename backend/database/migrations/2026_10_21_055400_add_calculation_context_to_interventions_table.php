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
            $table->string('energy_adjustment_mode')->nullable();
            $table->unsignedInteger('energy_adjustment_kcal')->nullable();
            $table->string('fluid_mode')->nullable();
            $table->json('calculation_options')->nullable();
            $table->json('calculation_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropColumn(['energy_adjustment_mode', 'energy_adjustment_kcal', 'fluid_mode', 'calculation_options', 'calculation_snapshot']);
        });
    }
};
