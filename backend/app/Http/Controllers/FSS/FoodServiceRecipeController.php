<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycleDay;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Services\MenuCycleCostService;
use App\Support\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodServiceRecipeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min($request->query('per_page', 15), 100);
        $search = $request->query('search');
        $category = $request->query('category');

        $paginator = FoodServiceRecipe::orderBy('name')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->paginate($perPage, ['id', 'uuid', 'name', 'category', 'servings', 'created_at']);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($r) => [
                'id' => $r->uuid, 'name' => $r->name, 'category' => $r->category,
                'servings' => $r->servings, 'created_at' => $r->created_at,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
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

    /**
     * Block bundle/non-convertible units only when the catalog base unit is itself
     * convertible (mass/volume). When the base unit is an outlier (pc, pack, etc.)
     * any unit is allowed — those are surfaced as warnings, not hard errors.
     */
    /**
     * Frontend ingredient pickers submit each fs_item's public uuid (its Resource 'id').
     * Resolve those back to the internal integer FK the persistence + unit checks expect.
     */
    private function resolveIngredientFsItemIds(array $ingredients): array
    {
        return array_map(function ($ing) {
            if (isset($ing['fs_item_id'])) {
                $ing['fs_item_id'] = FsItem::idFromUuid($ing['fs_item_id']);
            }

            return $ing;
        }, $ingredients);
    }

    private function assertIngredientUnits(array $ingredients): void
    {
        foreach ($ingredients as $ing) {
            $base = FsItem::whereKey($ing['fs_item_id'])->value('base_unit');
            if (! $base) {
                continue;
            }
            $unit = $ing['unit'] ?? $base;
            // Catalog unit is convertible but the recipe unit is not -> reject.
            if (UnitConverter::isKnown($base) && ! UnitConverter::isKnown($unit)
                && UnitConverter::normalize($unit) !== UnitConverter::normalize($base)) {
                abort(422, "Ingredient unit '{$unit}' can't convert to catalog unit '{$base}' for item #{$ing['fs_item_id']}. Use a mass or volume unit.");
            }
        }
    }

    /**
     * Per-ingredient conversion metadata for the recipe response: whether the recipe
     * unit converts to the item's catalog base unit, the converted quantity and
     * unit cost (max 2 decimals), and a warning for non-convertible outliers.
     *
     * @return array{is_convertible:bool,catalog_unit:string|null,converted_quantity:float|null,converted_unit_cost:float|null,conversion_warning:string|null}
     */
    private static function conversionInfo(?FsItem $item, float $quantity, ?string $unit): array
    {
        $baseUnit = $item?->base_unit;
        $unit = $unit ?: $baseUnit;

        if (! $item || ! $baseUnit || $unit === null) {
            return [
                'is_convertible' => false,
                'catalog_unit' => $baseUnit,
                'converted_quantity' => null,
                'converted_unit_cost' => null,
                'conversion_warning' => null,
            ];
        }

        $sameUnit = UnitConverter::normalize($unit) === UnitConverter::normalize($baseUnit);

        if ($sameUnit) {
            return [
                'is_convertible' => true,
                'catalog_unit' => $baseUnit,
                'converted_quantity' => round($quantity, 2),
                'converted_unit_cost' => round($item->unit_cost, 2),
                'conversion_warning' => null,
            ];
        }

        if (UnitConverter::isKnown($unit) && UnitConverter::isKnown($baseUnit)) {
            // Recipe unit -> catalog unit. unit_cost is priced per catalog base unit,
            // so the rate per recipe unit converts inversely.
            $qtyInBase = UnitConverter::convert($quantity, $unit, $baseUnit);
            $basePerUnit = UnitConverter::convert(1, $unit, $baseUnit);

            return [
                'is_convertible' => true,
                'catalog_unit' => $baseUnit,
                'converted_quantity' => round($qtyInBase, 2),
                'converted_unit_cost' => round($item->unit_cost * $basePerUnit, 2),
                'conversion_warning' => null,
            ];
        }

        // Non-convertible outlier — allowed, but warn the planner.
        return [
            'is_convertible' => false,
            'catalog_unit' => $baseUnit,
            'converted_quantity' => null,
            'converted_unit_cost' => null,
            'conversion_warning' => "Unit '{$unit}' can't be converted to catalog unit '{$baseUnit}'; cost uses the entered unit as-is.",
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:food_service_recipes,name'],
            'category' => ['nullable', 'string', 'max:100'],
            'prep_notes' => ['nullable', 'string'],
            'servings' => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.fs_item_id' => ['required', 'string', 'exists:fs_items,uuid'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'ingredients.*.unit' => ['nullable', 'string'],
        ]);

        $data['ingredients'] = $this->resolveIngredientFsItemIds($data['ingredients']);
        $this->assertIngredientUnits($data['ingredients']);

        $recipe = $this->audited(function () use ($data): FoodServiceRecipe {
            $recipe = $this->auditLogger->withoutModelEvents(function () use ($data): FoodServiceRecipe {
                $recipe = FoodServiceRecipe::create([
                    'rnd_user_id' => Auth::id(),
                    'name' => $data['name'],
                    'category' => $data['category'] ?? null,
                    'prep_notes' => $data['prep_notes'] ?? null,
                    'servings' => $data['servings'] ?? 1,
                ]);
                $this->syncIngredients($recipe, $data['ingredients']);
                $recipe->recalculateCost();

                return $recipe->fresh(['ingredients.fsItem']);
            });
            $newValues = $this->auditValues($recipe);
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::FoodService,
                $recipe,
                [...array_keys($newValues), 'ingredients'],
                newValues: $newValues,
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, null, $this->revisionRegistry->capture($recipe));
            }

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
            'data' => MenuCycleCostService::recipeProfile($foodServiceRecipe, $population),
        ]);
    }

    public function update(Request $request, FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:food_service_recipes,name,'.$foodServiceRecipe->id],
            'category' => ['nullable', 'string', 'max:100'],
            'prep_notes' => ['nullable', 'string'],
            'servings' => ['nullable', 'integer', 'min:1'],
            'ingredients' => ['sometimes', 'array', 'min:1'],
            'ingredients.*.fs_item_id' => ['required_with:ingredients', 'string', 'exists:fs_items,uuid'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'numeric', 'min:0.01'],
            'ingredients.*.unit' => ['nullable', 'string'],
        ]);

        if (isset($data['ingredients'])) {
            $data['ingredients'] = $this->resolveIngredientFsItemIds($data['ingredients']);
            $this->assertIngredientUnits($data['ingredients']);
        }

        $this->audited(function () use ($data, $foodServiceRecipe): void {
            $structural = array_key_exists('ingredients', $data);
            $beforeValues = $this->auditValues($foodServiceRecipe);
            $beforeSignature = $structural ? $this->ingredientSignature($foodServiceRecipe) : [];
            $before = $structural
                ? $this->revisionRegistry->capture($foodServiceRecipe->load('ingredients.fsItem'))
                : null;

            $after = $this->auditLogger->withoutModelEvents(function () use ($data, $foodServiceRecipe, $structural): FoodServiceRecipe {
                $foodServiceRecipe->update(array_filter([
                    'name' => $data['name'] ?? null,
                    'category' => $data['category'] ?? null,
                    'prep_notes' => $data['prep_notes'] ?? null,
                    'servings' => $data['servings'] ?? null,
                ], fn ($value) => $value !== null));

                if ($structural) {
                    $this->syncIngredients($foodServiceRecipe, $data['ingredients']);
                }
                $foodServiceRecipe->recalculateCost();

                return $foodServiceRecipe->fresh(['ingredients.fsItem']);
            });
            $afterValues = $this->auditValues($after);
            $changedFields = $this->changedValueKeys($beforeValues, $afterValues);
            $structureChanged = $structural && $beforeSignature !== $this->ingredientSignature($after);
            if ($structureChanged) {
                $changedFields[] = 'ingredients';
            }
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::FoodService,
                $after,
                $changedFields,
                oldValues: array_intersect_key($beforeValues, array_flip($changedFields)),
                newValues: array_intersect_key($afterValues, array_flip($changedFields)),
            );
            if ($activity !== null && $structureChanged && $before !== null) {
                $this->revisionWriter->write($activity, $before, $this->revisionRegistry->capture($after));
            }
        });

        return response()->json(['data' => $this->formatRecipe($foodServiceRecipe->fresh())]);
    }

    public function destroy(FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $usedBy = MenuCycleDay::where('recipe_id', $foodServiceRecipe->id)->count();
        if ($usedBy > 0) {
            abort(409, "Can't delete: this recipe is used by {$usedBy} menu-cycle slot(s). Remove it from the cycle(s) first.");
        }

        $this->audited(function () use ($foodServiceRecipe): void {
            $before = $this->revisionRegistry->capture($foodServiceRecipe->load('ingredients.fsItem'));
            $oldValues = $this->auditValues($foodServiceRecipe);
            $this->auditLogger->withoutModelEvents(fn () => $foodServiceRecipe->delete());
            $activity = $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::FoodService,
                $foodServiceRecipe,
                [],
                oldValues: $oldValues,
            );
            if ($activity !== null) {
                $this->revisionWriter->write($activity, $before, null);
            }
        });

        return response()->json(null, 204);
    }

    /** @param array<int, array{fs_item_id:int, quantity:mixed, unit?:string|null}> $ingredients */
    private function syncIngredients(FoodServiceRecipe $recipe, array $ingredients): void
    {
        $recipe->ingredients()->delete();
        foreach ($ingredients as $ingredient) {
            FoodServiceRecipeIngredient::create([
                'food_service_recipe_id' => $recipe->id,
                'fs_item_id' => $ingredient['fs_item_id'],
                'quantity' => $ingredient['quantity'],
                'unit' => $ingredient['unit'] ?? 'g',
            ]);
        }
    }

    /** @return array<string, string|int|float|null> */
    private function auditValues(FoodServiceRecipe $recipe): array
    {
        return [
            'name' => (string) $recipe->name,
            'category' => $recipe->category,
            'prep_notes' => $recipe->prep_notes,
            'servings' => (int) $recipe->servings,
            'cost' => (float) $recipe->cost,
        ];
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    private function changedValueKeys(array $before, array $after): array
    {
        return collect($after)
            ->filter(fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value)
            ->keys()
            ->values()
            ->all();
    }

    /** @return list<array{fs_item_id:int, quantity:string, unit:string|null}> */
    private function ingredientSignature(FoodServiceRecipe $recipe): array
    {
        return $recipe->ingredients()->orderBy('fs_item_id')->orderBy('id')
            ->get(['fs_item_id', 'quantity', 'unit'])
            ->map(fn (FoodServiceRecipeIngredient $ingredient): array => [
                'fs_item_id' => (int) $ingredient->fs_item_id,
                'quantity' => (string) $ingredient->quantity,
                'unit' => $ingredient->unit,
            ])
            ->values()
            ->all();
    }

    private function formatRecipe(FoodServiceRecipe $recipe): array
    {
        $recipe->loadMissing('ingredients.fsItem');

        return [
            'id' => $recipe->uuid,
            'name' => $recipe->name,
            'category' => $recipe->category,
            'prep_notes' => $recipe->prep_notes,
            'servings' => $recipe->servings,
            'cost' => round((float) $recipe->cost, 2),
            'ingredients' => $recipe->ingredients->map(function ($ing) {
                $conv = self::conversionInfo($ing->fsItem, (float) $ing->quantity, $ing->unit);

                return [
                    'id' => $ing->id,
                    'fs_item_id' => $ing->fs_item_id,
                    'quantity' => round((float) $ing->quantity, 2),
                    'unit' => $ing->unit,
                    'is_convertible' => $conv['is_convertible'],
                    'catalog_unit' => $conv['catalog_unit'],
                    'converted_quantity' => $conv['converted_quantity'],
                    'converted_unit_cost' => $conv['converted_unit_cost'],
                    'conversion_warning' => $conv['conversion_warning'],
                    'fs_item' => $ing->fsItem ? [
                        'id' => $ing->fsItem->uuid,
                        'name' => $ing->fsItem->name,
                        'unit_cost' => round($ing->fsItem->unit_cost, 2),
                        'base_unit' => $ing->fsItem->base_unit,
                    ] : null,
                ];
            })->values(),
            'created_at' => $recipe->created_at,
            'updated_at' => $recipe->updated_at,
        ];
    }
}
