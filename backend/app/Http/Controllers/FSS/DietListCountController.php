<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreDietListCountRequest;
use App\Models\DietListCount;
use App\Models\MealPrepLog;
use App\Services\FSS\AccomplishmentReportArchiveService;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DietListCountController extends Controller
{
    public function store(
        StoreDietListCountRequest $request,
        PurchaseOrderLifecycleService $lifecycle,
        AccomplishmentReportArchiveService $archives
    ): JsonResponse
    {
        $validated = $request->validated();

        // Self-scoped write: force fss_user_id to the authenticated user; never accept from request.
        $count = DietListCount::create([
            ...$validated,
            'fss_user_id' => Auth::id(),
        ]);

        // Recompute served_population for this date from the sum of all diet_list_counts.
        $this->syncServedPopulation($count->service_date->toDateString(), $validated['menu_cycle_id'] ?? null);
        $lifecycle->refreshForServiceDate($count->service_date->toDateString());
        $archives->archiveCompletedWeek($request->user(), $count->service_date->toDateString());

        return response()->json(['data' => $count], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date', 'after_or_equal:from'],
            'menu_cycle_id' => ['nullable', 'integer'],
        ]);

        $counts = DietListCount::with('user:id,name')
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->orderByDesc('service_date')
            ->get();

        return response()->json(['data' => $counts]);
    }

    /**
     * After a diet-list submission, write the summed population back onto the
     * matching meal_prep_log row (if one exists for the date).
     */
    private function syncServedPopulation(string $serviceDate, ?int $menuCycleId): void
    {
        $sum = DietListCount::where('service_date', $serviceDate)
            ->when($menuCycleId, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->sum('population');

        $log = MealPrepLog::where('service_date', $serviceDate)
            ->when($menuCycleId, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->first();

        if ($log) {
            $log->update(['served_population' => $sum]);
        }
    }
}
