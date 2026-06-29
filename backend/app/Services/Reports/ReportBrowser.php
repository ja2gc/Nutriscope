<?php

namespace App\Services\Reports;

use App\Models\DietListCount;
use App\Models\MealPlan;
use App\Models\MenuCycle;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Services\Reports\Contracts\InstanceSource;
use App\Services\Reports\Instances\EntityInstanceSource;
use App\Services\Reports\Instances\PeriodInstanceSource;
use Illuminate\Support\Facades\Auth;

/**
 * Registry mapping each report type to its browse-axis {@see InstanceSource}.
 * The factories are closures so each call gets a fresh query (and the registry
 * stays cheap to construct). This is the "what can I browse?" companion to the
 * ReportService generator registry ("how do I render it?").
 */
class ReportBrowser
{
    /** type => Closure():InstanceSource */
    private array $sources;

    public function __construct()
    {
        $this->sources = [
            // ── period axis (year → month) ───────────────────────────────────
            'demographic_census' => fn () => new PeriodInstanceSource(
                fn () => Patient::query(),
                'admission_date',
            ),

            // ── entity axis ──────────────────────────────────────────────────
            'procurement_pack' => fn () => new EntityInstanceSource(
                fn () => PurchaseOrder::query()->whereIn('lifecycle_status', ['completed', 'archived'])->with('supplier'),
                'purchase_order_id',
                fn (PurchaseOrder $po) => trim(($po->po_number ?: "PO #{$po->id}")
                    . (optional($po->completed_at)?->format('M j, Y') ? ' — ' . $po->completed_at->format('M j, Y') : '')
                    . ($po->supplier ? " — {$po->supplier->name}" : '')),
                'completed_at',
            ),
            'program_project_activity' => fn () => new EntityInstanceSource(
                fn () => PurchaseOrder::query()
                    ->where('procurement_track', 'food')
                    ->whereIn('lifecycle_status', ['completed', 'archived'])
                    ->whereHas('programProjectActivity')
                    ->with(['programProjectActivity', 'shoppingList']),
                'purchase_order_id',
                fn (PurchaseOrder $po) => trim(($po->po_number ?: "PO #{$po->id}")
                    . ' — ' . (optional($po->programProjectActivity?->period_start)->format('M j') ?? optional($po->shoppingList?->period_start)->format('M j') ?? '?')
                    . '-' . (optional($po->programProjectActivity?->period_end)->format('M j, Y') ?? optional($po->shoppingList?->period_end)->format('M j, Y') ?? '?')),
                'completed_at',
            ),
            'menu_calendar'            => fn () => $this->menuCycleSource(),
            'patient_menu_plan' => fn () => new EntityInstanceSource(
                // One instance per meal plan so the RND selects the EXACT plan to print.
                // (Previously keyed by patient_id, which the generator can't render —
                // it requires a specific meal_plan_id.) (MP-08/RP-04)
                fn () => MealPlan::query()->with('patient'),
                'meal_plan_id',
                fn (MealPlan $mp) => trim(($mp->patient?->name ?? "Patient #{$mp->patient_id}")
                    . ' — week of ' . (optional($mp->week_start_date)->format('M j, Y') ?? '?')
                    . ($mp->status ? " ({$mp->status})" : '')),
                'created_at',
            ),
            'ncp_summary' => fn () => new EntityInstanceSource(
                fn () => NcpRecord::query()->with('patient'),
                'ncp_record_id',
                fn (NcpRecord $r) => trim(($r->patient?->name ?? "Patient #{$r->patient_id}")
                    . ' — ' . optional($r->created_at)->format('M j, Y')
                    . ($r->status ? " ({$r->status})" : '')),
                'created_at',
            ),

            // ── period axis: accomplishment report (FSS §4) ──────────────────
            'accomplishment_report' => fn () => new PeriodInstanceSource(
                fn () => DietListCount::query()
                    ->when(Auth::user()?->role === 'FSS', fn ($q) => $q->where('fss_user_id', Auth::id())),
                'service_date',
            ),
        ];
    }

    public function supports(string $type): bool
    {
        return isset($this->sources[$type]);
    }

    public function sourceFor(string $type): InstanceSource
    {
        $factory = $this->sources[$type] ?? null;
        if (! $factory) {
            throw new \InvalidArgumentException("No browse axis for report type [{$type}].");
        }

        return $factory();
    }

    private function menuCycleSource(): EntityInstanceSource
    {
        return new EntityInstanceSource(
            fn () => MenuCycle::query(),
            'menu_cycle_id',
            fn (MenuCycle $c) => $c->name ?: "Cycle #{$c->id}",
            'week_start_date',
        );
    }
}
