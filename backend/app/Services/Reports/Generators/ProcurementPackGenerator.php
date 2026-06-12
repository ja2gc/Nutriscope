<?php

namespace App\Services\Reports\Generators;

use App\Models\PurchaseOrder;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Procurement Pack — the three buy-event government docs bundled into one PDF:
 * Acceptance & Inspection Report (AIR), Statement of Marketing Purchased, and
 * Summary of Marketing. Each purchase order produces its own AIR + Statement +
 * Summary pages (one supplier per AIR, matching the real forms).
 *
 * All figures come straight from the PO + its items — no recompute.
 */
class ProcurementPackGenerator implements ReportGenerator
{
    public function type(): string
    {
        return 'procurement_pack';
    }

    public function view(): string
    {
        return 'reports.procurement-pack';
    }

    public function paper(): array
    {
        return ['a4', 'portrait'];
    }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];

        $orders = $this->resolveOrders($params);
        $packs  = $orders->map(fn (PurchaseOrder $po) => $this->buildPack($po))->all();

        $preparedBy = $params['prepared_by_name'] ?? null;

        return [
            'packs'                => $packs,
            'period_label'         => $this->periodLabel($orders),
            // Each bundled form carries its own signatory block (§2.7).
            'air_signatories'       => $this->sigsFor('inspection_report', $preparedBy),
            'statement_signatories' => $this->sigsFor('marketing_statement', $preparedBy),
            'summary_signatories'   => $this->sigsFor('marketing_summary', $preparedBy),
        ];
    }

    /**
     * Signatory defaults for a bundled form, with the "prepared by"/buyer role
     * overridden by the requesting user's name when available.
     *
     * @return array<int,array<string,string>>
     */
    private function sigsFor(string $type, ?string $preparedBy): array
    {
        $sigs = ReportTemplate::where('type', $type)->first()?->signatories ?? [];

        return array_map(function (array $sig) use ($preparedBy) {
            if ($preparedBy && in_array($sig['role'] ?? '', ['prepared_by', 'buyer', 'conforme', 'certified_correct'], true)) {
                $sig['name'] = $preparedBy;
            }
            return $sig;
        }, $sigs);
    }

    private function resolveOrders(array $params): Collection
    {
        $query = PurchaseOrder::with(['items.fsItem', 'supplier']);

        if (! empty($params['purchase_order_id'])) {
            return $query->whereKey($params['purchase_order_id'])->get();
        }
        if (! empty($params['shopping_list_id'])) {
            return $query->where('shopping_list_id', $params['shopping_list_id'])->orderBy('id')->get();
        }

        // Fall back to a date range of received POs.
        $start = ! empty($params['start']) ? Carbon::parse($params['start']) : Carbon::now()->startOfMonth();
        $end   = ! empty($params['end']) ? Carbon::parse($params['end']) : Carbon::now()->endOfMonth();

        return $query->where('status', 'received')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('order_date')->get();
    }

    private function buildPack(PurchaseOrder $po): array
    {
        $airItems = $po->items->values()->map(fn ($it, $i) => [
            'item_no'     => $i + 1,
            'unit'        => $it->unit,
            'description' => $it->description ?? $it->fsItem?->name,
            'quantity'    => $it->qty,
        ])->all();

        $statementItems = $po->items->map(fn ($it) => [
            'qty'         => $it->qty,
            'unit'        => $it->unit,
            'item'        => $it->description ?? $it->fsItem?->name,
            'unit_price'  => $it->unit_price,
            'total_value' => $it->total_value,
        ])->all();

        $grandTotal = (float) $po->items->sum('total_value');
        $date       = optional($po->order_date)->format('F j, Y') ?? '';

        return [
            'po'              => $po,
            'supplier'        => $po->supplier,
            'air_items'       => $airItems,
            'statement_items' => $statementItems,
            'grand_total'     => round($grandTotal, 2),
            'order_date'      => $date,
            'summary'         => [
                'date_purchased' => $date,
                'inclusive'      => $date,
                'amount'         => round($grandTotal, 2),
            ],
        ];
    }

    private function periodLabel(Collection $orders): string
    {
        if ($orders->isEmpty()) {
            return '';
        }
        $dates = $orders->pluck('order_date')->filter();
        if ($dates->isEmpty()) {
            return '';
        }
        return Carbon::parse($dates->min())->format('m/d/y') . '-' . Carbon::parse($dates->max())->format('m/d/y');
    }
}
