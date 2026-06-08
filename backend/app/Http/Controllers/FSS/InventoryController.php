<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreInventoryRequest;
use App\Http\Requests\FSS\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /** Merged, paginated inventory rows for the UI table (single query, no N+1). */
    public function rows(Request $request): JsonResponse
    {
        $search  = trim($request->get('search', ''));
        $type    = $request->get('type', 'all');
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
        $bindings = [];
        $parts    = [];

        {
            $sql = "SELECT fi.id AS item_id, fi.name, fi.category, 'food_item' AS item_type,
                           inv.id AS inventory_id, inv.quantity_in_stock, inv.unit, inv.expiry_date,
                           inv.minimum_stock_threshold, inv.unit_price, inv.notes, inv.usage_rate
                    FROM food_items fi
                    LEFT JOIN inventory inv ON inv.food_item_id = fi.id";
            if ($search !== '') {
                $sql .= ' WHERE fi.name LIKE ?';
                $bindings[] = "%{$search}%";
            }
            $parts[] = $sql;
        }

        $union = '(' . implode(' UNION ALL ', $parts) . ') AS t';

        $statusWhere = $this->statusWhere($status);
        $where       = $statusWhere ? "WHERE {$statusWhere}" : '';

        $total = DB::selectOne("SELECT COUNT(*) AS cnt FROM {$union} {$where}", $bindings)->cnt;

        $offset = ($page - 1) * $perPage;
        $rows   = DB::select(
            "SELECT * FROM {$union} {$where} ORDER BY name ASC LIMIT ? OFFSET ?",
            [...$bindings, $perPage, $offset]
        );

        $now  = Carbon::now();
        $data = array_map(fn($r) => $this->decorateRow($r, $now), $rows);

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

    private function statusWhere(string $status): string
    {
        return match ($status) {
            'untracked' => 'inventory_id IS NULL',
            'low'       => 'inventory_id IS NOT NULL AND minimum_stock_threshold IS NOT NULL AND quantity_in_stock < minimum_stock_threshold',
            'expiring'  => 'inventory_id IS NOT NULL AND expiry_date IS NOT NULL AND expiry_date > NOW() AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)',
            'ok'        => 'inventory_id IS NOT NULL AND (minimum_stock_threshold IS NULL OR quantity_in_stock >= minimum_stock_threshold) AND (expiry_date IS NULL OR expiry_date > DATE_ADD(NOW(), INTERVAL 7 DAY))',
            default     => '',
        };
    }

    private function decorateRow(object $r, Carbon $now): array
    {
        $hasRecord = $r->inventory_id !== null;
        $qty       = (float) ($r->quantity_in_stock ?? 0);
        $threshold = $r->minimum_stock_threshold !== null ? (float) $r->minimum_stock_threshold : null;
        $expiry    = $r->expiry_date ? Carbon::parse($r->expiry_date) : null;

        if (!$hasRecord) {
            $status = 'untracked'; $highlight = 'none';
        } elseif ($qty === 0.0) {
            $status = 'low'; $highlight = 'red';
        } elseif ($threshold !== null && $qty < $threshold) {
            $status = 'low'; $highlight = 'yellow';
        } elseif ($expiry && $expiry->isFuture() && $now->diffInDays($expiry) <= 7) {
            $status = 'expiring'; $highlight = 'yellow';
        } else {
            $status = 'ok'; $highlight = 'none';
        }

        return [
            'item_id'                 => (int) $r->item_id,
            'item_type'               => $r->item_type,
            'inventory_id'            => $r->inventory_id !== null ? (int) $r->inventory_id : null,
            'name'                    => $r->name,
            'category'                => $r->category ?? '',
            'quantity_in_stock'       => $r->quantity_in_stock,
            'unit'                    => $r->unit ?? '',
            'expiry_date'             => $r->expiry_date,
            'minimum_stock_threshold' => $r->minimum_stock_threshold,
            'unit_price'              => $r->unit_price,

            'notes'                   => $r->notes,
            'usage_rate'              => $r->usage_rate,
            'status'                  => $status,
            'highlight'               => $highlight,
        ];
    }

    private function getStats(): array
    {
        $union = "(
            SELECT inv.id AS inventory_id, inv.quantity_in_stock, inv.minimum_stock_threshold, inv.expiry_date
            FROM food_items fi
            LEFT JOIN inventory inv ON inv.food_item_id = fi.id
        ) AS s";

        $row = DB::selectOne("
            SELECT
                COUNT(*)                                                                                                                AS total,
                SUM(inventory_id IS NOT NULL)                                                                                           AS tracked,
                SUM(inventory_id IS NOT NULL AND minimum_stock_threshold IS NOT NULL AND quantity_in_stock < minimum_stock_threshold)   AS low,
                SUM(inventory_id IS NOT NULL AND expiry_date IS NOT NULL AND expiry_date > NOW() AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)) AS expiring,
                SUM(inventory_id IS NULL)                                                                                               AS untracked
            FROM {$union}
        ");

        return [
            'total'     => (int) $row->total,
            'tracked'   => (int) $row->tracked,
            'low'       => (int) $row->low,
            'expiring'  => (int) $row->expiring,
            'untracked' => (int) $row->untracked,
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => InventoryResource::collection(Inventory::with(['foodItem'])->get())]);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $inventory = Inventory::create($request->validated());
        Cache::flush();
        return response()->json(['data' => new InventoryResource($inventory->load(['foodItem']))], 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(['data' => new InventoryResource($inventory->load(['foodItem']))]);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $inventory->update($request->validated());
        Cache::flush();
        return response()->json(['data' => new InventoryResource($inventory->load(['foodItem']))]);
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

        return response()->json(['data' => new InventoryResource($inventory->fresh()->load(['foodItem']))]);
    }
}
