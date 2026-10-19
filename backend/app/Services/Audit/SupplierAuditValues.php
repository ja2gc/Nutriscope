<?php

namespace App\Services\Audit;

use App\Models\Supplier;

final class SupplierAuditValues
{
    /** @return array<string, string|null> */
    public function values(Supplier $supplier): array
    {
        $contacts = collect($supplier->additional_contacts ?? []);
        $describe = fn (array $entry): string => ($entry['method'] === 'other' ? $entry['label'] : ucfirst($entry['method'])).': '.$entry['value'];
        $preferred = $contacts->firstWhere('id', $supplier->preferred_contact_id);

        return [
            'name' => (string) $supplier->name,
            'category' => $supplier->category,
            'contact' => $supplier->contact,
            'additional_contacts' => $contacts->isEmpty() ? null : $contacts->map($describe)->implode('; '),
            'preferred_contact' => $supplier->preferred_contact_id === 'primary' ? 'Contact: '.$supplier->contact : ($preferred ? $describe($preferred) : null),
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
