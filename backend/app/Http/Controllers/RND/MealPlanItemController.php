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

    /**
     * MP-04: enforce the nested ownership chain ncpRecord → intervention →
     * mealPlan → day → item. Route-model binding resolves each id independently,
     * so without this a valid id from another patient/plan would be accepted.
     */
    private function assertScope(NcpRecord $ncpRecord, MealPlan $mealPlan, ?MealPlanDay $day = null, ?MealPlanItem $item = null): void
    {
        if ($mealPlan->intervention_id !== $ncpRecord->intervention?->id) {
            abort(404);
        }
        if ($day && $day->meal_plan_id !== $mealPlan->id) {
            abort(404);
        }
        if ($item && $day && $item->meal_plan_day_id !== $day->id) {
            abort(404);
        }
    }

    /** GET all items for a plan in one request */
    public function allItems(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan);
        $dayIds = $mealPlan->days()->pluck('id');
        $items  = MealPlanItem::whereIn('meal_plan_day_id', $dayIds)->get();
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function index(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day);
        $items = MealPlanItem::where('meal_plan_day_id', $day->id)->get();
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function store(StoreMealPlanItemRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day);
        // MP-03 / AD-04: hard-block allergen conflicts; warn on dislikes/restrictions.
        [$itemName, $itemAllergens] = $this->describeItem($request);
        $assessment = $ncpRecord->assessment;

        $patientAllergens = $this->lc($assessment?->allergies ?? []);
        $conflicts = array_values(array_intersect($this->lc($itemAllergens), $patientAllergens));
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'This item contains an allergen the patient is allergic to: '
                    . implode(', ', $conflicts) . '. It cannot be added.',
                'errors'  => ['allergens' => $conflicts],
            ], 422);
        }

        $item = MealPlanItem::create([
            'meal_plan_day_id'  => $day->id,
            'food_item_id'      => $request->input('food_item_id'),
            'fdc_id'            => $request->input('fdc_id'),
            'recipe_id'         => $request->input('recipe_id'),
            'quantity'          => $request->quantity,
            'unit'              => $request->unit,
            'nutrient_snapshot' => $this->buildSnapshot($request),
        ]);

        $warnings = $this->softWarnings($itemName, $assessment);

        return response()->json(array_filter([
            'data'     => new MealPlanItemResource($item),
            'warnings' => $warnings ?: null,
        ]), 201);
    }

    /** Lowercase + trim a list of strings for case-insensitive comparison. */
    private function lc(array $values): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => strtolower(trim((string) $v)),
            $values
        )));
    }

    /**
     * Resolve the item's display name and declared allergens from its source.
     * USDA (fdc_id) items carry no structured allergen data, so they're treated
     * as allergen-unknown (no hard block possible).
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function describeItem(StoreMealPlanItemRequest $request): array
    {
        if ($request->filled('recipe_id')) {
            $recipe = Recipe::with('ingredients.foodItem')->find($request->input('recipe_id'));
            if (! $recipe) return ['', []];
            $allergens = [];
            foreach ($recipe->ingredients as $ing) {
                $a = $ing->foodItem?->allergens ?? [];
                if (is_array($a)) $allergens = array_merge($allergens, $a);
            }
            return [$recipe->name, array_values(array_unique($allergens))];
        }

        if ($request->filled('food_item_id')) {
            $food = FoodItem::find($request->input('food_item_id'));
            if (! $food) return ['', []];
            return [$food->name, is_array($food->allergens) ? $food->allergens : []];
        }

        return ['', []]; // fdc_id / USDA — allergens unknown
    }

    /**
     * Non-blocking warnings: the item matches a recorded food dislike, or the
     * patient has dietary restrictions the RND should verify against.
     *
     * @return array<int,string>
     */
    private function softWarnings(string $itemName, ?\App\Models\Assessment $assessment): array
    {
        if (! $assessment || $itemName === '') return [];

        $warnings = [];
        $name = strtolower($itemName);

        foreach ($this->lc($assessment->food_dislikes ?? []) as $dislike) {
            if ($dislike !== '' && str_contains($name, $dislike)) {
                $warnings[] = "Patient reported disliking \"{$dislike}\" — confirm before serving.";
            }
        }

        if (filled($assessment->dietary_restrictions)) {
            $warnings[] = 'Patient has dietary restrictions ("' . $assessment->dietary_restrictions
                . '") — verify this item complies.';
        }

        return $warnings;
    }

    public function update(\Illuminate\Http\Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day, $item);
        $validated = $request->validate([
            'quantity'          => 'sometimes|numeric|min:0.01',
            'unit'              => 'sometimes|string|max:50',
            'nutrient_snapshot' => 'sometimes|array',
        ]);

        $item->fill($validated);
        $item->save();

        return response()->json(['data' => new MealPlanItemResource($item)]);
    }

    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day, $item);
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
                'water_g'        => null,
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
            'water_g'        => $food->water_g !== null ? (float) $food->water_g : null,
            'micronutrients' => $food->micronutrients ?? [],
            'serving_size'   => (float) ($food->serving_size ?? 100),
            'serving_unit'   => $food->serving_unit ?? 'g',
        ];
    }
}
