<?php

namespace App\Services\Audit;

use App\Models\FsItem;

final class FsItemAuditValues
{
    /** @return array<string, string|float|bool|null> */
    public function values(FsItem $item): array
    {
        $item->loadMissing('defaultSupplier');

        return [
            'name' => (string) $item->name,
            'kind' => (string) $item->kind,
            'include_in_generated_lists' => (bool) $item->include_in_generated_lists,
            'category' => $item->category,
            'base_unit' => (string) $item->base_unit,
            'purchase_unit' => (string) $item->purchase_unit,
            'purchase_price' => (float) $item->purchase_price,
            'units_per_purchase' => (float) $item->units_per_purchase,
            'unit_cost' => (float) $item->unit_cost,
            'vendor' => $item->defaultSupplier?->name,
            'vendor_locked' => $item->vendorLocked(),
            'is_active' => (bool) $item->is_active,
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
