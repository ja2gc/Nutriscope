<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreShoppingListRequest;
use App\Http\Requests\FSS\UpdateShoppingListRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\FsItem;
use App\Models\MenuCycle;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Services\MenuCycleCostService;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingListController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ShoppingListResource::collection(ShoppingList::with('items')->orderByDesc('created_at')->get())]);
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['fss_user_id'] = Auth::id();
        $data['list_type'] = $data['list_type'] ?? 'manual';
        $data['status'] = $data['status'] ?? 'draft';
        $data['list_date'] = $data['list_date'] ?? now()->toDateString();

        $shoppingList = ShoppingList::create($data);
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))], 201);
    }

    public function show(ShoppingList $shoppingList): JsonResponse
    {
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))]);
    }

    public function update(UpdateShoppingListRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->update($request->validated());
        return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))]);
    }

    public function destroy(ShoppingList $shoppingList): JsonResponse
    {
        $shoppingList->delete();
        return response()->json(null, 204);
    }

    /**
     * Auto-build a suggested list from a menu cycle scaled to a purchasing day span.
     * Quantities + costs come from the menu engine; the default vendor for each item
     * is the one remembered on the catalog (fs_items.default_supplier_id).
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_cycle_id' => ['required', 'integer', 'exists:menu_cycles,id'],
            'days_span'     => ['required', 'integer', 'min:1', 'max:60'],
            'name'          => ['nullable', 'string', 'max:255'],
        ]);

        $cycle  = MenuCycle::findOrFail($data['menu_cycle_id']);
        $result = MenuCycleCostService::forCycle($cycle);
        $items  = ProcurementService::suggestedItems($result['ingredient_usage'], (int) $cycle->cycle_days, (int) $data['days_span']);

        $fsItems = FsItem::whereIn('id', array_column($items, 'fs_item_id'))->get()->keyBy('id');

        $list = DB::transaction(function () use ($data, $cycle, $items, $fsItems) {
            $list = ShoppingList::create([
                'fss_user_id'   => Auth::id(),
                'menu_cycle_id' => $cycle->id,
                'name'          => $data['name'] ?? "Suggested — {$cycle->name} ({$data['days_span']}d)",
                'list_date'     => now()->toDateString(),
                'days_span'     => $data['days_span'],
                'list_type'     => 'suggested',
                'status'        => 'draft',
            ]);

            foreach ($items as $it) {
                $fs        = $fsItems[$it['fs_item_id']] ?? null;
                $unitPrice = $it['qty'] > 0 ? round($it['total'] / $it['qty'], 4) : 0;
                $list->items()->create([
                    'fs_item_id'      => $it['fs_item_id'],
                    'ingredient_name' => $it['name'],
                    'qty'             => $it['qty'],
                    'unit'            => $it['unit'],
                    'supplier_id'     => $fs?->default_supplier_id,
                    'unit_price'      => $unitPrice,
                    'total'           => $it['total'],
                ]);
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($list->load('items'))], 201);
    }

    /**
     * Edit one line: vendor / qty / price. Picking a vendor remembers it on the
     * catalog item so it's the default on the next suggested list.
     */
    public function updateItem(Request $request, ShoppingListItem $shoppingListItem): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'qty'         => ['nullable', 'numeric', 'min:0'],
            'unit_price'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $shoppingListItem->fill($data);
        $shoppingListItem->total = round((float) $shoppingListItem->qty * (float) $shoppingListItem->unit_price, 2);
        $shoppingListItem->save();

        if (array_key_exists('supplier_id', $data) && $data['supplier_id'] && $shoppingListItem->fs_item_id) {
            FsItem::whereKey($shoppingListItem->fs_item_id)->update(['default_supplier_id' => $data['supplier_id']]);
        }

        return response()->json(['data' => [
            'id'          => $shoppingListItem->id,
            'supplier_id' => $shoppingListItem->supplier_id,
            'qty'         => $shoppingListItem->qty,
            'unit_price'  => $shoppingListItem->unit_price,
            'total'       => $shoppingListItem->total,
        ]]);
    }

    public function destroyItem(ShoppingListItem $shoppingListItem): JsonResponse
    {
        $shoppingListItem->delete();
        return response()->json(null, 204);
    }
}
