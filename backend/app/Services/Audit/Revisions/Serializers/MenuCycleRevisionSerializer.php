<?php

namespace App\Services\Audit\Revisions\Serializers;

use App\Data\AuditHistoryFieldDto;
use App\Data\AuditHistorySnapshotDto;
use App\Data\AuditHistoryTableDto;
use App\Data\AuditHistoryTableRowDto;
use App\Data\AuditRevisionSnapshot;
use App\Data\AuditValueDto;
use App\Models\MenuCycle;
use App\Services\Audit\Contracts\AuditRevisionSerializer;
use App\Services\MenuCycleCostService;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MenuCycleRevisionSerializer implements AuditRevisionSerializer
{
    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    private const MEALS = ['breakfast', 'am_snack', 'lunch', 'pm_snack', 'dinner'];

    public function key(): string
    {
        return 'menu_cycle';
    }

    public function subjectType(): string
    {
        return MenuCycle::class;
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function capture(Model $subject): AuditRevisionSnapshot
    {
        if (! $subject instanceof MenuCycle) {
            throw new InvalidArgumentException('Menu cycle serializer requires a menu cycle.');
        }
        $subject->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');
        $cost = MenuCycleCostService::forCycle($subject);
        $dayRanks = array_flip(self::WEEKDAYS);
        $mealRanks = array_flip(self::MEALS);
        $occurrences = [];
        $orderedDays = $subject->days->sort(function ($left, $right) use ($dayRanks, $mealRanks): int {
            return [
                $dayRanks[$left->day_of_week] ?? PHP_INT_MAX,
                $mealRanks[$left->meal_type] ?? PHP_INT_MAX,
                $left->id,
            ] <=> [
                $dayRanks[$right->day_of_week] ?? PHP_INT_MAX,
                $mealRanks[$right->meal_type] ?? PHP_INT_MAX,
                $right->id,
            ];
        })->values();

        $slots = $orderedDays->map(function ($day, int $index) use (&$occurrences): array {
            $record = $day->recipe ?? $day->fsItem;
            $reference = $record?->uuid;
            $itemType = $day->recipe !== null ? 'recipe' : ($day->fsItem !== null ? 'catalog_item' : 'unavailable');
            $baseKey = Str::slug((string) $day->day_of_week).'-'.Str::slug((string) $day->meal_type).'-'.(
                is_string($reference) && Str::isUuid($reference) ? strtolower($reference) : 'slot-'.($index + 1)
            );
            $occurrences[$baseKey] = ($occurrences[$baseKey] ?? 0) + 1;

            return [
                'key' => $baseKey.'-'.$occurrences[$baseKey],
                'day' => (string) $day->day_of_week,
                'meal' => (string) $day->meal_type,
                'item_type' => $itemType,
                'item' => $record?->name ?? 'Unavailable planned item',
                'reference' => is_string($reference) && Str::isUuid($reference) ? strtolower($reference) : null,
                'quantity' => (float) $day->quantity,
                'servings' => $day->servings_override ?? $day->estimate_population,
                'estimated_population' => $day->estimate_population,
                'is_event' => (bool) $day->is_event,
                'event_allocation' => $day->event_allocation !== null ? (float) $day->event_allocation : null,
            ];
        })->all();

        $populations = $subject->days
            ->filter(fn ($day): bool => $day->recipe !== null || $day->fsItem !== null)
            ->groupBy('day_of_week')
            ->map(function ($days): int {
                $firstRecorded = $days->first(fn ($day): bool => $day->estimate_population !== null);

                return (int) ($firstRecorded?->estimate_population ?? 0);
            });
        $dayTotals = collect(self::WEEKDAYS)->map(function (string $day) use ($cost, $populations): ?array {
            $total = $cost['days'][$day] ?? null;
            if (! is_array($total)) {
                return null;
            }

            return [
                'key' => Str::slug($day),
                'day' => $day,
                'population' => (int) ($populations[$day] ?? 0),
                'cost' => (float) ($total['cost'] ?? 0),
                'cost_per_head' => (float) ($total['cost_per_head'] ?? 0),
            ];
        })->filter()->values()->all();

        $weekStart = $subject->week_start_date?->toDateString();
        $cycleDays = (int) ($subject->cycle_days ?: 7);

        return new AuditRevisionSnapshot(
            serializer: $this->key(),
            subjectType: MenuCycle::class,
            subjectPublicId: (string) $subject->uuid,
            schemaVersion: $this->schemaVersion(),
            payload: [
                'title' => (string) $subject->name,
                'name' => (string) $subject->name,
                'reference' => (string) $subject->uuid,
                'cycle_days' => $cycleDays,
                'week_start_date' => $weekStart,
                'week_end_date' => $weekStart !== null
                    ? $subject->week_start_date->copy()->addDays($cycleDays - 1)->toDateString()
                    : null,
                'status' => (string) $subject->status,
                'is_active' => (bool) $subject->is_active,
                'activation_date' => $subject->activation_date?->toDateString(),
                'totals' => [
                    'population' => (int) $cost['population'],
                    'total_cost' => (float) $cost['total_cost'],
                    'cost_per_head' => (float) $cost['cost_per_head'],
                ],
                'slots' => $slots,
                'day_totals' => $dayTotals,
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
                new AuditHistoryFieldDto('week_start_date', 'Week start', new AuditValueDto('date', $snapshot['week_start_date'])),
                new AuditHistoryFieldDto('week_end_date', 'Week end', new AuditValueDto('date', $snapshot['week_end_date'])),
                new AuditHistoryFieldDto('status', 'Status', new AuditValueDto('enum', $snapshot['status'])),
                new AuditHistoryFieldDto('is_active', 'Active', new AuditValueDto('boolean', $snapshot['is_active'])),
                new AuditHistoryFieldDto('activation_date', 'Activation date', new AuditValueDto('date', $snapshot['activation_date'])),
                new AuditHistoryFieldDto('population', 'Planned head-days', new AuditValueDto('number', $totals['population'])),
                new AuditHistoryFieldDto('total_cost', 'Total cost', new AuditValueDto('currency', $totals['total_cost'], currency: 'PHP')),
                new AuditHistoryFieldDto('cost_per_head', 'Cost per head/day', new AuditValueDto('currency', $totals['cost_per_head'], currency: 'PHP')),
            ],
            tables: [
                new AuditHistoryTableDto(
                    key: 'slots',
                    label: 'Planned meals',
                    columns: [
                        'day' => 'Day', 'meal' => 'Meal', 'item_type' => 'Type', 'item' => 'Item',
                        'quantity' => 'Quantity', 'servings' => 'Servings',
                        'estimated_population' => 'Estimated population', 'is_event' => 'Event',
                        'event_allocation' => 'Event allocation',
                    ],
                    rows: array_map(fn (array $slot): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                        key: $slot['key'],
                        values: [
                            'day' => new AuditValueDto('enum', $slot['day']),
                            'meal' => new AuditValueDto('enum', $slot['meal']),
                            'item_type' => new AuditValueDto('enum', $slot['item_type']),
                            'item' => new AuditValueDto('text', $slot['item']),
                            'quantity' => new AuditValueDto('number', $slot['quantity']),
                            'servings' => new AuditValueDto('number', $slot['servings']),
                            'estimated_population' => new AuditValueDto('number', $slot['estimated_population']),
                            'is_event' => new AuditValueDto('boolean', $slot['is_event']),
                            'event_allocation' => new AuditValueDto('currency', $slot['event_allocation'], currency: 'PHP'),
                        ],
                    ), $snapshot['slots']),
                ),
                new AuditHistoryTableDto(
                    key: 'day_totals',
                    label: 'Daily totals',
                    columns: [
                        'day' => 'Day', 'population' => 'Population', 'cost' => 'Cost',
                        'cost_per_head' => 'Cost per head',
                    ],
                    rows: array_map(fn (array $day): AuditHistoryTableRowDto => new AuditHistoryTableRowDto(
                        key: $day['key'],
                        values: [
                            'day' => new AuditValueDto('enum', $day['day']),
                            'population' => new AuditValueDto('number', $day['population']),
                            'cost' => new AuditValueDto('currency', $day['cost'], currency: 'PHP'),
                            'cost_per_head' => new AuditValueDto('currency', $day['cost_per_head'], currency: 'PHP'),
                        ],
                    ), $snapshot['day_totals']),
                ),
            ],
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function assertValidPayload(array $snapshot): void
    {
        $valid = $this->hasExactKeys($snapshot, [
            'title', 'name', 'reference', 'cycle_days', 'week_start_date', 'week_end_date',
            'status', 'is_active', 'activation_date', 'totals', 'slots', 'day_totals',
        ])
            && is_string($snapshot['title']) && trim($snapshot['title']) !== '' && mb_strlen($snapshot['title']) <= 255
            && is_string($snapshot['name']) && trim($snapshot['name']) !== '' && mb_strlen($snapshot['name']) <= 255
            && is_string($snapshot['reference']) && Str::isUuid($snapshot['reference'])
            && is_int($snapshot['cycle_days']) && $snapshot['cycle_days'] > 0 && $snapshot['cycle_days'] <= 366
            && $this->dateOrNull($snapshot['week_start_date'])
            && $this->dateOrNull($snapshot['week_end_date'])
            && is_string($snapshot['status']) && preg_match('/^[a-z0-9_.:-]{1,64}$/iD', $snapshot['status']) === 1
            && is_bool($snapshot['is_active'])
            && $this->dateOrNull($snapshot['activation_date'])
            && is_array($snapshot['totals'])
            && $this->hasExactKeys($snapshot['totals'], ['population', 'total_cost', 'cost_per_head'])
            && is_int($snapshot['totals']['population']) && $snapshot['totals']['population'] >= 0
            && $this->nonNegativeNumber($snapshot['totals']['total_cost'])
            && $this->nonNegativeNumber($snapshot['totals']['cost_per_head'])
            && is_array($snapshot['slots']) && array_is_list($snapshot['slots'])
            && collect($snapshot['slots'])->every(fn (mixed $slot): bool => $this->validSlot($slot))
            && is_array($snapshot['day_totals']) && array_is_list($snapshot['day_totals'])
            && collect($snapshot['day_totals'])->every(fn (mixed $day): bool => $this->validDayTotal($day));

        if (! $valid) {
            throw new InvalidArgumentException('Invalid menu cycle revision payload.');
        }
    }

    private function validSlot(mixed $slot): bool
    {
        return is_array($slot)
            && $this->hasExactKeys($slot, [
                'key', 'day', 'meal', 'item_type', 'item', 'reference', 'quantity', 'servings',
                'estimated_population', 'is_event', 'event_allocation',
            ])
            && is_string($slot['key']) && trim($slot['key']) !== '' && mb_strlen($slot['key']) <= 100
            && in_array($slot['day'], self::WEEKDAYS, true)
            && in_array($slot['meal'], self::MEALS, true)
            && in_array($slot['item_type'], ['recipe', 'catalog_item', 'unavailable'], true)
            && is_string($slot['item']) && trim($slot['item']) !== '' && mb_strlen($slot['item']) <= 255
            && ($slot['reference'] === null || (is_string($slot['reference']) && Str::isUuid($slot['reference'])))
            && $this->nonNegativeNumber($slot['quantity'])
            && ($slot['servings'] === null || (is_int($slot['servings']) && $slot['servings'] >= 0))
            && ($slot['estimated_population'] === null || (is_int($slot['estimated_population']) && $slot['estimated_population'] >= 0))
            && is_bool($slot['is_event'])
            && ($slot['event_allocation'] === null || $this->nonNegativeNumber($slot['event_allocation']));
    }

    private function validDayTotal(mixed $day): bool
    {
        return is_array($day)
            && $this->hasExactKeys($day, ['key', 'day', 'population', 'cost', 'cost_per_head'])
            && is_string($day['key']) && trim($day['key']) !== '' && mb_strlen($day['key']) <= 100
            && in_array($day['day'], self::WEEKDAYS, true)
            && is_int($day['population']) && $day['population'] >= 0
            && $this->nonNegativeNumber($day['cost'])
            && $this->nonNegativeNumber($day['cost_per_head']);
    }

    private function dateOrNull(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function number(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function nonNegativeNumber(mixed $value): bool
    {
        return $this->number($value) && $value >= 0;
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
