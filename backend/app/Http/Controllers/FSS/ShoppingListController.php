<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreShoppingListRequest;
use App\Http\Requests\FSS\UpdateShoppingListRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShoppingListController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => ShoppingListResource::collection(ShoppingList::with('items')->get())]);
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['fss_user_id'] = Auth::id();

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

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        return DB::transaction(function () use ($request) {
            $shoppingList = ShoppingList::create([
                'fss_user_id'  => Auth::id(),
                'name'         => 'Suggested Shopping List ' . now()->toDateString(),
                'list_date'    => now()->toDateString(),
                'list_type'    => 'suggested',
                'status'       => 'draft',
                'period_start' => $request->period_start,
                'period_end'   => $request->period_end,
            ]);

            // Retrieve active menu cycle and aggregate ingredient needs
            $activeMenuCycle = \App\Models\MenuCycle::with(['days.recipe.ingredients', 'days.foodItem'])
                ->where('is_active', true)
                ->first();

            $menuCycleIngredients = [];
            if ($activeMenuCycle) {
                foreach ($activeMenuCycle->days as $day) {
                    if ($day->food_item_id) {
                        $menuCycleIngredients[$day->food_item_id] = ($menuCycleIngredients[$day->food_item_id] ?? 0) + $day->quantity;
                    } elseif ($day->recipe_id && $day->recipe) {
                        foreach ($day->recipe->ingredients as $ingredient) {
                            if ($ingredient->food_item_id) {
                                $menuCycleIngredients[$ingredient->food_item_id] = ($menuCycleIngredients[$ingredient->food_item_id] ?? 0) + ($ingredient->quantity * $day->quantity);
                            }
                        }
                    }
                }
            }

            // Find shortfall across all items in physical inventory
            $inventories = Inventory::with('foodItem')->get();

            foreach ($inventories as $inventory) {
                $neededFromMenuCycle = $menuCycleIngredients[$inventory->food_item_id] ?? 0;
                $shortfall = ($neededFromMenuCycle + $inventory->minimum_stock_threshold) - $inventory->quantity_in_stock;

                if ($shortfall > 0) {
                    ShoppingListItem::create([
                        'shopping_list_id' => $shoppingList->id,
                        'food_item_id'     => $inventory->food_item_id,
                        'ingredient_name'  => $inventory->foodItem->name,
                        'qty'              => $shortfall,
                        'unit'             => $inventory->unit,
                        'unit_price'       => 0,
                        'total'            => 0,
                    ]);
                }
            }

            return response()->json(['data' => new ShoppingListResource($shoppingList->load('items'))], 201);
        });
    }
}
