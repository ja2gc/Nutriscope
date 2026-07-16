<?php

namespace App\Services\Audit;

use Illuminate\Support\Str;

class AuditFieldLabels
{
    private const LABELS = [
        'energy_kcal' => 'Energy Target',
        'energy_target' => 'Energy Target',
        'protein_g' => 'Protein Target',
        'protein_target' => 'Protein Target',
        'carbs_g' => 'Carbohydrate Target',
        'fat_g' => 'Fat Target',
        'fluid_ml' => 'Fluid Target',
        'serving_size' => 'Serving Size',
        'serving_unit' => 'Serving Unit',
        'purchase_price' => 'Purchase Price',
        'unit_price' => 'Unit Price',
        'total_amount' => 'Total Amount',
        'allocated_amount' => 'Opening Allocation',
        'per_head_day_limit' => 'Budget Per Head Per Day',
        'balance_before' => 'Balance Before',
        'balance_after' => 'Balance After',
        'purchase_order_public_id' => 'Purchase Order Reference',
        'signed_amount' => 'Signed Amount',
        'open_purchase_orders_re_evaluated_count' => 'Open Purchase Orders Re-evaluated',
        'usda_fdc_id' => 'USDA FoodData Central Reference',
        'estimated_population' => 'Estimated Population',
        'served_population' => 'Served Population',
    ];

    public function label(string $field): string
    {
        return self::LABELS[$field]
            ?? Str::of($field)->replace(['_', '.'], ' ')->title()->toString();
    }
}
