<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FoodServiceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodServiceSettingController extends Controller
{
    /** Shared Food Service settings (per-head/day budget limit). */
    public function show(): JsonResponse
    {
        $setting = FoodServiceSetting::singleton();

        return response()->json(['data' => [
            'per_head_day_limit' => $setting->per_head_day_limit,
            'updated_by'         => $setting->updatedBy?->name,
            'updated_at'         => $setting->updated_at?->toDateTimeString(),
        ]]);
    }

    /** RND/Admin sets the budget per head per day. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_head_day_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $setting = FoodServiceSetting::singleton();
        $setting->update([
            'per_head_day_limit' => $data['per_head_day_limit'] ?? null,
            'updated_by'         => Auth::id(),
        ]);

        return response()->json(['data' => [
            'per_head_day_limit' => $setting->per_head_day_limit,
            'updated_by'         => $setting->updatedBy?->name,
            'updated_at'         => $setting->updated_at?->toDateTimeString(),
        ]]);
    }
}
