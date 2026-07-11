<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreSupplierRequest;
use App\Http\Requests\FSS\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => SupplierResource::collection(Supplier::all())]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $supplier = $this->audited(function () use ($data): Supplier {
            $supplier = Supplier::create($data);
            $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::Procurement,
                $supplier,
                array_map(fn (string $field): string => $field === 'notes' ? 'content' : $field, array_keys($supplier->getAttributes())),
            );

            return $supplier;
        });

        return response()->json(['data' => new SupplierResource($supplier)], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validated();
        $this->audited(function () use ($supplier, $data): void {
            $supplier->update($data);
            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::Procurement,
                $supplier,
                array_map(fn (string $field): string => $field === 'notes' ? 'content' : $field, array_keys($supplier->getChanges())),
            );
        });

        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->audited(function () use ($supplier): void {
            $supplier->delete();
            $this->auditLogger->recordMutation(AuditAction::Deleted, AuditDomain::Procurement, $supplier, []);
        });

        return response()->json(null, 204);
    }
}
