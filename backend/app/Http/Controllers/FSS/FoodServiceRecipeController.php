<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Support\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FoodServiceRecipeController extends Controller
{
    public function index(): JsonResponse
    {
        $recipes = FoodServiceRecipe::orderBy('name')->get(['id', 'name', 'category', 'servings', 'created_at']);
        return response()->json(['data' => $recipes]);
    }

    /**
     * An ingredient quantity can only be costed if its unit is the same dimension
     * as the item's base_unit (mass↔mass, volume↔volume) or exactly equal. Count
     * units (pc/pack) must match a count base exactly — never cross to mass/volume.
     */
    public static function unitCompatible(string $ingredientUnit, string $baseUnit): bool
    {
        $a = UnitConverter::normalize($ingredientUnit);
        $b = UnitConverter::normalize($baseUnit);
        if ($a === '' || $b === '' || $a === $b) {
            return true;
        }
        return UnitConverter::isKnown($a) && UnitConverter::isKnown($b);
    }

    /** Reject any ingredient whose unit can't be costed against its item's base_unit. */
    private function assertIngredientUnits(array $ingredients): void
    {
        foreach ($ingredients as $ing) {
            $base = FsItem::whereKey($ing['fs_item_id'])->value('base_unit');
            if ($base && ! self::unitCompatible($ing['unit'] ?? $base, $base)) {
                abort(422, "Ingredient unit '" . ($ing['unit'] ?? '') . "' is not compatible with base unit '{$base}' for item #{$ing['fs_item_id']}.");
            }
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:food_service_recipes,name'],
            'category'    => ['nullable', 'string', 'max:100'],
            'prep_notes'  => ['nullable', 'string'],
            'servings'    => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.fs_item_id' => ['required', 'integer', 'exists:fs_items,id'],
            'ingredients.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'ingredients.*.unit'       => ['nullable', 'string'],
        ]);

        $this->assertIngredientUnits($data['ingredients']);

        $recipe = DB::transaction(function () use ($data) {
            $recipe = FoodServiceRecipe::create([
                'rnd_user_id' => Auth::id(),
                'name'        => $data['name'],
                'category'    => $data['category'] ?? null,
                'prep_notes'  => $data['prep_notes'] ?? null,
                'servings'    => $data['servings'] ?? 1,
            ]);

            foreach ($data['ingredients'] as $ing) {
                FoodServiceRecipeIngredient::create([
                    'food_service_recipe_id' => $recipe->id,
                    'fs_item_id'             => $ing['fs_item_id'],
                    'quantity'               => $ing['quantity'],
                    'unit'                   => $ing['unit'] ?? 'g',
                ]);
            }

            $recipe->recalculateCost();
            return $recipe;
        });

        return response()->json(['data' => $this->formatRecipe($recipe)], 201);
    }

    public function show(FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        return response()->json(['data' => $this->formatRecipe($foodServiceRecipe)]);
    }

    /**
     * Cost profile scaled to a headcount — per-ingredient scaled quantity + cost,
     * recipe total, and cost-per-head. Shown when a planner clicks a menu cell to see
     * what that recipe costs for that day's estimated population.
     */
    public function profile(Request $request, FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $population = max(0, (int) $request->query('population', 0));

        return response()->json([
            'data' => \App\Services\MenuCycleCostService::recipeProfile($foodServiceRecipe, $population),
        ]);
    }

    public function update(Request $request, FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', 'unique:food_service_recipes,name,' . $foodServiceRecipe->id],
            'category'    => ['nullable', 'string', 'max:100'],
            'prep_notes'  => ['nullable', 'string'],
            'servings'    => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['sometimes', 'array', 'min:1'],
            'ingredients.*.fs_item_id' => ['required_with:ingredients', 'integer', 'exists:fs_items,id'],
            'ingredients.*.quantity'   => ['required_with:ingredients', 'numeric', 'min:0.01'],
            'ingredients.*.unit'       => ['nullable', 'string'],
        ]);

        if (isset($data['ingredients'])) {
            $this->assertIngredientUnits($data['ingredients']);
        }

        DB::transaction(function () use ($data, $foodServiceRecipe) {
            $foodServiceRecipe->update(array_filter([
                'name'       => $data['name'] ?? null,
                'category'   => $data['category'] ?? null,
                'prep_notes' => $data['prep_notes'] ?? null,
                'servings'   => $data['servings'] ?? null,
            ], fn($v) => $v !== null));

            if (isset($data['ingredients'])) {
                $foodServiceRecipe->ingredients()->delete();
                foreach ($data['ingredients'] as $ing) {
                    FoodServiceRecipeIngredient::create([
                        'food_service_recipe_id' => $foodServiceRecipe->id,
                        'fs_item_id'             => $ing['fs_item_id'],
                        'quantity'               => $ing['quantity'],
                        'unit'                   => $ing['unit'] ?? 'g',
                    ]);
                }
            }

            $foodServiceRecipe->recalculateCost();
        });

        return response()->json(['data' => $this->formatRecipe($foodServiceRecipe->fresh())]);
    }

    public function destroy(FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $usedBy = \App\Models\MenuCycleDay::where('recipe_id', $foodServiceRecipe->id)->count();
        if ($usedBy > 0) {
            abort(409, "Can't delete: this recipe is used by {$usedBy} menu-cycle slot(s). Remove it from the cycle(s) first.");
        }

        $foodServiceRecipe->delete();
        return response()->json(null, 204);
    }

    private function formatRecipe(FoodServiceRecipe $recipe): array
    {
        $recipe->loadMissing('ingredients.fsItem');

        return [
            'id'          => $recipe->id,
            'name'        => $recipe->name,
            'category'    => $recipe->category,
            'prep_notes'  => $recipe->prep_notes,
            'servings'    => $recipe->servings,
            'cost'        => (float) $recipe->cost,
            'ingredients' => $recipe->ingredients->map(fn ($ing) => [
                'id'         => $ing->id,
                'fs_item_id' => $ing->fs_item_id,
                'quantity'   => (float) $ing->quantity,
                'unit'       => $ing->unit,
                'fs_item'    => $ing->fsItem ? [
                    'id'        => $ing->fsItem->id,
                    'name'      => $ing->fsItem->name,
                    'unit_cost' => $ing->fsItem->unit_cost,
                    'base_unit' => $ing->fsItem->base_unit,
                ] : null,
            ])->values(),
            'created_at'  => $recipe->created_at,
            'updated_at'  => $recipe->updated_at,
        ];
    }
}
