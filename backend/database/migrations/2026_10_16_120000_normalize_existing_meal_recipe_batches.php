<?php

use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $recipes = Recipe::where('category', 'meal')->where('servings', '!=', 5)->orderBy('id')->lockForUpdate()->get();
            foreach (['meal_plan_items', 'meal_plan_template_items'] as $table) {
                foreach (DB::table($table)->whereIn('recipe_id', $recipes->modelKeys())->lockForUpdate()->get(['nutrient_snapshot']) as $row) {
                    $snapshot = json_decode($row->nutrient_snapshot ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                    if (empty($snapshot['recipe_original_ingredients']) || empty($snapshot['recipe_ingredients'])
                        || (float) ($snapshot['recipe_yield'] ?? 0) <= 0
                        || ($snapshot['recipe_snapshot_source'] ?? '') === 'current_recipe_fallback') {
                        throw new RuntimeException('Repair saved recipe snapshots before resizing Meal batches.');
                    }
                }
            }
            foreach ($recipes as $recipe) {
                $recipe->setRelation('ingredients', $recipe->ingredients()->with('foodItem')->lockForUpdate()->get());
                if (! $recipe->hasValidPortionData()) {
                    throw new RuntimeException('Meal batch normalization requires valid measured recipes.');
                }
                $factor = 5 / (float) $recipe->servings;
                foreach ($recipe->ingredients as $ingredient) {
                    $quantity = round((float) $ingredient->quantity * $factor, 6);
                    if (! is_finite($quantity) || $quantity <= 0 || $quantity >= 10000000000) {
                        throw new RuntimeException('Scaled Meal ingredients must fit the stored quantity precision.');
                    }
                    DB::table('recipe_ingredients')->where('id', $ingredient->id)->update(['quantity' => $quantity]);
                }
                Recipe::withoutTimestamps(function () use ($recipe): void {
                    $recipe->update(['servings' => 5]);
                    $recipe->recalculateTotals();
                });
            }
        });
    }

    /**
     * Catalog batch normalization is forward-only; saved snapshots retain their original batch.
     */
    public function down(): void {}
};
