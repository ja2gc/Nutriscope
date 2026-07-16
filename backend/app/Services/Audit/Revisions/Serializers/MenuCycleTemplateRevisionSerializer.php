<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\MenuCycleTemplate;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuCycleTemplateRevisionSerializer implements AuditRevisionSerializer
{
    public function key(): string
    {
        return 'menu_cycle_template';
    }

    public function subjectType(): string
    {
        return MenuCycleTemplate::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof MenuCycleTemplate) {
            throw new InvalidArgumentException('Menu cycle template serializer requires a menu cycle template.');
        }
        $subject->loadMissing(['days.recipe', 'days.fsItem']);
        $occurrences = [];
        $slots = $subject->days->values()->map(function ($day, int $index) use (&$occurrences): array {
            $record = $day->recipe ?? $day->fsItem;
            $reference = $record?->uuid;
            $itemType = $day->recipe_id !== null ? 'recipe' : ($day->fs_item_id !== null ? 'catalog_item' : 'unavailable');
            $baseKey = Str::lower($day->day_of_week.'-'.$day->meal_type.'-'.($reference ?? $index + 1));
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;

            return [
                'key' => $baseKey.'-'.$occurrences[$baseKey],
                'day' => (string) $day->day_of_week,
                'meal' => (string) $day->meal_type,
                'item_type' => $itemType,
                'item' => $record?->name ?? 'Unavailable item',
                'reference' => is_string($reference) && Str::isUuid($reference) ? Str::lower($reference) : null,
                'quantity' => (float) $day->quantity,
                'unit' => $day->recipe_id !== null ? 'serving' : $day->fsItem?->base_unit,
            ];
        })->sortBy(fn (array $slot): string => $slot['day'].'-'.$slot['meal'].'-'.$slot['key'])->values()->all();

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: MenuCycleTemplate::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: $this->schemaVersion(),
            payload: [
                'title' => (string) $subject->name,
                'name' => (string) $subject->name,
                'reference' => (string) $subject->uuid,
                'cycle_days' => (int) $subject->cycle_days,
                'slots' => $slots,
            ],
        );
    }

    public function present(array $snapshot): AuditHistorySnapshotDto
    {
        $this->assertValidPayload($snapshot);

        return new AuditHistorySnapshotDto(
            type: $this->key(),
            title: $snapshot['title'],
            reference: $snapshot['reference'],
            fields: [
                new AuditHistoryFieldDto('name', 'Name', new AuditValueDto('text', $snapshot['name'])),
                new AuditHistoryFieldDto('cycle_days', 'Cycle days', new AuditValueDto('number', $snapshot['cycle_days'])),
            ],
            tables: [
                new AuditHistoryTableDto(
                    key: 'slots',
                    label: 'Planned meals',
                    columns: [
                        'day' => 'Day',
                        'meal' => 'Meal',
                        'item_type' => 'Item type',
                        'item' => 'Item',
                        'quantity' => 'Quantity',
                        'unit' => 'Unit',
                    ],
                    rows: array_map(fn (array $slot): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                        key: $slot['key'],
                        values: [
                            'day' => new AuditValueDto('enum', $slot['day']),
                            'meal' => new AuditValueDto('enum', $slot['meal']),
                            'item_type' => new AuditValueDto('enum', $slot['item_type']),
                            'item' => new AuditValueDto('text', $slot['item']),
                            'quantity' => new AuditValueDto('number', $slot['quantity']),
                            'unit' => new AuditValueDto('text', $slot['unit']),
                        ],
                    ), $snapshot['slots']),
                ),
            ],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function assertValidPayload(array $snapshot): void
    {
        $valid = $this->hasExactKeys($snapshot, ['title', 'name', 'reference', 'cycle_days', 'slots'])
            && is_string($snapshot['title']) && trim($snapshot['title']) !== ''
            && is_string($snapshot['name']) && trim($snapshot['name']) !== ''
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && is_int($snapshot['cycle_days']) && $snapshot['cycle_days'] > 0
            && is_array($snapshot['slots']) && array_is_list($snapshot['slots'])
            && collect($snapshot['slots'])->every(fn (mixed $slot): bool => is_array($slot)
                && $this->hasExactKeys($slot, ['key', 'day', 'meal', 'item_type', 'item', 'reference', 'quantity', 'unit'])
                && is_string($slot['key']) && preg_match('/^[a-z0-9_.:-]{1,255}$/D', $slot['key']) === 1
                && is_string($slot['day']) && in_array($slot['day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true)
                && is_string($slot['meal']) && preg_match('/^[a-z_]{1,32}$/D', $slot['meal']) === 1
                && in_array($slot['item_type'], ['recipe', 'catalog_item', 'unavailable'], true)
                && is_string($slot['item']) && trim($slot['item']) !== ''
                && ($slot['reference'] === null || (is_string($slot['reference']) && Str::isUuid($slot['reference'])))
                && (is_int($slot['quantity']) || is_float($slot['quantity'])) && $slot['quantity'] >= 0
                && ($slot['unit'] === null || (is_string($slot['unit']) && trim($slot['unit']) !== '')));

        if (! $valid) {
            throw new InvalidArgumentException('Invalid menu cycle template revision payload.');
        }
    }

    /** @param list<string> $expected */
    private function hasExactKeys(array $value, array $expected): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
