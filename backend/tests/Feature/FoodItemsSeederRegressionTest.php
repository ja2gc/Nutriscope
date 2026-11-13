<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use Database\Seeders\FoodItemPricesSeeder;
use Database\Seeders\FoodItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\UsdaFoodImportFixture;
use Tests\TestCase;

class FoodItemsSeederRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_foods_use_the_normal_usda_importer_and_curated_settings(): void
    {
        UsdaFoodImportFixture::fake();
        $this->seed(FoodItemsSeeder::class);
        $rice = FoodItem::where('usda_fdc_id', 169708)->firstOrFail();
        $this->assertSame('Cooked White Rice', $rice->name);
        $this->assertSame(120.0, (float) $rice->calories);
        $this->assertSame(2.0, (float) $rice->protein);
        $this->assertSame('g', $rice->serving_unit);
        $this->assertSame(100.0, (float) $rice->serving_size);
        Http::assertSentCount(count($this->definitions()) - 1);
        $this->assertDatabaseCount('food_items', count($this->definitions()));
    }

    public function test_matching_names_do_not_replace_a_different_usda_source(): void
    {
        UsdaFoodImportFixture::fake();
        $imported = FoodItem::factory()->create(['name' => 'Cooked White Rice', 'usda_fdc_id' => 99999990, 'calories' => 77, 'unit_price' => 63]);
        $this->seed([FoodItemsSeeder::class, FoodItemPricesSeeder::class]);
        $this->assertSame('99999990', (string) $imported->fresh()->usda_fdc_id);
        $this->assertSame(77.0, (float) $imported->fresh()->calories);
        $this->assertSame(63.0, (float) $imported->fresh()->unit_price);
        $this->assertSame(1, FoodItem::where('usda_fdc_id', 169708)->count());
    }

    public function test_reruns_keep_imported_nutrients_and_preserve_manual_foods(): void
    {
        UsdaFoodImportFixture::fake();
        $canonical = FoodItem::factory()->create(['name' => 'My rice label', 'usda_fdc_id' => 169708,
            'serving_size' => 100, 'serving_unit' => 'g', 'calories' => 77, 'protein' => 9, 'micronutrients' => ['iron' => 2]]);
        $manual = FoodItem::factory()->create(['usda_fdc_id' => null]);
        $this->seed(FoodItemsSeeder::class);
        $requests = Http::recorded()->count();
        $ids = FoodItem::orderBy('id')->pluck('uuid')->all();
        $this->seed(FoodItemsSeeder::class);
        $this->assertSame($requests, Http::recorded()->count());
        $this->assertSame($ids, FoodItem::orderBy('id')->pluck('uuid')->all());
        $this->assertSame('Cooked White Rice', $canonical->fresh()->name);
        $this->assertSame(77.0, (float) $canonical->fresh()->calories);
        $this->assertSame(9.0, (float) $canonical->fresh()->protein);
        $this->assertSame(['iron' => 2], $canonical->fresh()->micronutrients);
        $this->assertModelExists($manual);
    }

    public function test_failed_usda_import_does_not_leave_a_partial_food_library(): void
    {
        UsdaFoodImportFixture::fake([169704 => 503]);
        $manual = FoodItem::factory()->create(['usda_fdc_id' => null]);
        try {
            $this->seed(FoodItemsSeeder::class);
            $this->fail('Unavailable USDA data must stop the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('USDA is temporarily unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('food_items', 1);
        $this->assertModelExists($manual);
    }

    public function test_water_is_a_manual_zero_energy_fluid_without_a_usda_import(): void
    {
        UsdaFoodImportFixture::fake([174158 => 503]);
        $this->seed(FoodItemsSeeder::class);
        $water = FoodItem::where('name', 'Water')->firstOrFail();
        $this->assertNull($water->usda_fdc_id);
        $this->assertSame('mL', $water->serving_unit);
        $this->assertSame(100.0, (float) $water->serving_size);
        foreach (['calories', 'protein', 'carbs', 'fat'] as $field) {
            $this->assertSame(0.0, (float) $water->{$field});
        }
        $this->assertEquals(100, $water->water_g);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/food/174158'));
    }

    private function definitions(): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR)['foods'];
    }
}
