<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => BudgetResource::collection(Budget::orderByDesc('fiscal_year')->get())]);
    }

    public function show(Budget $budget): JsonResponse
    {
        return response()->json(['data' => new BudgetResource($budget)]);
    }

    /** RND sets up a new fiscal year allocation. Unique per year — enforced in DB. */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = Budget::create($request->validated());

        // Re-evaluate open-execution POs that were blocked waiting for this allocation.
        $year      = $budget->fiscal_year;
        $lifecycle = app(PurchaseOrderLifecycleService::class);
        PurchaseOrder::where('lifecycle_status', 'open_execution')
            ->whereHas('shoppingList', fn ($q) => $q
                ->whereYear('period_start', $year)
                ->orWhereYear('period_end', $year))
            ->get()
            ->each(fn (PurchaseOrder $po) => $lifecycle->refresh($po));

        return response()->json(['data' => new BudgetResource($budget->fresh())], 201);
    }

    /** Fiscal year summary. Returns null with a notice if no allocation exists. */
    public function summary(Request $request): JsonResponse
    {
        $year   = (int) ($request->input('fiscal_year') ?? now()->year);
        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            return response()->json([
                'data'   => null,
                'notice' => "No allocation found for fiscal year {$year}. Please set it up.",
            ]);
        }

        return response()->json(['data' => new BudgetResource($budget)]);
    }

    /** Ledger entries for a fiscal year, optionally filtered by type. */
    public function ledger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year' => ['nullable', 'integer'],
            // single types plus the "manual" / "po" groups from the Budget page filter
            'type'        => ['nullable', 'in:po_deduction,manual_addition,manual_deduction,manual,po,all'],
        ]);

        $year = (int) ($data['fiscal_year'] ?? now()->year);

        $query = BudgetLedger::where('fiscal_year', $year)
            ->with(['purchaseOrder:id,po_number', 'creator:id,name'])
            ->orderByDesc('created_at');

        $filter = $data['type'] ?? null;
        if ($filter === 'manual') {
            $query->whereIn('type', ['manual_addition', 'manual_deduction']);
        } elseif ($filter === 'po') {
            $query->where('type', 'po_deduction');
        } elseif ($filter && $filter !== 'all') {
            $query->where('type', $filter);
        }

        $entries = $query->get()->map(fn (BudgetLedger $e) => [
            'id'                => $e->id,
            'fiscal_year'       => $e->fiscal_year,
            'type'              => $e->type,
            'amount'            => (float) $e->amount,
            'signed_amount'     => $e->signedAmount(),
            'reason'            => $e->reason,
            'reference'         => $e->reference,
            'purchase_order_id' => $e->purchase_order_id,
            'po_number'         => $e->purchaseOrder?->po_number,
            'procurement_span'  => $e->procurement_span,
            'created_by'        => $e->creator?->name,
            'created_at'        => $e->created_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $entries]);
    }

    /** RND logs a manual addition or deduction. Entries are immutable once created. */
    public function manualAdjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year' => ['required', 'integer'],
            'type'        => ['required', 'in:manual_addition,manual_deduction'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'reason'      => ['required', 'string', 'max:1000'],
            'reference'   => ['nullable', 'string', 'max:255'],
        ]);

        $year   = (int) $data['fiscal_year'];
        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            return response()->json(['message' => "No allocation found for fiscal year {$year}."], 422);
        }

        $entry = BudgetLedger::create([
            'fiscal_year' => $year,
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'reason'      => $data['reason'],
            'reference'   => $data['reference'] ?? null,
            'created_by'  => Auth::id(),
        ]);

        return response()->json(['data' => [
            'id'            => $entry->id,
            'type'          => $entry->type,
            'amount'        => (float) $entry->amount,
            'signed_amount' => $entry->signedAmount(),
            'reason'        => $entry->reason,
            'created_by'    => Auth::user()?->name,
            'created_at'    => $entry->created_at?->toDateTimeString(),
        ]], 201);
    }
}
