<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\Budget;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BudgetRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'budget';
    }

    public function subjectType(): string
    {
        return Budget::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof Budget) {
            throw new InvalidArgumentException('Budget serializer requires a budget.');
        }
        $subject->loadMissing(['ledgerEntries.purchaseOrder', 'ledgerEntries.creator']);
        $balance = (float) $subject->allocated_amount;
        $occurrences = [];
        $ledger = $subject->ledgerEntries
            ->sortBy(fn ($entry): string => ($entry->created_at?->format('YmdHis.u') ?? '').'-'.str_pad((string) $entry->id, 20, '0', STR_PAD_LEFT))
            ->values()
            ->map(function ($entry) use (&$balance, &$occurrences): array {
                $signed = $entry->signedAmount();
                $balance = round($balance + $signed, 2);
                $occurredAt = $entry->created_at?->toISOString();
                $safeKeySource = implode('|', [
                    $entry->type,
                    $entry->source,
                    number_format((float) $entry->amount, 2, '.', ''),
                    (string) $entry->reference,
                    (string) $entry->purchaseOrder?->uuid,
                    (string) $occurredAt,
                ]);
                $occurrences[$safeKeySource] = ($occurrences[$safeKeySource] ?? 0) + 1;

                return [
                    'key' => hash('sha256', $safeKeySource.'|'.$occurrences[$safeKeySource]),
                    'type' => (string) $entry->type,
                    'source' => (string) $entry->source,
                    'amount' => (float) $entry->amount,
                    'signed_amount' => $signed,
                    'reason' => $entry->reason,
                    'reference' => $entry->reference,
                    'purchase_order_reference' => $entry->purchaseOrder?->uuid,
                    'purchase_order' => $entry->purchaseOrder?->po_number,
                    'actor_reference' => $entry->creator?->uuid,
                    'actor' => $entry->creator?->display_name,
                    'occurred_at' => $occurredAt,
                    'balance_after' => $balance,
                ];
            })->all();
        $entries = $subject->ledgerEntries;
        $manualAdditions = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $manualDeductions = (float) $entries->where('type', 'manual_deduction')->sum('amount');
        $poDeductions = (float) $entries->where('type', 'po_deduction')->sum('amount');

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: Budget::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: 1,
            payload: [
                'title' => "FY {$subject->fiscal_year} Budget",
                'reference' => (string) $subject->uuid,
                'fiscal_year' => (int) $subject->fiscal_year,
                'allocated_amount' => (float) $subject->allocated_amount,
                'per_head_day_limit' => $subject->per_head_day_limit !== null ? (float) $subject->per_head_day_limit : null,
                'totals' => [
                    'entry_count' => count($ledger),
                    'manual_additions' => $manualAdditions,
                    'manual_deductions' => $manualDeductions,
                    'po_deductions' => $poDeductions,
                    'remaining_balance' => round((float) $subject->allocated_amount + $manualAdditions - $manualDeductions - $poDeductions, 2),
                ],
                'ledger' => $ledger,
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
                new AuditHistoryFieldDto('fiscal_year', 'Fiscal year', new AuditValueDto('number', $snapshot['fiscal_year'])),
                new AuditHistoryFieldDto('allocated_amount', 'Opening allocation', new AuditValueDto('currency', $snapshot['allocated_amount'], currency: 'PHP')),
                new AuditHistoryFieldDto('per_head_day_limit', 'Budget per head/day', new AuditValueDto('currency', $snapshot['per_head_day_limit'], currency: 'PHP')),
                new AuditHistoryFieldDto('entry_count', 'Ledger entries', new AuditValueDto('number', $totals['entry_count'])),
                new AuditHistoryFieldDto('manual_additions', 'Manual additions', new AuditValueDto('currency', $totals['manual_additions'], currency: 'PHP')),
                new AuditHistoryFieldDto('manual_deductions', 'Manual deductions', new AuditValueDto('currency', $totals['manual_deductions'], currency: 'PHP')),
                new AuditHistoryFieldDto('po_deductions', 'PO deductions', new AuditValueDto('currency', $totals['po_deductions'], currency: 'PHP')),
                new AuditHistoryFieldDto('remaining_balance', 'Remaining balance', new AuditValueDto('currency', $totals['remaining_balance'], currency: 'PHP')),
            ],
            tables: [new AuditHistoryTableDto(
                key: 'ledger',
                label: 'Budget ledger',
                columns: [
                    'type' => 'Type', 'source' => 'Source', 'amount' => 'Amount', 'signed_amount' => 'Signed amount',
                    'reason' => 'Reason', 'reference' => 'Reference', 'purchase_order' => 'Purchase order',
                    'actor' => 'Actor', 'occurred_at' => 'Occurred at', 'balance_after' => 'Balance after',
                ],
                rows: array_map(fn (array $entry): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                    key: $entry['key'],
                    values: [
                        'type' => new AuditValueDto('enum', $entry['type']),
                        'source' => new AuditValueDto('enum', $entry['source']),
                        'amount' => new AuditValueDto('currency', $entry['amount'], currency: 'PHP'),
                        'signed_amount' => new AuditValueDto('currency', $entry['signed_amount'], currency: 'PHP'),
                        'reason' => new AuditValueDto('text', $entry['reason']),
                        'reference' => new AuditValueDto('reference', $entry['reference']),
                        'purchase_order' => new AuditValueDto('reference', $entry['purchase_order']),
                        'actor' => new AuditValueDto('text', $entry['actor']),
                        'occurred_at' => new AuditValueDto('datetime', $entry['occurred_at']),
                        'balance_after' => new AuditValueDto('currency', $entry['balance_after'], currency: 'PHP'),
                    ],
                ), $snapshot['ledger']),
            )],
        );
    }

    private function assertValidPayload(array $snapshot): void
    {
        $valid = $this->exact($snapshot, ['title', 'reference', 'fiscal_year', 'allocated_amount', 'per_head_day_limit', 'totals', 'ledger'])
            && is_string($snapshot['title']) && preg_match('/^FY \d{4} Budget$/D', $snapshot['title']) === 1
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && is_int($snapshot['fiscal_year']) && $snapshot['fiscal_year'] >= 1900 && $snapshot['fiscal_year'] <= 9999
            && $this->number($snapshot['allocated_amount'])
            && ($snapshot['per_head_day_limit'] === null || $this->number($snapshot['per_head_day_limit']))
            && $this->validTotals($snapshot['totals'])
            && is_array($snapshot['ledger']) && array_is_list($snapshot['ledger'])
            && collect($snapshot['ledger'])->every(fn ($entry): bool => $this->validLedgerEntry($entry));
        if (! $valid) {
            throw new InvalidArgumentException('Invalid budget revision payload.');
        }
    }

    private function validTotals(mixed $value): bool
    {
        return is_array($value)
            && $this->exact($value, ['entry_count', 'manual_additions', 'manual_deductions', 'po_deductions', 'remaining_balance'])
            && is_int($value['entry_count']) && $value['entry_count'] >= 0
            && $this->number($value['manual_additions']) && $this->number($value['manual_deductions'])
            && $this->number($value['po_deductions']) && $this->number($value['remaining_balance']);
    }

    private function validLedgerEntry(mixed $value): bool
    {
        return is_array($value)
            && $this->exact($value, [
                'key', 'type', 'source', 'amount', 'signed_amount', 'reason', 'reference',
                'purchase_order_reference', 'purchase_order', 'actor_reference', 'actor',
                'occurred_at', 'balance_after',
            ])
            && is_string($value['key']) && preg_match('/^[a-f0-9]{64}$/D', $value['key']) === 1
            && in_array($value['type'], ['po_deduction', 'manual_addition', 'manual_deduction'], true)
            && in_array($value['source'], ['system', 'manual'], true)
            && $this->number($value['amount']) && $this->number($value['signed_amount'])
            && $this->boundedTextOrNull($value['reason'], 1000) && $this->boundedTextOrNull($value['reference'], 255)
            && $this->uuidOrNull($value['purchase_order_reference']) && $this->boundedTextOrNull($value['purchase_order'], 255)
            && $this->uuidOrNull($value['actor_reference']) && $this->boundedTextOrNull($value['actor'], 255)
            && is_string($value['occurred_at']) && mb_strlen($value['occurred_at']) <= 40
            && $this->number($value['balance_after']);
    }

    private function exact(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }

    private function number(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function boundedTextOrNull(mixed $value, int $maximum): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $maximum);
    }

    private function uuidOrNull(mixed $value): bool
    {
        return $value === null || (is_string($value) && Str::isUuid($value));
    }
}
