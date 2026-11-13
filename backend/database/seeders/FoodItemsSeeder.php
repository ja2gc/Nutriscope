<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Services\UsdaService;
use App\Support\UnitConverter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FoodItemsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR)['foods'];
        $this->validateDefinitions($definitions);
        $usda = app(UsdaService::class);
        $prepared = [];

        // Resolve all missing imports before writing any library records.
        foreach ($definitions as $definition) {
            $fdcId = $definition['usda_fdc_id'] ?? null;
            $query = FoodItem::query();
            $existing = $fdcId !== null
                ? $query->where('usda_fdc_id', $fdcId)->first()
                : $query->whereNull('usda_fdc_id')->where('name', $definition['name'])->first();
            $overrides = Arr::only($definition, ['name', 'category', 'allergens']);
            if ($existing) {
                $attributes = $overrides;
            } elseif ($fdcId !== null) {
                $attributes = [...$usda->prepareImport($fdcId), ...$overrides];
                if ((string) $attributes['usda_fdc_id'] !== (string) $fdcId) {
                    throw new RuntimeException('The USDA response did not match the requested food identifier.');
                }
            } else {
                $attributes = $definition;
            }
            $prepared[] = [$existing, $attributes];
        }

        DB::transaction(function () use ($prepared, $usda): void {
            foreach ($prepared as [$existing, $attributes]) {
                if ($existing) {
                    $existing->update($attributes);
                } else {
                    $usda->persistImport($attributes);
                }
            }
        });
        $this->command?->info('Synchronized '.count($definitions).' clinical foods using USDA imports and configured edits.');
    }

    private function validateDefinitions(array $definitions): void
    {
        $identities = [];
        foreach ($definitions as $definition) {
            $name = $definition['name'] ?? null;
            $fdcId = $definition['usda_fdc_id'] ?? null;
            if (! is_string($name) || trim($name) === '' || ($fdcId !== null && (! is_int($fdcId) || $fdcId < 1))) {
                throw new RuntimeException('Clinical food definitions require a name and a valid USDA identifier when importing.');
            }
            $identity = $fdcId !== null ? 'usda:'.$fdcId : 'manual:'.$name;
            if (isset($identities[$identity])) {
                throw new RuntimeException('Clinical food definitions contain a duplicate identity.');
            }
            $identities[$identity] = true;
            if ($fdcId !== null) {
                if (array_diff(array_keys($definition), ['usda_fdc_id', 'name', 'category', 'allergens']) !== []) {
                    throw new RuntimeException('USDA food definitions may contain only their identifier and configuration overrides.');
                }

                continue;
            }
            foreach (['calories', 'protein', 'carbs', 'fat', 'water_g'] as $field) {
                if (! is_numeric($definition[$field] ?? null) || ! is_finite((float) $definition[$field]) || $definition[$field] < 0) {
                    throw new RuntimeException('Manual clinical foods require verified nonnegative nutrient amounts.');
                }
            }
            if (! is_numeric($definition['serving_size'] ?? null) || ! is_finite((float) $definition['serving_size'])
                || $definition['serving_size'] <= 0 || ! UnitConverter::isKnown($definition['serving_unit'] ?? '')) {
                throw new RuntimeException('Manual clinical foods require a positive measured nutrient basis.');
            }
        }
    }
}
