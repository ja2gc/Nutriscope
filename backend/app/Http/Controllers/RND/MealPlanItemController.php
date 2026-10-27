<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMealPlanItemRequest;
use App\Http\Resources\MealPlanItemResource;
use App\Models\Assessment;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Recipe;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MealPlanItemController extends Controller
{
    public function __construct(
        private AuditPolicy $auditPolicy,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * MP-04: enforce the nested ownership chain ncpRecord → intervention →
     * mealPlan → day → item. Route-model binding resolves each id independently,
     * so without this a valid id from another patient/plan would be accepted.
     */
    private function assertScope(NcpRecord $ncpRecord, MealPlan $mealPlan, ?MealPlanDay $day = null, ?MealPlanItem $item = null): void
    {
        abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
        if (! $mealPlan->intervention()
            ->where('ncp_record_id', $ncpRecord->id)
            ->exists()) {
            abort(404);
        }
        if ($day && $day->meal_plan_id !== $mealPlan->id) {
            abort(404);
        }
        if ($item && $day && $item->meal_plan_day_id !== $day->id) {
            abort(404);
        }
        if (! request()->isMethodSafe()) {
            abort_unless((int) $ncpRecord->latestIntervention()->value('id') === $mealPlan->intervention_id, 403, 'Historical intervention menus are read-only.');
            abort_if($mealPlan->status === 'completed', 403, 'Completed menus are read-only.');
        }
    }

    /** GET all items for a plan in one request */
    public function allItems(NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan);
        $dayIds = $mealPlan->days()->pluck('id');
        // Eager-load day/recipe/food-item so the resource can expose their public uuids
        // (used for the day lookup and the recipe-detail fetch) without an N+1.
        $items = MealPlanItem::whereIn('meal_plan_day_id', $dayIds)
            ->with('mealPlanDay:id,uuid', 'recipe:id,uuid,servings', 'recipe.ingredients:id,recipe_id,food_item_id,quantity,unit', 'recipe.ingredients.foodItem:id,name', 'foodItem:id,uuid')
            ->get();

        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function index(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day);
        $items = MealPlanItem::where('meal_plan_day_id', $day->id)
            ->with('mealPlanDay:id,uuid', 'recipe:id,uuid,servings', 'recipe.ingredients:id,recipe_id,food_item_id,quantity,unit', 'recipe.ingredients.foodItem:id,name', 'foodItem:id,uuid')
            ->get();

        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    public function store(StoreMealPlanItemRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day);
        if ($request->filled('recipe_id') && ! Recipe::with('ingredients')->findOrFail($request->recipe_id)->hasValidPortionData()) {
            return response()->json([
                'message' => 'Check the recipe yield and ingredient units before adding it to a meal plan.',
            ], 422);
        }
        // MP-03 / AD-04: hard-block allergen conflicts; warn on dislikes/restrictions.
        [$itemName, $itemAllergens] = $this->describeItem($request);
        $assessment = $ncpRecord->assessment;

        $patientAllergens = $this->lc($assessment?->allergies ?? []);
        $conflicts = array_values(array_intersect($this->lc($itemAllergens), $patientAllergens));
        if (! empty($conflicts)) {
            return response()->json([
                'message' => 'This item contains an allergen the patient is allergic to: '
                    .implode(', ', $conflicts).'. It cannot be added.',
                'errors' => ['allergens' => $conflicts],
            ], 422);
        }

        $snapshot = $this->buildSnapshot($request);
        $item = $this->audited(function () use ($request, $day, $snapshot, $mealPlan, $ncpRecord) {
            $item = MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'recipe_id' => $request->input('recipe_id'),
                'quantity' => $request->quantity,
                'unit' => $request->unit,
                'nutrient_snapshot' => $snapshot,
            ]);
            $this->markPlanForRescaling($mealPlan);
            $this->recordMutation(AuditAction::Created, $mealPlan, $ncpRecord, ['meal_plan_item'], 201);

            return $item;
        });
        $item->load('recipe.ingredients.foodItem');

        $warnings = $this->softWarnings($itemName, $assessment);

        return response()->json(array_filter([
            'data' => new MealPlanItemResource($item),
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
     * Resolve the Menu Item's name and allergens from every measured Ingredient.
     *
     * @return array{0:string,1:array<int,string>}
     */
    private function describeItem(StoreMealPlanItemRequest $request): array
    {
        $recipe = Recipe::with('ingredients.foodItem')->findOrFail($request->input('recipe_id'));
        $allergens = [];
        foreach ($recipe->ingredients as $ing) {
            $a = $ing->foodItem?->allergens ?? [];
            if (is_array($a)) {
                $allergens = array_merge($allergens, $a);
            }
        }

        return [$recipe->name, array_values(array_unique($allergens))];
    }

    /**
     * Non-blocking warnings: the item matches a recorded food dislike, or the
     * patient has dietary restrictions the RND should verify against.
     *
     * @return array<int,string>
     */
    private function softWarnings(string $itemName, ?Assessment $assessment): array
    {
        if (! $assessment || $itemName === '') {
            return [];
        }

        $warnings = [];
        $name = strtolower($itemName);

        foreach ($this->lc($assessment->food_dislikes ?? []) as $dislike) {
            if ($dislike !== '' && str_contains($name, $dislike)) {
                $warnings[] = "Patient reported disliking \"{$dislike}\" — confirm before serving.";
            }
        }

        if (filled($assessment->dietary_restrictions)) {
            $warnings[] = 'Patient has dietary restrictions ("'.$assessment->dietary_restrictions
                .'") — verify this item complies.';
        }

        return $warnings;
    }

    public function update(Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day, $item);
        // DI-03: never accept a client-supplied nutrient snapshot — it would let a
        // payload falsify the nutrient totals that drive reports. Edits may adjust
        // quantity/unit, and ingredient quantities for a recipe item; in the latter
        // case the snapshot is RE-COMPUTED server-side from trusted food data.
        if (is_array($request->input('ingredient_overrides'))) {
            $request->merge(['ingredient_overrides' => array_map(function ($override) {
                if (is_array($override) && is_float($override['quantity'] ?? null) && round($override['quantity'], 6) === $override['quantity']) {
                    $override['quantity'] = number_format($override['quantity'], 6, '.', '');
                }

                return $override;
            }, $request->input('ingredient_overrides'))]);
        }
        $validated = $request->validate([
            'quantity' => 'sometimes|numeric|min:0.01',
            'unit' => 'sometimes|string|max:50',
            'ingredient_overrides' => 'sometimes|array',
            'ingredient_overrides.*.id' => 'required_with:ingredient_overrides|integer',
            'ingredient_overrides.*.quantity' => 'required_with:ingredient_overrides|numeric|gt:0|decimal:0,6',
            'ingredients' => ['sometimes', 'array', 'min:1'],
            'ingredients.*.food_item_id' => ['required', 'uuid', 'exists:food_items,uuid'],
            'ingredients.*.quantity' => ['required', 'numeric', 'between:0.000001,100000'],
            'ingredients.*.unit' => ['required', 'string', 'max:50'],
        ]);

        $original = $item->nutrient_snapshot['recipe_reset_baseline'] ?? $item->nutrient_snapshot;
        $originalQuantity = $item->nutrient_snapshot['recipe_reset_quantity'] ?? (float) $item->quantity;
        if (isset($validated['ingredients'])) {
            abort_unless($item->recipe_id, 422, 'Only recipe Menu Items have editable Ingredients.');
            try {
                $item->nutrient_snapshot = $item->recipe->planSnapshot([], $item->nutrient_snapshot, $validated['ingredients']);
            } catch (\InvalidArgumentException $error) {
                throw ValidationException::withMessages(['ingredients' => $error->getMessage()]);
            }
        } elseif (! empty($validated['ingredient_overrides'])) {
            $snapshot = $this->recomputeRecipeSnapshot($item, $validated['ingredient_overrides']);
            if ($snapshot !== null) {
                $item->nutrient_snapshot = $snapshot;
            }
        }

        if ($item->recipe_id && $original) {
            $item->nutrient_snapshot = [...$item->nutrient_snapshot, 'recipe_reset_baseline' => $original, 'recipe_reset_quantity' => $originalQuantity];
        }
        $this->audited(function () use ($item, $validated, $mealPlan, $ncpRecord): void {
            $item->fill(array_intersect_key($validated, array_flip(['quantity', 'unit'])));
            $item->save();
            $this->markPlanForRescaling($mealPlan);
            $this->recordMutation(AuditAction::Updated, $mealPlan, $ncpRecord, array_keys($validated));
        });

        return response()->json(['data' => new MealPlanItemResource($item->load('recipe.ingredients.foodItem'))]);
    }

    /**
     * Recompute a plan item's nutrient snapshot from trusted recipe ingredient data.
     *
     * @param  array<int,array{id:int,quantity:float|int}>  $overrides
     */
    private function recomputeRecipeSnapshot(MealPlanItem $item, array $overrides): ?array
    {
        if (! $item->recipe_id) {
            return null;
        }
        $recipe = Recipe::with('ingredients.foodItem')->find($item->recipe_id);
        if (! $recipe) {
            throw ValidationException::withMessages(['ingredient_overrides' => 'Correct the Menu Item yield and measured Ingredient units before editing this portion.']);
        }

        try {
            $snapshot = $recipe->planSnapshot($overrides, $item->nutrient_snapshot);

            return [
                ...$snapshot,
                'name' => $item->nutrient_snapshot['name'] ?? $recipe->name,
            ];
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ingredient_overrides' => $exception->getMessage()]);
        }
    }

    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $this->assertScope($ncpRecord, $mealPlan, $day, $item);
        $this->audited(function () use ($item, $mealPlan, $ncpRecord): void {
            $item->delete();
            $this->markPlanForRescaling($mealPlan);
            $this->recordMutation(AuditAction::Deleted, $mealPlan, $ncpRecord, ['meal_plan_item'], 204);
        });

        return response()->json(null, 204);
    }

    /** @param array<int, string> $fields */
    private function recordMutation(AuditAction $action, MealPlan $mealPlan, NcpRecord $ncpRecord, array $fields, int $status = 200): void
    {
        $this->auditLogger->record(
            $action,
            AuditCategory::Clinical,
            AuditDomain::Ncp,
            subject: $mealPlan,
            context: $ncpRecord,
            details: ['fields' => $fields, 'status' => $status],
        );
    }

    private function markPlanForRescaling(MealPlan $mealPlan): void
    {
        $mealPlan->updateQuietly(['needs_rescaling' => true, 'scaled_at' => null]);
    }

    private function buildSnapshot(StoreMealPlanItemRequest $request): array
    {
        $recipe = Recipe::findOrFail($request->input('recipe_id'));

        return $recipe->planSnapshot();
    }
}
