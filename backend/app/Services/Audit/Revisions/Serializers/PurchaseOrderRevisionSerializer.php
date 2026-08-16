<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\PurchaseOrder;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseOrderRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'purchase_order';
    }

    public function subjectType(): string
    {
        return PurchaseOrder::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof PurchaseOrder) {
            throw new InvalidArgumentException('Purchase order serializer requires a purchase order.');
        }
        $subject->loadMissing([
            'shoppingList', 'supplier', 'vendorGroups.supplier', 'items.fsItem',
            'items.vendorGroup', 'attachments.vendorGroup', 'programProjectActivity',
        ]);
        $groups = $subject->vendorGroups->sortBy('id')->values()->map(fn ($group): array => [
            'key' => strtolower((string) $group->uuid),
            'reference' => strtolower((string) $group->uuid),
            'supplier_reference' => $group->supplier !== null ? strtolower((string) $group->supplier->uuid) : null,
            'supplier' => $group->supplier?->name,
            'or_number' => $group->or_number,
            'status' => (string) $group->status,
            'total' => (float) $group->total_amount,
            'received_at' => $group->received_at?->toISOString(),
            'stocked_at' => $group->stocked_at?->toISOString(),
        ])->all();
        $occurrences = [];
        $lines = $subject->items->sortBy('id')->values()->map(function ($line, int $index) use (&$occurrences): array {
            $catalogReference = $line->fsItem?->uuid;
            $groupReference = $line->vendorGroup?->uuid;
            $base = (is_string($groupReference) ? strtolower($groupReference) : 'ungrouped')
                .'-'.(is_string($catalogReference) ? strtolower($catalogReference) : Str::slug((string) $line->description).'-'.($index + 1));
            $occurrences[$base] = ($occurrences[$base] ?? 0) + 1;

            return [
                'key' => $base.'-'.$occurrences[$base],
                'group_reference' => is_string($groupReference) && Str::isUuid($groupReference) ? strtolower($groupReference) : null,
                'catalog_reference' => is_string($catalogReference) && Str::isUuid($catalogReference) ? strtolower($catalogReference) : null,
                'item' => (string) $line->description,
                'quantity' => (float) $line->qty,
                'unit' => (string) $line->unit,
                'unit_price' => (float) $line->unit_price,
                'total' => (float) $line->total_value,
                'purchase_quantity' => $line->purchase_qty !== null ? (float) $line->purchase_qty : null,
                'purchase_unit' => $line->purchase_unit,
                'purchase_price' => $line->purchase_price !== null ? (float) $line->purchase_price : null,
                'actual_quantity' => $line->actual_qty !== null ? (float) $line->actual_qty : null,
                'actual_unit_price' => $line->actual_unit_price !== null ? (float) $line->actual_unit_price : null,
            ];
        })->all();
        $attachments = $subject->attachments->sortBy('id')->values()->map(fn ($attachment): array => [
            'key' => strtolower((string) $attachment->uuid),
            'reference' => strtolower((string) $attachment->uuid),
            'group_reference' => $attachment->vendorGroup !== null ? strtolower((string) $attachment->vendorGroup->uuid) : null,
            'type' => (string) $attachment->type,
            'caption' => $attachment->caption,
        ])->all();
        $shoppingListReference = $subject->shoppingList?->uuid;
        $supplierReference = $subject->supplier?->uuid;
        $ppa = $subject->programProjectActivity;

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: PurchaseOrder::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: 1,
            payload: [
                'title' => (string) $subject->po_number,
                'reference' => (string) $subject->uuid,
                'po_number' => (string) $subject->po_number,
                'shopping_list_reference' => is_string($shoppingListReference) ? strtolower($shoppingListReference) : null,
                'shopping_list' => $subject->shoppingList?->name,
                'supplier_reference' => is_string($supplierReference) ? strtolower($supplierReference) : null,
                'supplier' => $subject->supplier?->name,
                'or_number' => $subject->or_number,
                'order_date' => $subject->order_date?->toDateString(),
                'received_date' => $subject->received_date?->toDateString(),
                'status' => (string) $subject->status,
                'lifecycle_status' => (string) $subject->lifecycle_status,
                'procurement_track' => (string) ($subject->procurement_track ?? 'food'),
                'total' => (float) $subject->total_amount,
                'actual_budget_per_head_per_day' => $subject->actual_budget_per_head_per_day !== null ? (float) $subject->actual_budget_per_head_per_day : null,
                'converted_at' => $subject->converted_at?->toISOString(),
                'completed_at' => $subject->completed_at?->toISOString(),
                'archived_at' => $subject->archived_at?->toISOString(),
                'ppa' => $ppa === null ? null : [
                    'activity' => (string) $ppa->activity,
                    'target_date_range' => $ppa->target_date_range,
                    'period_start' => $ppa->period_start?->toDateString(),
                    'period_end' => $ppa->period_end?->toDateString(),
                    'estimated_total_cost' => $ppa->estimated_total_cost !== null ? (float) $ppa->estimated_total_cost : null,
                    'estimated_output_patients' => $ppa->estimated_output_patients,
                    'actual_total_cost' => $ppa->actual_total_cost !== null ? (float) $ppa->actual_total_cost : null,
                    'actual_output_patients' => $ppa->actual_output_patients,
                ],
                'groups' => $groups,
                'lines' => $lines,
                'attachments' => $attachments,
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        $this->assertValidPayload($snapshot);
        $ppa = $snapshot['ppa'];
        $fields = [
            new AuditHistoryFieldDto('po_number', 'PO number', new AuditValueDto('reference', $snapshot['po_number'])),
            new AuditHistoryFieldDto('shopping_list', 'Shopping list', new AuditValueDto('text', $snapshot['shopping_list'])),
            new AuditHistoryFieldDto('supplier', 'Supplier', new AuditValueDto('text', $snapshot['supplier'])),
            new AuditHistoryFieldDto('or_number', 'OR number', new AuditValueDto('reference', $snapshot['or_number'])),
            new AuditHistoryFieldDto('order_date', 'Order date', new AuditValueDto('date', $snapshot['order_date'])),
            new AuditHistoryFieldDto('received_date', 'Received date', new AuditValueDto('date', $snapshot['received_date'])),
            new AuditHistoryFieldDto('status', 'Status', new AuditValueDto('enum', $snapshot['status'])),
            new AuditHistoryFieldDto('lifecycle_status', 'Lifecycle status', new AuditValueDto('enum', $snapshot['lifecycle_status'])),
            new AuditHistoryFieldDto('procurement_track', 'Procurement track', new AuditValueDto('enum', $snapshot['procurement_track'])),
            new AuditHistoryFieldDto('total', 'Total', new AuditValueDto('currency', $snapshot['total'], currency: 'PHP')),
            new AuditHistoryFieldDto('actual_budget_per_head_per_day', 'Food purchase cost per served patient-day', new AuditValueDto('currency', $snapshot['actual_budget_per_head_per_day'], currency: 'PHP')),
            new AuditHistoryFieldDto('converted_at', 'Converted at', new AuditValueDto('datetime', $snapshot['converted_at'])),
            new AuditHistoryFieldDto('completed_at', 'Completed at', new AuditValueDto('datetime', $snapshot['completed_at'])),
            new AuditHistoryFieldDto('archived_at', 'Archived at', new AuditValueDto('datetime', $snapshot['archived_at'])),
        ];
        if (is_array($ppa)) {
            $fields[] = new AuditHistoryFieldDto('ppa_activity', 'PPA activity', new AuditValueDto('text', $ppa['activity']));
            $fields[] = new AuditHistoryFieldDto('estimated_output_patients', 'Estimated output patients', new AuditValueDto('number', $ppa['estimated_output_patients']));
            $fields[] = new AuditHistoryFieldDto('actual_output_patients', 'Actual output patients', new AuditValueDto('number', $ppa['actual_output_patients']));
        }

        return new AuditHistorySnapshotDto(
            type: $this->key(), title: $snapshot['title'], reference: $snapshot['reference'], fields: $fields,
            tables: [
                new AuditHistoryTableDto('groups', 'Vendor groups', [
                    'supplier' => 'Supplier', 'or_number' => 'OR number', 'status' => 'Status',
                    'total' => 'Total', 'received_at' => 'Received at', 'stocked_at' => 'Stocked at',
                ], array_map(fn (array $group) => new AuditHistoryTableRowDto($group['key'], [
                    'supplier' => new AuditValueDto('text', $group['supplier']),
                    'or_number' => new AuditValueDto('reference', $group['or_number']),
                    'status' => new AuditValueDto('enum', $group['status']),
                    'total' => new AuditValueDto('currency', $group['total'], currency: 'PHP'),
                    'received_at' => new AuditValueDto('datetime', $group['received_at']),
                    'stocked_at' => new AuditValueDto('datetime', $group['stocked_at']),
                ]), $snapshot['groups'])),
                new AuditHistoryTableDto('lines', 'Order lines', [
                    'item' => 'Item', 'quantity' => 'Quantity', 'unit' => 'Unit', 'unit_price' => 'Unit price',
                    'total' => 'Total', 'purchase_quantity' => 'Purchase quantity',
                    'purchase_unit' => 'Purchase unit', 'purchase_price' => 'Purchase price',
                    'actual_quantity' => 'Actual quantity', 'actual_unit_price' => 'Actual unit price',
                ], array_map(fn (array $line) => new AuditHistoryTableRowDto($line['key'], [
                    'item' => new AuditValueDto('text', $line['item']),
                    'quantity' => new AuditValueDto('number', $line['quantity']),
                    'unit' => new AuditValueDto('text', $line['unit']),
                    'unit_price' => new AuditValueDto('currency', $line['unit_price'], currency: 'PHP'),
                    'total' => new AuditValueDto('currency', $line['total'], currency: 'PHP'),
                    'purchase_quantity' => new AuditValueDto('number', $line['purchase_quantity']),
                    'purchase_unit' => new AuditValueDto('text', $line['purchase_unit']),
                    'purchase_price' => new AuditValueDto('currency', $line['purchase_price'], currency: 'PHP'),
                    'actual_quantity' => new AuditValueDto('number', $line['actual_quantity']),
                    'actual_unit_price' => new AuditValueDto('currency', $line['actual_unit_price'], currency: 'PHP'),
                ]), $snapshot['lines'])),
                new AuditHistoryTableDto('attachments', 'Attachment metadata', [
                    'type' => 'Type', 'caption' => 'Caption',
                ], array_map(fn (array $attachment) => new AuditHistoryTableRowDto($attachment['key'], [
                    'type' => new AuditValueDto('enum', $attachment['type']),
                    'caption' => new AuditValueDto('text', $attachment['caption']),
                ]), $snapshot['attachments'])),
            ],
        );
    }

    private function assertValidPayload(array $snapshot): void
    {
        $keys = ['title', 'reference', 'po_number', 'shopping_list_reference', 'shopping_list', 'supplier_reference', 'supplier', 'or_number', 'order_date', 'received_date', 'status', 'lifecycle_status', 'procurement_track', 'total', 'actual_budget_per_head_per_day', 'converted_at', 'completed_at', 'archived_at', 'ppa', 'groups', 'lines', 'attachments'];
        $valid = $this->exact($snapshot, $keys)
            && is_string($snapshot['title']) && trim($snapshot['title']) !== ''
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && is_string($snapshot['po_number']) && trim($snapshot['po_number']) !== ''
            && $this->uuidOrNull($snapshot['shopping_list_reference']) && $this->textOrNull($snapshot['shopping_list'])
            && $this->uuidOrNull($snapshot['supplier_reference']) && $this->textOrNull($snapshot['supplier'])
            && $this->textOrNull($snapshot['or_number']) && $this->date($snapshot['order_date']) && $this->date($snapshot['received_date'])
            && $this->token($snapshot['status']) && $this->token($snapshot['lifecycle_status']) && $this->token($snapshot['procurement_track'])
            && $this->number($snapshot['total']) && ($snapshot['actual_budget_per_head_per_day'] === null || $this->number($snapshot['actual_budget_per_head_per_day']))
            && $this->datetime($snapshot['converted_at']) && $this->datetime($snapshot['completed_at']) && $this->datetime($snapshot['archived_at'])
            && ($snapshot['ppa'] === null || $this->validPpa($snapshot['ppa']))
            && is_array($snapshot['groups']) && array_is_list($snapshot['groups']) && collect($snapshot['groups'])->every(fn ($v) => $this->validGroup($v))
            && is_array($snapshot['lines']) && array_is_list($snapshot['lines']) && collect($snapshot['lines'])->every(fn ($v) => $this->validLine($v))
            && is_array($snapshot['attachments']) && array_is_list($snapshot['attachments']) && collect($snapshot['attachments'])->every(fn ($v) => $this->validAttachment($v));
        if (! $valid) {
            throw new InvalidArgumentException('Invalid purchase order revision payload.');
        }
    }

    private function validGroup(mixed $v): bool
    {
        return is_array($v) && $this->exact($v, ['key', 'reference', 'supplier_reference', 'supplier', 'or_number', 'status', 'total', 'received_at', 'stocked_at']) && Str::isUuid($v['key']) && Str::isUuid($v['reference']) && $this->uuidOrNull($v['supplier_reference']) && $this->textOrNull($v['supplier']) && $this->textOrNull($v['or_number']) && $this->token($v['status']) && $this->number($v['total']) && $this->datetime($v['received_at']) && $this->datetime($v['stocked_at']);
    }

    private function validPpa(mixed $value): bool
    {
        return is_array($value)
            && $this->exact($value, [
                'activity', 'target_date_range', 'period_start', 'period_end',
                'estimated_total_cost', 'estimated_output_patients',
                'actual_total_cost', 'actual_output_patients',
            ])
            && is_string($value['activity']) && trim($value['activity']) !== '' && mb_strlen($value['activity']) <= 255
            && $this->boundedTextOrNull($value['target_date_range'], 64)
            && $this->date($value['period_start']) && $this->date($value['period_end'])
            && ($value['estimated_total_cost'] === null || $this->number($value['estimated_total_cost']))
            && ($value['actual_total_cost'] === null || $this->number($value['actual_total_cost']))
            && ($value['estimated_output_patients'] === null || (is_int($value['estimated_output_patients']) && $value['estimated_output_patients'] >= 0))
            && ($value['actual_output_patients'] === null || (is_int($value['actual_output_patients']) && $value['actual_output_patients'] >= 0));
    }

    private function validLine(mixed $v): bool
    {
        return is_array($v) && $this->exact($v, ['key', 'group_reference', 'catalog_reference', 'item', 'quantity', 'unit', 'unit_price', 'total', 'purchase_quantity', 'purchase_unit', 'purchase_price', 'actual_quantity', 'actual_unit_price']) && is_string($v['key']) && trim($v['key']) !== '' && $this->uuidOrNull($v['group_reference']) && $this->uuidOrNull($v['catalog_reference']) && is_string($v['item']) && is_string($v['unit']) && $this->number($v['quantity']) && $this->number($v['unit_price']) && $this->number($v['total']) && ($v['purchase_quantity'] === null || $this->number($v['purchase_quantity'])) && $this->textOrNull($v['purchase_unit']) && ($v['purchase_price'] === null || $this->number($v['purchase_price'])) && ($v['actual_quantity'] === null || $this->number($v['actual_quantity'])) && ($v['actual_unit_price'] === null || $this->number($v['actual_unit_price']));
    }

    private function validAttachment(mixed $v): bool
    {
        return is_array($v) && $this->exact($v, ['key', 'reference', 'group_reference', 'type', 'caption']) && Str::isUuid($v['key']) && Str::isUuid($v['reference']) && $this->uuidOrNull($v['group_reference']) && $this->token($v['type']) && $this->textOrNull($v['caption']);
    }

    private function exact(array $v, array $e): bool
    {
        $a = array_keys($v);
        sort($a);
        sort($e);

        return $a === $e;
    }

    private function uuidOrNull(mixed $v): bool
    {
        return $v === null || (is_string($v) && Str::isUuid($v));
    }

    private function textOrNull(mixed $v): bool
    {
        return $v === null || is_string($v);
    }

    private function boundedTextOrNull(mixed $value, int $maximum): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maximum);
    }

    private function token(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $v) === 1;
    }

    private function date(mixed $v): bool
    {
        return $v === null || (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) === 1);
    }

    private function datetime(mixed $v): bool
    {
        return $v === null || (is_string($v) && mb_strlen($v) <= 40);
    }

    private function number(mixed $v): bool
    {
        return is_int($v) || is_float($v);
    }
}
