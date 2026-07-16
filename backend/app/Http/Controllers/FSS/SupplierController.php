<?php

namespace App\Http\Controllers\FSS;

use App\Enums\AuditAction;
use App\Enums\AuditDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreSupplierRequest;
use App\Http\Requests\FSS\UpdateSupplierRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\SupplierAuditValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly SupplierAuditValues $auditValues,
    ) {}

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $suppliers = Supplier::query()
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();
        $supplier = $this->audited(function () use ($data): Supplier {
            $supplier = Supplier::create($data);
            $values = $this->auditValues->values($supplier);
            $fields = array_keys(array_filter($values, fn (mixed $value): bool => $value !== null));
            if (($data['notes'] ?? null) !== null) {
                $fields[] = 'content';
            }
            $this->auditLogger->recordMutation(
                AuditAction::Created,
                AuditDomain::Procurement,
                $supplier,
                $fields,
                ['entity_name' => $supplier->name],
                oldValues: array_fill_keys(array_keys($values), null),
                newValues: $values,
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
            $before = $this->auditValues->values($supplier);
            $supplier->update($data);
            $after = $this->auditValues->values($supplier);
            $fields = $this->auditValues->changedFields($before, $after);
            if (array_key_exists('notes', $data) && $supplier->wasChanged('notes')) {
                $fields[] = 'content';
            }
            $fieldMap = array_flip($fields);
            $this->auditLogger->recordMutation(
                AuditAction::Updated,
                AuditDomain::Procurement,
                $supplier,
                $fields,
                ['entity_name' => $supplier->name],
                oldValues: array_intersect_key($before, $fieldMap),
                newValues: array_intersect_key($after, $fieldMap),
            );
        });

        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->audited(function () use ($supplier): void {
            $before = $this->auditValues->values($supplier);
            $fields = array_keys(array_filter($before, fn (mixed $value): bool => $value !== null));
            if ($supplier->notes !== null) {
                $fields[] = 'content';
            }
            $supplier->delete();
            $this->auditLogger->recordMutation(
                AuditAction::Deleted,
                AuditDomain::Procurement,
                $supplier,
                $fields,
                ['entity_name' => $supplier->name],
                oldValues: $before,
                newValues: array_fill_keys(array_keys($before), null),
            );
        });

        return response()->json(null, 204);
    }
}
