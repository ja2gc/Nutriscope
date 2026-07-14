<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add meal_types JSON column to recipes.
     *
     * meal_types values: breakfast | am_snack | lunch | pm_snack | dinner | any
     * A recipe eligible for a slot if its meal_types contains the slot name or "any".
     * Snack slots (am_snack/pm_snack) additionally accept "snack" as an alias.
     *
     * Backfill: best-effort map from existing `category` free-text. Anything
     * unrecognised defaults to ["any"] — intentionally permissive so old recipes
     * are still schedulable. `category` column is KEPT for display.
     */
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->json('meal_types')->nullable()->after('category');
        });

        // Best-effort backfill from category free-text
        $categoryMap = [
            'breakfast' => ['breakfast'],
            'lunch' => ['lunch'],
            'dinner' => ['dinner'],
            'snack' => ['am_snack', 'pm_snack'],
            'am_snack' => ['am_snack'],
            'pm_snack' => ['pm_snack'],
            'main' => ['lunch', 'dinner'],
            'main course' => ['lunch', 'dinner'],
            'side' => ['lunch', 'dinner'],
            'soup' => ['lunch', 'dinner'],
            'salad' => ['lunch', 'dinner'],
            'dessert' => ['am_snack', 'pm_snack'],
            'beverage' => ['any'],
            'drink' => ['any'],
        ];

        $recipes = DB::table('recipes')->select('id', 'category')->get();
        foreach ($recipes as $recipe) {
            $cat = strtolower(trim((string) $recipe->category));
            $types = null;
            foreach ($categoryMap as $keyword => $mapped) {
                if (str_contains($cat, $keyword)) {
                    $types = $mapped;
                    break;
                }
            }
            // Default to ["any"] if no match
            DB::table('recipes')->where('id', $recipe->id)->update([
                'meal_types' => json_encode($types ?? ['any']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('meal_types');
        });
    }
};
