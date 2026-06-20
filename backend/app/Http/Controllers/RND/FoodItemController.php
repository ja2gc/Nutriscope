<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFoodItemRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FoodItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FoodItem::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('allergen')) {
            $query->withAllergen($request->allergen);
        }

        $items = $query->orderBy('name')->paginate((int) min($request->query('per_page', 15), 100));

        return FoodItemResource::collection($items);
    }

    public function store(StoreFoodItemRequest $request): JsonResponse
    {
        $food = FoodItem::create($request->validated());

        return (new FoodItemResource($food))
            ->response()
            ->setStatusCode(201);
    }

    public function show(FoodItem $foodItem): FoodItemResource
    {
        return new FoodItemResource($foodItem);
    }

    public function update(StoreFoodItemRequest $request, FoodItem $foodItem): FoodItemResource
    {
        $foodItem->update($request->validated());

        return new FoodItemResource($foodItem->fresh());
    }

    public function destroy(FoodItem $foodItem): JsonResponse
    {
        $foodItem->delete();

        return response()->json(null, 204);
    }
}
