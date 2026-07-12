<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Models\FsItem;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    private const RELATIONS = ['fsItem', 'recipe'];

    /**
     * Compatibility catalog endpoint retained while callers move to fs-items/catalog.
     * It exposes catalog and recipe costing data only; it is not a stock ledger.
     */
    public function rows(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $type = (string) $request->query('type', 'all');
        $type = $type === 'food_item' ? 'ingredient' : $type;
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $page = max((int) $request->query('page', 1), 1);
        $cacheKey = 'catalog_rows_'.md5("{$search}|{$type}|{$perPage}|{$page}");

        $result = Cache::remember($cacheKey, 30, function () use ($search, $type, $perPage, $page): array {
            [$union, $bindings] = $this->catalogUnion($type, $search);
            $total = (int) DB::selectOne("SELECT COUNT(*) AS aggregate FROM ({$union}) AS catalog", $bindings)->aggregate;
            $rows = DB::select(
                "SELECT * FROM ({$union}) AS catalog ORDER BY name ASC LIMIT ? OFFSET ?",
                [...$bindings, $perPage, ($page - 1) * $perPage],
            );

            return [
                'data' => array_map(fn (object $row): array => $this->catalogRow($row), $rows),
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                ],
                'stats' => Cache::remember('catalog_row_stats', 30, function (): array {
                    $catalogItems = FsItem::query()->where('is_active', true)->count();
                    $recipes = DB::table('food_service_recipes')->count();

                    return [
                        'total' => $catalogItems + $recipes,
                        'catalog_items' => $catalogItems,
                        'recipes' => $recipes,
                    ];
                }),
            ];
        });

        return response()->json($result);
    }

    /** @return array{0:string,1:array<int, mixed>} */
    private function catalogUnion(string $type, string $search): array
    {
        $parts = [];
        $bindings = [];

        if (in_array($type, ['all', 'ingredient', 'supply'], true)) {
            $sql = 'SELECT f.uuid AS item_id, f.name, f.category, f.kind AS item_type,
                           f.base_unit, f.purchase_unit, f.purchase_price, f.units_per_purchase,
                           NULL AS recipe_cost, NULL AS recipe_servings
                    FROM fs_items f WHERE f.is_active = 1';
            if ($type !== 'all') {
                $sql .= ' AND f.kind = ?';
                $bindings[] = $type;
            }
            if ($search !== '') {
                $sql .= ' AND f.name LIKE ?';
                $bindings[] = "%{$search}%";
            }
            $parts[] = $sql;
        }

        if (in_array($type, ['all', 'recipe'], true)) {
            $sql = "SELECT r.uuid AS item_id, r.name, r.category, 'recipe' AS item_type,
                           NULL AS base_unit, NULL AS purchase_unit, NULL AS purchase_price, NULL AS units_per_purchase,
                           r.cost AS recipe_cost, r.servings AS recipe_servings
                    FROM food_service_recipes r";
            if ($search !== '') {
                $sql .= ' WHERE r.name LIKE ?';
                $bindings[] = "%{$search}%";
            }
            $parts[] = $sql;
        }

        $empty = 'SELECT NULL AS item_id, NULL AS name, NULL AS category, NULL AS item_type,
                         NULL AS base_unit, NULL AS purchase_unit, NULL AS purchase_price, NULL AS units_per_purchase,
                         NULL AS recipe_cost, NULL AS recipe_servings WHERE 1 = 0';

        return [$parts === [] ? $empty : implode(' UNION ALL ', $parts), $bindings];
    }

    /** @return array<string, mixed> */
    private function catalogRow(object $row): array
    {
        $isRecipe = $row->item_type === 'recipe';
        $unitCost = $isRecipe ? null : (new FsItem([
            'purchase_price' => $row->purchase_price,
            'purchase_unit' => $row->purchase_unit,
            'base_unit' => $row->base_unit,
            'units_per_purchase' => $row->units_per_purchase,
        ]))->unit_cost;

        return [
            'item_id' => $row->item_id,
            'item_type' => $row->item_type,
            'name' => $row->name,
            'category' => $row->category ?? '',
            'purchase_price' => $isRecipe ? null : $row->purchase_price,
            'unit_cost' => $unitCost,
            'base_unit' => $row->base_unit,
            'purchase_unit' => $row->purchase_unit,
            'units_per_purchase' => $row->units_per_purchase !== null ? (float) $row->units_per_purchase : null,
            'recipe_cost' => $row->recipe_cost,
            'recipe_servings' => $row->recipe_servings !== null ? (int) $row->recipe_servings : null,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => InventoryResource::collection(
            Inventory::query()->with(self::RELATIONS)->get(),
        )]);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(['data' => new InventoryResource($inventory->load(self::RELATIONS))]);
    }
}
