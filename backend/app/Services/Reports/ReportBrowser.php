<?php

namespace App\Services\Reports;

use App\Models\Budget;
use App\Models\MealPlan;
use App\Models\MenuCycle;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Services\Reports\Contracts\InstanceSource;
use App\Services\Reports\Instances\EntityInstanceSource;
use App\Services\Reports\Instances\PeriodInstanceSource;
use App\Services\Reports\Instances\SingletonInstanceSource;

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
            'dietary_cash_book' => fn () => new PeriodInstanceSource(
                fn () => PurchaseOrder::query()->where('status', 'received'),
                'order_date',
            ),
            'demographic_census' => fn () => new PeriodInstanceSource(
                fn () => Patient::query(),
                'admission_date',
            ),

            // ── entity axis ──────────────────────────────────────────────────
            'procurement_pack' => fn () => new EntityInstanceSource(
                fn () => PurchaseOrder::query()->where('status', 'received')->with('supplier'),
                'purchase_order_id',
                fn (PurchaseOrder $po) => trim(($po->po_number ?: "PO #{$po->id}")
                    . ($po->supplier ? " — {$po->supplier->name}" : '')),
                'order_date',
            ),
            'budget_report' => fn () => new EntityInstanceSource(
                fn () => Budget::query(),
                'budget_id',
                fn (Budget $b) => $b->name ?: "Budget #{$b->id}",
                'period_start',
            ),
            'program_project_activity' => fn () => $this->menuCycleSource(),
            'menu_calendar'            => fn () => $this->menuCycleSource(),
            'patient_menu_plan' => fn () => new EntityInstanceSource(
                // Only patients that actually have a meal plan (the generator firstOrFail's one).
                fn () => Patient::query()->whereIn('id', MealPlan::query()->select('patient_id')),
                'patient_id',
                fn (Patient $p) => $p->name ?? "Patient #{$p->id}",
            ),

            // ── singleton axis (point-in-time) ───────────────────────────────
            'inventory_report' => fn () => new SingletonInstanceSource('Current stock'),
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
