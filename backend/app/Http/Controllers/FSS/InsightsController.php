<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Food-Service analytics. Each method returns chart-ready
 * {points, summary}. Pure aggregation over existing frozen data (received POs,
 * menu-cycle cost, completed meal-prep logs) — no writable state, no graphs in
 * the compliance PDFs (Spec 3 hard rule).
 */
class InsightsController extends Controller
{
    /** Parse start/end (default = current month). @return array{0:Carbon,1:Carbon} */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        return [
            Carbon::parse($data['start'] ?? now()->startOfMonth()),
            Carbon::parse($data['end'] ?? now()->endOfMonth()),
        ];
    }

    /** Received-PO spend grouped by supplier (same rule as budget cash_flow). */
    public function spendBySupplier(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request);

        $rows = PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('supplier_id, SUM(total_amount) as total')
            ->groupBy('supplier_id')->get();

        $names  = Supplier::whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $points = $rows->map(fn ($r) => [
            'supplier_id' => $r->supplier_id,
            'supplier'    => $r->supplier_id ? ($names[$r->supplier_id] ?? 'Unknown') : 'Unassigned',
            'total'       => round((float) $r->total, 2),
        ])->sortByDesc('total')->values();

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => ['total' => round((float) $rows->sum('total'), 2), 'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()]],
        ]]);
    }

    /** Average daily cost-per-head per menu cycle (from MenuCycleCostService). */
    public function costPerHead(Request $request): JsonResponse
    {
        $cycles = MenuCycle::with('days.recipe.ingredients.fsItem', 'days.fsItem')
            ->orderBy('id')->get();

        $points = $cycles->map(function (MenuCycle $cycle) {
            $cost = MenuCycleCostService::forCycle($cycle);
            $perHeadByDay = collect($cost['days'])->pluck('cost_per_head');
            $avg = $perHeadByDay->isNotEmpty() ? round($perHeadByDay->avg(), 2) : 0.0;

            return [
                'cycle_id'      => $cycle->id,
                'cycle'         => $cycle->name,
                'cost_per_head' => $avg,
                'population'    => (int) $cycle->population,
            ];
        })->values();

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => ['avg' => $points->isNotEmpty() ? round($points->avg('cost_per_head'), 2) : 0.0],
        ]]);
    }

    /** Value of food served per day (completed logs only) + shortfall marker. */
    public function consumption(Request $request): JsonResponse
    {
        [$start, $end] = $this->range($request);

        // DATE(...) normalises keys across sqlite (datetimes on date cols) + MySQL.
        $rows = MealPrepLog::where('status', 'completed')
            ->whereBetween('service_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DATE(service_date) as d, SUM(total_value) as actual, MAX(has_shortfall) as shortfall')
            ->groupByRaw('DATE(service_date)')->orderByRaw('DATE(service_date)')->get();

        $points = $rows->map(fn ($r) => [
            'date'      => $r->d,
            'actual'    => round((float) $r->actual, 2),
            'shortfall' => (bool) $r->shortfall,
        ])->values();

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => [
                'total'          => round((float) $rows->sum('actual'), 2),
                'days'           => $rows->count(),
                'shortfall_days' => $rows->where('shortfall', '>', 0)->count(),
                'range'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            ],
        ]]);
    }
}
