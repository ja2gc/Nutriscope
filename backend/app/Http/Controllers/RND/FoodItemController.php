<?php

namespace App\Http\Controllers\RND;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\StoreFoodItemRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\FoodItemAuditValues;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Support\Search\RankedSearch;
use App\Support\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class FoodItemController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FoodItemAuditValues $auditValues,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $query = FoodItem::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('allergen')) {
            $query->withAllergen($request->allergen);
        }

        RankedSearch::apply($query, $request->string('search')->toString(), ['name']);

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

    public function update(StoreFoodItemRequest $request, FoodItem $foodItem): FoodItemResource|JsonResponse
    {
        $data = $request->validated();
        $targetUnit = UnitConverter::canonical((string) ($data['serving_unit'] ?? $foodItem->serving_unit));
        $unitChanged = $targetUnit !== UnitConverter::canonical((string) $foodItem->serving_unit);
        $basisTouched = array_intersect(array_keys($data), ['serving_size', 'serving_unit', 'calories', 'protein', 'carbs', 'fat', 'water_g', 'micronutrients']) !== [];
        $rows = $foodItem->recipeIngredients()->with('recipe')->orderBy('recipe_id')->orderBy('id')->get();
        $affected = [];
        $affectedRows = [];
        $lineIndices = [];
        foreach ($rows as $row) {
            $index = $lineIndices[$row->recipe_id] ?? 0;
            $lineIndices[$row->recipe_id] = $index + 1;
            if (! $basisTouched || ((float) $row->quantity > 0 && UnitConverter::isCompatible(UnitConverter::canonical((string) $row->unit), $targetUnit))) {
                continue;
            }
            $key = $row->recipe->uuid.':'.$index;
            $affectedRows[$key] = $row;
            $affected[] = ['recipe_id' => $row->recipe->uuid, 'recipe_name' => $row->recipe->name, 'line_index' => $index, 'quantity' => $row->quantity, 'unit' => $row->unit];
        }

        if ($unitChanged && UnitConverter::isCompatible((string) $foodItem->serving_unit, $targetUnit)) {
            $converted = UnitConverter::convert((float) $foodItem->serving_size, (string) $foodItem->serving_unit, $targetUnit);
            $size = round($converted, 6);
            $restored = UnitConverter::convert($size, $targetUnit, (string) $foodItem->serving_unit);
            if ($size <= 0 || $size > 10000 || round($restored, 6) !== round((float) $foodItem->serving_size, 6)) {
                return response()->json(['message' => 'This unit change cannot preserve the measured baseline at six decimals. Choose another compatible unit.', 'errors' => ['serving_unit' => ['The measured baseline would lose precision.']]], 422);
            }
            if (isset($data['serving_size']) && (float) $data['serving_size'] !== $size) {
                return response()->json(['message' => "The converted baseline is {$size} {$targetUnit}.", 'errors' => ['serving_size' => ['Use the automatically converted baseline for this unit change.']]], 422);
            }
            $data['serving_size'] = $size;
        } elseif ($unitChanged && ! array_key_exists('serving_size', $data)) {
            return response()->json(['message' => 'Enter a measured nutrient baseline for the new unit family.', 'errors' => ['serving_size' => ['A measured baseline is required.']], 'affected_recipes' => $affected], 422);
        }

        $corrections = [];
        foreach ($data['recipe_corrections'] ?? [] as $correction) {
            $key = $correction['recipe_id'].':'.$correction['line_index'];
            if (isset($corrections[$key]) || ! isset($affectedRows[$key]) || ! UnitConverter::isCompatible($correction['unit'], $targetUnit)) {
                return response()->json(['message' => 'Enter one measured quantity and compatible unit for every affected Menu Item.', 'affected_recipes' => $affected], 422);
            }
            $corrections[$key] = $correction;
        }
        if (count($corrections) !== count($affectedRows)) {
            return response()->json(['message' => 'Correct the affected Menu Items before changing this Ingredient baseline.', 'affected_recipes' => $affected], 422);
        }
        unset($data['recipe_corrections']);

        try {
            $this->audited(function () use ($foodItem, $data, $rows, $basisTouched, $affectedRows, $corrections): void {
                $locked = FoodItem::query()->whereKey($foodItem->id)->lockForUpdate()->firstOrFail();
                if ($locked->serving_size !== $foodItem->serving_size || $locked->serving_unit !== $foodItem->serving_unit) {
                    abort(409, 'This Ingredient changed. Reload it before saving the baseline.');
                }
                $correctedRecipes = collect($affectedRows)->map(fn ($row) => $row->recipe)->unique('id');
                $recipeBefore = $correctedRecipes->mapWithKeys(fn (Recipe $recipe): array => [$recipe->id => $this->revisionRegistry->capture($recipe->fresh('ingredients.foodItem'))]);
                $before = $this->auditValues->values($foodItem);
                $foodItem->update($data);
                foreach ($affectedRows as $key => $row) {
                    $row->update(['quantity' => $corrections[$key]['quantity'], 'unit' => $corrections[$key]['unit']]);
                }
                if ($basisTouched) {
                    foreach ($rows->map(fn ($row) => $row->recipe)->unique('id') as $recipe) {
                        $recipe->recalculateTotals();
                    }
                }
                foreach ($correctedRecipes as $recipe) {
                    $activity = $this->auditLogger->recordMutation(AuditAction::Updated, AuditDomain::NutritionLibrary, $recipe, ['ingredients'], ['entity_name' => $recipe->name]);
                    if ($activity !== null) {
                        $this->revisionWriter->write($activity, $recipeBefore[$recipe->id], $this->revisionRegistry->capture($recipe->fresh('ingredients.foodItem')));
                    }
                }
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
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['serving_unit' => ['Correct the linked Menu Item units before saving.']]], 422);
        }

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
