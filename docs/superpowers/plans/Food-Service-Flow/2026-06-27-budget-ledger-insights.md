# Budget Ledger + Insights (Prompt D+E) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the multi-record Budget model with a single fiscal-year allocation + append-only ledger, redesign the Budget page (no tabs, no graphs), create a dedicated Insights page with 4 chart categories, wire PO completion → auto ledger deduction, and update all reports to use the new data sources.

**Architecture:** `Budget` becomes one row per fiscal year (fiscal_year UNIQUE). A new `budget_ledger` table holds append-only entries typed `po_deduction | manual_addition | manual_deduction`. The `PurchaseOrderCompleted` event (already fired by `PurchaseOrderLifecycleService`) triggers a new `BudgetLedgerListener` that creates the `po_deduction` entry. If no fiscal-year allocation exists when a PO would complete, the lifecycle service blocks completion and returns early — RND sets up the year, then the system re-evaluates pending POs. The frontend Insights page is a new page at `food-service/insights`; the Budget page is rewritten as 4 vertical sections with no tabs and no charts.

**Tech Stack:** Laravel 11 (PHP), Next.js 15 (App Router), Tailwind, Recharts, Sanctum auth, phpunit. Test DB: `nutriscope_test` (shared, migrate:fresh --seed, never concurrent). Run tests: `cd backend && php artisan test --filter=BudgetLedgerTest` etc.

---

## Pre-flight: Read Before Touching Anything

**C+B integration points (from walkthrough doc):**
- `backend/app/Events/PurchaseOrderCompleted.php` — event is fired with `$po` fresh(['vendorGroups','shoppingList','programProjectActivity'])
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php:35` — `refresh()` runs inside a DB transaction; `PurchaseOrderCompleted` is fired at line 76
- `backend/app/Models/PurchaseOrder.php` — has `lifecycle_status`, `total_amount`, `actual_budget_per_head_per_day`, `shoppingList`, `vendorGroups`
- `backend/app/Models/PurchaseOrderVendorGroup.php` — has `total_amount`, supplier relation, attachments
- `backend/app/Models/ProgramProjectActivity.php` — has `execution_frozen_at`

**Files to delete after migration:**
- `backend/app/Models/BudgetAdjustment.php`
- `backend/app/Models/BudgetDailyLog.php`
- `backend/app/Services/BudgetActualService.php`
- `backend/app/Services/BudgetService.php`
- `backend/app/Services/FSS/ProcurementCostEfficiencyService.php`
- `backend/app/Http/Requests/FSS/UpdateBudgetRequest.php`
- `backend/components/foodservice/InsightsPanel.tsx` (replaced by insights page)

**Tests that will break and must be replaced (don't fix, rewrite from spec):**
- `backend/tests/Feature/BudgetAdjustmentTest.php` — entire file replaced by Task 13
- Budget-related tests in `FoodServiceOpsTest.php` lines 960–1200 — replaced by Task 13

---

## File Map

**Backend — Create:**
- `backend/database/migrations/2026_06_27_000001_redesign_budgets_for_fiscal_year.php`
- `backend/database/migrations/2026_06_27_000002_create_budget_ledger_table.php`
- `backend/database/migrations/2026_06_27_000003_drop_stale_budget_tables.php`
- `backend/app/Models/BudgetLedger.php`
- `backend/app/Listeners/BudgetLedgerListener.php`
- `backend/tests/Feature/BudgetLedgerTest.php`

**Backend — Modify:**
- `backend/app/Models/Budget.php` — fiscal_year schema only
- `backend/app/Http/Controllers/FSS/BudgetController.php` — full rewrite
- `backend/app/Http/Controllers/FSS/InsightsController.php` — full rewrite (4 new endpoints)
- `backend/app/Http/Requests/FSS/StoreBudgetRequest.php` — rewrite for fiscal_year
- `backend/app/Http/Resources/BudgetResource.php` — rewrite
- `backend/app/Providers/AppServiceProvider.php` — register event listener
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php` — add fiscal year guard before completing
- `backend/app/Services/Reports/Generators/BudgetReportGenerator.php` — rewrite for ledger
- `backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php` — update replenishments source
- `backend/database/factories/BudgetFactory.php` — rewrite
- `backend/routes/api.php` — update budget + insights routes

**Frontend — Create:**
- `frontend/app/(rnd)/food-service/insights/page.tsx`

**Frontend — Modify:**
- `frontend/services/budgetService.ts` — full rewrite
- `frontend/services/insightsService.ts` — full rewrite
- `frontend/app/(rnd)/food-service/budget/page.tsx` — full rewrite
- `frontend/components/layout/Sidebar.tsx` — add Insights nav link

---

## Task 1: Migrate — Redesign budgets table

**Files:**
- Create: `backend/database/migrations/2026_06_27_000001_redesign_budgets_for_fiscal_year.php`

- [ ] **Step 1: Write migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop FK from budget_daily_logs so we can alter budgets
        if (Schema::hasTable('budget_daily_logs')) {
            Schema::table('budget_daily_logs', function (Blueprint $table) {
                $table->dropForeign(['budget_id']);
            });
        }
        if (Schema::hasTable('budget_adjustments')) {
            Schema::table('budget_adjustments', function (Blueprint $table) {
                $table->dropForeign(['budget_id']);
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            // Remove stale columns
            $cols = Schema::getColumnListing('budgets');
            $drop = array_intersect($cols, [
                'rnd_user_id','menu_cycle_id','scope','name','actual_amount',
                'period_start','period_end','cost_per_person','population',
                'budget_per_head_day','budget_per_head_month','budget_per_head_year',
            ]);
            if ($drop) {
                $table->dropColumn($drop);
            }

            // Add new columns if missing
            if (!in_array('fiscal_year', Schema::getColumnListing('budgets'))) {
                $table->unsignedSmallInteger('fiscal_year')->unique()->after('id');
            }
            if (!in_array('per_head_day_limit', Schema::getColumnListing('budgets'))) {
                $table->decimal('per_head_day_limit', 10, 2)->nullable()->after('allocated_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['fiscal_year']);
            $table->dropColumn(['fiscal_year', 'per_head_day_limit']);
            $table->string('name')->nullable();
            $table->string('scope')->default('custom');
            $table->decimal('period_start', 8, 0)->nullable();
            $table->decimal('period_end', 8, 0)->nullable();
            $table->decimal('budget_per_head_day', 10, 2)->nullable();
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
cd backend && php artisan migrate
```

Expected: runs without error. `budgets` table has `id, fiscal_year, allocated_amount, per_head_day_limit, created_at, updated_at`.

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_06_27_000001_redesign_budgets_for_fiscal_year.php
git commit -m "feat: migration — redesign budgets table for fiscal year allocation"
```

---

## Task 2: Migrate — Create budget_ledger table

**Files:**
- Create: `backend/database/migrations/2026_06_27_000002_create_budget_ledger_table.php`

- [ ] **Step 1: Write migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->enum('type', ['po_deduction', 'manual_addition', 'manual_deduction']);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 1000)->nullable();
            $table->string('reference', 255)->nullable();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->string('procurement_span', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_ledger');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
cd backend && php artisan migrate
```

Expected: `budget_ledger` table created.

- [ ] **Step 3: Commit**

```bash
git add backend/database/migrations/2026_06_27_000002_create_budget_ledger_table.php
git commit -m "feat: migration — create budget_ledger append-only table"
```

---

## Task 3: Migrate — Drop stale budget tables

**Files:**
- Create: `backend/database/migrations/2026_06_27_000003_drop_stale_budget_tables.php`

- [ ] **Step 1: Write migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('budget_daily_logs');
        Schema::dropIfExists('budget_adjustments');
    }

    public function down(): void
    {
        // Recreate stubs — actual columns not restored, rollback only for safety
        \Illuminate\Support\Facades\DB::statement('CREATE TABLE IF NOT EXISTS budget_daily_logs (id bigint unsigned primary key auto_increment)');
        \Illuminate\Support\Facades\DB::statement('CREATE TABLE IF NOT EXISTS budget_adjustments (id bigint unsigned primary key auto_increment)');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
cd backend && php artisan migrate
```

Expected: `budget_daily_logs` and `budget_adjustments` tables gone.

- [ ] **Step 3: Delete stale model files**

Delete:
- `backend/app/Models/BudgetAdjustment.php`
- `backend/app/Models/BudgetDailyLog.php`
- `backend/app/Services/BudgetActualService.php`
- `backend/app/Services/BudgetService.php`
- `backend/app/Services/FSS/ProcurementCostEfficiencyService.php`
- `backend/app/Http/Requests/FSS/UpdateBudgetRequest.php`
- `backend/tests/Feature/BudgetAdjustmentTest.php` (to be rewritten in Task 13)

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: drop stale budget tables and delete superseded models/services"
```

---

## Task 4: Models — Budget + BudgetLedger

**Files:**
- Modify: `backend/app/Models/Budget.php`
- Create: `backend/app/Models/BudgetLedger.php`
- Modify: `backend/database/factories/BudgetFactory.php`

- [ ] **Step 1: Rewrite Budget model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['fiscal_year', 'allocated_amount', 'per_head_day_limit'];

    protected $casts = [
        'fiscal_year'       => 'integer',
        'allocated_amount'  => 'decimal:2',
        'per_head_day_limit' => 'decimal:2',
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(BudgetLedger::class, 'fiscal_year', 'fiscal_year');
    }

    public function remainingBalance(): float
    {
        $entries = $this->ledgerEntries()->get();
        $additions = $entries->whereIn('type', ['manual_addition'])->sum('amount');
        $deductions = $entries->whereIn('type', ['po_deduction', 'manual_deduction'])->sum('amount');
        return round((float)$this->allocated_amount + $additions - $deductions, 2);
    }

    public static function forYear(int $year): ?self
    {
        return static::where('fiscal_year', $year)->first();
    }
}
```

- [ ] **Step 2: Create BudgetLedger model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLedger extends Model
{
    protected $table = 'budget_ledger';

    protected $fillable = [
        'fiscal_year', 'type', 'amount', 'reason', 'reference',
        'purchase_order_id', 'procurement_span', 'created_by',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'amount'      => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed value for balance calculation. Additions positive, deductions negative. */
    public function signedAmount(): float
    {
        return $this->type === 'manual_addition'
            ? (float) $this->amount
            : -(float) $this->amount;
    }
}
```

- [ ] **Step 3: Rewrite BudgetFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Budget;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'fiscal_year'       => now()->year,
            'allocated_amount'  => $this->faker->randomFloat(2, 500000, 2000000),
            'per_head_day_limit' => $this->faker->randomFloat(2, 100, 500),
        ];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add backend/app/Models/Budget.php backend/app/Models/BudgetLedger.php backend/database/factories/BudgetFactory.php
git commit -m "feat: Budget model fiscal-year schema, new BudgetLedger append-only model"
```

---

## Task 5: BudgetController rewrite

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/BudgetController.php`
- Modify: `backend/app/Http/Requests/FSS/StoreBudgetRequest.php`
- Modify: `backend/app/Http/Resources/BudgetResource.php`

- [ ] **Step 1: Rewrite StoreBudgetRequest**

```php
<?php

namespace App\Http\Requests\FSS;

use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'fiscal_year'       => ['required', 'integer', 'min:2000', 'max:2100', 'unique:budgets,fiscal_year'],
            'allocated_amount'  => ['required', 'numeric', 'min:0'],
            'per_head_day_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 2: Rewrite BudgetResource**

```php
<?php

namespace App\Http\Resources;

use App\Models\BudgetLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entries = $this->ledgerEntries()->get();
        $additions    = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $manualDeduc  = (float) $entries->where('type', 'manual_deduction')->sum('amount');
        $poDeduc      = (float) $entries->where('type', 'po_deduction')->sum('amount');
        $remaining    = (float)$this->allocated_amount + $additions - $manualDeduc - $poDeduc;

        return [
            'id'                 => $this->id,
            'fiscal_year'        => $this->fiscal_year,
            'allocated_amount'   => $this->allocated_amount,
            'per_head_day_limit' => $this->per_head_day_limit,
            'total_po_deductions'      => round($poDeduc, 2),
            'total_manual_additions'   => round($additions, 2),
            'total_manual_deductions'  => round($manualDeduc, 2),
            'remaining_balance'        => round($remaining, 2),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
```

- [ ] **Step 3: Rewrite BudgetController**

```php
<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Services\FSS\PurchaseOrderLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
{
    /** List all fiscal year allocations. */
    public function index(): JsonResponse
    {
        return response()->json(['data' => BudgetResource::collection(Budget::orderByDesc('fiscal_year')->get())]);
    }

    /** Get one fiscal year. */
    public function show(Budget $budget): JsonResponse
    {
        return response()->json(['data' => new BudgetResource($budget)]);
    }

    /** RND sets up a new fiscal year allocation. One per year — unique enforced in DB. */
    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = Budget::create($request->validated());

        // Re-evaluate any open-execution POs in this fiscal year that were
        // blocked waiting for the allocation.
        $year = $budget->fiscal_year;
        $lifecycle = app(PurchaseOrderLifecycleService::class);
        PurchaseOrder::where('lifecycle_status', 'open_execution')
            ->whereHas('shoppingList', fn ($q) => $q
                ->whereYear('period_start', $year)
                ->orWhereYear('period_end', $year))
            ->get()
            ->each(fn (PurchaseOrder $po) => $lifecycle->refresh($po));

        return response()->json(['data' => new BudgetResource($budget->fresh())], 201);
    }

    /** Fiscal year summary: allocation + ledger totals. */
    public function summary(Request $request): JsonResponse
    {
        $year = (int) ($request->input('fiscal_year') ?? now()->year);
        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            return response()->json([
                'data' => null,
                'notice' => "No allocation found for fiscal year {$year}. Please set it up.",
            ]);
        }

        return response()->json(['data' => new BudgetResource($budget)]);
    }

    /** Ledger entries for a fiscal year, with optional type filter. */
    public function ledger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscal_year' => ['nullable', 'integer'],
            'type'        => ['nullable', 'in:po_deduction,manual_addition,manual_deduction'],
        ]);

        $year = (int) ($data['fiscal_year'] ?? now()->year);

        $query = BudgetLedger::where('fiscal_year', $year)
            ->with(['purchaseOrder:id,po_number', 'creator:id,name'])
            ->orderByDesc('created_at');

        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        $entries = $query->get()->map(fn (BudgetLedger $e) => [
            'id'               => $e->id,
            'fiscal_year'      => $e->fiscal_year,
            'type'             => $e->type,
            'amount'           => (float) $e->amount,
            'signed_amount'    => $e->signedAmount(),
            'reason'           => $e->reason,
            'reference'        => $e->reference,
            'purchase_order_id' => $e->purchase_order_id,
            'po_number'        => $e->purchaseOrder?->po_number,
            'procurement_span' => $e->procurement_span,
            'created_by'       => $e->creator?->name,
            'created_at'       => $e->created_at?->toDateTimeString(),
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
        ]);

        $year = (int) $data['fiscal_year'];
        $budget = Budget::where('fiscal_year', $year)->first();
        if (! $budget) {
            return response()->json(['message' => "No allocation found for fiscal year {$year}."], 422);
        }

        $entry = BudgetLedger::create([
            'fiscal_year' => $year,
            'type'        => $data['type'],
            'amount'      => $data['amount'],
            'reason'      => $data['reason'],
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
```

- [ ] **Step 4: Commit**

```bash
git add backend/app/Http/Controllers/FSS/BudgetController.php \
        backend/app/Http/Requests/FSS/StoreBudgetRequest.php \
        backend/app/Http/Resources/BudgetResource.php
git commit -m "feat: BudgetController — fiscal year setup, ledger list, manual adjustment, summary"
```

---

## Task 6: BudgetLedgerListener + EventServiceProvider

**Files:**
- Create: `backend/app/Listeners/BudgetLedgerListener.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Create listener**

```php
<?php

namespace App\Listeners;

use App\Events\PurchaseOrderCompleted;
use App\Models\Budget;
use App\Models\BudgetLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BudgetLedgerListener
{
    public function handle(PurchaseOrderCompleted $event): void
    {
        $po = $event->purchaseOrder;
        $sl = $po->shoppingList;

        if (! $sl?->period_start) {
            Log::warning("BudgetLedgerListener: PO {$po->id} has no shopping list period_start, skipping deduction.");
            return;
        }

        $year = Carbon::parse($sl->period_start)->year;
        $budget = Budget::where('fiscal_year', $year)->first();

        if (! $budget) {
            Log::warning("BudgetLedgerListener: No Budget allocation for fiscal year {$year}. PO {$po->id} deduction skipped — set up the year to create a pending deduction.");
            return;
        }

        // Idempotency: one deduction per PO
        if (BudgetLedger::where('purchase_order_id', $po->id)->where('type', 'po_deduction')->exists()) {
            return;
        }

        $span = $sl->period_start->format('m/d/Y') . ' - ' . $sl->period_end->format('m/d/Y');

        BudgetLedger::create([
            'fiscal_year'       => $year,
            'type'              => 'po_deduction',
            'amount'            => (float) $po->total_amount,
            'reference'         => $po->po_number ?? "PO #{$po->id}",
            'purchase_order_id' => $po->id,
            'procurement_span'  => $span,
            'created_by'        => null,
        ]);
    }
}
```

- [ ] **Step 2: Register listener in AppServiceProvider boot()**

Add to `AppServiceProvider::boot()` before the closing `}`:

```php
\Illuminate\Support\Facades\Event::listen(
    \App\Events\PurchaseOrderCompleted::class,
    \App\Listeners\BudgetLedgerListener::class,
);
```

- [ ] **Step 3: Verify listener fires — manual check**

```bash
cd backend && php artisan event:list | grep PurchaseOrderCompleted
```

Expected output includes: `App\Events\PurchaseOrderCompleted → App\Listeners\BudgetLedgerListener`

- [ ] **Step 4: Commit**

```bash
git add backend/app/Listeners/BudgetLedgerListener.php backend/app/Providers/AppServiceProvider.php
git commit -m "feat: BudgetLedgerListener — auto po_deduction on PO completion"
```

---

## Task 7: PurchaseOrderLifecycleService — fiscal year guard

**Files:**
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`

When a PO is otherwise ready to complete (all receipts + all served_population logged) but no fiscal year allocation exists for the PO's procurement year, do NOT advance lifecycle_status. The PO stays at `open_execution`. When RND later creates the fiscal year allocation, `BudgetController::store()` re-calls `refresh()` on those POs.

- [ ] **Step 1: Add fiscal year guard in refresh()**

In `PurchaseOrderLifecycleService::refresh()`, after computing `$actualTotal` and `$actualPerHead` (around line 50), insert:

```php
// Block completion if no fiscal year allocation exists for the procurement year.
$procurementYear = optional($po->shoppingList?->period_start)->year ?? now()->year;
if (! \App\Models\Budget::where('fiscal_year', $procurementYear)->exists()) {
    // PO stays open_execution. BudgetController::store() re-calls refresh() when set up.
    return $po;
}
```

Insert this block BEFORE the `$po->forceFill([...])` call.

- [ ] **Step 2: Run existing PO tests to confirm guard doesn't break existing flow**

```bash
cd backend && php artisan test --filter=PurchaseOrderLifecycleTest
```

Expected: existing tests pass (they create Budget factory records — update factory first in Task 4 if needed).

Note: Any test that previously completed a PO must also create a `Budget::factory()->create(['fiscal_year' => now()->year])` record or the PO will stay in open_execution. Check test setup in FoodServiceOpsTest around lines 200–241.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Services/FSS/PurchaseOrderLifecycleService.php
git commit -m "feat: block PO completion if no fiscal year Budget allocation exists"
```

---

## Task 8: InsightsController — 4 new endpoints

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/InsightsController.php`

Replace all 3 existing methods with 4 new ones. All endpoints accept `?fiscal_year=YYYY` (defaults to current year). Insights show Jan–Dec full range; missing months are zeroed.

- [ ] **Step 1: Rewrite InsightsController**

```php
<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\BudgetLedger;
use App\Models\Budget;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderVendorGroup;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    private function fiscalYear(Request $request): int
    {
        return (int) ($request->input('fiscal_year') ?? now()->year);
    }

    /**
     * Budget burn: Jan–Dec cumulative ledger deductions vs flat allocation line.
     * PO deductions stamped on PO completed_at date.
     * Manual entries stamped on created_at.
     */
    public function budgetBurn(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);
        $budget = Budget::where('fiscal_year', $year)->first();

        $allocated = $budget ? (float) $budget->allocated_amount : 0.0;

        $entries = BudgetLedger::where('fiscal_year', $year)
            ->with('purchaseOrder:id,completed_at,po_number')
            ->get();

        // Build monthly buckets Jan–Dec
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[sprintf('%04d-%02d', $year, $m)] = 0.0;
        }

        foreach ($entries as $entry) {
            $date = $entry->type === 'po_deduction'
                ? optional($entry->purchaseOrder?->completed_at)->format('Y-m')
                : Carbon::parse($entry->created_at)->format('Y-m');

            if ($date && isset($months[$date])) {
                $months[$date] += (float) $entry->amount * ($entry->type === 'manual_addition' ? -1 : 1);
            }
        }

        // Cumulative deductions
        $cumulative = 0.0;
        $points = [];
        foreach ($months as $month => $net) {
            $cumulative += $net;
            $points[] = [
                'month'            => $month,
                'cumulative_spent' => round($cumulative, 2),
                'allocated'        => $allocated,
                'remaining'        => round($allocated - $cumulative, 2),
            ];
        }

        return response()->json(['data' => [
            'points'   => $points,
            'summary'  => [
                'fiscal_year'    => $year,
                'allocated'      => $allocated,
                'total_deducted' => round($cumulative, 2),
                'remaining'      => round($allocated - $cumulative, 2),
            ],
        ]]);
    }

    /**
     * Per-head actual vs limit: one point per completed PO procurement span.
     * Shows actual_budget_per_head_per_day vs per_head_day_limit.
     * Phase 2 POs show pending markers (actual = null).
     */
    public function perHeadActualVsLimit(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);
        $budget = Budget::where('fiscal_year', $year)->first();
        $limit = $budget ? (float) $budget->per_head_day_limit : null;

        $pos = PurchaseOrder::with('shoppingList:id,period_start,period_end')
            ->whereIn('lifecycle_status', ['open_execution', 'completed', 'archived'])
            ->whereHas('shoppingList', fn ($q) => $q->whereYear('period_start', $year))
            ->orderBy('completed_at')
            ->get();

        $points = $pos->map(function (PurchaseOrder $po) use ($limit) {
            $sl = $po->shoppingList;
            $span = $sl
                ? ($sl->period_start?->format('M j') . '–' . $sl->period_end?->format('M j'))
                : null;

            return [
                'po_id'              => $po->id,
                'span'               => $span,
                'period_start'       => $sl?->period_start?->toDateString(),
                'lifecycle_status'   => $po->lifecycle_status,
                'actual_per_head'    => $po->lifecycle_status === 'open_execution'
                    ? null
                    : (float) ($po->actual_budget_per_head_per_day ?? 0),
                'pending'            => $po->lifecycle_status === 'open_execution',
                'limit_per_head'     => $limit,
            ];
        });

        return response()->json(['data' => [
            'points'  => $points,
            'summary' => [
                'fiscal_year'    => $year,
                'limit_per_head' => $limit,
                'avg_actual'     => $points->where('pending', false)->avg('actual_per_head'),
            ],
        ]]);
    }

    /**
     * Procurement deduction timeline: each completed PO stamped on finalized date.
     * Manual adjustments appear as separate markers.
     */
    public function procurementDeductionTimeline(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);

        $pos = PurchaseOrder::with('shoppingList:id,period_start,period_end')
            ->whereIn('lifecycle_status', ['completed', 'archived'])
            ->whereHas('shoppingList', fn ($q) => $q->whereYear('period_start', $year))
            ->orderBy('completed_at')
            ->get()
            ->map(fn (PurchaseOrder $po) => [
                'type'             => 'po',
                'date'             => $po->completed_at?->toDateString(),
                'po_id'            => $po->id,
                'reference'        => $po->po_number ?? "PO #{$po->id}",
                'procurement_span' => $po->shoppingList
                    ? ($po->shoppingList->period_start?->format('m/d/Y') . ' - ' . $po->shoppingList->period_end?->format('m/d/Y'))
                    : null,
                'total_cost'       => (float) $po->total_amount,
                'actual_per_head'  => (float) ($po->actual_budget_per_head_per_day ?? 0),
            ]);

        $manuals = BudgetLedger::where('fiscal_year', $year)
            ->whereIn('type', ['manual_addition', 'manual_deduction'])
            ->with('creator:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (BudgetLedger $e) => [
                'type'      => $e->type,
                'date'      => $e->created_at?->toDateString(),
                'amount'    => (float) $e->amount,
                'reason'    => $e->reason,
                'created_by' => $e->creator?->name,
            ]);

        $timeline = $pos->merge($manuals)->sortBy('date')->values();

        return response()->json(['data' => [
            'timeline'    => $timeline,
            'fiscal_year' => $year,
        ]]);
    }

    /**
     * Spend by supplier: total per vendor for the year using PurchaseOrderVendorGroup.
     */
    public function spendBySupplier(Request $request): JsonResponse
    {
        $year = $this->fiscalYear($request);

        $groups = PurchaseOrderVendorGroup::with('supplier:id,name')
            ->whereHas('purchaseOrder', fn ($q) => $q
                ->whereIn('lifecycle_status', ['completed', 'archived'])
                ->whereHas('shoppingList', fn ($q2) => $q2->whereYear('period_start', $year)))
            ->get(['supplier_id', 'total_amount']);

        $points = $groups
            ->groupBy('supplier_id')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'supplier_id' => $first->supplier_id,
                    'supplier'    => $first->supplier?->name ?? 'Unassigned',
                    'total'       => round((float) $group->sum('total_amount'), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return response()->json(['data' => [
            'points'      => $points,
            'fiscal_year' => $year,
            'total'       => round((float) $points->sum('total'), 2),
        ]]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add backend/app/Http/Controllers/FSS/InsightsController.php
git commit -m "feat: InsightsController — 4 new fiscal-year endpoints (budgetBurn, perHeadActualVsLimit, procurementDeductionTimeline, spendBySupplier)"
```

---

## Task 9: Routes update

**Files:**
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Replace budget + insights route block**

Find and replace the existing budget/insights routes (around lines 226–233 and 278–280):

**Remove these lines:**
```php
Route::get('budgets/{budget}/summary', [BudgetController::class, 'summary']);
Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);

Route::get('insights/spend-by-supplier', [\App\Http\Controllers\FSS\InsightsController::class, 'spendBySupplier']);
Route::get('insights/cost-per-head', [\App\Http\Controllers\FSS\InsightsController::class, 'costPerHead']);
Route::get('insights/consumption', [\App\Http\Controllers\FSS\InsightsController::class, 'consumption']);
```

**Replace with:**
```php
// Budgets — fiscal year summary + ledger (FSS read-only)
Route::get('budgets/summary', [BudgetController::class, 'summary']);
Route::get('budgets/ledger', [BudgetController::class, 'ledger']);
Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);

// Insights — fiscal year analytics (both roles, read-only)
Route::get('insights/budget-burn', [\App\Http\Controllers\FSS\InsightsController::class, 'budgetBurn']);
Route::get('insights/per-head-actual-vs-limit', [\App\Http\Controllers\FSS\InsightsController::class, 'perHeadActualVsLimit']);
Route::get('insights/procurement-deduction-timeline', [\App\Http\Controllers\FSS\InsightsController::class, 'procurementDeductionTimeline']);
Route::get('insights/spend-by-supplier', [\App\Http\Controllers\FSS\InsightsController::class, 'spendBySupplier']);
```

Also in the RND-only block, remove the old budget write routes and replace:

**Remove:**
```php
Route::post('budgets/{budget}/daily-logs', [BudgetController::class, 'storeDailyLog']);
Route::post('budgets/{budget}/adjustments', [BudgetController::class, 'storeAdjustment']);
Route::apiResource('budgets', BudgetController::class)->only(['store', 'update', 'destroy']);
```

**Replace with:**
```php
Route::post('budgets/adjust', [BudgetController::class, 'manualAdjust']);
Route::apiResource('budgets', BudgetController::class)->only(['store']);
```

- [ ] **Step 2: Verify routes resolve**

```bash
cd backend && php artisan route:list --path=api/fss/budgets
cd backend && php artisan route:list --path=api/fss/insights
```

Expected: 5 budget routes (`GET index, GET show, GET summary, GET ledger, POST store, POST adjust`) and 4 insight routes.

- [ ] **Step 3: Commit**

```bash
git add backend/routes/api.php
git commit -m "feat: update budget + insights routes for fiscal year model"
```

---

## Task 10: BudgetReportGenerator + DietaryCashBookGenerator updates

**Files:**
- Modify: `backend/app/Services/Reports/Generators/BudgetReportGenerator.php`
- Modify: `backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php`

- [ ] **Step 1: Rewrite BudgetReportGenerator**

```php
<?php

namespace App\Services\Reports\Generators;

use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\Report;
use App\Services\Reports\Contracts\ReportGenerator;

class BudgetReportGenerator implements ReportGenerator
{
    public function type(): string { return 'budget_report'; }
    public function view(): string { return 'reports.budget'; }
    public function paper(): array { return ['a4', 'portrait']; }

    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        $year = (int) ($params['fiscal_year'] ?? now()->year);

        $budget = Budget::where('fiscal_year', $year)->first();
        $entries = BudgetLedger::where('fiscal_year', $year)
            ->with(['purchaseOrder:id,po_number,completed_at', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->get();

        $allocated   = $budget ? (float) $budget->allocated_amount : 0.0;
        $poDeduc     = (float) $entries->where('type', 'po_deduction')->sum('amount');
        $manAdd      = (float) $entries->where('type', 'manual_addition')->sum('amount');
        $manDeduc    = (float) $entries->where('type', 'manual_deduction')->sum('amount');
        $remaining   = $allocated + $manAdd - $manDeduc - $poDeduc;

        return [
            'fiscal_year'             => $year,
            'budget'                  => $budget,
            'allocated_amount'        => $allocated,
            'per_head_day_limit'      => $budget ? (float) $budget->per_head_day_limit : null,
            'total_po_deductions'     => round($poDeduc, 2),
            'total_manual_additions'  => round($manAdd, 2),
            'total_manual_deductions' => round($manDeduc, 2),
            'remaining_balance'       => round($remaining, 2),
            'entries'                 => $entries->map(fn ($e) => [
                'type'             => $e->type,
                'amount'           => (float) $e->amount,
                'signed_amount'    => $e->signedAmount(),
                'reason'           => $e->reason,
                'reference'        => $e->reference,
                'po_number'        => $e->purchaseOrder?->po_number,
                'procurement_span' => $e->procurement_span,
                'created_by'       => $e->creator?->name,
                'created_at'       => $e->created_at?->toDateTimeString(),
            ])->all(),
        ];
    }
}
```

- [ ] **Step 2: Update DietaryCashBookGenerator — replenishments from BudgetLedger**

In `DietaryCashBookGenerator`, find the private static method `replenishmentsFromBudgets()` (or wherever it derives replenishments from Budget). Replace `Budget::coveringDate()` / `Budget::all()` usage with:

```php
private static function replenishmentsFromBudgets(\Carbon\Carbon $start, \Carbon\Carbon $end, string $officer): array
{
    $year = $start->year;
    $budget = \App\Models\Budget::where('fiscal_year', $year)->first();
    if (! $budget) {
        return [];
    }

    // Replenishments = manual_addition ledger entries in this period
    $additions = \App\Models\BudgetLedger::where('fiscal_year', $year)
        ->where('type', 'manual_addition')
        ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
        ->get();

    return $additions->map(fn ($e) => [
        'date'          => $e->created_at->toDateString(),
        'ref'           => $e->reference ?? '',
        'payee'         => $officer,
        'nature'        => 'Replenishment',
        'replenishment' => (float) $e->amount,
        'disbursement'  => 0.0,
    ])->all();
}
```

Also update the disbursements section: replace any `PurchaseOrder::where('status', 'received')` with `PurchaseOrder::where('lifecycle_status', 'completed')`.

- [ ] **Step 3: Verify report generators don't reference deleted classes**

```bash
cd backend && grep -r "BudgetActualService\|BudgetService\|ProcurementCostEfficiencyService\|BudgetAdjustment\|BudgetDailyLog" app/ routes/ tests/
```

Expected: zero matches (all deleted references cleaned up).

- [ ] **Step 4: Commit**

```bash
git add backend/app/Services/Reports/Generators/BudgetReportGenerator.php \
        backend/app/Services/Reports/Generators/DietaryCashBookGenerator.php
git commit -m "feat: update budget + cash book report generators for fiscal year ledger"
```

---

## Task 11: Frontend budgetService.ts rewrite

**Files:**
- Modify: `frontend/services/budgetService.ts`

- [ ] **Step 1: Rewrite budgetService.ts**

```typescript
import { apiFetch } from "@/lib/apiFetch";

export interface FiscalYearBudget {
  id: number;
  fiscal_year: number;
  allocated_amount: string;
  per_head_day_limit: string | null;
  total_po_deductions: number;
  total_manual_additions: number;
  total_manual_deductions: number;
  remaining_balance: number;
  created_at: string;
  updated_at: string;
}

export interface BudgetSetupPayload {
  fiscal_year: number;
  allocated_amount: number;
  per_head_day_limit?: number | null;
}

export interface BudgetLedgerEntry {
  id: number;
  fiscal_year: number;
  type: "po_deduction" | "manual_addition" | "manual_deduction";
  amount: number;
  signed_amount: number;
  reason: string | null;
  reference: string | null;
  purchase_order_id: number | null;
  po_number: string | null;
  procurement_span: string | null;
  created_by: string | null;
  created_at: string | null;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

export async function listFiscalYears(): Promise<FiscalYearBudget[]> {
  return unwrap(await apiFetch("/api/fss/budgets"), "Failed to load fiscal years.");
}

export async function getFiscalYearSummary(fiscalYear: number): Promise<FiscalYearBudget | null> {
  const res = await apiFetch(`/api/fss/budgets/summary?fiscal_year=${fiscalYear}`);
  const data = await res.json();
  return data.data ?? null;
}

export async function setupFiscalYear(payload: BudgetSetupPayload): Promise<FiscalYearBudget> {
  return unwrap(await apiFetch("/api/fss/budgets", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to set up fiscal year.");
}

export async function getLedger(opts: {
  fiscal_year?: number;
  type?: "po_deduction" | "manual_addition" | "manual_deduction";
}): Promise<BudgetLedgerEntry[]> {
  const qs = new URLSearchParams();
  if (opts.fiscal_year) qs.set("fiscal_year", String(opts.fiscal_year));
  if (opts.type) qs.set("type", opts.type);
  return unwrap(await apiFetch(`/api/fss/budgets/ledger?${qs}`), "Failed to load ledger.");
}

export async function addManualAdjustment(payload: {
  fiscal_year: number;
  type: "manual_addition" | "manual_deduction";
  amount: number;
  reason: string;
}): Promise<BudgetLedgerEntry> {
  return unwrap(await apiFetch("/api/fss/budgets/adjust", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }), "Failed to add adjustment.");
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/services/budgetService.ts
git commit -m "feat: rewrite budgetService.ts for fiscal year + ledger API"
```

---

## Task 12: Frontend insightsService.ts rewrite

**Files:**
- Modify: `frontend/services/insightsService.ts`

- [ ] **Step 1: Rewrite insightsService.ts**

```typescript
import { apiFetch } from "@/lib/apiFetch";

export interface BudgetBurnPoint {
  month: string;
  cumulative_spent: number;
  allocated: number;
  remaining: number;
}
export interface BudgetBurn {
  points: BudgetBurnPoint[];
  summary: { fiscal_year: number; allocated: number; total_deducted: number; remaining: number };
}

export interface PerHeadPoint {
  po_id: number;
  span: string | null;
  period_start: string | null;
  lifecycle_status: string;
  actual_per_head: number | null;
  pending: boolean;
  limit_per_head: number | null;
}
export interface PerHeadActualVsLimit {
  points: PerHeadPoint[];
  summary: { fiscal_year: number; limit_per_head: number | null; avg_actual: number | null };
}

export type TimelineEntry =
  | { type: "po"; date: string | null; po_id: number; reference: string; procurement_span: string | null; total_cost: number; actual_per_head: number }
  | { type: "manual_addition" | "manual_deduction"; date: string | null; amount: number; reason: string | null; created_by: string | null };

export interface ProcurementTimeline {
  timeline: TimelineEntry[];
  fiscal_year: number;
}

export interface SpendBySupplierPoint { supplier_id: number | null; supplier: string; total: number }
export interface SpendBySupplier {
  points: SpendBySupplierPoint[];
  fiscal_year: number;
  total: number;
}

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

const qs = (o: { fiscal_year?: number }) => {
  const p = new URLSearchParams();
  if (o.fiscal_year) p.set("fiscal_year", String(o.fiscal_year));
  return p.toString();
};

export async function getBudgetBurn(o: { fiscal_year?: number }): Promise<BudgetBurn> {
  return unwrap(await apiFetch(`/api/fss/insights/budget-burn?${qs(o)}`), "Failed to load budget burn.");
}
export async function getPerHeadActualVsLimit(o: { fiscal_year?: number }): Promise<PerHeadActualVsLimit> {
  return unwrap(await apiFetch(`/api/fss/insights/per-head-actual-vs-limit?${qs(o)}`), "Failed to load per-head insights.");
}
export async function getProcurementDeductionTimeline(o: { fiscal_year?: number }): Promise<ProcurementTimeline> {
  return unwrap(await apiFetch(`/api/fss/insights/procurement-deduction-timeline?${qs(o)}`), "Failed to load deduction timeline.");
}
export async function getSpendBySupplier(o: { fiscal_year?: number }): Promise<SpendBySupplier> {
  return unwrap(await apiFetch(`/api/fss/insights/spend-by-supplier?${qs(o)}`), "Failed to load spend by supplier.");
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/services/insightsService.ts
git commit -m "feat: rewrite insightsService.ts for 4 fiscal-year insight endpoints"
```

---

## Task 13: Frontend Budget page rewrite

**Files:**
- Modify: `frontend/app/(rnd)/food-service/budget/page.tsx`

Budget page has NO tabs, NO graphs. 4 vertical sections:
1. Fiscal year selector + summary (allocated, remaining, totals)
2. Manual adjustment controls — RND only (Add Funds ghost button, Deduct Funds ghost button)
3. Budget ledger table — reverse chronological, filterable
4. New fiscal year setup — RND only

**Rules (from Prompt A theme):** Plain text status, no badge pills, no colored background rows. Deductions use danger red text only. Additions use primary green text. Actions column rightmost in all tables.

- [ ] **Step 1: Write the new BudgetPage component**

File: `frontend/app/(rnd)/food-service/budget/page.tsx`

```tsx
"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/Button";
import {
  FiscalYearBudget, BudgetLedgerEntry, BudgetSetupPayload,
  listFiscalYears, getFiscalYearSummary, setupFiscalYear,
  getLedger, addManualAdjustment,
} from "@/services/budgetService";

const peso = (n: number) => `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

type LedgerFilter = "all" | "po_deduction" | "manual_addition" | "manual_deduction";

function Crumbs() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link>
      <span>/</span>
      <span>Food Service</span>
      <span>/</span>
      <span className="font-bold text-zinc-600">Budget</span>
    </div>
  );
}

function SummaryRow({ label, value, tone }: { label: string; value: string; tone?: "green" | "red" | "zinc" }) {
  const cls = tone === "green"
    ? "text-emerald-700"
    : tone === "red"
    ? "text-red-600"
    : "text-zinc-700";
  return (
    <div className="flex justify-between py-2 border-b border-zinc-100">
      <span className="text-xs text-zinc-500">{label}</span>
      <span className={`text-xs font-bold ${cls}`}>{value}</span>
    </div>
  );
}

function LedgerTable({ entries }: { entries: BudgetLedgerEntry[] }) {
  if (entries.length === 0) {
    return <p className="text-xs text-zinc-400">No ledger entries.</p>;
  }
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-xs">
        <thead className="bg-zinc-50 border-y border-zinc-100">
          <tr>
            {["Date", "Type", "Amount", "Reason", "Reference", "Created By"].map((h) => (
              <th key={h} className="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-zinc-100">
          {entries.map((e) => (
            <tr key={e.id}>
              <td className="px-3 py-2 text-zinc-500">{e.created_at?.slice(0, 16).replace("T", " ") ?? "—"}</td>
              <td className="px-3 py-2 text-zinc-600 capitalize">{e.type.replace(/_/g, " ")}</td>
              <td className={`px-3 py-2 font-mono font-bold ${e.type === "manual_addition" ? "text-emerald-700" : "text-red-600"}`}>
                {e.type === "manual_addition" ? "+" : "−"}{peso(e.amount)}
              </td>
              <td className="px-3 py-2 text-zinc-600">{e.reason ?? "—"}</td>
              <td className="px-3 py-2 text-zinc-500">{e.reference ?? e.po_number ?? "—"}{e.procurement_span ? ` (${e.procurement_span})` : ""}</td>
              <td className="px-3 py-2 text-zinc-500">{e.created_by ?? "System"}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function BudgetPage() {
  const { user } = useAuth();
  const isRnd = user?.role === "RND";
  const currentYear = new Date().getFullYear();

  const [years, setYears] = useState<FiscalYearBudget[]>([]);
  const [selectedYear, setSelectedYear] = useState<number>(currentYear);
  const [summary, setSummary] = useState<FiscalYearBudget | null>(null);
  const [noAllocation, setNoAllocation] = useState(false);
  const [ledger, setLedger] = useState<BudgetLedgerEntry[]>([]);
  const [filter, setFilter] = useState<LedgerFilter>("all");
  const [loading, setLoading] = useState(true);

  // Manual adjust form
  const [adjustType, setAdjustType] = useState<"manual_addition" | "manual_deduction">("manual_addition");
  const [adjustAmount, setAdjustAmount] = useState("");
  const [adjustReason, setAdjustReason] = useState("");
  const [adjusting, setAdjusting] = useState(false);
  const [adjustErr, setAdjustErr] = useState("");

  // New year setup form
  const [setupYear, setSetupYear] = useState(currentYear + 1);
  const [setupAmount, setSetupAmount] = useState("");
  const [setupLimit, setSetupLimit] = useState("");
  const [setupSaving, setSetupSaving] = useState(false);
  const [setupErr, setSetupErr] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const allYears = await listFiscalYears();
      setYears(allYears);

      const s = await getFiscalYearSummary(selectedYear);
      if (s) {
        setSummary(s);
        setNoAllocation(false);
      } else {
        setSummary(null);
        setNoAllocation(true);
      }

      const entries = await getLedger({
        fiscal_year: selectedYear,
        type: filter === "all" ? undefined : filter,
      });
      setLedger(entries);
    } finally {
      setLoading(false);
    }
  }, [selectedYear, filter]);

  useEffect(() => { load(); }, [load]);

  async function submitAdjust() {
    if (!adjustAmount || parseFloat(adjustAmount) <= 0) {
      setAdjustErr("Enter an amount greater than 0.");
      return;
    }
    if (!adjustReason.trim()) {
      setAdjustErr("Reason is required.");
      return;
    }
    setAdjusting(true);
    setAdjustErr("");
    try {
      await addManualAdjustment({
        fiscal_year: selectedYear,
        type: adjustType,
        amount: parseFloat(adjustAmount),
        reason: adjustReason.trim(),
      });
      setAdjustAmount("");
      setAdjustReason("");
      load();
    } catch (e) {
      setAdjustErr(e instanceof Error ? e.message : "Failed.");
    } finally {
      setAdjusting(false);
    }
  }

  async function submitSetup() {
    if (!setupAmount || parseFloat(setupAmount) <= 0) {
      setSetupErr("Enter a valid allocated amount.");
      return;
    }
    setSetupSaving(true);
    setSetupErr("");
    try {
      const payload: BudgetSetupPayload = {
        fiscal_year: setupYear,
        allocated_amount: parseFloat(setupAmount),
        per_head_day_limit: setupLimit ? parseFloat(setupLimit) : null,
      };
      await setupFiscalYear(payload);
      setSetupAmount("");
      setSetupLimit("");
      setSelectedYear(setupYear);
      load();
    } catch (e) {
      setSetupErr(e instanceof Error ? e.message : "Failed to set up fiscal year.");
    } finally {
      setSetupSaving(false);
    }
  }

  const inp = "w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500";

  return (
    <div className="space-y-8">
      <Crumbs />

      <div className="flex items-center justify-between">
        <h1 className="text-lg font-extrabold text-zinc-800">Budget</h1>
      </div>

      {/* ── Section 1: Fiscal year selector + summary ── */}
      <section className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex items-center gap-4">
          <label className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider">Fiscal Year</label>
          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(parseInt(e.target.value))}
            className="px-3 py-1.5 text-sm border border-zinc-200 rounded-lg bg-white"
          >
            {Array.from({ length: 5 }, (_, i) => currentYear - 2 + i).map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
        </div>

        {loading ? (
          <p className="text-xs text-zinc-400">Loading…</p>
        ) : noAllocation ? (
          <p className="text-sm text-amber-600 font-semibold">
            No allocation set for fiscal year {selectedYear}. Use the setup section below to create one.
          </p>
        ) : summary ? (
          <div className="max-w-sm space-y-1">
            <SummaryRow label="Allocated amount" value={peso(parseFloat(summary.allocated_amount))} />
            <SummaryRow label="Per-head/day limit" value={summary.per_head_day_limit ? peso(parseFloat(summary.per_head_day_limit)) : "—"} />
            <SummaryRow label="Total PO deductions" value={peso(summary.total_po_deductions)} tone="red" />
            <SummaryRow label="Total manual additions" value={peso(summary.total_manual_additions)} tone="green" />
            <SummaryRow label="Total manual deductions" value={peso(summary.total_manual_deductions)} tone="red" />
            <SummaryRow label="Remaining balance" value={peso(summary.remaining_balance)} tone={summary.remaining_balance < 0 ? "red" : "green"} />
          </div>
        ) : null}
      </section>

      {/* ── Section 2: Manual adjustment controls (RND only) ── */}
      {isRnd && !noAllocation && (
        <section className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm space-y-4">
          <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Manual Budget Adjustment</h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Type</label>
              <select
                value={adjustType}
                onChange={(e) => setAdjustType(e.target.value as "manual_addition" | "manual_deduction")}
                className={`${inp} bg-white`}
              >
                <option value="manual_addition">Add Funds</option>
                <option value="manual_deduction">Deduct Funds</option>
              </select>
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Amount ₱</label>
              <input type="number" min="0.01" step="0.01" value={adjustAmount}
                onChange={(e) => setAdjustAmount(e.target.value)} className={inp} />
            </div>
            <div className="sm:col-span-1">
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Reason</label>
              <input value={adjustReason} onChange={(e) => setAdjustReason(e.target.value)}
                placeholder="Required" className={inp} />
            </div>
            <Button variant="ghost" onClick={submitAdjust} disabled={adjusting} className="!py-2 text-xs">
              {adjusting ? "Saving…" : "Log"}
            </Button>
          </div>
          {adjustErr && <p className="text-[10px] text-red-600 font-semibold">{adjustErr}</p>}
          <p className="text-[10px] text-zinc-400">Entries are immutable. Corrections use offsetting entries.</p>
        </section>
      )}

      {/* ── Section 3: Ledger table ── */}
      <section className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Budget Ledger — FY {selectedYear}</h2>
          <select
            value={filter}
            onChange={(e) => setFilter(e.target.value as LedgerFilter)}
            className="px-3 py-1.5 text-xs border border-zinc-200 rounded-lg bg-white"
          >
            <option value="all">All</option>
            <option value="po_deduction">Purchase Orders</option>
            <option value="manual_addition">Manual Additions</option>
            <option value="manual_deduction">Manual Deductions</option>
          </select>
        </div>
        {loading ? <p className="text-xs text-zinc-400">Loading…</p> : <LedgerTable entries={ledger} />}
      </section>

      {/* ── Section 4: New fiscal year setup (RND only) ── */}
      {isRnd && (
        <section className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm space-y-4">
          <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Set Up New Fiscal Year</h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Fiscal Year</label>
              <input type="number" value={setupYear} onChange={(e) => setSetupYear(parseInt(e.target.value))}
                className={inp} min={2024} max={2100} />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">Allocated Amount ₱</label>
              <input type="number" min="0" step="0.01" value={setupAmount}
                onChange={(e) => setSetupAmount(e.target.value)} className={inp} />
            </div>
            <div>
              <label className="block text-[10px] font-bold text-zinc-500 uppercase mb-1">₱/Head/Day Limit</label>
              <input type="number" min="0" step="0.01" value={setupLimit}
                onChange={(e) => setSetupLimit(e.target.value)} placeholder="Optional" className={inp} />
            </div>
            <Button variant="primary" onClick={submitSetup} disabled={setupSaving} className="!py-2 text-xs">
              {setupSaving ? "Saving…" : `Set Up ${setupYear}`}
            </Button>
          </div>
          {setupErr && <p className="text-[10px] text-red-600 font-semibold">{setupErr}</p>}
          <p className="text-[10px] text-zinc-400">Each year is independent. No carryover from prior years. Creates a new record — will not overwrite existing.</p>
        </section>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Delete old InsightsPanel component**

```bash
rm frontend/components/foodservice/InsightsPanel.tsx
```

- [ ] **Step 3: Commit**

```bash
git add frontend/app/(rnd)/food-service/budget/page.tsx
git rm frontend/components/foodservice/InsightsPanel.tsx
git commit -m "feat: redesign Budget page — fiscal year, ledger, manual adjust, no tabs no graphs"
```

---

## Task 14: Frontend Insights page (new)

**Files:**
- Create: `frontend/app/(rnd)/food-service/insights/page.tsx`

RND only (FSS cannot see insights). Shows fiscal year selector → category picker → chart for selected category. Full Jan–Dec range always shown. Uses Recharts (already in project — check existing budget page for import pattern).

- [ ] **Step 1: Create insights page**

```tsx
"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import {
  ResponsiveContainer, LineChart, Line, BarChart, Bar, XAxis, YAxis,
  CartesianGrid, Tooltip, Legend, ReferenceLine,
} from "recharts";
import { useAuth } from "@/contexts/AuthContext";
import {
  getBudgetBurn, getPerHeadActualVsLimit,
  getProcurementDeductionTimeline, getSpendBySupplier,
  BudgetBurn, PerHeadActualVsLimit, ProcurementTimeline, SpendBySupplier,
} from "@/services/insightsService";

const peso = (n: number | null | undefined) =>
  n == null ? "—" : `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const CATEGORIES = [
  { key: "budget_burn",       label: "Budget Burn" },
  { key: "per_head",          label: "Budget/Head/Day Actual vs Limit" },
  { key: "deduction_timeline", label: "Procurement Deduction Timeline" },
  { key: "spend_by_supplier", label: "Spend by Supplier" },
] as const;

type Category = typeof CATEGORIES[number]["key"];

function Crumbs() {
  return (
    <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
      <Link href="/dashboard" className="hover:text-emerald-700">Home</Link>
      <span>/</span><span>Food Service</span><span>/</span>
      <span className="font-bold text-zinc-600">Insights</span>
    </div>
  );
}

function BudgetBurnChart({ data }: { data: BudgetBurn }) {
  const { points, summary } = data;
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-4 text-xs">
        <span className="text-zinc-500">Allocated <strong className="text-zinc-800">{peso(summary.allocated)}</strong></span>
        <span className="text-zinc-500">Spent <strong className="text-red-600">{peso(summary.total_deducted)}</strong></span>
        <span className="text-zinc-500">Remaining <strong className={summary.remaining < 0 ? "text-red-600" : "text-emerald-700"}>{peso(summary.remaining)}</strong></span>
      </div>
      <ResponsiveContainer width="100%" height={280}>
        <LineChart data={points} margin={{ top: 8, right: 16, left: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
          <XAxis dataKey="month" tick={{ fontSize: 10 }} />
          <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `₱${(v / 1000).toFixed(0)}k`} />
          <Tooltip formatter={(v: number) => peso(v)} />
          <Legend wrapperStyle={{ fontSize: 11 }} />
          <Line type="monotone" dataKey="allocated" name="Allocation" stroke="#10b981" strokeDasharray="4 2" dot={false} />
          <Line type="monotone" dataKey="cumulative_spent" name="Cumulative Spent" stroke="#ef4444" dot={false} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

function PerHeadChart({ data }: { data: PerHeadActualVsLimit }) {
  const { points, summary } = data;
  const chartData = points.map((p) => ({
    span: p.span ?? `PO ${p.po_id}`,
    actual: p.pending ? null : p.actual_per_head,
    limit: p.limit_per_head,
    pending: p.pending,
  }));
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-4 text-xs">
        <span className="text-zinc-500">Limit <strong className="text-zinc-800">{peso(summary.limit_per_head)}/head/day</strong></span>
        <span className="text-zinc-500">Avg Actual <strong className="text-emerald-700">{peso(summary.avg_actual)}/head/day</strong></span>
      </div>
      <ResponsiveContainer width="100%" height={280}>
        <BarChart data={chartData} margin={{ top: 8, right: 16, left: 8, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" />
          <XAxis dataKey="span" tick={{ fontSize: 10 }} />
          <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `₱${v}`} />
          <Tooltip formatter={(v: number | null) => v == null ? "Pending" : peso(v)} />
          <Legend wrapperStyle={{ fontSize: 11 }} />
          {summary.limit_per_head && (
            <ReferenceLine y={summary.limit_per_head} stroke="#ef4444" strokeDasharray="4 2" label={{ value: "Limit", fontSize: 10, fill: "#ef4444" }} />
          )}
          <Bar dataKey="actual" name="Actual ₱/head/day" fill="#10b981" radius={[3, 3, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
      {points.some((p) => p.pending) && (
        <p className="text-[10px] text-zinc-400">Pending markers: PO not yet completed — actual not available.</p>
      )}
    </div>
  );
}

function DeductionTimeline({ data }: { data: ProcurementTimeline }) {
  if (data.timeline.length === 0) {
    return <p className="text-xs text-zinc-400">No entries for this fiscal year.</p>;
  }
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-xs">
        <thead className="bg-zinc-50 border-y border-zinc-100">
          <tr>
            {["Date", "Type", "Reference / Span", "Amount", "Notes"].map((h) => (
              <th key={h} className="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-zinc-100">
          {data.timeline.map((e, i) => {
            if (e.type === "po") {
              return (
                <tr key={i}>
                  <td className="px-3 py-2 text-zinc-500">{e.date ?? "—"}</td>
                  <td className="px-3 py-2 text-zinc-600">PO Deduction</td>
                  <td className="px-3 py-2 text-zinc-600">{e.reference}{e.procurement_span ? ` · ${e.procurement_span}` : ""}</td>
                  <td className="px-3 py-2 text-red-600 font-bold">{peso(e.total_cost)}</td>
                  <td className="px-3 py-2 text-zinc-400">{peso(e.actual_per_head)}/head/day</td>
                </tr>
              );
            }
            return (
              <tr key={i}>
                <td className="px-3 py-2 text-zinc-500">{e.date ?? "—"}</td>
                <td className="px-3 py-2 text-zinc-600 capitalize">{e.type.replace(/_/g, " ")}</td>
                <td className="px-3 py-2 text-zinc-600">—</td>
                <td className={`px-3 py-2 font-bold ${e.type === "manual_addition" ? "text-emerald-700" : "text-red-600"}`}>
                  {e.type === "manual_addition" ? "+" : "−"}{peso(e.amount)}
                </td>
                <td className="px-3 py-2 text-zinc-500">{e.reason ?? "—"} · {e.created_by ?? "System"}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function SpendBySupplierChart({ data }: { data: SpendBySupplier }) {
  if (data.points.length === 0) {
    return <p className="text-xs text-zinc-400">No completed POs found for this fiscal year.</p>;
  }
  return (
    <div className="space-y-3">
      <div className="text-xs text-zinc-500">Total spend <strong className="text-zinc-800">{peso(data.total)}</strong></div>
      <ResponsiveContainer width="100%" height={280}>
        <BarChart data={data.points} layout="vertical" margin={{ top: 8, right: 48, left: 80, bottom: 8 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="#f4f4f5" horizontal={false} />
          <XAxis type="number" tick={{ fontSize: 10 }} tickFormatter={(v) => `₱${(v / 1000).toFixed(0)}k`} />
          <YAxis type="category" dataKey="supplier" tick={{ fontSize: 10 }} width={80} />
          <Tooltip formatter={(v: number) => peso(v)} />
          <Bar dataKey="total" name="Total Spend" fill="#10b981" radius={[0, 3, 3, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

export default function InsightsPage() {
  const { user } = useAuth();

  if (user?.role !== "RND") {
    return (
      <div className="space-y-4">
        <Crumbs />
        <p className="text-sm text-zinc-500">Insights are available to RND only.</p>
      </div>
    );
  }

  const currentYear = new Date().getFullYear();
  const [fiscalYear, setFiscalYear] = useState<number>(currentYear);
  const [category, setCategory] = useState<Category | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const [burnData, setBurnData] = useState<BudgetBurn | null>(null);
  const [perHeadData, setPerHeadData] = useState<PerHeadActualVsLimit | null>(null);
  const [timelineData, setTimelineData] = useState<ProcurementTimeline | null>(null);
  const [supplierData, setSupplierData] = useState<SpendBySupplier | null>(null);

  const loadCategory = useCallback(async (cat: Category, year: number) => {
    setLoading(true);
    setError("");
    try {
      if (cat === "budget_burn") setBurnData(await getBudgetBurn({ fiscal_year: year }));
      else if (cat === "per_head") setPerHeadData(await getPerHeadActualVsLimit({ fiscal_year: year }));
      else if (cat === "deduction_timeline") setTimelineData(await getProcurementDeductionTimeline({ fiscal_year: year }));
      else if (cat === "spend_by_supplier") setSupplierData(await getSpendBySupplier({ fiscal_year: year }));
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load insight.");
    } finally {
      setLoading(false);
    }
  }, []);

  function selectCategory(cat: Category) {
    setCategory(cat);
    loadCategory(cat, fiscalYear);
  }

  function selectYear(year: number) {
    setFiscalYear(year);
    if (category) loadCategory(category, year);
  }

  return (
    <div className="space-y-6">
      <Crumbs />
      <h1 className="text-lg font-extrabold text-zinc-800">Insights</h1>

      {/* Step 1: Year selector */}
      <section className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
        <div className="flex items-center gap-4 flex-wrap">
          <label className="text-xs font-extrabold text-zinc-500 uppercase tracking-wider">Fiscal Year</label>
          <select
            value={fiscalYear}
            onChange={(e) => selectYear(parseInt(e.target.value))}
            className="px-3 py-1.5 text-sm border border-zinc-200 rounded-lg bg-white"
          >
            {Array.from({ length: 5 }, (_, i) => currentYear - 2 + i).map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>
          <p className="text-xs text-zinc-400">Select fiscal year, then choose an insight category below.</p>
        </div>
      </section>

      {/* Step 2: Category selector */}
      <section className="flex flex-wrap gap-2">
        {CATEGORIES.map((c) => (
          <button
            key={c.key}
            onClick={() => selectCategory(c.key)}
            className={`px-4 py-2 text-xs font-bold rounded-xl border transition-all ${
              category === c.key
                ? "bg-emerald-50 border-emerald-300 text-emerald-700"
                : "bg-white border-zinc-200 text-zinc-600 hover:border-zinc-300"
            }`}
          >
            {c.label}
          </button>
        ))}
      </section>

      {/* Step 3: Insight display */}
      {category && (
        <section className="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
          <h2 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">
            {CATEGORIES.find((c) => c.key === category)?.label} — {fiscalYear}
          </h2>
          {loading && <p className="text-xs text-zinc-400">Loading…</p>}
          {error && <p className="text-xs text-red-600 font-semibold">{error}</p>}
          {!loading && !error && category === "budget_burn" && burnData && <BudgetBurnChart data={burnData} />}
          {!loading && !error && category === "per_head" && perHeadData && <PerHeadChart data={perHeadData} />}
          {!loading && !error && category === "deduction_timeline" && timelineData && <DeductionTimeline data={timelineData} />}
          {!loading && !error && category === "spend_by_supplier" && supplierData && <SpendBySupplierChart data={supplierData} />}
        </section>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/(rnd)/food-service/insights/page.tsx
git commit -m "feat: create Insights page with 4 fiscal-year chart categories"
```

---

## Task 15: Sidebar nav — add Insights link

**Files:**
- Modify: `frontend/components/layout/Sidebar.tsx`

- [ ] **Step 1: Add Insights link after Budget in the food-service sidebar section**

Find the Budget link block (around line 401) and add Insights immediately after it:

```tsx
<Link
  href="/food-service/insights"
  className={`flex items-center gap-2 px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wider transition-all duration-150 ${
    pathname === "/food-service/insights"
      ? "text-emerald-500 font-extrabold"
      : "text-zinc-500 hover:text-zinc-300"
  }`}
>
  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/insights" ? "bg-emerald-500" : "bg-zinc-700"}`} />
  Insights
</Link>
```

- [ ] **Step 2: Commit**

```bash
git add frontend/components/layout/Sidebar.tsx
git commit -m "feat: add Insights nav link to Sidebar"
```

---

## Task 16: Backend tests — BudgetLedgerTest

**Files:**
- Create: `backend/tests/Feature/BudgetLedgerTest.php`
- Modify: `backend/tests/Feature/FoodServiceOpsTest.php` (update stale budget tests)

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature;

use App\Events\PurchaseOrderCompleted;
use App\Listeners\BudgetLedgerListener;
use App\Models\Budget;
use App\Models\BudgetLedger;
use App\Models\PurchaseOrder;
use App\Models\ShoppingList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BudgetLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $rnd;
    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rnd = User::factory()->create(['role' => 'RND']);
        $this->fss = User::factory()->create(['role' => 'FSS']);
    }

    // ── Fiscal year allocation ─────────────────────────────────────────────

    public function test_rnd_can_create_fiscal_year_allocation(): void
    {
        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', [
                'fiscal_year'       => 2026,
                'allocated_amount'  => 1000000,
                'per_head_day_limit' => 250.00,
            ])
            ->assertCreated()
            ->assertJsonPath('data.fiscal_year', 2026)
            ->assertJsonPath('data.allocated_amount', '1000000.00');

        $this->assertDatabaseHas('budgets', ['fiscal_year' => 2026, 'allocated_amount' => 1000000]);
    }

    public function test_duplicate_fiscal_year_is_rejected(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', [
                'fiscal_year' => 2026, 'allocated_amount' => 500000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fiscal_year']);
    }

    public function test_fss_cannot_create_fiscal_year(): void
    {
        $this->actingAs($this->fss)
            ->postJson('/api/fss/budgets', [
                'fiscal_year' => 2026, 'allocated_amount' => 500000,
            ])
            ->assertForbidden();
    }

    public function test_no_fiscal_year_allocation_returns_notice(): void
    {
        $res = $this->actingAs($this->rnd)
            ->getJson('/api/fss/budgets/summary?fiscal_year=2099')
            ->assertOk();

        $this->assertNull($res->json('data'));
        $this->assertStringContainsString('2099', $res->json('notice'));
    }

    public function test_new_year_has_no_carryover_from_prior_year(): void
    {
        Budget::factory()->create(['fiscal_year' => 2025, 'allocated_amount' => 999999]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 500000])
            ->assertCreated()
            ->assertJsonPath('data.remaining_balance', 500000.0);
    }

    // ── Manual adjustments ────────────────────────────────────────────────

    public function test_rnd_can_add_manual_addition_and_balance_updates(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)
            ->postJson('/api/fss/budgets/adjust', [
                'fiscal_year' => 2026,
                'type'        => 'manual_addition',
                'amount'      => 20000,
                'reason'      => 'LGU donation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'manual_addition');

        $summary = $this->actingAs($this->rnd)
            ->getJson('/api/fss/budgets/summary?fiscal_year=2026')
            ->assertOk();

        $this->assertEquals(100000 + 20000, $summary->json('data.remaining_balance'));
    }

    public function test_manual_deduction_reduces_balance(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2026, 'type' => 'manual_deduction',
            'amount' => 5000, 'reason' => 'Correction',
        ])->assertCreated();

        $res = $this->actingAs($this->rnd)->getJson('/api/fss/budgets/summary?fiscal_year=2026')->assertOk();
        $this->assertEquals(100000 - 5000, $res->json('data.remaining_balance'));
    }

    public function test_manual_adjustment_requires_reason(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->rnd)->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_fss_cannot_log_manual_adjustment(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 100000]);

        $this->actingAs($this->fss)->postJson('/api/fss/budgets/adjust', [
            'fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1000, 'reason' => 'Test',
        ])->assertForbidden();
    }

    public function test_ledger_entries_cannot_be_edited_or_deleted(): void
    {
        // Ledger has no update/delete routes — verify API returns 404/405
        $this->actingAs($this->rnd)->patchJson('/api/fss/budgets/ledger/1', [])->assertStatus(405);
        $this->actingAs($this->rnd)->deleteJson('/api/fss/budgets/ledger/1')->assertStatus(405);
    }

    public function test_remaining_balance_correct_across_mixed_entries(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 200000]);

        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 50000, 'created_by' => $this->rnd->id]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_deduction', 'amount' => 10000, 'created_by' => $this->rnd->id]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction', 'amount' => 30000, 'created_by' => null]);

        // 200000 + 50000 - 10000 - 30000 = 210000
        $res = $this->actingAs($this->rnd)->getJson('/api/fss/budgets/summary?fiscal_year=2026')->assertOk();
        $this->assertEquals(210000, $res->json('data.remaining_balance'));
    }

    // ── PO completion → ledger auto-deduction ────────────────────────────

    public function test_po_completion_creates_po_deduction_ledger_entry(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 500000]);

        $sl = ShoppingList::factory()->create([
            'period_start' => '2026-06-05',
            'period_end'   => '2026-06-08',
            'status'       => 'converted',
        ]);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
            'total_amount'     => 45000,
            'po_number'        => 'PO-001',
            'completed_at'     => now(),
        ]);

        $listener = new BudgetLedgerListener();
        $listener->handle(new PurchaseOrderCompleted($po->load('shoppingList')));

        $this->assertDatabaseHas('budget_ledger', [
            'fiscal_year'       => 2026,
            'type'              => 'po_deduction',
            'amount'            => 45000,
            'purchase_order_id' => $po->id,
            'reference'         => 'PO-001',
        ]);
    }

    public function test_po_deduction_not_created_if_no_fiscal_year_allocation(): void
    {
        $sl = ShoppingList::factory()->create([
            'period_start' => '2099-06-05',
            'period_end'   => '2099-06-08',
            'status'       => 'converted',
        ]);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
            'total_amount'     => 10000,
            'completed_at'     => now(),
        ]);

        $listener = new BudgetLedgerListener();
        $listener->handle(new PurchaseOrderCompleted($po->load('shoppingList')));

        $this->assertDatabaseMissing('budget_ledger', ['type' => 'po_deduction']);
    }

    public function test_po_deduction_is_idempotent(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 500000]);
        $sl = ShoppingList::factory()->create([
            'period_start' => '2026-06-05', 'period_end' => '2026-06-08', 'status' => 'converted',
        ]);
        $po = PurchaseOrder::factory()->create([
            'shopping_list_id' => $sl->id,
            'lifecycle_status' => 'completed',
            'total_amount'     => 45000,
            'completed_at'     => now(),
        ]);

        $listener = new BudgetLedgerListener();
        $listener->handle(new PurchaseOrderCompleted($po->load('shoppingList')));
        $listener->handle(new PurchaseOrderCompleted($po->load('shoppingList')));

        $this->assertDatabaseCount('budget_ledger', 1);
    }

    // ── Insights routes ───────────────────────────────────────────────────

    public function test_insights_routes_respond_for_rnd(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 500000]);

        $routes = [
            '/api/fss/insights/budget-burn?fiscal_year=2026',
            '/api/fss/insights/per-head-actual-vs-limit?fiscal_year=2026',
            '/api/fss/insights/procurement-deduction-timeline?fiscal_year=2026',
            '/api/fss/insights/spend-by-supplier?fiscal_year=2026',
        ];

        foreach ($routes as $route) {
            $this->actingAs($this->rnd)->getJson($route)->assertOk();
        }
    }

    public function test_ledger_filter_by_type_works(): void
    {
        Budget::factory()->create(['fiscal_year' => 2026, 'allocated_amount' => 500000]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'manual_addition', 'amount' => 1000, 'created_by' => $this->rnd->id]);
        BudgetLedger::create(['fiscal_year' => 2026, 'type' => 'po_deduction', 'amount' => 2000]);

        $res = $this->actingAs($this->rnd)
            ->getJson('/api/fss/budgets/ledger?fiscal_year=2026&type=manual_addition')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('manual_addition', $res->json('data.0.type'));
    }
}
```

- [ ] **Step 2: Update stale budget tests in FoodServiceOpsTest.php**

Find tests `test_rnd_can_create_budget`, `test_rnd_can_log_daily_budget_expense`, `test_budget_requires_allocated_amount`, `test_budget_actual_*`, `test_budget_report_*` (lines 960–1200 approx). Replace all with:

```php
// ── BUDGETS (fiscal year model) ────────────────────────────────────────────

public function test_rnd_can_create_fiscal_year_in_foodserviceops(): void
{
    $this->actingAs($this->rnd)
        ->postJson('/api/fss/budgets', [
            'fiscal_year'      => 2026,
            'allocated_amount' => 500000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.fiscal_year', 2026);

    $this->assertDatabaseHas('budgets', ['fiscal_year' => 2026]);
}

public function test_budget_allocated_amount_required(): void
{
    $this->actingAs($this->rnd)
        ->postJson('/api/fss/budgets', ['fiscal_year' => 2026])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['allocated_amount']);
}

public function test_fss_cannot_manage_budget_allocation(): void
{
    $this->actingAs($this->fss)
        ->postJson('/api/fss/budgets', ['fiscal_year' => 2026, 'allocated_amount' => 100000])
        ->assertForbidden();
}
```

Also update the test at line ~1514 (`test_insights_routes_respond_for_fss`) since old endpoints are gone:

```php
public function test_old_insights_routes_are_gone(): void
{
    $this->actingAs($this->fss)->getJson('/api/fss/insights/cost-per-head')->assertNotFound();
    $this->actingAs($this->fss)->getJson('/api/fss/insights/consumption')->assertNotFound();
}
```

- [ ] **Step 3: Run the new tests to confirm they pass**

```bash
cd backend && php artisan test --filter=BudgetLedgerTest
```

Expected: all pass.

- [ ] **Step 4: Run full suite**

```bash
cd backend && php artisan test
```

Expected: 650+ tests pass. No failures.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/BudgetLedgerTest.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "test: BudgetLedgerTest + update stale budget tests in FoodServiceOpsTest"
```

---

## Task 17: Frontend typecheck

- [ ] **Step 1: Verify no InsightsPanel import remains**

```bash
grep -r "InsightsPanel" frontend/
```

Expected: zero matches.

- [ ] **Step 2: Verify no stale budgetService imports remain**

```bash
grep -r "addDailyLog\|getBudgetSummary\|deleteBudget\|BudgetScope\|BudgetPayload" frontend/ --include="*.tsx" --include="*.ts"
```

Expected: zero matches (all callers updated).

- [ ] **Step 3: Run typecheck**

```bash
cd frontend && npx tsc --noEmit
```

Expected: clean. Fix any type errors before proceeding.

- [ ] **Step 4: Commit if fixes needed**

```bash
git add -A
git commit -m "fix: typecheck — resolve stale budget service imports"
```

---

## Task 18: Blast radius check + final verification

- [ ] **Step 1: Confirm no deleted class references in codebase**

```bash
cd backend && grep -r "BudgetActualService\|BudgetService\|ProcurementCostEfficiencyService\|BudgetAdjustment\|BudgetDailyLog" app/ routes/ tests/ database/
```

Expected: zero matches.

- [ ] **Step 2: Confirm PPA still generated at PO conversion**

```bash
cd backend && php artisan test --filter=test_rnd_approving_list_creates_per_vendor_purchase_orders
```

Expected: passes.

- [ ] **Step 3: Confirm PO Phase 3 triggers actual_budget_per_head_per_day + PPA freeze + ledger deduction**

Read `PurchaseOrderLifecycleService::refresh()` and confirm:
1. fiscal year guard returns early if no Budget exists for that year
2. PurchaseOrderCompleted event is still fired after completing
3. BudgetLedgerListener is wired in AppServiceProvider

- [ ] **Step 4: Confirm reports page is unaffected structurally**

```bash
cd backend && php artisan test --filter=ReportsBrowseTest
```

Expected: passes. If `budget_report` tests fail because they pass `budget_id` instead of `fiscal_year`, update those test fixtures to pass `fiscal_year`.

- [ ] **Step 5: Confirm FSS budget management routes absent**

```bash
cd backend && php artisan route:list --path=api/fss | grep -E "POST|PATCH|DELETE" | grep budget
```

Expected: only `POST /api/fss/budgets` (RND-only) and `POST /api/fss/budgets/adjust` (RND-only). No FSS budget write routes.

- [ ] **Step 6: Full test suite**

```bash
cd backend && php artisan test
```

Expected: 650+ pass, 0 failures.

- [ ] **Step 7: Final commit**

```bash
git add -A
git commit -m "feat: D+E budget ledger + insights complete — all tests pass"
```

---

## Self-Review: Spec Coverage Check

| Spec Requirement | Task |
|---|---|
| Kill New Budget records concept | Tasks 1–4 (redesign Budget to fiscal_year only) |
| Fiscal year: year, allocated_amount, per_head_day_limit | Task 4 (Budget model) |
| New year no carryover | Task 16 (test_new_year_has_no_carryover) |
| Current year missing allocation notifies RND | Task 5 (summary returns notice when null) |
| Budget ledger append-only: po_deduction/manual_addition/manual_deduction | Task 2+4 (budget_ledger table + BudgetLedger model) |
| Ledger fields: fiscal_year, type, amount, reason, reference, purchase_order_id, procurement_span, created_by | Task 2 (migration) |
| Remaining balance = allocated + additions - deductions - po_deductions | Task 4 (remainingBalance()), Task 16 (test_remaining_balance) |
| Manual adjustment RND only, require reason, immutable | Task 5 (BudgetController), Task 16 (tests) |
| PO auto-deduction on Phase 3 finalization using final PO total | Tasks 6+7 (listener + lifecycle guard) |
| No deduction on PO conversion/open execution | Task 16 (listener only fires on PurchaseOrderCompleted) |
| No fiscal year allocation blocks or safely holds PO finalization | Task 7 (fiscal year guard in lifecycle service) |
| FSS budget management UI/routes absent | Task 9 (routes) |
| Budget page: no tabs, no graphs | Task 13 (BudgetPage rewrite) |
| Budget page: fiscal year selector + summary | Task 13 |
| Budget page: manual adjustment controls RND only | Task 13 |
| Budget page: ledger table reverse chronological with filter | Task 13 |
| Budget page: new fiscal year setup RND only | Task 13 |
| Deductions danger red text, additions green text | Task 13 (SummaryRow + LedgerTable) |
| All graphs in Insights only | Tasks 14+15 (Insights page) |
| Insights: user selects fiscal year first | Task 14 (category null until year chosen) |
| Insights: 4 categories (budget burn, per-head, deduction timeline, spend by supplier) | Tasks 8+12+14 |
| Budget burn: Jan–Dec allocated flat + cumulative deductions | Tasks 8+14 (InsightsController budgetBurn) |
| Per-head: actual vs limit per procurement span, Phase 2 pending markers | Tasks 8+14 |
| Deduction timeline: full year, PO + manual markers | Tasks 8+14 |
| Spend by supplier: uses PurchaseOrderVendorGroup | Tasks 8+14 |
| Verify PO Phase 3 triggers all downstream | Task 18 (blast radius check) |
| Reports remain frozen snapshots | Tasks 10 (BudgetReportGenerator + DietaryCashBookGenerator update) |
| estimate_population never affects actual budget | Unchanged — served_population stays on MealPrepLog |

---

**Plan complete and saved to `docs/superpowers/plans/Food-Service-Flow/2026-06-27-budget-ledger-insights.md`.**

**Two execution options:**

**1. Subagent-Driven (recommended)** — Dispatch a fresh subagent per task, review between tasks

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
