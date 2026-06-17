<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreInventoryRequest;
use App\Http\Requests\FSS\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\FsItem;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    private const RELATIONS = ['fsItem', 'recipe'];

    /** Merged, paginated inventory rows for the UI table (single query, no N+1). */
    public function rows(Request $request): JsonResponse
    {
        $search  = trim($request->get('search', ''));
        $type    = $request->get('type', 'all');
        // Back-compat: the old UI called ingredients "food_item".
        $type    = $type === 'food_item' ? 'ingredient' : $type;
        $status  = $request->get('status', 'all');
        $perPage = (int) min($request->get('per_page', 25), 100);
        $page    = max(1, (int) $request->get('page', 1));

        $cacheKey = 'inv_rows_' . md5("{$search}|{$type}|{$status}|{$perPage}|{$page}");

        $result = Cache::remember($cacheKey, 30, function () use ($search, $type, $status, $perPage, $page) {
            return $this->buildRows($search, $type, $status, $perPage, $page);
        });

        return response()->json($result);
    }

    private function buildRows(string $search, string $type, string $status, int $perPage, int $page): array
    {
        [$union, $bindings] = $this->unionFor($type, $search);

        $statusWhere = $this->statusWhere($status);
        $where       = $statusWhere ? "WHERE {$statusWhere}" : '';

        $total = DB::selectOne("SELECT COUNT(*) AS cnt FROM ({$union}) AS t {$where}", $bindings)->cnt;

        $offset = ($page - 1) * $perPage;
        $rows   = DB::select(
            "SELECT * FROM ({$union}) AS t {$where} ORDER BY name ASC LIMIT ? OFFSET ?",
            [...$bindings, $perPage, $offset]
        );

        $now  = Carbon::now();
        $data = array_map(fn ($r) => $this->decorateRow($r, $now), $rows);

        return [
            'data'  => $data,
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => (int) $total,
                'last_page'    => max(1, (int) ceil($total / $perPage)),
            ],
            'stats' => Cache::remember('inv_stats', 30, fn () => $this->getStats()),
        ];
    }

    /**
     * Build the UNION of catalog rows (fs_items by kind) and recipe rows, both
     * LEFT JOINed to their inventory stock. Columns are identical across selects.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private function unionFor(string $type, string $search): array
    {
        $parts    = [];
        $bindings = [];

        $catalogCols = "f.id AS item_id, f.name, f.category, f.kind AS item_type,
                        f.base_unit, f.purchase_unit, f.purchase_price, f.units_per_purchase,
                        inv.id AS inventory_id, inv.quantity_in_stock, inv.unit,
                        NULL AS recipe_cost, NULL AS recipe_servings";

        if (in_array($type, ['all', 'ingredient', 'supply'], true)) {
            $sql = "SELECT {$catalogCols}
                    FROM fs_items f
                    LEFT JOIN inventory inv ON inv.fs_item_id = f.id
                    WHERE f.is_active = 1";
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
            $sql = "SELECT r.id AS item_id, r.name, r.category, 'recipe' AS item_type,
                           NULL AS base_unit, NULL AS purchase_unit, NULL AS purchase_price, NULL AS units_per_purchase,
                           inv.id AS inventory_id, inv.quantity_in_stock, inv.unit,
                           r.cost AS recipe_cost, r.servings AS recipe_servings
                    FROM food_service_recipes r
                    LEFT JOIN inventory inv ON inv.recipe_id = r.id";
            if ($search !== '') {
                $sql .= ' WHERE r.name LIKE ?';
                $bindings[] = "%{$search}%";
            }
            $parts[] = $sql;
        }

        // Guard against an empty IN-set (unknown type) → return nothing rather than error.
        $union = $parts ? implode(' UNION ALL ', $parts) : 'SELECT NULL AS item_id, NULL AS name, NULL AS category, NULL AS item_type, NULL AS base_unit, NULL AS purchase_unit, NULL AS purchase_price, NULL AS units_per_purchase, NULL AS inventory_id, NULL AS quantity_in_stock, NULL AS unit, NULL AS recipe_cost, NULL AS recipe_servings WHERE 1=0';

        return [$union, $bindings];
    }

    private function statusWhere(string $status): string
    {
        // Binary stock state: in stock (green) vs out — qty 0 or no inventory row (red).
        return match ($status) {
            'no_stock' => 'inventory_id IS NULL OR quantity_in_stock = 0',
            'ok'       => 'inventory_id IS NOT NULL AND quantity_in_stock > 0',
            default    => '',
        };
    }

    private function decorateRow(object $r, Carbon $now): array
    {
        $qty = (float) ($r->quantity_in_stock ?? 0);

        // Binary indicator: green when in stock, red when out (or untracked).
        if ($qty > 0.0) {
            $status = 'ok'; $highlight = 'green';
        } else {
            $status = 'no_stock'; $highlight = 'red';
        }

        $isRecipe = $r->item_type === 'recipe';

        // Catalog rows: derive ₱/base-unit via the tested FsItem accessor (DRY).
        $unitCost = null;
        if (! $isRecipe) {
            $unitCost = (new FsItem([
                'purchase_price'     => $r->purchase_price,
                'purchase_unit'      => $r->purchase_unit,
                'base_unit'          => $r->base_unit,
                'units_per_purchase' => $r->units_per_purchase,
            ]))->unit_cost;
        }

        return [
            'item_id'                 => (int) $r->item_id,
            'item_type'               => $r->item_type,
            'inventory_id'            => $r->inventory_id !== null ? (int) $r->inventory_id : null,
            'name'                    => $r->name,
            'category'                => $r->category ?? '',
            'quantity_in_stock'       => $r->quantity_in_stock,
            'unit'                    => $r->unit ?? '',
            // Catalog "price" shown in the table = the buy price; unit_cost is the derived ₱/base-unit.
            'unit_price'              => $isRecipe ? null : $r->purchase_price,
            'unit_cost'               => $unitCost,
            'base_unit'               => $r->base_unit,
            'purchase_unit'           => $r->purchase_unit,
            'recipe_cost'             => $r->recipe_cost,
            'recipe_servings'         => $r->recipe_servings !== null ? (int) $r->recipe_servings : null,
            'status'                  => $status,
            'highlight'               => $highlight,
        ];
    }

    private function getStats(): array
    {
        $union = "
            SELECT inv.id AS inventory_id, inv.quantity_in_stock
            FROM fs_items f LEFT JOIN inventory inv ON inv.fs_item_id = f.id WHERE f.is_active = 1
            UNION ALL
            SELECT inv.id AS inventory_id, inv.quantity_in_stock
            FROM food_service_recipes r LEFT JOIN inventory inv ON inv.recipe_id = r.id
        ";

        $row = DB::selectOne("
            SELECT
                COUNT(*)                                                          AS total,
                SUM(inventory_id IS NOT NULL AND quantity_in_stock > 0)           AS in_stock,
                SUM(inventory_id IS NULL OR quantity_in_stock = 0)               AS no_stock
            FROM ({$union}) AS s
        ");

        return [
            'total'    => (int) $row->total,
            'in_stock' => (int) $row->in_stock,
            'no_stock' => (int) $row->no_stock,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => InventoryResource::collection(Inventory::with(self::RELATIONS)->get())]);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Force base-unit storage for catalog items (ingredient/supply) so a manual
        // entry can never mix units with a received-PO row in the same stock total.
        if (($data['item_type'] ?? null) !== 'recipe' && ! empty($data['fs_item_id'])) {
            $fs = FsItem::find($data['fs_item_id']);
            if ($fs) {
                [$qtyBase] = \App\Services\FSS\ReceivingService::normalizeLine(
                    (float) $data['quantity_in_stock'], (string) ($data['unit'] ?? ''), 0.0, (string) $fs->base_unit
                );
                $data['quantity_in_stock'] = $qtyBase;
                $data['unit'] = $fs->base_unit;
            }
        }

        // inventory has UNIQUE(fs_item_id)/UNIQUE(recipe_id) → upsert, never a dup-row 500.
        $key = ! empty($data['fs_item_id'])
            ? ['fs_item_id' => $data['fs_item_id']]
            : ['recipe_id' => $data['recipe_id']];

        $inventory = Inventory::updateOrCreate($key, $data);
        Cache::flush();

        return response()->json(['data' => new InventoryResource($inventory->load(self::RELATIONS))], 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(['data' => new InventoryResource($inventory->load(self::RELATIONS))]);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $inventory->update($request->validated());
        Cache::flush();
        return response()->json(['data' => new InventoryResource($inventory->load(self::RELATIONS))]);
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        $inventory->delete();
        Cache::flush();
        return response()->json(null, 204);
    }

    public function restock(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $inventory->increment('quantity_in_stock', $data['quantity']);
        Cache::flush();

        return response()->json(['data' => new InventoryResource($inventory->fresh()->load(self::RELATIONS))]);
    }
}
