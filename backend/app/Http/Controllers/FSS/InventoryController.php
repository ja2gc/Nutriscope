<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreInventoryRequest;
use App\Http\Requests\FSS\UpdateInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => InventoryResource::collection(Inventory::with('foodItem')->get())]);
    }

    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $inventory = Inventory::create($request->validated());
        return response()->json(['data' => new InventoryResource($inventory->load('foodItem'))], 201);
    }

    public function show(Inventory $inventory): JsonResponse
    {
        return response()->json(['data' => new InventoryResource($inventory->load('foodItem'))]);
    }

    public function update(UpdateInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $inventory->update($request->validated());
        return response()->json(['data' => new InventoryResource($inventory->load('foodItem'))]);
    }

    public function destroy(Inventory $inventory): JsonResponse
    {
        $inventory->delete();
        return response()->json(null, 204);
    }

    public function restock(\Illuminate\Http\Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $inventory->increment('quantity_in_stock', $data['quantity']);

        return response()->json(['data' => new InventoryResource($inventory->fresh()->load('foodItem'))]);
    }
}
