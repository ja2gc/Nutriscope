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
        Schema::create('menu_cycle_days', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('menu_cycle_id')->constrained()->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->enum('meal_type', ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']);
            $table->foreignId('recipe_id')->nullable()->references('id')->on('recipes');
            $table->foreignId('food_item_id')->nullable()->references('id')->on('food_items');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->timestamps();
            $table->unique(['menu_cycle_id', 'day_of_week', 'meal_type'], 'menu_cycle_day_meal_unique');
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_cycle_days');
    }
};
