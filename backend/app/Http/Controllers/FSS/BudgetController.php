<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreBudgetRequest;
use App\Http\Requests\FSS\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetDailyLog;
use App\Models\PurchaseOrder;
use App\Services\BudgetActualService;
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
     * the "planned" target line) vs consumption-actual (value of food served) — or a
     * purchases fallback when no day has been served yet. Received POs are surfaced
     * separately as cash_flow. Rolled up at the requested granularity via the shared
     * {@see BudgetActualService} so the dashboard and the printed report never diverge.
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

        $series = BudgetActualService::dailySeries($budget, $start, $end);

        $summary = BudgetService::summarize($series['days'], $gran);
        $summary['range']               = ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'granularity' => $gran];
        $summary['source']              = $series['source'];
        $summary['cash_flow']           = $series['cash_flow'];
        $summary['days_served']         = $series['days_served'];
        $summary['allocated']           = (float) ($budget->allocated_amount ?? 0);
        $summary['budget_per_head_day'] = $budget->budget_per_head_day ? (float) $budget->budget_per_head_day : null;
        $summary['population']          = $budget->population;
        // Daily-headcount actuals (null until a served day records a population — A8).
        $summary['avg_population']      = $series['avg_population'];
        $summary['per_head_actual']     = $series['per_head_actual'];

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
        ]);

        // Received POs are already counted as spend on their date — warn so the user
        // doesn't double-count by also logging the same cash by hand.
        $poCount = PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) = ?', [$data['log_date']])
            ->count();
        $warning = $poCount > 0
            ? "{$poCount} received purchase order(s) are already counted on {$data['log_date']}. Manual logs are for non-PO cash spends only."
            : null;

        return response()->json([
            'data' => [
                'id'         => $dailyLog->id,
                'budget_id'  => $dailyLog->budget_id,
                'log_date'   => $dailyLog->log_date?->toDateString(),
                'spent'      => $dailyLog->spent,
                'notes'      => $dailyLog->notes,
                'warning'    => $warning,
                'created_at' => $dailyLog->created_at,
                'updated_at' => $dailyLog->updated_at,
            ]
        ], 201);
    }
}
