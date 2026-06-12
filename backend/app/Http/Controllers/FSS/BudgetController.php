<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreBudgetRequest;
use App\Http\Requests\FSS\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetDailyLog;
use App\Models\PurchaseOrder;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => BudgetResource::collection(Budget::all())]);
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['fss_user_id'] = Auth::id();
        $data['scope'] = $data['scope'] ?? 'custom';

        $budget = Budget::create($data);
        return response()->json(['data' => new BudgetResource($budget)], 201);
    }

    /**
     * Dashboard summary over any range: daily budget cap (per-head/day × population,
     * the "planned" target line) vs real spend (received POs + manual logs), rolled
     * up at the requested granularity. Powers the trend + variance charts.
     */
    public function summary(Request $request, Budget $budget): JsonResponse
    {
        $data = $request->validate([
            'start'       => ['nullable', 'date'],
            'end'         => ['nullable', 'date', 'after_or_equal:start'],
            'granularity' => ['nullable', 'in:day,week,month'],
        ]);

        $start = Carbon::parse($data['start'] ?? $budget->period_start ?? now()->startOfMonth());
        $end   = Carbon::parse($data['end'] ?? $budget->period_end ?? now()->endOfMonth());
        $gran  = $data['granularity'] ?? 'day';

        $poByDay = PurchaseOrder::where('status', 'received')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('order_date as d, SUM(total_amount) as t')
            ->groupBy('order_date')->pluck('t', 'd');

        $logByDay = $budget->dailyLogs()
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('log_date as d, SUM(spent) as t')
            ->groupBy('log_date')->pluck('t', 'd');

        $cap = ($budget->budget_per_head_day && $budget->population)
            ? (float) $budget->budget_per_head_day * (int) $budget->population
            : ((float) ($budget->allocated_amount ?? 0) / max(1, $start->diffInDays($end) + 1));

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $days[] = [
                'date'    => $ds,
                'planned' => $cap,
                'actual'  => (float) ($poByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0),
            ];
        }

        $summary = BudgetService::summarize($days, $gran);
        $summary['range']               = ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'granularity' => $gran];
        $summary['allocated']           = (float) ($budget->allocated_amount ?? 0);
        $summary['budget_per_head_day'] = $budget->budget_per_head_day ? (float) $budget->budget_per_head_day : null;
        $summary['population']          = $budget->population;

        return response()->json(['data' => $summary]);
    }

    public function show(Budget $budget): JsonResponse
    {
        return response()->json(['data' => new BudgetResource($budget)]);
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $budget->update($request->validated());
        return response()->json(['data' => new BudgetResource($budget)]);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $budget->delete();
        return response()->json(null, 204);
    }

    public function storeDailyLog(Request $request, Budget $budget): JsonResponse
    {
        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'spent'    => ['required', 'numeric', 'min:0'],
            'notes'    => ['nullable', 'string'],
        ]);

        $dailyLog = BudgetDailyLog::create([
            'budget_id' => $budget->id,
            'log_date'  => $data['log_date'],
            'spent'     => $data['spent'],
            'notes'     => $data['notes'] ?? null,
            'planned'   => 0,
            'actual'    => $data['spent'],
            'variance'  => 0,
        ]);

        return response()->json([
            'data' => [
                'id'         => $dailyLog->id,
                'budget_id'  => $dailyLog->budget_id,
                'log_date'   => $dailyLog->log_date?->toDateString(),
                'spent'      => $dailyLog->spent,
                'notes'      => $dailyLog->notes,
                'created_at' => $dailyLog->created_at,
                'updated_at' => $dailyLog->updated_at,
            ]
        ], 201);
    }
}
