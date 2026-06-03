<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreBudgetRequest;
use App\Http\Requests\FSS\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetDailyLog;
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

        $budget = Budget::create($data);
        return response()->json(['data' => new BudgetResource($budget)], 201);
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
