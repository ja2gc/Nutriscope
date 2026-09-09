<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class FoodItemsSeeder extends Seeder
{
    private const DATASET = 'seeders/data/clinical-foods.json';

    public function run(): void
    {
        $foods = $this->loadDataset();

        DB::transaction(function () use ($foods): void {
            foreach ($foods as $food) {
                $this->synchronizeFood($food);
            }
        });

        $this->command?->info('Synchronized '.count($foods).' USDA-derived clinical foods from the pinned local dataset.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadDataset(): array
    {
        $path = database_path(self::DATASET);
        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("Clinical food dataset is missing or unreadable: {$path}");
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Clinical food dataset contains invalid JSON.', previous: $exception);
        }

        $foods = $payload['foods'] ?? null;
        if (! is_array($foods) || $foods === []) {
            throw new RuntimeException('Clinical food dataset does not contain any foods.');
        }

        foreach ($foods as $food) {
            if (! is_array($food)
                || ! is_string($food['name'] ?? null)
                || ! is_int($food['usda_fdc_id'] ?? null)
                || (float) ($food['calories'] ?? 0) <= 0
            ) {
                throw new RuntimeException('Clinical food dataset contains an invalid canonical food record.');
            }
        }

        // The legacy category fallback treats every vegetable as a ready-to-eat
        // snack. Pin the curated demo interpretation so raw garlic and cooked
        // vegetables cannot appear as standalone snacks, while fruit can.
        $foods = array_map(function (array $food): array {
            if (($food['ready_to_eat'] ?? null) === null) {
                if (($food['category'] ?? null) === 'fruit') {
                    $food['ready_to_eat'] = true;
                } elseif (($food['category'] ?? null) === 'vegetable') {
                    $food['ready_to_eat'] = false;
                }
            }

            return $food;
        }, $foods);

        return array_values($foods);
    }

    /**
     * @param  array<string, mixed>  $food
     */
    private function synchronizeFood(array $food): void
    {
        unset($food['source']);

        $fdcId = (string) $food['usda_fdc_id'];
        $name = $food['name'];

        $canonical = FoodItem::query()
            ->where('usda_fdc_id', $fdcId)
            ->orWhere(function ($query) use ($name): void {
                $query->where('name', $name)->whereNotNull('usda_fdc_id');
            })
            ->first();

        if ($canonical === null) {
            FoodItem::query()->create($food);

            return;
        }

        $canonical->update($food);
    }
}
