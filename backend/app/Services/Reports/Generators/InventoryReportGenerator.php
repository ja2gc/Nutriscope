<?php

namespace App\Services\Reports\Generators;

use App\Models\Inventory;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;

/**
 * Inventory report — stock levels, value, and low/no-stock status across the
 * food-service catalog (ingredients, supplies, prepared recipes).
 */
class InventoryReportGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'inventory_report';
    }

    public function view(): string
    {
        return 'reports.inventory';
    }

    public function paper(): array
    {
        return ['a4', 'portrait'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        $rows = Inventory::with(['fsItem', 'recipe'])
            ->when(! empty($params['item_type']), fn ($q) => $q->where('item_type', $params['item_type']))
            ->get()
            ->map(function (Inventory $inv) {
                $name = $inv->fsItem?->name ?? $inv->recipe?->name ?? '—';
                $qty  = (float) $inv->quantity_in_stock;
                // stored last-cost (₱/base) first; fall back to live catalog only for never-received items
                $cost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);

                return [
                    'name'      => $name,
                    'item_type' => $inv->item_type,
                    'category'  => $inv->fsItem?->category,
                    'quantity'  => $qty,
                    'unit'      => $inv->unit,
                    // Binary stock state (no threshold): in stock vs out.
                    'status'    => $qty > 0 ? 'ok' : 'no_stock',
                    'value'     => round($qty * $cost, 2),
                ];
            });

        return [
            'rows'        => $rows->all(),
            'total_value' => round($rows->sum('value'), 2),
            'no_count'    => $rows->where('status', 'no_stock')->count(),
        ];
    }
}
