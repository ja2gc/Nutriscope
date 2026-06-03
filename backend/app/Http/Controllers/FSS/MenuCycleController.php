<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreMenuCycleRequest;
use App\Http\Requests\FSS\UpdateMenuCycleRequest;
use App\Http\Resources\MenuCycleResource;
use App\Models\MenuCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MenuCycleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => MenuCycleResource::collection(MenuCycle::all())]);
    }

    public function store(StoreMenuCycleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['fss_user_id'] = Auth::id();
        $data['week_start_date'] = $data['week_start_date'] ?? now()->toDateString();

        $menuCycle = MenuCycle::create($data);
        return response()->json(['data' => new MenuCycleResource($menuCycle)], 201);
    }

    public function show(MenuCycle $menuCycle): JsonResponse
    {
        return response()->json(['data' => new MenuCycleResource($menuCycle)]);
    }

    public function update(UpdateMenuCycleRequest $request, MenuCycle $menuCycle): JsonResponse
    {
        $menuCycle->update($request->validated());
        return response()->json(['data' => new MenuCycleResource($menuCycle)]);
    }

    public function destroy(MenuCycle $menuCycle): JsonResponse
    {
        $menuCycle->delete();
        return response()->json(null, 204);
    }

    public function activate(MenuCycle $menuCycle): JsonResponse
    {
        $menuCycle->update([
            'is_active'       => true,
            'status'          => 'active',
            'activation_date' => now()->toDateString(),
        ]);
        return response()->json(['data' => new MenuCycleResource($menuCycle)]);
    }
}
