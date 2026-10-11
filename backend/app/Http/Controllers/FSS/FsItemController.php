<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Models\FoodServiceRecipe;
use App\Models\FoodServiceRecipeIngredient;
use App\Models\FsItem;
use App\Models\MenuCycleDay;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\FsItemAuditValues;
use App\Services\Audit\Revisions\AuditRevisionRegistry;
use App\Services\Audit\Revisions\AuditRevisionWriter;
use App\Support\Search\RankedSearch;
use App\Support\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FsItemController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly FsItemAuditValues $auditValues,
        private readonly AuditRevisionRegistry $revisionRegistry,
        private readonly AuditRevisionWriter $revisionWriter,
    ) {}

    /**
     * Ready-to-eat catalog items usable as standalone menu entries (e.g. a banana or
     * Yakult snack placed directly in any meal slot). Raw ingredients and non-food
     * supplies are excluded — those are only used inside recipes.
     */
    public function index(PaginatedRequest $request): JsonResponse
    {
        $query = FsItem::query()
            ->where('is_active', true)
            ->where('kind', 'ingredient');

        RankedSearch::apply($query, $request->string('search')->toString(), ['name']);

        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage(), ['id', 'uuid', 'name', 'category', 'base_unit', 'purchase_price', 'purchase_unit', 'units_per_purchase', 'include_in_generated_lists'])
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (FsItem $i) => [
                'id' => $i->uuid,
                'name' => $i->name,
                'category' => $i->category,
                'unit' => $i->base_unit,
                'unit_cost' => $i->unit_cost,
                'include_in_generated_lists' => $i->include_in_generated_lists,
            ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * The reference catalog — the backend list of ingredients/supplies that recipes
     * and procurement are built from. NOT a stock ledger: items carry no quantity, only
     * how they're bought (unit + cost) and their auto-suggested vendor. Optionally
     * filtered by kind (ingredient | supply | ready_to_eat).
     */
    public function catalog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', 'in:ingredient,supply'],
            'grocery' => ['nullable', 'in:auto,non_auto'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = FsItem::query()
            ->with('defaultSupplier:id,uuid,name')
            ->where('is_active', true)
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($data['grocery'] ?? null, fn ($q, $grocery) => $q->where('include_in_generated_lists', $grocery === 'auto'));

        RankedSearch::apply($query, $data['search'] ?? null, ['name']);
        $query->orderBy('name')->orderBy('id');

        $items = $query->paginate(
            perPage: $data['limit'] ?? 10,
            page: $data['page'] ?? 1,
        );

        return response()->json([
            'data' => collect($items->items())->map(fn (FsItem $i) => $this->catalogRow($i)),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function catalogRow(FsItem $i): array
    {
        return [
            'id' => $i->uuid,
            'name' => $i->name,
            'kind' => $i->kind,
            'category' => $i->category,
            'base_unit' => $i->base_unit,
            'purchase_unit' => $i->purchase_unit,
            'purchase_price' => $i->purchase_price,
            'units_per_purchase' => $i->units_per_purchase,
            'unit_cost' => round($i->unit_cost, 6),
            // Public uuid — the inventory edit form matches this against uuid-valued
            // supplier <option>s (store/update already accept a uuid here).
            'default_supplier_id' => $i->defaultSupplier?->uuid,
            'vendor' => $i->defaultSupplier?->name,
            'include_in_generated_lists' => $i->include_in_generated_lists,
        ];
    }

    /**
     * Create a reference catalog item. Ingredients carry a base/purchase unit and a
     * purchase price (→ unit cost). Supplies have no qty — only a cost per unit — so the
     * base and purchase unit are the same and one purchase unit is one base unit.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:fs_items,name'],
            'kind' => ['required', 'in:ingredient,supply'],
            'category' => ['nullable', 'string', 'max:100'],
            'base_unit' => ['required', 'string', 'max:20'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'default_supplier_id' => ['nullable', 'string', 'exists:suppliers,uuid'],
            'include_in_generated_lists' => ['sometimes', 'boolean'],
        ]);

        if (($data['include_in_generated_lists'] ?? true)
            && UnitConverter::normalize($data['base_unit']) === 'sack') {
            return response()->json(['message' => 'Choose a measured unit for Auto Grocery. Sack has no defined conversion.'], 422);
        }

        if (! empty($data['default_supplier_id'])) {
            $data['default_supplier_id'] = Supplier::idFromUuid($data['default_supplier_id']);
        }

        // Catalog items carry a single unit and a cost per that unit (plan: ingredient =
        // name, category, vendor, unit, unit/cost; supply = name, category, vendor, cost).
        // purchase_unit == base_unit and 1 base unit per purchase ⇒ unit_cost == cost entered.
        $data['purchase_unit'] = $data['base_unit'];
        $data['units_per_purchase'] = 1;

        $item = $this->audited(function () use ($data): FsItem {
            $item = $this->auditLogger->withoutModelEvents(fn (): FsItem => FsItem::create($data + ['is_active' => true]));
            $values = $this->auditValues->values($item);
            $fields = array_keys(array_filter($values, fn (mixed $value): bool => $value !== null));
            $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::FoodService,
                $item,
                $fields,
                ['entity_name' => $item->name],
                oldValues: array_fill_keys(array_keys($values), null),
                newValues: $values,
            );

            return $item;
        });
        Cache::flush();

        return response()->json(['data' => $this->catalogRow($item->fresh('defaultSupplier'))], 201);
    }

    public function update(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:fs_items,name,'.$fsItem->id],
            'category' => ['nullable', 'string', 'max:100'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'base_unit' => ['sometimes', 'string', 'max:20'],
            'default_supplier_id' => ['sometimes', 'nullable', 'string', 'exists:suppliers,uuid'],
            'include_in_generated_lists' => ['sometimes', 'boolean'],
            'recipe_corrections' => ['sometimes', 'array'],
            'recipe_corrections.*.recipe_id' => ['sometimes', 'string', 'exists:food_service_recipes,uuid'],
            'recipe_corrections.*.slot_id' => ['sometimes', 'string', 'exists:menu_cycle_days,uuid'],
            'recipe_corrections.*.line_index' => ['required', 'integer', 'min:0'],
            'recipe_corrections.*.quantity' => ['required', 'numeric', 'between:0.01,100000', 'decimal:0,2'],
            'recipe_corrections.*.unit' => ['required', 'string', 'max:50'],
        ]);

        if (! empty($data['default_supplier_id'])) {
            $data['default_supplier_id'] = Supplier::idFromUuid($data['default_supplier_id']);
        }

        $targetUnit = (string) ($data['base_unit'] ?? $fsItem->base_unit);
        $willBeAuto = (bool) ($data['include_in_generated_lists'] ?? $fsItem->include_in_generated_lists);
        if ($willBeAuto && UnitConverter::normalize($targetUnit) === 'sack') {
            return response()->json(['message' => 'Choose a measured unit for Auto Grocery. Sack has no defined conversion.'], 422);
        }
        $becomingAuto = $willBeAuto && ! $fsItem->include_in_generated_lists;
        $unitChanged = UnitConverter::normalize($targetUnit) !== UnitConverter::normalize((string) $fsItem->base_unit);
        $affected = [];
        $affectedLines = [];
        $affectedSlots = [];
        if ($willBeAuto && ($becomingAuto || $unitChanged)) {
            $ingredients = FoodServiceRecipeIngredient::query()
                ->with('recipe:id,uuid,name')
                ->where('fs_item_id', $fsItem->id)
                ->orderBy('food_service_recipe_id')->orderBy('id')->get();
            $lineIndices = [];
            foreach ($ingredients as $ingredient) {
                $index = $lineIndices[$ingredient->food_service_recipe_id] ?? 0;
                $lineIndices[$ingredient->food_service_recipe_id] = $index + 1;
                if ((float) $ingredient->quantity > 0
                    && UnitConverter::isCompatible((string) $ingredient->unit, $targetUnit)) {
                    continue;
                }
                $key = $ingredient->recipe->uuid.':'.$index;
                $affectedLines[$key] = $ingredient;
                $affected[] = [
                    'recipe_id' => $ingredient->recipe->uuid,
                    'recipe_name' => $ingredient->recipe->name,
                    'line_index' => $index,
                    'quantity' => $ingredient->quantity,
                    'unit' => $ingredient->unit,
                ];
            }
            $slots = MenuCycleDay::query()
                ->with('menuCycle:id,uuid,name')
                ->whereNotNull('recipe_override')
                ->where('po_snapshot_locked', false)
                ->get();
            foreach ($slots as $slot) {
                foreach (($slot->recipe_override['ingredients'] ?? []) as $index => $ingredient) {
                    if ((int) ($ingredient['fs_item_id'] ?? 0) !== $fsItem->id
                        || (isset($ingredient['quantity']) && (float) $ingredient['quantity'] > 0
                            && UnitConverter::isCompatible((string) $ingredient['unit'], $targetUnit))) {
                        continue;
                    }
                    $key = 'slot:'.$slot->uuid.':'.$index;
                    $affectedSlots[$key] = $slot;
                    $affected[] = [
                        'slot_id' => $slot->uuid,
                        'cycle_id' => $slot->menuCycle->uuid,
                        'recipe_name' => ($slot->recipe_override['name'] ?? $slot->recipe?->name ?? 'Menu recipe')
                            .' — '.$slot->menuCycle->name.' / '.$slot->day_of_week.' '.$slot->meal_type,
                        'line_index' => $index,
                        'quantity' => $ingredient['quantity'] ?? null,
                        'unit' => $ingredient['unit'],
                    ];
                }
            }
        }
        $corrections = [];
        foreach ($data['recipe_corrections'] ?? [] as $correction) {
            $key = isset($correction['slot_id'])
                ? 'slot:'.$correction['slot_id'].':'.$correction['line_index']
                : ($correction['recipe_id'] ?? '').':'.$correction['line_index'];
            if (isset($corrections[$key]) || (! isset($affectedLines[$key]) && ! isset($affectedSlots[$key]))
                || ! UnitConverter::isCompatible($correction['unit'], $targetUnit)) {
                return response()->json([
                    'message' => 'Enter a measured quantity and compatible unit for every listed Food or menu item.',
                    'affected_recipes' => $affected,
                ], 422);
            }
            $corrections[$key] = $correction;
        }
        if (count($corrections) !== count($affectedLines) + count($affectedSlots)) {
            return response()->json([
                'message' => 'Enter a measured quantity and compatible unit for every listed Food or menu item.',
                'affected_recipes' => $affected,
            ], 422);
        }
        unset($data['recipe_corrections']);

        // Single unit + cost per unit: keep purchase unit aligned to the base unit so
        // unit_cost stays equal to the entered cost.
        if (array_key_exists('base_unit', $data)) {
            if (! array_key_exists('purchase_price', $data)
                && UnitConverter::isCompatible((string) $fsItem->base_unit, (string) $data['base_unit'])) {
                $data['purchase_price'] = round(
                    UnitConverter::convert((float) $fsItem->unit_cost, (string) $data['base_unit'], (string) $fsItem->base_unit),
                    6,
                );
            }
            $data['purchase_unit'] = $data['base_unit'];
            $data['units_per_purchase'] = 1;
        }

        // A price/unit change shifts derived unit_cost → refresh dependent recipe costs.
        $priceTouched = array_intersect(array_keys($data), ['purchase_price', 'base_unit']) !== [];

        $this->audited(function () use ($fsItem, $data, $priceTouched, $affectedLines, $affectedSlots, $corrections): void {
            foreach ($affectedSlots as $slot) {
                if (MenuCycleDay::query()->whereKey($slot->id)->lockForUpdate()->firstOrFail()->po_snapshot_locked) {
                    abort(409, 'A menu item was locked to a purchase order. Reopen Inventory to review this change.');
                }
            }
            $before = $this->auditValues->values($fsItem);
            $recipes = collect($affectedLines)->map(fn (FoodServiceRecipeIngredient $line) => $line->recipe)->unique('id');
            $recipeBefore = $recipes->mapWithKeys(fn (FoodServiceRecipe $recipe): array => [
                $recipe->id => $this->revisionRegistry->capture($recipe->fresh('ingredients.fsItem')),
            ]);
            $cycles = collect($affectedSlots)->map(fn (MenuCycleDay $slot) => $slot->menuCycle)->unique('id');
            $cycleBefore = $cycles->mapWithKeys(fn ($cycle): array => [
                $cycle->id => $this->revisionRegistry->capture($cycle->fresh('days.recipe.ingredients.fsItem', 'days.fsItem')),
            ]);
            $this->auditLogger->withoutModelEvents(function () use ($fsItem, $data, $priceTouched, $affectedLines, $affectedSlots, $corrections): void {
                foreach ($corrections as $key => $correction) {
                    if (isset($affectedLines[$key])) {
                        $affectedLines[$key]->update(['quantity' => $correction['quantity'], 'unit' => $correction['unit']]);
                    } else {
                        $slot = $affectedSlots[$key];
                        $override = $slot->recipe_override;
                        $override['ingredients'][$correction['line_index']]['quantity'] = $correction['quantity'];
                        $override['ingredients'][$correction['line_index']]['unit'] = $correction['unit'];
                        $slot->update(['recipe_override' => $override]);
                    }
                }
                $fsItem->update($data);
                if ($priceTouched || $affectedLines !== []) {
                    FoodServiceRecipe::recalculateForItems([$fsItem->id]);
                }
            });
            foreach ($recipes as $recipe) {
                $afterRecipe = $recipe->fresh('ingredients.fsItem');
                $activity = $this->auditLogger->recordMutation(
                    AuditAction::Updated, AuditDomain::FoodService, $afterRecipe, ['ingredients'],
                );
                if ($activity !== null) {
                    $this->revisionWriter->write($activity, $recipeBefore[$recipe->id], $this->revisionRegistry->capture($afterRecipe));
                }
            }
            foreach ($cycles as $cycle) {
                $afterCycle = $cycle->fresh('days.recipe.ingredients.fsItem', 'days.fsItem');
                $activity = $this->auditLogger->recordMutation(
                    AuditAction::Updated, AuditDomain::FoodService, $afterCycle, ['days'],
                );
                if ($activity !== null) {
                    $this->revisionWriter->write($activity, $cycleBefore[$cycle->id], $this->revisionRegistry->capture($afterCycle));
                }
            }
            $fsItem->unsetRelation('defaultSupplier');
            $after = $this->auditValues->values($fsItem);
            $fields = $this->auditValues->changedFields($before, $after);
            $fieldMap = array_flip($fields);
            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::FoodService,
                $fsItem,
                $fields,
                ['entity_name' => $fsItem->name],
                oldValues: array_intersect_key($before, $fieldMap),
                newValues: array_intersect_key($after, $fieldMap),
            );
        });
        Cache::flush();

        return response()->json(['data' => $this->catalogRow($fsItem->fresh('defaultSupplier'))]);
    }

    /** Remove a catalog item — blocked while any recipe still references it. */
    public function destroy(FsItem $fsItem): JsonResponse
    {
        $usedBy = FoodServiceRecipeIngredient::where('fs_item_id', $fsItem->id)->count();
        if ($usedBy > 0) {
            return response()->json([
                'message' => "Can't delete: this item is used by {$usedBy} recipe ingredient(s). Remove it from those recipes first.",
            ], 409);
        }

        $this->audited(function () use ($fsItem): void {
            $before = $this->auditValues->values($fsItem);
            $fields = array_keys(array_filter($before, fn (mixed $value): bool => $value !== null));
            $this->auditLogger->withoutModelEvents(fn () => $fsItem->delete());
            $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::FoodService,
                $fsItem,
                $fields,
                ['entity_name' => $fsItem->name],
                oldValues: $before,
                newValues: array_fill_keys(array_keys($before), null),
            );
        });
        Cache::flush();

        return response()->json(null, 204);
    }

    /**
     * Cost profile for a ready-to-serve item placed directly in a menu-cycle slot.
     * Quantity is per head, so total = quantity x population x unit cost.
     */
    public function profile(Request $request, FsItem $fsItem): JsonResponse
    {
        if ($fsItem->kind !== 'ingredient') {
            abort(404);
        }

        $data = $request->validate([
            'population' => ['nullable', 'integer', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $population = (int) ($data['population'] ?? 0);
        $quantity = (float) ($data['quantity'] ?? 1);
        $totalQuantity = $population * $quantity;
        $unitCost = $fsItem->unit_cost;
        $totalCost = round($totalQuantity * $unitCost, 2);

        return response()->json(['data' => [
            'id' => $fsItem->uuid,
            'fs_item_id' => $fsItem->uuid,
            'name' => $fsItem->name,
            'kind' => $fsItem->kind,
            'category' => $fsItem->category,
            'unit' => $fsItem->base_unit,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'population' => $population,
            'servings' => $population,
            'total_quantity' => round($totalQuantity, 2),
            'total_cost' => $totalCost,
            'cost_per_head' => $population > 0 ? round($totalCost / $population, 2) : 0.0,
            'prep_notes' => $fsItem->notes,
            'include_in_generated_lists' => $fsItem->include_in_generated_lists,
            'formula' => 'total_cost = quantity_per_head * population * unit_cost',
            'ingredient_usage' => [[
                'fs_item_id' => $fsItem->uuid,
                'name' => $fsItem->name,
                'unit' => $fsItem->base_unit,
                'quantity' => round($totalQuantity, 2),
                'cost' => $totalCost,
            ]],
        ]]);
    }

    /** @param array<int,array{date:string,unit_price:float}> $points */
    public static function summarizeTrend(array $points): array
    {
        if (! $points) {
            return ['min' => 0.0, 'max' => 0.0, 'latest' => 0.0, 'avg' => 0.0];
        }
        $last = $points[array_key_last($points)];
        $prices = array_map(fn ($p) => (float) $p['unit_price'], $points);

        return [
            'min' => min($prices),
            'max' => max($prices),
            'latest' => (float) $last['unit_price'],
            'avg' => round(array_sum($prices) / count($prices), 6),
        ];
    }

    /** Purchase-price trend for one catalog item, derived from frozen received-PO lines. */
    public function priceTrend(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);
        $start = $data['start'] ?? now()->subMonths(6)->toDateString();
        $end = $data['end'] ?? now()->toDateString();

        $rows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->where('purchase_order_items.fs_item_id', $fsItem->id)
            ->whereRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) BETWEEN ? AND ?', [$start, $end])
            ->orderByRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date)')
            ->get([
                DB::raw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) as date'),
                'purchase_order_items.unit_price as unit_price',
            ])
            ->map(fn ($r) => ['date' => (string) $r->date, 'unit_price' => (float) $r->unit_price])
            ->all();

        return response()->json(['data' => ['points' => $rows] + self::summarizeTrend($rows)]);
    }
}
