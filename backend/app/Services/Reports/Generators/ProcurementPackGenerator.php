<?php

namespace App\Services\Reports\Generators;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\Reports\Contracts\ReportGenerator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
        $packs = $orders
            ->flatMap(fn (PurchaseOrder $po) => $po->vendorGroups->isNotEmpty()
                ? $po->vendorGroups->map(fn (PurchaseOrderVendorGroup $group) => $this->buildPack($po, $group))
                : collect([$this->buildPack($po)]))
            ->values()
            ->all();

        $preparedBy = $params['prepared_by_name'] ?? null;

        return [
            'packs' => $packs,
            'period_label' => $this->periodLabel($orders),
            // Each bundled form carries its own signatory block (§2.7).
            'air_signatories' => $this->sigsFor('inspection_report', $preparedBy),
            'statement_signatories' => $this->sigsFor('marketing_statement', $preparedBy),
            'summary_signatories' => $this->sigsFor('marketing_summary', $preparedBy),
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
        $query = PurchaseOrder::with([
            'items.fsItem',
            'supplier',
            'attachments.storedObject',
            'shoppingList',
            'vendorGroups.supplier',
            'vendorGroups.items.fsItem',
            'vendorGroups.attachments.storedObject',
        ]);

        if (! empty($params['purchase_order_id'])) {
            return $query->whereKey($params['purchase_order_id'])->get();
        }
        if (! empty($params['shopping_list_id'])) {
            return $query->where('shopping_list_id', $params['shopping_list_id'])->orderBy('id')->get();
        }

        // Explicit date range of received POs — no current-date fallback (must be reproducible).
        if (empty($params['start']) || empty($params['end'])) {
            throw new \InvalidArgumentException(
                'Procurement pack requires a purchase_order_id, shopping_list_id, or an explicit start/end range.'
            );
        }

        $start = Carbon::parse($params['start']);
        $end = Carbon::parse($params['end']);

        return $query->where('status', 'received')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('order_date')->get();
    }

    private function buildPack(PurchaseOrder $po, ?PurchaseOrderVendorGroup $group = null): array
    {
        $items = $group?->items ?? $po->items;
        // Vendor docs show whole purchase units (kg/sacks), not base grams (Spec 6 #4).
        // total_value unchanged: purchase_qty × purchase_price == qty × unit_price.
        $airItems = $items->values()->map(fn ($it, $i) => [
            'item_no' => $i + 1,
            'unit' => $it->purchase_unit ?? $it->unit,
            'description' => $it->description ?? $it->fsItem?->name,
            'quantity' => $it->actual_qty ?? $it->purchase_qty ?? $it->qty,
        ])->all();

        $statementItems = $items->map(fn ($it) => [
            'qty' => $it->actual_qty ?? $it->purchase_qty ?? $it->qty,
            'unit' => $it->purchase_unit ?? $it->unit,
            'item' => $it->description ?? $it->fsItem?->name,
            'unit_price' => $it->actual_unit_price ?? $it->purchase_price ?? $it->unit_price,
            'total_value' => $it->actual_qty !== null && $it->actual_unit_price !== null
                ? round((float) $it->actual_qty * (float) $it->actual_unit_price, 2)
                : $it->total_value,
        ])->all();

        $grandTotal = (float) collect($statementItems)->sum('total_value');
        $date = optional($po->order_date)->format('F j, Y') ?? '';
        $inclusive = $po->shoppingList?->period_start && $po->shoppingList?->period_end
            ? $po->shoppingList->period_start->format('m/d/y').'-'.$po->shoppingList->period_end->format('m/d/y')
            : $date;
        $attachments = $group ? $group->attachments : $po->attachments;

        return [
            'po' => $po,
            'vendor_group' => $group,
            'supplier' => $group?->supplier ?? $po->supplier,
            'or_number' => $group?->or_number ?? $po->or_number ?? 'Not provided',
            'is_final' => $po->final_locked_at !== null,
            'air_items' => $airItems,
            'statement_items' => $statementItems,
            'grand_total' => round($grandTotal, 2),
            'order_date' => $date,
            // Uploaded receipt / proof-of-purchase photos, embedded as an appendix.
            'attachments' => $attachments->map(fn ($a) => [
                'type' => $a->type,
                'caption' => $a->caption,
                'src' => $this->attachmentSource($a),
            ])->values()->all(),
            'summary' => [
                'date_purchased' => $date,
                'inclusive' => $inclusive,
                'amount' => round($grandTotal, 2),
            ],
        ];
    }

    private function attachmentSource($attachment): ?string
    {
        try {
            if ($attachment->storedObject) {
                $object = $attachment->storedObject;
                $bytes = Storage::disk($object->storage_disk)->get($object->object_key);

                return 'data:'.$object->mime_type.';base64,'.base64_encode($bytes);
            }
            if ($attachment->path) {
                $disk = Storage::disk(config('filesystems.uploads'));
                $bytes = $disk->get($attachment->path);
                $mime = $disk->mimeType($attachment->path) ?: 'image/jpeg';

                return 'data:'.$mime.';base64,'.base64_encode($bytes);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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

        return Carbon::parse($dates->min())->format('m/d/y').'-'.Carbon::parse($dates->max())->format('m/d/y');
    }
}
