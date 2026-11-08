<?php

namespace Database\Seeders;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Support\UnitConverter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FoodServiceRecipeSeeder extends Seeder
{
    use ResolvesReferenceOwner;

    public function run(): void
    {
        $data = require database_path('seeders/data/food-service.php');
        $items = FsItem::query()->get()->keyBy('name');
        $owner = $this->referenceOwner();
        foreach ($data['recipes'] as $name => $definition) {
            foreach ($definition['ingredients'] as $itemName => $quantity) {
                if (! $items->has($itemName) || ! is_numeric($quantity) || $quantity <= 0) {
                    throw new RuntimeException("Recipe '{$name}' requires a valid measured ingredient '{$itemName}'.");
                }
                $unit = $this->recipeUnit((string) $items[$itemName]->base_unit);
                $expectedUnit = $data['catalog_units'][$itemName];
                if (! UnitConverter::isCompatible($unit, $expectedUnit)) {
                    throw new RuntimeException("Recipe '{$name}' cannot use the current catalog unit for '{$itemName}'. Review that item before seeding.");
                }
            }
        }

        DB::transaction(function () use ($data, $items, $owner): void {
            foreach ($data['recipes'] as $name => $definition) {
                $recipe = FoodServiceRecipe::query()->firstOrCreate(['name' => $name], [
                    'rnd_user_id' => $owner->id,
                    'category' => 'meal',
                    'servings' => 50,
                    'portion_label' => $definition['portion'] ?? '1 serving',
                    'prep_notes' => 'Estimated 50-serving kitchen baseline using raw ingredient amounts. Adjust after a measured kitchen batch. Salt and pepper are adjusted to taste. '.($definition['notes'] ?? ''),
                ]);
                if (! $recipe->wasRecentlyCreated) {
                    continue;
                }
                foreach ($definition['ingredients'] as $itemName => $quantity) {
                    $item = $items[$itemName];
                    $recipe->ingredients()->create([
                        'fs_item_id' => $item->id,
                        'quantity' => $quantity,
                        'unit' => $this->recipeUnit((string) $data['catalog_units'][$itemName]),
                        'adjust_to_taste' => in_array($itemName, ['Salt', 'Ground black pepper'], true),
                    ]);
                }
                $recipe->recalculateCost();
            }
        });
    }

    private function recipeUnit(string $unit): string
    {
        return match ($unit) {
            'kg' => 'g',
            'L' => 'mL',
            default => $unit,
        };
    }
}
