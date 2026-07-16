<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\StoreFoodItemRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodItem;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\FoodItemAuditValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FoodItemController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FoodItemAuditValues $auditValues,
    ) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
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

        $items = $query->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();

        return FoodItemResource::collection($items);
    }

    public function store(StoreFoodItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $food = $this->audited(function () use ($data): FoodItem {
            $food = FoodItem::create($data);
            $values = $this->auditValues->values($food);
            $fields = array_keys(array_filter($values, fn (mixed $value): bool => $value !== null));
            $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::NutritionLibrary,
                $food,
                $fields,
                ['source' => $this->auditValues->source($food), 'entity_name' => $food->name],
                oldValues: array_fill_keys(array_keys($values), null),
                newValues: $values,
            );

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
            $before = $this->auditValues->values($foodItem);
            $foodItem->update($data);
            $changedFields = array_keys($foodItem->getChanges());
            $safeChangedFields = array_intersect(array_keys($before), $changedFields);
            $safeFieldMap = array_flip($safeChangedFields);
            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::NutritionLibrary,
                $foodItem,
                $changedFields,
                ['source' => $this->auditValues->source($foodItem), 'entity_name' => $foodItem->name],
                oldValues: array_intersect_key($before, $safeFieldMap),
                newValues: array_intersect_key($this->auditValues->values($foodItem), $safeFieldMap),
            );
        });

        return new FoodItemResource($foodItem->fresh());
    }

    public function destroy(FoodItem $foodItem): JsonResponse
    {
        $this->audited(function () use ($foodItem): void {
            $before = $this->auditValues->values($foodItem);
            $fields = array_keys(array_filter($before, fn (mixed $value): bool => $value !== null));
            $foodItem->delete();
            $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::NutritionLibrary,
                $foodItem,
                $fields,
                ['source' => $this->auditValues->source($foodItem), 'entity_name' => $foodItem->name],
                oldValues: $before,
                newValues: array_fill_keys(array_keys($before), null),
            );
        });

        return response()->json(null, 204);
    }
}
