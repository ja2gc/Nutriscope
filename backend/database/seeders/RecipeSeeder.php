<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Recipe;
use App\Support\UnitConverter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecipeSeeder extends Seeder
{
    use ResolvesReferenceOwner;

    public function run(): void
    {
        $owner = $this->referenceOwner();
        $catalog = json_decode(file_get_contents(database_path('seeders/data/clinical-recipes.json')), true, flags: JSON_THROW_ON_ERROR);
        $foodCatalog = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);
        $manualNames = array_column(array_filter($foodCatalog['foods'], fn (array $food): bool => ! isset($food['usda_fdc_id'])), 'name');
        $foods = FoodItem::whereIn('usda_fdc_id', array_column($foodCatalog['foods'], 'usda_fdc_id'))
            ->orWhere(fn ($query) => $query->whereNull('usda_fdc_id')->whereIn('name', $manualNames))
            ->get()->keyBy('name');

        // Validate the entire input before changing library records.
        foreach ($catalog['recipes'] as $key => $definition) {
            if ($definition['servings'] < 1 || empty($definition['source']) || empty($definition['ingredients'])) {
                throw new RuntimeException("Recipe '{$key}' requires a source, yield and measured ingredients.");
            }
            foreach ($definition['ingredients'] as [$name, $quantity, $unit]) {
                $food = $foods->get($name);
                if (! $food) {
                    throw new RuntimeException("Recipe '{$key}' requires missing ingredient '{$name}'.");
                }
                UnitConverter::scalingFactor($quantity, $unit, (float) $food->serving_size, $food->serving_unit);
            }
        }

        DB::transaction(function () use ($catalog, $foods, $owner): void {
            foreach ($catalog['recipes'] as $key => $definition) {
                $seedKey = 'clinical:'.$key;
                $recipe = Recipe::withTrashed()->where('rnd_user_id', $owner->id)->where('seed_key', $seedKey)->first();
                $names = [$definition['name'], ...($definition['aliases'] ?? [])];
                $referenceNotes = array_values(array_intersect_key($catalog['references'], array_flip($names)));
                if (! $recipe) {
                    foreach ($names as $name) {
                        $recipe = Recipe::where('rnd_user_id', $owner->id)->whereNull('seed_key')->where('name', $name)
                            ->whereIn('prep_notes', $referenceNotes)->first();
                        if ($recipe) {
                            break;
                        }
                    }
                }
                $recipe ??= new Recipe;
                $recipe->forceFill([
                    'seed_key' => $seedKey,
                    'rnd_user_id' => $owner->id,
                    'name' => $definition['name'],
                    'category' => $definition['category'],
                    'component_type' => $definition['component_type'],
                    'meal_types' => $definition['category'] === 'snack' ? ['am_snack', 'pm_snack'] : ['breakfast', 'lunch', 'dinner'],
                    'servings' => $definition['servings'],
                    'prepared_portion_amount' => $definition['portion'][0],
                    'prepared_portion_unit' => $definition['portion'][1],
                    'prep_notes' => $definition['notes'].' Source: '.$definition['source'],
                    'deleted_at' => null,
                ])->save();

                $recipe->ingredients()->delete();
                foreach ($definition['ingredients'] as [$name, $quantity, $unit]) {
                    $recipe->ingredients()->create([
                        'food_item_id' => $foods[$name]->id,
                        'quantity' => $quantity,
                        'unit' => UnitConverter::canonical($unit),
                    ]);
                }
                $recipe->recalculateTotals();

                // Consolidate only recognized reference aliases.
                Recipe::where('rnd_user_id', $owner->id)->whereNull('seed_key')
                    ->whereIn('name', $names)->whereIn('prep_notes', $referenceNotes)->whereKeyNot($recipe->id)->get()->each->delete();
            }
            foreach ($catalog['retired'] as $name => $originalNotes) {
                Recipe::where('rnd_user_id', $owner->id)->whereNull('seed_key')->where('name', $name)
                    ->where('prep_notes', $originalNotes)->get()->each->delete();
            }
        });
    }
}
