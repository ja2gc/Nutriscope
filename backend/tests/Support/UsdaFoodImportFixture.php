<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UsdaFoodImportFixture
{
    /** Synthetic provider responses for tests; these are not food-composition references. */
    public static function fake(array $failureStatuses = []): void
    {
        Cache::flush();
        $catalog = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);
        $foods = collect($catalog['foods'])->whereNotNull('usda_fdc_id')->keyBy('usda_fdc_id');
        Http::preventStrayRequests();
        Http::fake([rtrim(config('services.usda.base_url'), '/').'/food/*' => function (Request $request) use ($foods, $failureStatuses) {
            $id = (int) basename(parse_url($request->url(), PHP_URL_PATH));
            if (isset($failureStatuses[$id])) {
                return Http::response([], $failureStatuses[$id]);
            }
            $food = $foods->get($id);
            if (! $food) {
                return Http::response([], 404);
            }
            [$energy, $protein, $carbs, $fat] = match ($food['category'] ?? '') {
                'protein' => [150, 20, 2, 7],
                'carbs' => [120, 2, 26, 0.5],
                'vegetable' => [25, 1, 5, 0.2],
                'fruit' => [50, 1, 12, 0.2],
                'dairy' => [42, 3.37, 4.99, 0.97],
                'fat' => [900, 0, 0, 100],
                default => [150, 8, 20, 4],
            };
            $amounts = [1008 => $energy, 1003 => $protein, 1005 => $carbs, 1004 => $fat, 1051 => 50, 1093 => 10];
            if ($id === 173468) {
                $amounts = [1008 => 0, 1003 => 0, 1005 => 0, 1004 => 0, 1051 => 0, 1093 => 38758];
            }

            return Http::response([
                'fdcId' => $id, 'description' => 'Synthetic provider food '.$id,
                'dataType' => in_array($id, [2652846, 2119814], true) ? 'Branded' : 'SR Legacy',
                'servingSizeUnit' => 'g',
                'foodNutrients' => collect($amounts)->map(fn ($amount, $nutrientId) => [
                    'nutrient' => ['id' => $nutrientId], 'amount' => $amount,
                ])->values()->all(),
            ]);
        }]);
    }
}
