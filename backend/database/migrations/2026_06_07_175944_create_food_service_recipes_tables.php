<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_service_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_user_id')->constrained('users');
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->text('prep_notes')->nullable();
            $table->integer('servings')->default(1);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('total_calories', 8, 2)->default(0);
            $table->decimal('total_protein', 8, 2)->default(0);
            $table->decimal('total_carbs', 8, 2)->default(0);
            $table->decimal('total_fat', 8, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('food_service_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_service_recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventory');
            $table->decimal('quantity', 8, 2);
            $table->string('unit')->default('g');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_service_recipe_ingredients');
        Schema::dropIfExists('food_service_recipes');
    }
};
