<?php

namespace Database\Seeders;

use App\Models\FoodItem;
use App\Models\Inventory;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $foodItems = FoodItem::all();

        foreach ($foodItems as $food) {
            // Determine a reasonable unit and amount
            $unit = match ($food->category) {
                'protein', 'vegetable', 'carbs', 'fat' => 'kg',
                'dairy'                                 => 'liters',
                'fruit'                                 => 'kg',
                default                                 => 'kg',
            };

            // Set stock quantities and usage rates
            Inventory::updateOrCreate(
                ['food_item_id' => $food->id],
                [
                    'quantity_in_stock' => rand(50, 200),
                    'unit' => $unit,
                    'expiry_date' => now()->addDays(rand(10, 90))->toDateString(),
                    'usage_rate' => rand(5, 20),
                    'notes' => 'Seeded sample inventory.',
                ]
            );
        }
    }
}
