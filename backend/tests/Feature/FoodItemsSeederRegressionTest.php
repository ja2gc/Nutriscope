<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Services\UsdaService;
use Database\Seeders\FoodItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use ReflectionClass;
use Tests\TestCase;

class FoodItemsSeederRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerun_removes_manual_foods_after_inventory_decoupling(): void
    {
        $ingredientNames = array_keys((new ReflectionClass(FoodItemsSeeder::class))->getConstant('INGREDIENTS'));
        foreach ($ingredientNames as $index => $name) {
            FoodItem::factory()->create([
                'name' => $name,
                'usda_fdc_id' => 9_000_000 + $index,
            ]);
        }
        $manual = FoodItem::factory()->create(['usda_fdc_id' => null]);

        $this->artisan('db:seed', [
            '--class' => FoodItemsSeeder::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertModelMissing($manual);
    }

    public function test_incomplete_usda_import_fails_after_retries(): void
    {
        $ingredientNames = array_keys((new ReflectionClass(FoodItemsSeeder::class))->getConstant('INGREDIENTS'));
        foreach (array_slice($ingredientNames, 1) as $index => $name) {
            FoodItem::factory()->create([
                'name' => $name,
                'usda_fdc_id' => 9_100_000 + $index,
            ]);
        }

        $this->mock(UsdaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('search')
                ->times(3)
                ->with('rice white long-grain cooked enriched', 10)
                ->andReturn([]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('USDA food import incomplete.');

        $this->seed(FoodItemsSeeder::class);
    }
}
