<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use Database\Seeders\FoodItemsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodItemsSeederRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rerun_refreshes_canonical_foods_and_preserves_manual_foods(): void
    {
        $dataset = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);
        $first = $dataset['foods'][0];
        $canonical = FoodItem::factory()->create([
            'name' => $first['name'],
            'usda_fdc_id' => $first['usda_fdc_id'],
            'calories' => 1,
        ]);
        $manual = FoodItem::factory()->create(['usda_fdc_id' => null]);

        $this->artisan('db:seed', [
            '--class' => FoodItemsSeeder::class,
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $this->assertModelExists($manual);
        $this->assertSame((float) $first['calories'], (float) $canonical->fresh()->calories);
    }

    public function test_local_dataset_seeds_every_pinned_usda_food_offline(): void
    {
        $dataset = json_decode(file_get_contents(database_path('seeders/data/clinical-foods.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->seed(FoodItemsSeeder::class);

        $ids = collect($dataset['foods'])->pluck('usda_fdc_id')->map(fn ($id) => (string) $id)->all();
        $this->assertCount(count($ids), FoodItem::query()->whereIn('usda_fdc_id', $ids)->get());
    }
}
