<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decouple food-service inventory + recipes from the `food_items` library.
 *
 *  - inventory now references `fs_items` (ingredient/supply) or a food_service_recipe,
 *    NOT food_items.
 *  - food_service_recipe_ingredients reference `fs_items`.
 *  - drop the now-meaningless nutrition columns on food_service_recipes (FS is cost-only).
 *
 * FS inventory + FS recipe data is disposable (approved fresh rebuild), so we truncate
 * to reshape the FKs cleanly. NCP tables, `recipes`, and `food_items` are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('food_service_recipe_ingredients')->truncate();
        DB::table('food_service_recipes')->truncate();
        DB::table('inventory')->truncate();
        Schema::enableForeignKeyConstraints();

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['food_item_id']);
            $table->dropUnique('inventory_food_item_id_unique');
            $table->dropColumn('food_item_id');

            $table->foreignId('fs_item_id')->nullable()->after('id')->constrained('fs_items')->cascadeOnDelete();
            $table->foreignId('recipe_id')->nullable()->after('fs_item_id')->constrained('food_service_recipes')->cascadeOnDelete();
            $table->unique('fs_item_id');
            $table->unique('recipe_id');
        });

        // enum change isn't expressible via Blueprint without doctrine/dbal
        DB::statement("ALTER TABLE inventory MODIFY COLUMN item_type ENUM('ingredient','supply','recipe') NOT NULL DEFAULT 'ingredient'");

        Schema::table('food_service_recipe_ingredients', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
            $table->dropColumn('inventory_id');
            $table->foreignId('fs_item_id')->after('food_service_recipe_id')->constrained('fs_items');
        });

        Schema::table('food_service_recipes', function (Blueprint $table) {
            $table->dropColumn(['total_calories', 'total_protein', 'total_carbs', 'total_fat']);
        });
    }

    public function down(): void
    {
        Schema::table('food_service_recipes', function (Blueprint $table) {
            $table->decimal('total_calories', 8, 2)->default(0);
            $table->decimal('total_protein', 8, 2)->default(0);
            $table->decimal('total_carbs', 8, 2)->default(0);
            $table->decimal('total_fat', 8, 2)->default(0);
        });

        Schema::table('food_service_recipe_ingredients', function (Blueprint $table) {
            $table->dropForeign(['fs_item_id']);
            $table->dropColumn('fs_item_id');
            $table->foreignId('inventory_id')->after('food_service_recipe_id')->constrained('inventory');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['fs_item_id']);
            $table->dropForeign(['recipe_id']);
            $table->dropUnique(['fs_item_id']);
            $table->dropUnique(['recipe_id']);
            $table->dropColumn(['fs_item_id', 'recipe_id']);

            $table->foreignId('food_item_id')->nullable()->after('id')->constrained('food_items');
            $table->unique('food_item_id');
        });

        DB::statement("ALTER TABLE inventory MODIFY COLUMN item_type ENUM('food_item','recipe') NOT NULL DEFAULT 'food_item'");
    }
};
