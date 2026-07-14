<?php

namespace Tests\Unit;

use App\Models\FoodItem;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class UsdaServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsdaService $service;

    private const SEARCH_URL = 'https://api.nal.usda.gov/fdc/v1/foods/search*';

    private const DETAIL_URL = 'https://api.nal.usda.gov/fdc/v1/food/12345*';

    private array $searchResponse = [
        'foods' => [
            [
                'fdcId' => 12345,
                'description' => 'Chicken, breast, raw',
                'dataType' => 'SR Legacy',
                'foodNutrients' => [
                    // Search endpoint: flat structure (nutrientId + value)
                    ['nutrientId' => 1008, 'nutrientName' => 'Energy', 'value' => 110, 'unitName' => 'kcal'],
                    ['nutrientId' => 1003, 'nutrientName' => 'Protein', 'value' => 23.1, 'unitName' => 'g'],
                    ['nutrientId' => 1005, 'nutrientName' => 'Carbohydrate, by difference', 'value' => 0, 'unitName' => 'g'],
                    ['nutrientId' => 1004, 'nutrientName' => 'Total lipid (fat)', 'value' => 1.24, 'unitName' => 'g'],
                ],
            ],
        ],
    ];

    // Minimal macros-only detail response (for basic fetch/import tests)
    private array $detailResponse = [
        'fdcId' => 12345,
        'description' => 'Chicken, breast, raw',
        'dataType' => 'SR Legacy',
        'foodNutrients' => [
            // Detail endpoint: nested structure (nutrient.id + amount)
            ['nutrient' => ['id' => 1008, 'name' => 'Energy', 'unitName' => 'kcal'], 'amount' => 110],
            ['nutrient' => ['id' => 1003, 'name' => 'Protein', 'unitName' => 'g'], 'amount' => 23.1],
            ['nutrient' => ['id' => 1005, 'name' => 'Carbohydrate, by difference', 'unitName' => 'g'], 'amount' => 0],
            ['nutrient' => ['id' => 1004, 'name' => 'Total lipid (fat)', 'unitName' => 'g'], 'amount' => 1.24],
        ],
    ];

    // Full response with micronutrients for key-convention and omega-3 tests
    private array $detailResponseWithMicros = [
        'fdcId' => 12345,
        'description' => 'Chicken, breast, raw',
        'dataType' => 'SR Legacy',
        'foodNutrients' => [
            ['nutrient' => ['id' => 1008, 'name' => 'Energy', 'unitName' => 'kcal'], 'amount' => 110],
            ['nutrient' => ['id' => 1003, 'name' => 'Protein', 'unitName' => 'g'], 'amount' => 23.1],
            ['nutrient' => ['id' => 1005, 'name' => 'Carbohydrate, by difference', 'unitName' => 'g'], 'amount' => 0],
            ['nutrient' => ['id' => 1004, 'name' => 'Total lipid (fat)', 'unitName' => 'g'], 'amount' => 1.24],
            // Minerals
            ['nutrient' => ['id' => 1093, 'name' => 'Sodium, Na', 'unitName' => 'mg'], 'amount' => 74],
            ['nutrient' => ['id' => 1092, 'name' => 'Potassium, K', 'unitName' => 'mg'], 'amount' => 256],
            ['nutrient' => ['id' => 1091, 'name' => 'Phosphorus, P', 'unitName' => 'mg'], 'amount' => 220],
            ['nutrient' => ['id' => 1087, 'name' => 'Calcium, Ca', 'unitName' => 'mg'], 'amount' => 15],
            ['nutrient' => ['id' => 1253, 'name' => 'Cholesterol', 'unitName' => 'mg'], 'amount' => 85],
            ['nutrient' => ['id' => 1079, 'name' => 'Fiber, total dietary', 'unitName' => 'g'], 'amount' => 0],
            // Vitamins
            ['nutrient' => ['id' => 1162, 'name' => 'Vitamin C, total ascorbic acid', 'unitName' => 'mg'], 'amount' => 1.6],
            ['nutrient' => ['id' => 1114, 'name' => 'Vitamin D (D2 + D3)', 'unitName' => 'mcg'], 'amount' => 0.1],
            // Omega-3 fatty acids (EPA + DHA + ALA)
            ['nutrient' => ['id' => 1278, 'name' => '20:5 n-3 (EPA)', 'unitName' => 'g'], 'amount' => 0.02],
            ['nutrient' => ['id' => 1272, 'name' => 'PUFA 22:6 n-3 (DHA)', 'unitName' => 'g'], 'amount' => 0.05],
            ['nutrient' => ['id' => 1404, 'name' => '18:3 n-3 c,c,c (ALA)', 'unitName' => 'g'], 'amount' => 0.03],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.usda.key' => 'test-key',
            'services.usda.base_url' => 'https://api.nal.usda.gov/fdc/v1',
        ]);
        $this->service = app(UsdaService::class);
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function test_can_search_usda_foods_returns_mapped_results(): void
    {
        Http::fake([self::SEARCH_URL => Http::response($this->searchResponse, 200)]);

        $results = $this->service->search('chicken breast');

        Http::assertSent(function (Request $request) {
            return $request['dataType'] === ['SR Legacy', 'Foundation', 'Survey (FNDDS)']
                && $request['query'] === 'chicken breast';
        });

        $this->assertCount(1, $results);
        $this->assertEquals(12345, $results[0]['fdc_id']);
        $this->assertEquals('Chicken, breast, raw', $results[0]['name']);
        $this->assertEquals(110.0, $results[0]['calories']);
        $this->assertEquals(23.1, $results[0]['protein']);
    }

    public function test_handles_usda_api_error_gracefully(): void
    {
        Http::fake([self::SEARCH_URL => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);
        $this->service->search('chicken');
    }

    // ── Fetch / Caching ───────────────────────────────────────────────────────

    public function test_can_fetch_single_usda_food_by_fdc_id(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponse, 200)]);

        $result = $this->service->fetch(12345);

        $this->assertEquals(12345, $result['fdc_id']);
        $this->assertEquals('Chicken, breast, raw', $result['name']);
        $this->assertEquals(110.0, $result['calories']);
        $this->assertEquals(23.1, $result['protein']);
    }

    public function test_fetch_is_cached_and_http_called_only_once(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponse, 200)]);

        $this->service->fetch(12345);
        $this->service->fetch(12345); // should hit cache, not HTTP

        Http::assertSentCount(1);
    }

    // ── Micronutrient key convention ──────────────────────────────────────────

    public function test_micronutrient_keys_match_project_convention(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponseWithMicros, 200)]);

        $result = $this->service->fetch(12345);
        $micros = $result['micronutrients'];

        // Project uses plain names (not 'sodium_mg', 'phosphorus_mg', etc.)
        $this->assertEquals(74, $micros['sodium']);
        $this->assertEquals(256, $micros['potassium']);
        $this->assertEquals(220, $micros['phosphate']); // USDA Phosphorus → project 'phosphate'
        $this->assertEquals(15, $micros['calcium']);
        $this->assertEquals(85, $micros['cholesterol']);
    }

    public function test_omega3_is_summed_from_epa_dha_and_ala(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponseWithMicros, 200)]);

        $result = $this->service->fetch(12345);

        // EPA 0.02 + DHA 0.05 + ALA 0.03 = 0.1
        $this->assertEquals(0.1, $result['micronutrients']['omega3']);
    }

    public function test_vitamins_are_extracted(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponseWithMicros, 200)]);

        $result = $this->service->fetch(12345);
        $micros = $result['micronutrients'];

        $this->assertEquals(1.6, $micros['vitamin_c']);
        $this->assertEquals(0.1, $micros['vitamin_d']);
    }

    // ── Water content ─────────────────────────────────────────────────────────

    public function test_fetch_extracts_water_g_from_nutrient_1051(): void
    {
        $responseWithWater = $this->detailResponse;
        $responseWithWater['foodNutrients'][] = [
            'nutrient' => ['id' => 1051, 'name' => 'Water', 'unitName' => 'g'],
            'amount' => 65.46,
        ];

        Http::fake([self::DETAIL_URL => Http::response($responseWithWater, 200)]);

        $result = $this->service->fetch(12345);

        $this->assertEquals(65.46, $result['water_g']);
    }

    public function test_fetch_sets_water_g_null_when_nutrient_1051_absent(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponse, 200)]);

        $result = $this->service->fetch(12345);

        $this->assertNull($result['water_g']);
    }

    public function test_import_persists_water_g_on_food_item(): void
    {
        $responseWithWater = $this->detailResponse;
        $responseWithWater['foodNutrients'][] = [
            'nutrient' => ['id' => 1051, 'name' => 'Water', 'unitName' => 'g'],
            'amount' => 65.46,
        ];

        Http::fake([self::DETAIL_URL => Http::response($responseWithWater, 200)]);

        $foodItem = $this->service->import(12345);

        $this->assertEquals(65.46, $foodItem->water_g);
        $this->assertDatabaseHas('food_items', ['usda_fdc_id' => 12345, 'water_g' => 65.46]);
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function test_import_creates_food_item_from_usda_data(): void
    {
        Http::fake([self::DETAIL_URL => Http::response($this->detailResponse, 200)]);

        $foodItem = $this->service->import(12345);

        $this->assertInstanceOf(FoodItem::class, $foodItem);
        $this->assertEquals(12345, $foodItem->usda_fdc_id);
        $this->assertEquals('Chicken, breast, raw', $foodItem->name);
        $this->assertDatabaseHas('food_items', ['usda_fdc_id' => 12345]);
    }

    public function test_import_prevents_duplicate_by_usda_fdc_id(): void
    {
        FoodItem::factory()->create(['usda_fdc_id' => 12345, 'name' => 'Existing Food']);

        $this->expectException(RuntimeException::class);
        $this->service->import(12345);
    }
}
