<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMealPlanItemRequest;
use App\Http\Resources\MealPlanItemResource;
use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;
use App\Services\UsdaService;
use Illuminate\Http\JsonResponse;

class MealPlanItemController extends Controller
{
    public function __construct(private UsdaService $usdaService) {}

    /** GET all items for a plan in one request */
    public function allItems(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $dayIds = $mealPlan->days()->pluck('id');
        $items  = MealPlanItem::whereIn('meal_plan_day_id', $dayIds)->get();
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function index(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $items = MealPlanItem::where('meal_plan_day_id', $day->id)->get();
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function store(StoreMealPlanItemRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $item = MealPlanItem::create([
            'meal_plan_day_id'  => $day->id,
            'food_item_id'      => $request->input('food_item_id'),
            'fdc_id'            => $request->input('fdc_id'),
            'recipe_id'         => $request->input('recipe_id'),
            'quantity'          => $request->quantity,
            'unit'              => $request->unit,
            'nutrient_snapshot' => $this->buildSnapshot($request),
        ]);

        return response()->json(['data' => new MealPlanItemResource($item)], 201);
    }

    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(null, 204);
    }

    private function buildSnapshot(StoreMealPlanItemRequest $request): array
    {
        if ($request->filled('fdc_id')) {
            $data = $this->usdaService->fetch((int) $request->input('fdc_id'));
            return array_merge($data, ['serving_size' => 100, 'serving_unit' => 'g']);
        }

        if ($request->filled('recipe_id')) {
            $recipe = Recipe::findOrFail($request->input('recipe_id'));
            return [
                'name'           => $recipe->name,
                'calories'       => (float) $recipe->total_calories,
                'protein'        => (float) $recipe->total_protein,
                'carbs'          => (float) $recipe->total_carbs,
                'fat'            => (float) $recipe->total_fat,
                'micronutrients' => $recipe->micronutrients ?? [],
                'serving_size'   => (float) ($recipe->servings ?? 1),
                'serving_unit'   => 'serving',
            ];
        }

        $food = FoodItem::findOrFail($request->input('food_item_id'));
        return [
            'fdc_id'         => $food->usda_fdc_id,
            'name'           => $food->name,
            'calories'       => (float) $food->calories,
            'protein'        => (float) $food->protein,
            'carbs'          => (float) $food->carbs,
            'fat'            => (float) $food->fat,
            'micronutrients' => $food->micronutrients ?? [],
            'serving_size'   => (float) ($food->serving_size ?? 100),
            'serving_unit'   => $food->serving_unit ?? 'g',
        ];
    }
}
