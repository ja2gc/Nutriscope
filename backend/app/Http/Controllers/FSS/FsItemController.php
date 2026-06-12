<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FsItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FsItemController extends Controller
{
    public function update(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'category'           => ['nullable', 'string', 'max:100'],
            'purchase_price'     => ['sometimes', 'numeric', 'min:0'],
            'purchase_unit'      => ['sometimes', 'string', 'max:20'],
            'base_unit'          => ['sometimes', 'string', 'max:20'],
            'units_per_purchase' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        // A price/unit change shifts derived unit_cost → refresh dependent recipe costs.
        $priceTouched = array_intersect(array_keys($data), ['purchase_price', 'purchase_unit', 'base_unit', 'units_per_purchase']) !== [];

        $fsItem->update($data);
        if ($priceTouched) {
            \App\Models\FoodServiceRecipe::recalculateForItems([$fsItem->id]);
        }
        Cache::flush();

        return response()->json(['data' => $fsItem->fresh()]);
    }
}
