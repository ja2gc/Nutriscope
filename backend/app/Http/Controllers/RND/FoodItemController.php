<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFoodItemRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FoodItemController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FoodItem::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
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
        $data = $request->validated();
        $food = $this->audited(function () use ($data): FoodItem {
            $food = FoodItem::create($data);
            $this->auditLogger->recordMutation(AuditAction::Created, AuditDomain::NutritionLibrary, $food, array_keys($food->getAttributes()));

            return $food;
        });

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
        $data = $request->validated();
        $this->audited(function () use ($foodItem, $data): void {
            $foodItem->update($data);
            $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::NutritionLibrary, $foodItem, array_keys($foodItem->getChanges()));
        });

        return new FoodItemResource($foodItem->fresh());
    }

    public function destroy(FoodItem $foodItem): JsonResponse
    {
        $this->audited(function () use ($foodItem): void {
            $foodItem->delete();
            $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::NutritionLibrary, $foodItem, []);
        });

        return response()->json(null, 204);
    }
}
