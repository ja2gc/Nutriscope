<?php

use App\Models\Recipe;
use App\Support\UnitConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $updates = [];
            $recipes = [];
            foreach (['meal_plan_items', 'meal_plan_template_items'] as $table) {
                foreach (DB::table($table)->whereNotNull('recipe_id')->orderBy('id')->lockForUpdate()->get() as $row) {
                    $snapshot = json_decode($row->nutrient_snapshot ?? '{}', true, flags: JSON_THROW_ON_ERROR);
                    if (! empty($snapshot['recipe_original_ingredients']) && ! empty($snapshot['recipe_ingredients'])
                        && (float) ($snapshot['recipe_yield'] ?? 0) > 0
                        && ($snapshot['recipe_snapshot_source'] ?? '') !== 'current_recipe_fallback') {
                        continue;
                    }
                    $recipe = $recipes[$row->recipe_id] ??= Recipe::with('ingredients.foodItem')->find($row->recipe_id);
                    if (! $recipe || ! $recipe->hasValidPortionData()) {
                        throw new RuntimeException('Snapshot repair requires valid measured catalog recipes.');
                    }
                    $quantity = (float) $row->quantity;
                    $unit = UnitConverter::canonical($row->unit);
                    if ($unit !== 'serving') {
                        $reference = (float) ($snapshot['serving_size'] ?? 0);
                        $referenceUnit = UnitConverter::canonical((string) ($snapshot['serving_unit'] ?? ''));
                        $prepared = (float) $recipe->prepared_portion_amount;
                        $preparedUnit = UnitConverter::canonical((string) $recipe->prepared_portion_unit);
                        if ($table !== 'meal_plan_items' || $reference <= 0 || $prepared <= 0
                            || ! UnitConverter::isCompatible($referenceUnit, $preparedUnit)
                            || ! UnitConverter::isCompatible($unit, $preparedUnit)
                            || abs(UnitConverter::convert($reference, $referenceUnit, $preparedUnit) - $prepared) > 0.000001) {
                            throw new RuntimeException('Snapshot repair stopped: the saved reference does not match the measured recipe portion.');
                        }
                        $quantity = round(UnitConverter::convert($quantity, $unit, $preparedUnit) / $prepared, 2);
                    }
                    if (! is_finite($quantity) || $quantity < 0.01 || $quantity > 999999.99) {
                        throw new RuntimeException('Snapshot repair requires a positive representable serving quantity.');
                    }
                    $overrides = [];
                    $savedIngredients = $snapshot['recipe_ingredients'] ?? [];
                    if ($savedIngredients !== []) {
                        if (count($savedIngredients) !== $recipe->ingredients->count()) {
                            throw new RuntimeException('Snapshot repair stopped: saved ingredient rows do not match the recipe.');
                        }
                        $seen = [];
                        foreach ($savedIngredients as $saved) {
                            $matches = $recipe->ingredients->filter(fn ($ingredient): bool => isset($saved['id'])
                                ? $ingredient->id === (int) $saved['id']
                                : $ingredient->foodItem->name === ($saved['name'] ?? null));
                            $savedUnit = UnitConverter::canonical((string) ($saved['unit'] ?? ''));
                            $ingredient = $matches->count() === 1 ? $matches->first() : null;
                            if (! $ingredient || isset($seen[$ingredient->id])
                                || ! UnitConverter::isCompatible($savedUnit, UnitConverter::canonical($ingredient->unit))
                                || ! is_finite((float) ($saved['quantity'] ?? 0)) || (float) ($saved['quantity'] ?? 0) <= 0) {
                                throw new RuntimeException('Snapshot repair stopped: a saved ingredient edit cannot be verified.');
                            }
                            $seen[$ingredient->id] = true;
                            $overrides[] = ['id' => $ingredient->id, 'quantity' => UnitConverter::convert((float) $saved['quantity'], $savedUnit, UnitConverter::canonical($ingredient->unit))];
                        }
                    }
                    $repaired = $recipe->planSnapshot($overrides);
                    $repaired['name'] = $snapshot['name'] ?? $recipe->name;
                    $repaired['recipe_snapshot_source'] = 'verified_catalog_repair';
                    $repaired['recipe_snapshot_repair'] = [
                        'previous_quantity' => (float) $row->quantity, 'previous_unit' => $row->unit,
                        'previous_serving_size' => $snapshot['serving_size'] ?? null, 'previous_serving_unit' => $snapshot['serving_unit'] ?? null,
                    ];
                    $values = ['nutrient_snapshot' => json_encode($repaired, JSON_THROW_ON_ERROR)];
                    if ($table === 'meal_plan_items') {
                        $values += ['quantity' => $quantity, 'unit' => 'serving'];
                    }
                    $updates[] = [$table, $row->id, $values];
                }
            }
            foreach ($updates as [$table, $id, $values]) {
                if (DB::table($table)->where('id', $id)->update($values) !== 1) {
                    throw new RuntimeException('Snapshot repair stopped: a locked target was not updated.');
                }
            }
        });
    }

    /**
     * Verified data repairs are forward-only.
     */
    public function down(): void {}
};
