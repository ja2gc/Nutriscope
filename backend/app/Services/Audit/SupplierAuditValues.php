<?php

namespace App\Services\Audit;

use App\Models\Supplier;

final class SupplierAuditValues
{
    /** @return array<string, string|null> */
    public function values(Supplier $supplier): array
    {
        return [
            'name' => (string) $supplier->name,
            'category' => $supplier->category,
            'contact' => $supplier->contact,
            'address' => $supplier->address,
            'payment_terms' => $supplier->payment_terms,
        ];
    }

    /** @return list<string> */
    public function changedFields(array $before, array $after): array
    {
        return collect(array_keys($before))
            ->filter(fn (string $field): bool => $before[$field] !== $after[$field])
            ->values()->all();
    }
}
