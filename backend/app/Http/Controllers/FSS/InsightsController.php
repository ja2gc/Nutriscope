<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only analytics for the Budget → Insights tab: spend by supplier, cost-per-head
 * by menu cycle, and value-served consumption. All derive from existing records
 * (received POs, menu-cycle cost snapshots, completed meal-prep logs) so the charts can
 * never drift from the rest of the system.
 */
class InsightsController extends Controller
{
    private function range(Request $request): array
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        return [
            Carbon::parse($data['start'] ?? now()->startOfMonth())->toDateString(),
            Carbon::parse($data['end'] ?? now())->toDateString(),
        ];
    }

    /** Received-PO cash grouped by vendor over the range. */
    public function spendBySupplier(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request);

        $rows = PurchaseOrder::with('supplier')
            ->where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$start, $end])
            ->get(['id', 'supplier_id', 'total_amount']);

        $points = $rows->groupBy('supplier_id')->map(function ($group) {
            $first = $group->first();

            return [
                'supplier_id' => $first->supplier_id,
                'supplier'    => $first->supplier?->name ?? 'Unassigned',
                'total'       => round((float) $group->sum('total_amount'), 2),
            ];
        })->sortByDesc('total')->values();

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => ['total' => round((float) $rows->sum('total_amount'), 2), 'range' => ['start' => $start, 'end' => $end]],
        ]]);
    }

    /** Average daily ₱/head for each menu cycle (frozen snapshot, else live cost). */
    public function costPerHead(): JsonResponse
    {
        $cycles = MenuCycle::orderByDesc('week_start_date')->limit(12)->get();

        $points = $cycles->map(function (MenuCycle $cycle) {
            $cost = MenuCycleCostService::forReport($cycle);

            return [
                'cycle_id'      => $cycle->id,
                'cycle'         => $cycle->name,
                'cost_per_head' => round((float) ($cost['cost_per_head'] ?? 0), 2),
                'population'    => (int) ($cost['population'] ?? 0),
            ];
        })->filter(fn ($p) => $p['cost_per_head'] > 0)->values();

        $avg = $points->count() > 0 ? round($points->avg('cost_per_head'), 2) : 0.0;

        return response()->json(['data' => ['points' => $points, 'summary' => ['avg' => $avg]]]);
    }

    /** Value of food served per day (completed meal-prep logs) over the range. */
    public function consumption(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request);

        $logs = MealPrepLog::where('status', 'completed')
            ->whereBetween('service_date', [$start, $end])
            ->get(['service_date', 'total_value', 'has_shortfall']);

        $points = $logs->groupBy(fn ($log) => Carbon::parse($log->service_date)->toDateString())
            ->map(fn ($group, $date) => [
                'date'      => $date,
                'actual'    => round((float) $group->sum('total_value'), 2),
                'shortfall' => (bool) $group->contains(fn ($l) => $l->has_shortfall),
            ])
            ->sortKeys()->values();

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => [
                'total'          => round((float) $logs->sum('total_value'), 2),
                'days'           => $points->count(),
                'shortfall_days' => $points->where('shortfall', true)->count(),
                'range'          => ['start' => $start, 'end' => $end],
            ],
        ]]);
    }
}
