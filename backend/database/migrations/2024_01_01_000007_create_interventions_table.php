<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ncp_record_id')->constrained()->cascadeOnDelete();
            $table->string('goal_type')->nullable();
            $table->string('disease_stage')->nullable(); // stub — logic TBD
            $table->json('displayed_nutrients')->nullable();
            $table->decimal('energy_kcal', 8, 2)->nullable();
            $table->decimal('protein_g', 8, 2)->nullable();
            $table->decimal('carbs_g', 8, 2)->nullable();
            $table->decimal('fat_g', 8, 2)->nullable();
            $table->decimal('fluid_ml', 8, 2)->nullable();
            $table->json('micronutrient_limits')->nullable();
            $table->text('education_notes')->nullable();
            $table->text('counseling_goals')->nullable();
            $table->text('barriers')->nullable();
            $table->text('strategies')->nullable();

            $table->string('session_type')->nullable();
            $table->date('next_followup_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
