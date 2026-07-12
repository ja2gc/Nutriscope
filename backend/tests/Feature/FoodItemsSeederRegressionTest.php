<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use Database\Seeders\FoodItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
