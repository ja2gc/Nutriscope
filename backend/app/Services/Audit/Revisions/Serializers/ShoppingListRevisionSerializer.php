<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\ShoppingList;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ShoppingListRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'shopping_list';
    }

    public function subjectType(): string
    {
        return ShoppingList::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof ShoppingList) {
            throw new InvalidArgumentException('Shopping list serializer requires a shopping list.');
        }
        $subject->loadMissing('items.fsItem', 'items.supplier');
        $items = $subject->items->sortBy('id')->values()->map(function ($item): array {
            $catalogReference = $item->fsItem?->uuid;
            $supplierReference = $item->supplier?->uuid;

            return [
                'key' => strtolower((string) $item->uuid),
                'reference' => strtolower((string) $item->uuid),
                'catalog_reference' => is_string($catalogReference) && Str::isUuid($catalogReference) ? strtolower($catalogReference) : null,
                'item' => (string) $item->ingredient_name,
                'item_type' => (string) ($item->fsItem?->kind ?? 'ingredient'),
                'quantity' => (float) $item->qty,
                'unit' => (string) $item->unit,
                'supplier_reference' => is_string($supplierReference) && Str::isUuid($supplierReference) ? strtolower($supplierReference) : null,
                'supplier' => $item->supplier?->name,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
                'purchase_quantity' => $item->purchase_qty !== null ? (float) $item->purchase_qty : null,
                'purchase_unit' => $item->purchase_unit,
                'purchase_price' => $item->purchase_price !== null ? (float) $item->purchase_price : null,
                'vendor_locked' => $item->vendor_locked_at !== null,
                'baseline_servings' => $item->baseline_servings,
                'baseline_quantity' => $item->baseline_quantity !== null ? (float) $item->baseline_quantity : null,
                'scaled_quantity' => $item->scaled_quantity !== null ? (float) $item->scaled_quantity : null,
                'scaled_unit' => $item->scaled_unit,
            ];
        })->all();
        $total = (float) $subject->items->sum(fn ($item): float => (float) $item->total);
        $days = $subject->period_start !== null && $subject->period_end !== null
            ? $subject->period_start->diffInDays($subject->period_end) + 1
            : (int) ($subject->days_span ?? 0);
        $population = $subject->estimate_population;

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: ShoppingList::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: 1,
            payload: [
                'title' => (string) $subject->name,
                'name' => (string) $subject->name,
                'reference' => (string) $subject->uuid,
                'list_date' => $subject->list_date?->toDateString(),
                'period_start' => $subject->period_start?->toDateString(),
                'period_end' => $subject->period_end?->toDateString(),
                'days_span' => (int) ($subject->days_span ?? $days),
                'list_type' => (string) $subject->list_type,
                'procurement_track' => (string) ($subject->procurement_track ?? 'food'),
                'status' => (string) $subject->status,
                'coverage_status' => $subject->coverage_status,
                'estimate_population' => $population,
                'total_served_population' => $subject->total_served_population,
                'totals' => [
                    'item_count' => count($items),
                    'total' => $total,
                    'estimated_budget_per_head_per_day' => $population !== null && $population > 0 && $days > 0
                        ? round($total / ($population * $days), 2)
                        : null,
                ],
                'items' => $items,
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        $this->assertValidPayload($snapshot);
        $totals = $snapshot['totals'];

        return new AuditHistorySnapshotDto(
            type: $this->key(),
            title: $snapshot['title'],
            reference: $snapshot['reference'],
            fields: [
                new AuditHistoryFieldDto('name', 'Name', new AuditValueDto('text', $snapshot['name'])),
                new AuditHistoryFieldDto('list_date', 'List date', new AuditValueDto('date', $snapshot['list_date'])),
                new AuditHistoryFieldDto('period_start', 'Period start', new AuditValueDto('date', $snapshot['period_start'])),
                new AuditHistoryFieldDto('period_end', 'Period end', new AuditValueDto('date', $snapshot['period_end'])),
                new AuditHistoryFieldDto('days_span', 'Days', new AuditValueDto('number', $snapshot['days_span'])),
                new AuditHistoryFieldDto('list_type', 'List type', new AuditValueDto('enum', $snapshot['list_type'])),
                new AuditHistoryFieldDto('procurement_track', 'Procurement track', new AuditValueDto('enum', $snapshot['procurement_track'])),
                new AuditHistoryFieldDto('status', 'Status', new AuditValueDto('enum', $snapshot['status'])),
                new AuditHistoryFieldDto('coverage_status', 'Coverage', new AuditValueDto('enum', $snapshot['coverage_status'])),
                new AuditHistoryFieldDto('estimate_population', 'Estimated population', new AuditValueDto('number', $snapshot['estimate_population'])),
                new AuditHistoryFieldDto('total_served_population', 'Served population', new AuditValueDto('number', $snapshot['total_served_population'])),
                new AuditHistoryFieldDto('item_count', 'Items', new AuditValueDto('number', $totals['item_count'])),
                new AuditHistoryFieldDto('total', 'Estimated total', new AuditValueDto('currency', $totals['total'], currency: 'PHP')),
                new AuditHistoryFieldDto('estimated_budget_per_head_per_day', 'Estimated cost per head/day', new AuditValueDto('currency', $totals['estimated_budget_per_head_per_day'], currency: 'PHP')),
            ],
            tables: [new AuditHistoryTableDto(
                key: 'items',
                label: 'Shopping list items',
                columns: [
                    'item' => 'Item', 'item_type' => 'Type', 'quantity' => 'Quantity', 'unit' => 'Unit',
                    'supplier' => 'Supplier', 'unit_price' => 'Unit price', 'total' => 'Total',
                    'purchase_quantity' => 'Purchase quantity', 'purchase_unit' => 'Purchase unit',
                    'purchase_price' => 'Purchase price', 'vendor_locked' => 'Vendor locked',
                ],
                rows: array_map(fn (array $item): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                    key: $item['key'],
                    values: [
                        'item' => new AuditValueDto('text', $item['item']),
                        'item_type' => new AuditValueDto('enum', $item['item_type']),
                        'quantity' => new AuditValueDto('number', $item['quantity']),
                        'unit' => new AuditValueDto('text', $item['unit']),
                        'supplier' => new AuditValueDto('text', $item['supplier']),
                        'unit_price' => new AuditValueDto('currency', $item['unit_price'], currency: 'PHP'),
                        'total' => new AuditValueDto('currency', $item['total'], currency: 'PHP'),
                        'purchase_quantity' => new AuditValueDto('number', $item['purchase_quantity']),
                        'purchase_unit' => new AuditValueDto('text', $item['purchase_unit']),
                        'purchase_price' => new AuditValueDto('currency', $item['purchase_price'], currency: 'PHP'),
                        'vendor_locked' => new AuditValueDto('boolean', $item['vendor_locked']),
                    ],
                ), $snapshot['items']),
            )],
        );
    }

    private function assertValidPayload(array $snapshot): void
    {
        $keys = ['title', 'name', 'reference', 'list_date', 'period_start', 'period_end', 'days_span', 'list_type', 'procurement_track', 'status', 'coverage_status', 'estimate_population', 'total_served_population', 'totals', 'items'];
        $valid = $this->exact($snapshot, $keys)
            && is_string($snapshot['title']) && trim($snapshot['title']) !== ''
            && is_string($snapshot['name']) && trim($snapshot['name']) !== ''
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && $this->date($snapshot['list_date']) && $this->date($snapshot['period_start']) && $this->date($snapshot['period_end'])
            && is_int($snapshot['days_span']) && $snapshot['days_span'] >= 0
            && $this->token($snapshot['list_type']) && $this->token($snapshot['procurement_track']) && $this->token($snapshot['status'])
            && ($snapshot['coverage_status'] === null || $this->token($snapshot['coverage_status']))
            && ($snapshot['estimate_population'] === null || is_int($snapshot['estimate_population']))
            && ($snapshot['total_served_population'] === null || is_int($snapshot['total_served_population']))
            && is_array($snapshot['totals']) && $this->exact($snapshot['totals'], ['item_count', 'total', 'estimated_budget_per_head_per_day'])
            && is_int($snapshot['totals']['item_count']) && $this->number($snapshot['totals']['total'])
            && ($snapshot['totals']['estimated_budget_per_head_per_day'] === null || $this->number($snapshot['totals']['estimated_budget_per_head_per_day']))
            && is_array($snapshot['items']) && array_is_list($snapshot['items'])
            && collect($snapshot['items'])->every(fn ($item): bool => $this->validItem($item));
        if (! $valid) {
            throw new InvalidArgumentException('Invalid shopping list revision payload.');
        }
    }

    private function validItem(mixed $item): bool
    {
        $keys = ['key', 'reference', 'catalog_reference', 'item', 'item_type', 'quantity', 'unit', 'supplier_reference', 'supplier', 'unit_price', 'total', 'purchase_quantity', 'purchase_unit', 'purchase_price', 'vendor_locked', 'baseline_servings', 'baseline_quantity', 'scaled_quantity', 'scaled_unit'];

        return is_array($item) && $this->exact($item, $keys)
            && is_string($item['key']) && Str::isUuid($item['key'])
            && is_string($item['reference']) && Str::isUuid($item['reference'])
            && ($item['catalog_reference'] === null || Str::isUuid($item['catalog_reference']))
            && is_string($item['item']) && trim($item['item']) !== '' && $this->token($item['item_type'])
            && $this->number($item['quantity']) && is_string($item['unit'])
            && ($item['supplier_reference'] === null || Str::isUuid($item['supplier_reference']))
            && ($item['supplier'] === null || is_string($item['supplier']))
            && $this->number($item['unit_price']) && $this->number($item['total'])
            && ($item['purchase_quantity'] === null || $this->number($item['purchase_quantity']))
            && ($item['purchase_unit'] === null || is_string($item['purchase_unit']))
            && ($item['purchase_price'] === null || $this->number($item['purchase_price']))
            && is_bool($item['vendor_locked'])
            && ($item['baseline_servings'] === null || is_int($item['baseline_servings']))
            && ($item['baseline_quantity'] === null || $this->number($item['baseline_quantity']))
            && ($item['scaled_quantity'] === null || $this->number($item['scaled_quantity']))
            && ($item['scaled_unit'] === null || is_string($item['scaled_unit']));
    }

    private function exact(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    private function date(mixed $value): bool
    {
        return $value === null || (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1);
    }

    private function token(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $value) === 1;
    }

    private function number(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}
