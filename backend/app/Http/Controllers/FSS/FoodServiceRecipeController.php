<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\Inventory;
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:food_service_recipes,name'],
            'category'    => ['nullable', 'string', 'max:100'],
            'prep_notes'  => ['nullable', 'string'],
            'servings'    => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.inventory_id' => ['required', 'integer', 'exists:inventory,id'],
            'ingredients.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'ingredients.*.unit'         => ['nullable', 'string'],
        ]);

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
                    'inventory_id'           => $ing['inventory_id'],
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

    public function update(Request $request, FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255', 'unique:food_service_recipes,name,' . $foodServiceRecipe->id],
            'category'    => ['nullable', 'string', 'max:100'],
            'prep_notes'  => ['nullable', 'string'],
            'servings'    => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['sometimes', 'array', 'min:1'],
            'ingredients.*.inventory_id' => ['required_with:ingredients', 'integer', 'exists:inventory,id'],
            'ingredients.*.quantity'     => ['required_with:ingredients', 'numeric', 'min:0.01'],
            'ingredients.*.unit'         => ['nullable', 'string'],
        ]);

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
                        'inventory_id'           => $ing['inventory_id'],
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
        $foodServiceRecipe->delete();
        return response()->json(null, 204);
    }

    private function formatRecipe(FoodServiceRecipe $recipe): array
    {
        $recipe->loadMissing('ingredients.inventoryItem.foodItem');

        return [
            'id'          => $recipe->id,
            'name'        => $recipe->name,
            'category'    => $recipe->category,
            'prep_notes'  => $recipe->prep_notes,
            'servings'    => $recipe->servings,
            'cost'        => (float) $recipe->cost,
            'ingredients' => $recipe->ingredients->map(fn($ing) => [
                'id'           => $ing->id,
                'inventory_id' => $ing->inventory_id,
                'quantity'     => (float) $ing->quantity,
                'unit'         => $ing->unit,
                'inventory'    => $ing->inventoryItem ? [
                    'id'         => $ing->inventoryItem->id,
                    'name'       => $ing->inventoryItem->foodItem?->name ?? 'Unknown',
                    'unit_price' => (float) ($ing->inventoryItem->unit_price ?? 0),
                    'unit'       => $ing->inventoryItem->unit,
                ] : null,
            ])->values(),
            'created_at'  => $recipe->created_at,
            'updated_at'  => $recipe->updated_at,
        ];
    }
}
