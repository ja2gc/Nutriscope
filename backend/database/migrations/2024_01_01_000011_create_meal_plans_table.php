<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained();
            $table->date('week_start_date');
            $table->enum('generation_type', ['manual', 'auto'])->default('auto');
            $table->enum('status', ['draft', 'active'])->default('draft');
            $table->timestamps();
        });

        Schema::create('meal_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->enum('meal_type', ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']);
            $table->boolean('flagged')->default(false); // >10% off target
            $table->unique(['meal_plan_id', 'day_of_week', 'meal_type']);
        });

        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_item_id')->nullable()->constrained();
            $table->foreignId('recipe_id')->nullable()->constrained();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->json('nutrient_snapshot')->nullable(); // frozen nutrient values at time of planning
            $table->boolean('ai_suggested')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_items');
        Schema::dropIfExists('meal_plan_days');
        Schema::dropIfExists('meal_plans');
    }
};
