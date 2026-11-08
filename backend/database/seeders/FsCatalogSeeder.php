<?php

namespace Database\Seeders;

use App\Models\FsItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/food-service.php');
        DB::transaction(function () use ($data): void {
            $supplier = Supplier::query()->firstOrCreate(
                ['name' => 'Noralyn Macaballug, RPDH-MPC'],
                ['category' => 'Food and kitchen supplies', 'notes' => 'Supplier named in the September purchasing forms. Contact and address require confirmation.'],
            );
            foreach ($data['catalog'] as [$name, $category, $unit, $price, $auto, $basis]) {
                FsItem::query()->firstOrCreate(['name' => $name], [
                    'kind' => $category === 'Supply' ? 'supply' : 'ingredient',
                    'category' => $category,
                    'base_unit' => $unit,
                    'purchase_unit' => $unit,
                    'purchase_price' => $price,
                    'units_per_purchase' => 1,
                    'include_in_generated_lists' => $auto,
                    'default_supplier_id' => $basis === 'recorded' || in_array($name, $data['purchased_with_estimated_prices'], true) ? $supplier->id : null,
                    'is_active' => true,
                    'notes' => $basis === 'recorded'
                        ? 'September 2026 reference price in the stated unit. Update to the current supplier price before purchasing.'
                        : 'Estimated starting price per stated unit. Update before purchasing and confirm package contents before converting a buying unit.',
                ]);
            }
        });
    }
}
