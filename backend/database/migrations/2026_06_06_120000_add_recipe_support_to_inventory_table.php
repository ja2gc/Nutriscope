<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires FK dropped before unique index
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['food_item_id']);
            $table->dropUnique(['food_item_id']);
        });

        Schema::table('inventory', function (Blueprint $table) {
            // Make food_item_id nullable
            $table->unsignedBigInteger('food_item_id')->nullable()->change();

            // Add recipe support
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->after('food_item_id');
            $table->enum('item_type', ['food_item', 'recipe'])->default('food_item')->after('recipe_id');

            // Re-add FK + unique for food_item_id (nullable; MySQL allows multiple NULLs in unique)
            $table->foreign('food_item_id')->references('id')->on('food_items');
            $table->unique('food_item_id');
            $table->unique('recipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropUnique(['recipe_id']);
            $table->dropUnique(['food_item_id']);
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['food_item_id']);
            $table->dropColumn(['recipe_id', 'item_type']);
            $table->foreignId('food_item_id')->nullable(false)->change();
            $table->foreign('food_item_id')->references('id')->on('food_items');
            $table->unique('food_item_id');
        });
    }
};
