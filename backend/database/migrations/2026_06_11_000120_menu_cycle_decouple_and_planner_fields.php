<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu cycle Phase 2: decouple from NCP recipes + food library, add planner fields,
 * add menu-cycle templates.
 *
 *  - menu_cycles: RND-owned (fss_user_id → rnd_user_id) + population + budget/head/day.
 *  - menu_cycle_days: recipe_id → food_service_recipes, food_item_id → fs_item_id (fs_items),
 *    + per-slot servings_override.
 *  - new menu_cycle_templates / _days (save-as-template + create-from-template).
 *
 * Menu cycle data is disposable (stub page, no real data) → truncate to reshape FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('menu_cycle_days')->truncate();
        DB::table('menu_cycles')->truncate();
        Schema::enableForeignKeyConstraints();

        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->renameColumn('fss_user_id', 'rnd_user_id');
            $table->unsignedInteger('population')->default(0)->after('name');
            $table->decimal('budget_per_head_per_day', 8, 2)->nullable()->after('population');
        });

        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['food_item_id']);
            $table->renameColumn('food_item_id', 'fs_item_id');
            $table->unsignedInteger('servings_override')->nullable()->after('quantity');
        });

        // Re-point FKs after the rename (separate block so the column rename is committed first).
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->foreign('recipe_id')->references('id')->on('food_service_recipes')->nullOnDelete();
            $table->foreign('fs_item_id')->references('id')->on('fs_items')->nullOnDelete();
        });

        Schema::create('menu_cycle_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnd_user_id')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('cycle_days')->default(7);
            $table->timestamps();
        });

        Schema::create('menu_cycle_template_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('menu_cycle_templates')->cascadeOnDelete();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->enum('meal_type', ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner']);
            $table->foreignId('recipe_id')->nullable()->constrained('food_service_recipes')->nullOnDelete();
            $table->foreignId('fs_item_id')->nullable()->constrained('fs_items')->nullOnDelete();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_cycle_template_days');
        Schema::dropIfExists('menu_cycle_templates');

        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropForeign(['fs_item_id']);
            $table->dropColumn('servings_override');
            $table->renameColumn('fs_item_id', 'food_item_id');
        });
        Schema::table('menu_cycle_days', function (Blueprint $table) {
            $table->foreign('recipe_id')->references('id')->on('recipes');
            $table->foreign('food_item_id')->references('id')->on('food_items');
        });

        Schema::table('menu_cycles', function (Blueprint $table) {
            $table->dropColumn(['population', 'budget_per_head_per_day']);
            $table->renameColumn('rnd_user_id', 'fss_user_id');
        });
    }
};
