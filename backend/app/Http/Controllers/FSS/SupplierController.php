<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreSupplierRequest;
use App\Http\Requests\FSS\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SupplierResource::collection(Supplier::all())]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());
        return response()->json(['data' => new SupplierResource($supplier)], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());
        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return response()->json(null, 204);
    }
}
