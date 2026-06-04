<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Recipe::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $recipes = $query->orderBy('name')->paginate(20);

        return RecipeResource::collection($recipes);
    }

    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $recipe = Recipe::create([
            'rnd_user_id' => $request->user()->id,
            'name'        => $data['name'],
            'category'    => $data['category'] ?? null,
            'prep_notes'  => $data['prep_notes'] ?? null,
            'servings'    => $data['servings'] ?? 1,
        ]);

        $this->syncIngredients($recipe, $data['ingredients'] ?? []);
        $recipe->recalculateTotals();

        $recipe->load('ingredients.foodItem');

        return (new RecipeResource($recipe))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Recipe $recipe): RecipeResource
    {
        $recipe->load('ingredients.foodItem');

        return new RecipeResource($recipe);
    }

    public function update(StoreRecipeRequest $request, Recipe $recipe): RecipeResource
    {
        $data = $request->validated();

        $recipe->update(array_filter([
            'name'       => $data['name'] ?? $recipe->name,
            'category'   => $data['category'] ?? $recipe->category,
            'prep_notes' => $data['prep_notes'] ?? $recipe->prep_notes,
            'servings'   => $data['servings'] ?? $recipe->servings,
        ], fn($v) => $v !== null));

        if (array_key_exists('ingredients', $data)) {
            $this->syncIngredients($recipe, $data['ingredients']);
            $recipe->recalculateTotals();
        }

        $recipe->load('ingredients.foodItem');

        return new RecipeResource($recipe->fresh(['ingredients.foodItem']));
    }

    public function destroy(Recipe $recipe): JsonResponse
    {
        $recipe->delete();

        return response()->json(null, 204);
    }

    private function syncIngredients(Recipe $recipe, array $ingredients): void
    {
        $recipe->ingredients()->delete();

        foreach ($ingredients as $ing) {
            RecipeIngredient::create([
                'recipe_id'    => $recipe->id,
                'food_item_id' => $ing['food_item_id'],
                'quantity'     => $ing['quantity'],
                'unit'         => $ing['unit'],
            ]);
        }
    }
}
