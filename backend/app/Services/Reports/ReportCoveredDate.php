<?php

namespace App\Services\Reports;

use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** The last calendar day represented by a food-service report. */
class ReportCoveredDate
{
    public static function startForParameters(string $type, array $parameters): ?string
    {
        if (in_array($type, ['procurement_pack', 'program_project_activity'], true)) {
            if (isset($parameters['purchase_order_id'])) {
                $order = self::findSource(PurchaseOrder::class, $parameters['purchase_order_id'], ['shoppingList', 'programProjectActivity']);

                return self::purchaseOrderStart($type, $order);
            }
            if (isset($parameters['shopping_list_id'])) {
                $list = self::findSource(ShoppingList::class, $parameters['shopping_list_id']);

                return $list?->period_start?->toDateString() ?? $list?->list_date?->toDateString();
            }
            if ($type === 'program_project_activity' && isset($parameters['menu_cycle_id'])) {
                return self::findSource(MenuCycle::class, $parameters['menu_cycle_id'])?->week_start_date?->toDateString();
            }
        }
        if ($type === 'menu_calendar') {
            return self::findSource(MenuCycle::class, $parameters['menu_cycle_id'] ?? null)?->week_start_date?->toDateString();
        }

        return in_array($type, ['procurement_pack', 'program_project_activity', 'accomplishment_report'], true)
            ? self::explicitStart($parameters)
            : null;
    }

    /** @param list<array<string, mixed>> $instances @return array<string, string> */
    public static function startsForInstances(string $type, array $instances): array
    {
        $starts = [];
        if (in_array($type, ['procurement_pack', 'program_project_activity'], true)) {
            $orders = PurchaseOrder::query()
                ->with(['shoppingList', 'programProjectActivity'])
                ->whereIn('id', array_column(array_column($instances, 'params'), 'purchase_order_id'))
                ->get()->keyBy('id');
            foreach ($instances as $instance) {
                $start = self::purchaseOrderStart($type, $orders->get($instance['params']['purchase_order_id'] ?? null));
                if ($start !== null) {
                    $starts[$instance['key']] = $start;
                }
            }
        } elseif ($type === 'menu_calendar') {
            $menus = MenuCycle::query()
                ->whereIn('id', array_column(array_column($instances, 'params'), 'menu_cycle_id'))
                ->get()->keyBy('id');
            foreach ($instances as $instance) {
                $start = $menus->get($instance['params']['menu_cycle_id'] ?? null)?->week_start_date?->toDateString();
                if ($start !== null) {
                    $starts[$instance['key']] = $start;
                }
            }
        } elseif ($type === 'accomplishment_report') {
            foreach ($instances as $instance) {
                $start = self::explicitStart($instance['params']);
                if ($start !== null) {
                    $starts[$instance['key']] = $start;
                }
            }
        }

        return $starts;
    }

    private static function explicitStart(array $parameters): ?string
    {
        $value = $parameters['start'] ?? $parameters['from'] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function forParameters(string $type, array $parameters): ?string
    {
        return match ($type) {
            'procurement_pack', 'program_project_activity' => self::operationalDate($type, $parameters),
            'menu_calendar' => self::menuDate(self::findSource(MenuCycle::class, $parameters['menu_cycle_id'] ?? null)),
            'accomplishment_report' => self::explicitEnd($parameters),
            default => null,
        };
    }

    /** @param list<array<string, mixed>> $instances @return array<string, string> */
    public static function forInstances(string $type, array $instances): array
    {
        $dates = [];
        if (in_array($type, ['procurement_pack', 'program_project_activity'], true)) {
            $orders = PurchaseOrder::query()
                ->with(['shoppingList', 'programProjectActivity'])
                ->whereIn('id', array_column(array_column($instances, 'params'), 'purchase_order_id'))
                ->get()->keyBy('id');
            foreach ($instances as $instance) {
                $date = self::purchaseOrderDate($type, $orders->get($instance['params']['purchase_order_id'] ?? null));
                if ($date !== null) {
                    $dates[$instance['key']] = $date;
                }
            }
        } elseif ($type === 'menu_calendar') {
            $menus = MenuCycle::query()
                ->whereIn('id', array_column(array_column($instances, 'params'), 'menu_cycle_id'))
                ->get()->keyBy('id');
            foreach ($instances as $instance) {
                $date = self::menuDate($menus->get($instance['params']['menu_cycle_id'] ?? null));
                if ($date !== null) {
                    $dates[$instance['key']] = $date;
                }
            }
        } elseif ($type === 'accomplishment_report') {
            foreach ($instances as $instance) {
                $date = $instance['params']['end'] ?? null;
                if (is_string($date)) {
                    $dates[$instance['key']] = $date;
                }
            }
        }

        return $dates;
    }

    private static function purchaseOrderDate(string $type, ?PurchaseOrder $order): ?string
    {
        if ($order === null) {
            return null;
        }
        $periodEnd = $type === 'program_project_activity'
            ? $order->programProjectActivity?->period_end ?? $order->shoppingList?->period_end
            : $order->shoppingList?->period_end;
        $orderDate = $order->order_date ?? $order->completed_at;
        if ($periodEnd === null && $orderDate === null) {
            return null;
        }
        if ($type === 'program_project_activity') {
            return Carbon::parse($periodEnd ?? $orderDate)->toDateString();
        }

        return collect([$periodEnd, $orderDate])
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->max();
    }

    private static function purchaseOrderStart(string $type, ?PurchaseOrder $order): ?string
    {
        if ($order === null) {
            return null;
        }
        $periodStart = $type === 'program_project_activity'
            ? $order->programProjectActivity?->period_start ?? $order->shoppingList?->period_start
            : $order->shoppingList?->period_start;
        $orderDate = $order->order_date ?? $order->completed_at;
        if ($type === 'program_project_activity') {
            return $periodStart ? Carbon::parse($periodStart)->toDateString()
                : ($orderDate ? Carbon::parse($orderDate)->toDateString() : null);
        }

        return collect([$periodStart, $orderDate])->filter()
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())->min();
    }

    private static function operationalDate(string $type, array $parameters): ?string
    {
        if (isset($parameters['purchase_order_id'])) {
            return self::purchaseOrderDate(
                $type,
                self::findSource(PurchaseOrder::class, $parameters['purchase_order_id'], ['shoppingList', 'programProjectActivity']),
            );
        }
        if (isset($parameters['shopping_list_id'])) {
            $list = self::findSource(ShoppingList::class, $parameters['shopping_list_id']);

            return $list?->period_end?->toDateString() ?? $list?->list_date?->toDateString();
        }
        if ($type === 'program_project_activity' && isset($parameters['menu_cycle_id'])) {
            return self::menuDate(self::findSource(MenuCycle::class, $parameters['menu_cycle_id']));
        }

        return self::explicitEnd($parameters);
    }

    private static function explicitEnd(array $parameters): ?string
    {
        $value = $parameters['end'] ?? $parameters['to'] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private static function menuDate(?MenuCycle $menu): ?string
    {
        return $menu?->week_start_date?->copy()->addDays(6)->toDateString();
    }

    /** @param class-string<Model> $class */
    private static function findSource(string $class, mixed $identifier, array $relations = []): ?Model
    {
        if ($identifier === null) {
            return null;
        }

        $query = $class::query()->with($relations);

        return is_int($identifier) || ctype_digit((string) $identifier)
            ? $query->find((int) $identifier)
            : $query->where('uuid', (string) $identifier)->first();
    }
}
