<?php

namespace App\Services;

use App\Models\FoodItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UsdaService
{
    private string $apiKey;
    private string $baseUrl;

    private const ENERGY_ID  = 1008;
    private const PROTEIN_ID = 1003;
    private const CARBS_ID   = 1005;
    private const FAT_ID     = 1004;

    /**
     * Keys intentionally match the project's seeder and clinical-rule convention.
     * e.g. 'phosphate' (not 'phosphorus_mg'), 'sodium' (not 'sodium_mg').
     * The clinical-rule engine matches nutrient_or_food_tag values against these keys.
     */
    private const MICRO_IDS = [
        // Minerals — plain names, no _mg suffix
        1093 => 'sodium',       // mg
        1092 => 'potassium',    // mg
        1091 => 'phosphate',    // mg  (USDA label: Phosphorus — stored as 'phosphate' per project)
        1087 => 'calcium',      // mg
        1089 => 'iron',         // mg
        1090 => 'magnesium',    // mg
        1095 => 'zinc',         // mg
        1098 => 'copper',       // mg
        1101 => 'manganese',    // mg
        1103 => 'selenium',     // mcg
        1134 => 'iodine',       // mcg
        // Other clinically tracked nutrients
        1079 => 'fiber',        // g
        1253 => 'cholesterol',  // mg
        // Vitamins
        1106 => 'vitamin_a',    // mcg RAE
        1162 => 'vitamin_c',    // mg
        1114 => 'vitamin_d',    // mcg
        1109 => 'vitamin_e',    // mg alpha-tocopherol
        1185 => 'vitamin_k',    // mcg phylloquinone
        1165 => 'vitamin_b1',   // mg thiamin
        1166 => 'vitamin_b2',   // mg riboflavin
        1167 => 'vitamin_b3',   // mg niacin
        1175 => 'vitamin_b6',   // mg
        1178 => 'vitamin_b12',  // mcg
        1190 => 'folate',       // mcg DFE
    ];

    // EPA (1278) + DHA (1272) + ALA (1404) — summed into 'omega3' (g)
    // Note: 1280 is DPA (22:5 n-3), NOT DHA. Real DHA is 1272 (22:6 n-3).
    private const OMEGA3_IDS = [1278, 1272, 1404];

    private const CACHE_TTL_DAYS = 7;

    public function __construct()
    {
        $this->apiKey  = config('services.usda.key', '');
        $this->baseUrl = config('services.usda.base_url', 'https://api.nal.usda.gov/fdc/v1');
    }

    /**
     * Search USDA FoodData Central. Returns macro preview data only (for the import modal).
     * Full micronutrient data is fetched on import via fetch().
     */
    public function search(string $query, int $pageSize = 20): array
    {
        $response = Http::get("{$this->baseUrl}/foods/search", [
            'query'    => $query,
            'pageSize' => $pageSize,
            'api_key'  => $this->apiKey,
            'dataType' => 'SR Legacy,Foundation,Survey (FNDDS)',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("USDA API search failed: {$response->status()}");
        }

        return collect($response->json('foods', []))->map(function ($food) {
            // Search response uses flat structure: nutrientId + value
            $nutrients = collect($food['foodNutrients'] ?? []);
            return [
                'fdc_id'   => $food['fdcId'],
                'name'     => $food['description'],
                'category' => $food['dataType'] ?? null,
                'calories' => $this->findInSearch($nutrients, self::ENERGY_ID),
                'protein'  => $this->findInSearch($nutrients, self::PROTEIN_ID),
                'carbs'    => $this->findInSearch($nutrients, self::CARBS_ID),
                'fat'      => $this->findInSearch($nutrients, self::FAT_ID),
            ];
        })->values()->all();
    }

    /**
     * Fetch full nutrient detail for one food. Results cached in Redis for 7 days.
     * Detail response uses nested structure: nutrient.id + amount.
     */
    public function fetch(int $fdcId): array
    {
        return Cache::remember("usda_food_{$fdcId}", now()->addDays(self::CACHE_TTL_DAYS), function () use ($fdcId) {
            $response = Http::get("{$this->baseUrl}/food/{$fdcId}", [
                'api_key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("USDA API fetch failed for FDC ID {$fdcId}: {$response->status()}");
            }

            $data      = $response->json();
            $nutrients = collect($data['foodNutrients'] ?? []);

            return [
                'fdc_id'         => $data['fdcId'],
                'name'           => $data['description'],
                'calories'       => $this->findInDetail($nutrients, self::ENERGY_ID),
                'protein'        => $this->findInDetail($nutrients, self::PROTEIN_ID),
                'carbs'          => $this->findInDetail($nutrients, self::CARBS_ID),
                'fat'            => $this->findInDetail($nutrients, self::FAT_ID),
                'micronutrients' => $this->extractMicros($nutrients),
            ];
        });
    }

    /**
     * Import a USDA food into the local food_items table.
     * Throws if the FDC ID already exists to prevent duplicates.
     */
    public function import(int $fdcId): FoodItem
    {
        if (FoodItem::where('usda_fdc_id', $fdcId)->exists()) {
            throw new RuntimeException("Food item with USDA FDC ID {$fdcId} already exists.");
        }

        $data = $this->fetch($fdcId);

        return FoodItem::create([
            'name'           => $data['name'],
            'usda_fdc_id'    => $data['fdc_id'],
            'calories'       => $data['calories'],
            'protein'        => $data['protein'],
            'carbs'          => $data['carbs'],
            'fat'            => $data['fat'],
            'micronutrients' => $data['micronutrients'],
            'allergens'      => [],
            'serving_size'   => 100,
            'serving_unit'   => 'g',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /** Search response: flat structure — nutrientId + value */
    private function findInSearch($nutrients, int $id): float
    {
        return (float) ($nutrients->firstWhere('nutrientId', $id)['value'] ?? 0);
    }

    /** Detail response: nested structure — nutrient.id + amount */
    private function findInDetail($nutrients, int $id): float
    {
        $found = $nutrients->first(fn($n) => ($n['nutrient']['id'] ?? null) === $id);
        return (float) ($found['amount'] ?? 0);
    }

    private function extractMicros($nutrients): array
    {
        $micros = [];

        foreach (self::MICRO_IDS as $id => $key) {
            $found = $nutrients->first(fn($n) => ($n['nutrient']['id'] ?? null) === $id);
            if ($found && isset($found['amount'])) {
                $micros[$key] = round((float) $found['amount'], 3);
            }
        }

        // Omega-3: EPA + DHA + ALA summed into a single 'omega3' (g) key
        $omega3 = 0.0;
        foreach (self::OMEGA3_IDS as $id) {
            $found = $nutrients->first(fn($n) => ($n['nutrient']['id'] ?? null) === $id);
            if ($found && isset($found['amount'])) {
                $omega3 += (float) $found['amount'];
            }
        }
        if ($omega3 > 0) {
            $micros['omega3'] = round($omega3, 3);
        }

        return $micros;
    }
}
