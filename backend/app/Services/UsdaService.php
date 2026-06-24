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
    private const WATER_ID   = 1051;

    /**
     * Keys intentionally match the project's seeder and clinical-rule convention.
     * e.g. 'phosphate' (not 'phosphorus_mg'), 'sodium' (not 'sodium_mg').
     * The clinical-rule engine matches nutrient_or_food_tag values against these keys.
     */
    private const MICRO_IDS = [
        1093 => 'sodium',
        1092 => 'potassium',
        1091 => 'phosphate',  // USDA: Phosphorus — stored as 'phosphate' per project convention
        1087 => 'calcium',
        1089 => 'iron',
        1090 => 'magnesium',
        1095 => 'zinc',
        1098 => 'copper',
        1101 => 'manganese',
        1103 => 'selenium',
        1134 => 'iodine',
        1079 => 'fiber',
        1253 => 'cholesterol',
        1106 => 'vitamin_a',
        1162 => 'vitamin_c',
        1114 => 'vitamin_d',
        1109 => 'vitamin_e',
        1185 => 'vitamin_k',
        1165 => 'vitamin_b1',
        1166 => 'vitamin_b2',
        1167 => 'vitamin_b3',
        1175 => 'vitamin_b6',
        1178 => 'vitamin_b12',
        1190 => 'folate',
    ];

    // EPA (1278) + DHA (1272) + ALA (1404) — summed into 'omega3' (g)
    // Note: 1280 is DPA (22:5 n-3), NOT DHA. Real DHA is 1272 (22:6 n-3).
    private const OMEGA3_IDS = [1278, 1272, 1404];

    private const CACHE_TTL_DAYS = 7;

    /**
     * Maps USDA foodCategory.description → our internal category values.
     * Categories not listed here return null (manual input required).
     */
    private const CATEGORY_MAP = [
        'Poultry Products'                    => 'protein',
        'Beef Products'                       => 'protein',
        'Pork Products'                       => 'protein',
        'Lamb, Veal, and Game Products'       => 'protein',
        'Finfish and Shellfish Products'      => 'protein',
        'Legumes and Legume Products'         => 'protein',
        'Sausages and Luncheon Meats'         => 'protein',
        'Dairy and Egg Products'              => 'dairy',
        'Vegetables and Vegetable Products'   => 'vegetable',
        'Fruits and Fruit Juices'             => 'fruit',
        'Fats and Oils'                       => 'fat',
        'Nut and Seed Products'               => 'fat',
        'Grain and Cereal Products'           => 'carbs',
        'Breakfast Cereals'                   => 'carbs',
        'Baked Products'                      => 'carbs',
        'Sweets'                              => 'carbs',
        'Snacks'                              => 'carbs',
    ];

    /** Egg-specific name terms — used to distinguish eggs from dairy within "Dairy and Egg Products" */
    private const EGG_TERMS  = ['egg', 'eggs'];

    /** Milk/dairy name terms — catches dairy foods even when the USDA category is non-standard (e.g. FNDDS) */
    private const MILK_TERMS = ['milk', 'cream', 'cheese', 'butter', 'yogurt', 'dairy', 'whey', 'lactose', 'kefir', 'custard', 'pudding'];

    /**
     * Shellfish keywords — used to distinguish shellfish from fish within
     * 'Finfish and Shellfish Products' since USDA lumps them together.
     */
    private const SHELLFISH_TERMS = [
        'shrimp', 'crab', 'lobster', 'prawn', 'squid', 'clam',
        'oyster', 'scallop', 'mussel', 'crawfish', 'crayfish', 'abalone',
    ];

    public function __construct()
    {
        $this->apiKey  = config('services.usda.key', '');
        $this->baseUrl = config('services.usda.base_url', 'https://api.nal.usda.gov/fdc/v1');
    }

    /**
     * Search USDA FoodData Central. Returns macro preview + food category for display.
     */
    public function search(string $query, int $pageSize = 20): array
    {
        $response = Http::withoutVerifying()->get("{$this->baseUrl}/foods/search", [
            'query'    => $query,
            'pageSize' => $pageSize,
            'api_key'  => $this->apiKey,
            'dataType' => 'SR Legacy,Foundation,Survey (FNDDS)',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("USDA API search failed: {$response->status()}");
        }

        return collect($response->json('foods', []))->map(function ($food) {
            $nutrients = collect($food['foodNutrients'] ?? []);
            return [
                'fdc_id'        => $food['fdcId'],
                'name'          => $food['description'],
                'data_type'     => $food['dataType'] ?? null,
                'food_category' => $food['foodCategory'] ?? null,
                'calories'      => $this->findInSearch($nutrients, self::ENERGY_ID),
                'protein'       => $this->findInSearch($nutrients, self::PROTEIN_ID),
                'carbs'         => $this->findInSearch($nutrients, self::CARBS_ID),
                'fat'           => $this->findInSearch($nutrients, self::FAT_ID),
            ];
        })->values()->all();
    }

    /**
     * Fetch full nutrient detail for one food. Results cached in Redis for 7 days.
     * Also extracts food category from the API response.
     */
    public function fetch(int $fdcId): array
    {
        return Cache::remember("usda_food_{$fdcId}", now()->addDays(self::CACHE_TTL_DAYS), function () use ($fdcId) {
            $response = Http::withoutVerifying()->get("{$this->baseUrl}/food/{$fdcId}", [
                'api_key' => $this->apiKey,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("USDA API fetch failed for FDC ID {$fdcId}: {$response->status()}");
            }

            $data      = $response->json();
            $nutrients = collect($data['foodNutrients'] ?? []);

            // foodCategory is nested as object on detail endpoint, string on search endpoint
            $foodCategory = $data['foodCategory']['description']
                ?? $data['foodCategory']
                ?? null;

            $waterAmount = $this->findInDetail($nutrients, self::WATER_ID);
            return [
                'fdc_id'         => $data['fdcId'],
                'name'           => $data['description'],
                'food_category'  => $foodCategory,
                'calories'       => $this->findInDetail($nutrients, self::ENERGY_ID),
                'protein'        => $this->findInDetail($nutrients, self::PROTEIN_ID),
                'carbs'          => $this->findInDetail($nutrients, self::CARBS_ID),
                'fat'            => $this->findInDetail($nutrients, self::FAT_ID),
                'water_g'        => $waterAmount > 0 ? round($waterAmount, 2) : null,
                'micronutrients' => $this->extractMicros($nutrients),
            ];
        });
    }

    /**
     * Import a USDA food into food_items.
     * Auto-populates category and allergens from USDA data — RND should review after import.
     */
    public function import(int $fdcId): FoodItem
    {
        if (FoodItem::where('usda_fdc_id', $fdcId)->exists()) {
            throw new RuntimeException("Food item with USDA FDC ID {$fdcId} already exists.");
        }

        $data         = $this->fetch($fdcId);
        $foodCategory = $data['food_category'] ?? null;

        return FoodItem::create([
            'name'           => $data['name'],
            'usda_fdc_id'    => $data['fdc_id'],
            'calories'       => $data['calories'],
            'protein'        => $data['protein'],
            'carbs'          => $data['carbs'],
            'fat'            => $data['fat'],
            'water_g'        => $data['water_g'] ?? null,
            'micronutrients' => $data['micronutrients'],
            'category'       => $this->mapCategory($foodCategory),
            'allergens'      => $this->detectAllergens($data['name'], $foodCategory ?? ''),
            'serving_size'   => 100,
            'serving_unit'   => 'g',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapCategory(?string $usdaCategory): ?string
    {
        if ($usdaCategory === null) return null;
        return self::CATEGORY_MAP[$usdaCategory] ?? null;
    }

    /**
     * Auto-detect Big 9 allergens from USDA category + food name.
     * Returns suggestions — RND must review on the edit page.
     */
    private function detectAllergens(string $name, string $usdaCategory): array
    {
        $allergens = [];
        $lower     = strtolower($name);

        // "Dairy and Egg Products" — distinguish by food name since USDA lumps both together
        if ($usdaCategory === 'Dairy and Egg Products') {
            $isEgg   = collect(self::EGG_TERMS)->contains(fn($t) => str_contains($lower, $t));
            $isDairy = collect(self::MILK_TERMS)->contains(fn($t) => str_contains($lower, $t));

            if ($isEgg)   $allergens[] = 'eggs';
            if ($isDairy) $allergens[] = 'milk';
            // If neither keyword matches, flag both — better to over-flag for RND review
            if (! $isEgg && ! $isDairy) {
                $allergens = ['milk', 'eggs'];
            }
        }

        // Fish vs shellfish — USDA lumps them in one category
        if ($usdaCategory === 'Finfish and Shellfish Products') {
            $isShellfish = false;
            foreach (self::SHELLFISH_TERMS as $term) {
                if (str_contains($lower, $term)) {
                    $isShellfish = true;
                    break;
                }
            }
            $allergens[] = $isShellfish ? 'shellfish' : 'fish';
        }

        // Wheat from grain/baked products — not all grains contain wheat
        if (in_array($usdaCategory, ['Grain and Cereal Products', 'Baked Products', 'Breakfast Cereals'])) {
            $wheatTerms = ['wheat', 'flour', 'bread', 'pasta', 'barley', 'rye', 'semolina', 'bulgur', 'spelt'];
            foreach ($wheatTerms as $term) {
                if (str_contains($lower, $term)) {
                    $allergens[] = 'wheat';
                    break;
                }
            }
        }

        // Soy + peanuts from legumes — most legumes are NOT allergens
        if ($usdaCategory === 'Legumes and Legume Products') {
            if (str_contains($lower, 'soy') || str_contains($lower, 'tofu') || str_contains($lower, 'edamame') || str_contains($lower, 'miso')) {
                $allergens[] = 'soybeans';
            }
            if (str_contains($lower, 'peanut')) {
                $allergens[] = 'peanuts';
            }
        }

        // Nut and seed products — distinguish peanuts, sesame, and tree nuts
        if ($usdaCategory === 'Nut and Seed Products') {
            if (str_contains($lower, 'peanut')) {
                $allergens[] = 'peanuts';
            } elseif (str_contains($lower, 'sesame') || str_contains($lower, 'tahini')) {
                $allergens[] = 'sesame';
            } else {
                $allergens[] = 'tree nuts';
            }
        }

        // Milk/dairy — catch FNDDS and other non-standard categories by name scan
        if (! in_array('milk', $allergens)) {
            foreach (self::MILK_TERMS as $term) {
                if (str_contains($lower, $term)) {
                    $allergens[] = 'milk';
                    break;
                }
            }
        }

        // Sesame can appear anywhere — scan name regardless of category
        if (! in_array('sesame', $allergens) && (str_contains($lower, 'sesame') || str_contains($lower, 'tahini'))) {
            $allergens[] = 'sesame';
        }

        return array_values(array_unique($allergens));
    }

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
