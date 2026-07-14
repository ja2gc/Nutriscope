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
        Schema::create('meal_plan_template_days', function (Blueprint $table) {

            $table->id();
            $table->foreignId('template_id')->constrained('meal_plan_templates')->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->enum('meal_type', ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']);
            $table->foreignId('food_item_id')->nullable()->references('id')->on('food_items');
            $table->foreignId('recipe_id')->nullable()->references('id')->on('recipes');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('unit')->default('serving');
            $table->timestamps();
            $table->unique(['template_id', 'day_of_week', 'meal_type'], 'template_day_meal_unique');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plan_template_days');
    }
};
