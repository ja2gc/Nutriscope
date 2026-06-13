# Budget-from-Consumption + Split-Brain Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the budget dashboard's daily "actual" come from food *served* (Spec 2 consumption) instead of lumpy purchase-order dates, and collapse the split-brain `budget_daily_logs` table to one column set so seeded/manual logs are visible.

**Architecture:** Introduce one shared daily-series builder (`BudgetActualService::dailySeries`) that both `BudgetController::summary` and `BudgetReportGenerator` call — single source of truth for planned (daily cap) + actual. Actual uses a per-range switch: if any completed `MealPrepLog` falls in the range, actual = Σ `MealPrepLog.total_value` by `service_date` + manual `budget_daily_logs.spent` (POs excluded, surfaced as a separate `cash_flow` total); otherwise actual = received-PO totals + manual logs (legacy fallback, labelled "purchases"). Separately, drop the duplicate `date/planned/actual/variance` columns from `budget_daily_logs`, keeping only `log_date + spent + notes`.

**Tech Stack:** Laravel 11 (PHP), Eloquent, PHPUnit (sqlite for tests, MySQL in dev/prod), Carbon. Frontend: Next.js + TypeScript (recharts).

---

## Spec reference

`docs/superpowers/specs/2026-06-12-fs-procurement-accuracy-and-snapshots-design.md` §3.4 + the "Schema cleanup" bullet. Decision D resolved (range-level switch) — see §5-D.

## File structure

| File | Responsibility | Action |
|------|----------------|--------|
| `app/Services/BudgetActualService.php` | The shared daily-series builder: given a budget + range, return `{days:[{date,planned,actual}], source, cash_flow}`. Owns the cap calc, the consumption/purchases switch, and the cash-flow total. | **Create** |
| `app/Http/Controllers/FSS/BudgetController.php` | `summary()` delegates day-building to the service, then rolls up via `BudgetService::summarize` and adds `source`/`cash_flow` to the payload. `storeDailyLog()` stops writing the dropped columns. | Modify |
| `app/Services/Reports/Generators/BudgetReportGenerator.php` | Build its `$days` from `BudgetActualService` (not from `log->planned/actual`), so the PDF and dashboard never disagree. | Modify |
| `app/Models/BudgetDailyLog.php` | Trim `$fillable`/`$casts` to the surviving columns. | Modify |
| `database/migrations/2026_06_14_000002_consolidate_budget_daily_logs.php` | Back-fill `log_date`/`spent` from `date`/`actual` where null, then drop `date,planned,actual,variance`. | **Create** |
| `database/seeders/FoodServiceDemoSeeder.php` | Seed daily logs with `log_date` + `spent` (not `date`/`actual`). | Modify |
| `tests/Feature/FoodServiceOpsTest.php` | Feature tests for consumption mode, purchases fallback, and seeded/manual-log visibility. | Modify |
| `frontend/services/budgetService.ts` | Add `source` + `cash_flow` to `BudgetSummary`. | Modify |
| `frontend/app/(rnd)/food-service/budget/page.tsx` | Source badge + "Cash disbursed (POs)" chip. | Modify |

**Convention reminders:** work on `main`; commits authored by jared only, **NO `Co-Authored-By` trailer**. Run a single PHPUnit file with `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php` (not `artisan test --filter`). Full suite: `php artisan test`.

---

## Task 1: BudgetActualService — the shared daily-series builder

**Files:**
- Create: `backend/app/Services/BudgetActualService.php`
- Test: `backend/tests/Feature/FoodServiceOpsTest.php` (new methods)

- [ ] **Step 1: Write the failing test — consumption mode**

Add to `tests/Feature/FoodServiceOpsTest.php` (and add `use App\Models\MealPrepLog;` + `use App\Services\BudgetActualService;` + `use Carbon\Carbon;` to the imports at the top):

```php
public function test_budget_actual_uses_consumption_when_a_day_is_served(): void
{
    $budget = Budget::factory()->create([
        'fss_user_id' => $this->fss->id,
        'budget_per_head_day' => 100, 'population' => 10, // cap = 1000/day
    ]);
    $cycle = MenuCycle::factory()->create(['created_by' => $this->fss->id]);
    MealPrepLog::create([
        'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
        'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
    ]);

    $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-11'));

    $this->assertSame('consumption', $result['source']);
    $byDate = collect($result['days'])->keyBy('date');
    $this->assertEqualsWithDelta(1200, $byDate['2026-06-10']['actual'], 0.01);
    $this->assertEqualsWithDelta(0, $byDate['2026-06-09']['actual'], 0.01);
    $this->assertEqualsWithDelta(1000, $byDate['2026-06-10']['planned'], 0.01); // cap
}
```

> Note: if `MenuCycleFactory` uses a different owner column than `created_by`, drop that attribute and let the factory default it. Verify with `grep -n "definition" -A15 database/factories/MenuCycleFactory.php` before running.

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_budget_actual_uses_consumption_when_a_day_is_served`
Expected: FAIL — `Class "App\Services\BudgetActualService" not found`.

- [ ] **Step 3: Write the service**

Create `backend/app/Services/BudgetActualService.php`:

```php
<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\MealPrepLog;
use App\Models\PurchaseOrder;
use Carbon\Carbon;

/**
 * Single source of truth for a budget's daily planned-vs-actual series.
 *
 * planned = the daily cap (per-head/day × population, else allocated / range-days).
 * actual  = per a range-level switch (Spec 6 §5-D):
 *   - consumption mode (≥1 completed MealPrepLog in range): Σ completed
 *     MealPrepLog.total_value by service_date + manual budget_daily_logs.spent;
 *     received POs are NOT counted here — they are returned separately as
 *     cash_flow (the Dietary Cash Book disbursements).
 *   - purchases mode (no served days in range): received-PO totals by date +
 *     manual logs — the legacy estimate, labelled 'purchases'.
 *
 * Consumed by BudgetController::summary and BudgetReportGenerator so the live
 * dashboard and the printed report can never drift apart.
 */
class BudgetActualService
{
    /**
     * @return array{days: array<int,array{date:string,planned:float,actual:float}>, source: string, cash_flow: float}
     */
    public static function dailySeries(Budget $budget, Carbon $start, Carbon $end): array
    {
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();

        // Consumption: facility-wide food served per day (completed logs only).
        $consumptionByDay = MealPrepLog::where('status', 'completed')
            ->whereBetween('service_date', [$startStr, $endStr])
            ->selectRaw('service_date as d, SUM(total_value) as t')
            ->groupBy('service_date')->pluck('t', 'd');

        // Manual non-PO cash logs entered by hand.
        $logByDay = $budget->dailyLogs()
            ->whereBetween('log_date', [$startStr, $endStr])
            ->selectRaw('log_date as d, SUM(spent) as t')
            ->groupBy('log_date')->pluck('t', 'd');

        // Received POs: cash disbursed (Dietary Cash Book) — also the legacy
        // actual fallback when no day has been served yet.
        $poByDay = PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) BETWEEN ? AND ?', [$startStr, $endStr])
            ->selectRaw('COALESCE(received_date, order_date) as d, SUM(total_amount) as t')
            ->groupBy('d')->pluck('t', 'd');

        $source   = $consumptionByDay->isNotEmpty() ? 'consumption' : 'purchases';
        $cashFlow = (float) $poByDay->sum();

        $cap = ($budget->budget_per_head_day && $budget->population)
            ? (float) $budget->budget_per_head_day * (int) $budget->population
            : ((float) ($budget->allocated_amount ?? 0) / max(1, $start->diffInDays($end) + 1));

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $actual = $source === 'consumption'
                ? (float) ($consumptionByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0)
                : (float) ($poByDay[$ds] ?? 0) + (float) ($logByDay[$ds] ?? 0);

            $days[] = ['date' => $ds, 'planned' => $cap, 'actual' => $actual];
        }

        return ['days' => $days, 'source' => $source, 'cash_flow' => round($cashFlow, 2)];
    }
}
```

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_budget_actual_uses_consumption_when_a_day_is_served`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/BudgetActualService.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): BudgetActualService — daily series with consumption/purchases switch"
```

---

## Task 2: Purchases-mode fallback test (no day served)

**Files:**
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_budget_actual_falls_back_to_purchases_when_nothing_served(): void
{
    $budget = Budget::factory()->create([
        'fss_user_id' => $this->fss->id,
        'budget_per_head_day' => 100, 'population' => 10,
    ]);
    PurchaseOrder::factory()->create([
        'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 800,
    ]);

    $result = BudgetActualService::dailySeries($budget, Carbon::parse('2026-06-09'), Carbon::parse('2026-06-11'));

    $this->assertSame('purchases', $result['source']);
    $byDate = collect($result['days'])->keyBy('date');
    $this->assertEqualsWithDelta(800, $byDate['2026-06-10']['actual'], 0.01);
    $this->assertEqualsWithDelta(800, $result['cash_flow'], 0.01);
}
```

- [ ] **Step 2: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_budget_actual_falls_back_to_purchases_when_nothing_served`
Expected: PASS (the service already implements this; this test pins the fallback behavior).

> If `PurchaseOrderFactory` requires a `fss_user_id`/`supplier_id`, add `'fss_user_id' => $this->fss->id` (the factory should default the rest). Verify with `grep -n "definition" -A12 database/factories/PurchaseOrderFactory.php` if it fails.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "test(fs): pin budget purchases-mode fallback when no day served"
```

---

## Task 3: Wire BudgetController::summary to the service

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/BudgetController.php:40-83`
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing endpoint test**

```php
public function test_summary_endpoint_reports_consumption_source_and_cash_flow(): void
{
    $budget = Budget::factory()->create([
        'fss_user_id' => $this->fss->id,
        'budget_per_head_day' => 100, 'population' => 10,
        'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
    ]);
    $cycle = MenuCycle::factory()->create();
    MealPrepLog::create([
        'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
        'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
    ]);
    PurchaseOrder::factory()->create([
        'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 800,
    ]);

    $response = $this->actingAs($this->fss)
        ->getJson("/api/fss/budgets/{$budget->id}/summary?start=2026-06-09&end=2026-06-11&granularity=day");

    $response->assertOk()
        ->assertJsonPath('data.source', 'consumption')
        ->assertJsonPath('data.actual', 1200.0)   // consumption only, POs excluded from actual
        ->assertJsonPath('data.cash_flow', 800.0); // POs surfaced separately
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_summary_endpoint_reports_consumption_source_and_cash_flow`
Expected: FAIL — `actual` is currently PO+log (800), and `source`/`cash_flow` keys are missing.

- [ ] **Step 3: Rewrite `summary()` to delegate**

In `backend/app/Http/Controllers/FSS/BudgetController.php`, replace the body of `summary()` (lines ~48-82, everything after `$data = $request->validate([...]);`) with:

```php
        $start = Carbon::parse($data['start'] ?? $budget->period_start ?? now()->startOfMonth());
        $end   = Carbon::parse($data['end'] ?? $budget->period_end ?? now()->endOfMonth());
        $gran  = $data['granularity'] ?? 'day';

        $series = BudgetActualService::dailySeries($budget, $start, $end);

        $summary = BudgetService::summarize($series['days'], $gran);
        $summary['range']               = ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'granularity' => $gran];
        $summary['source']              = $series['source'];
        $summary['cash_flow']           = $series['cash_flow'];
        $summary['allocated']           = (float) ($budget->allocated_amount ?? 0);
        $summary['budget_per_head_day'] = $budget->budget_per_head_day ? (float) $budget->budget_per_head_day : null;
        $summary['population']          = $budget->population;

        return response()->json(['data' => $summary]);
```

Then add the import near the top: `use App\Services\BudgetActualService;`. Remove the now-unused `use App\Models\PurchaseOrder;` only if `storeDailyLog` no longer references it — it still does (the double-count warning), so **keep** the `PurchaseOrder` import. Update the docblock on `summary()` to: `// daily cap (planned) vs consumption-actual (food served) or purchases fallback; POs surfaced as cash_flow.`

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_summary_endpoint_reports_consumption_source_and_cash_flow`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/FSS/BudgetController.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): budget summary actual from consumption via BudgetActualService"
```

---

## Task 4: Consolidate budget_daily_logs (drop the duplicate columns)

**Files:**
- Create: `backend/database/migrations/2026_06_14_000002_consolidate_budget_daily_logs.php`
- Modify: `backend/app/Models/BudgetDailyLog.php`
- Modify: `backend/app/Http/Controllers/FSS/BudgetController.php` (`storeDailyLog`)

- [ ] **Step 1: Write the migration**

Create `backend/database/migrations/2026_06_14_000002_consolidate_budget_daily_logs.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapse the split-brain budget_daily_logs: it carried two parallel sets
     * (date+planned/actual/variance AND log_date+spent). Back-fill the survivors
     * from the legacy columns, then drop the legacy set. log_date+spent+notes win.
     */
    public function up(): void
    {
        // Back-fill survivors from legacy rows (e.g. previously-seeded data).
        DB::table('budget_daily_logs')->whereNull('log_date')->whereNotNull('date')
            ->update(['log_date' => DB::raw('date')]);
        DB::table('budget_daily_logs')->whereNull('spent')->whereNotNull('actual')
            ->update(['spent' => DB::raw('actual')]);

        Schema::table('budget_daily_logs', function (Blueprint $table) {
            $table->dropColumn(['date', 'planned', 'actual', 'variance']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_daily_logs', function (Blueprint $table) {
            $table->date('date')->nullable();
            $table->decimal('planned', 10, 2)->default(0);
            $table->decimal('actual', 10, 2)->default(0);
            $table->decimal('variance', 10, 2)->default(0);
        });
    }
};
```

> `dropColumn` works on both sqlite and MySQL — no driver guard needed (unlike ENUM `MODIFY`). The `DB::raw('date')` back-fill is plain SQL valid on both.

- [ ] **Step 2: Trim the model**

In `backend/app/Models/BudgetDailyLog.php`, replace `$fillable` and `$casts`:

```php
    protected $fillable = ['budget_id', 'log_date', 'spent', 'notes'];

    protected $casts = [
        'log_date' => 'date',
        'spent'    => 'decimal:2',
    ];
```

- [ ] **Step 3: Trim `storeDailyLog`**

In `backend/app/Http/Controllers/FSS/BudgetController.php`, change the `BudgetDailyLog::create([...])` call inside `storeDailyLog` to drop the dead columns:

```php
        $dailyLog = BudgetDailyLog::create([
            'budget_id' => $budget->id,
            'log_date'  => $data['log_date'],
            'spent'     => $data['spent'],
            'notes'     => $data['notes'] ?? null,
        ]);
```

- [ ] **Step 4: Migrate fresh and run the existing daily-log test**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_fss_can_log_daily_budget_expense`
Expected: PASS (RefreshDatabase re-runs migrations including the new one; the asserted `spent => 1500.00` row still inserts).

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_06_14_000002_consolidate_budget_daily_logs.php backend/app/Models/BudgetDailyLog.php backend/app/Http/Controllers/FSS/BudgetController.php
git commit -m "refactor(fs): consolidate split-brain budget_daily_logs to log_date+spent"
```

---

## Task 5: Point BudgetReportGenerator at the shared builder

**Files:**
- Modify: `backend/app/Services/Reports/Generators/BudgetReportGenerator.php:32-57`
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_budget_report_actual_matches_consumption(): void
{
    $budget = Budget::factory()->create([
        'fss_user_id' => $this->fss->id,
        'budget_per_head_day' => 100, 'population' => 10,
        'period_start' => '2026-06-09', 'period_end' => '2026-06-11',
    ]);
    $cycle = MenuCycle::factory()->create();
    MealPrepLog::create([
        'menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10',
        'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false,
    ]);

    $report = new \App\Models\Report(['type' => 'budget_report', 'parameters' => [
        'budget_id' => $budget->id, 'granularity' => 'day',
    ]]);
    $data = (new \App\Services\Reports\Generators\BudgetReportGenerator())->data($report);

    $this->assertEqualsWithDelta(1200, $data['summary']['actual'], 0.01);
}
```

> If `Report` is not mass-assignable for `type`/`parameters`, build it with `$report = new \App\Models\Report(); $report->parameters = [...];` instead. Check `grep -n "fillable\|guarded" app/Models/Report.php`.

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_budget_report_actual_matches_consumption`
Expected: FAIL — the generator reads `log->planned/actual` (now-dropped columns → would also error) and ignores consumption.

- [ ] **Step 3: Rewrite `data()`**

In `backend/app/Services/Reports/Generators/BudgetReportGenerator.php`, replace the `$days = ...` block (and the `$granularity` line above it) with delegation to the shared builder. Final `data()` body:

```php
    public function data(Report $report): array
    {
        $params = $report->parameters ?? [];
        $budget = Budget::findOrFail($params['budget_id']);

        $granularity = $params['granularity'] ?? 'month';
        $start = Carbon::parse($params['start'] ?? $budget->period_start ?? now()->startOfMonth());
        $end   = Carbon::parse($params['end'] ?? $budget->period_end ?? now()->endOfMonth());

        $series  = \App\Services\BudgetActualService::dailySeries($budget, $start, $end);
        $summary = BudgetService::summarize($series['days'], $granularity);
        $allocated = (float) $budget->allocated_amount;

        return [
            'budget'    => $budget,
            'summary'   => $summary,
            'source'    => $series['source'],
            'cash_flow' => $series['cash_flow'],
            'allocated' => $allocated,
            'remaining' => round($allocated - $summary['actual'], 2),
            'period_label' => $budget->period_start
                ? Carbon::parse($budget->period_start)->format('M j, Y') . ' – ' .
                  optional($budget->period_end ? Carbon::parse($budget->period_end) : null)?->format('M j, Y')
                : ($budget->name ?? 'Budget'),
        ];
    }
```

Remove the now-unused `with('dailyLogs')` eager-load (replaced by `findOrFail`). Keep the existing `use` statements; `Carbon` is already imported.

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_budget_report_actual_matches_consumption`
Expected: PASS.

- [ ] **Step 5: Check the report Blade view doesn't reference dropped fields**

Run: `grep -n "planned\|actual\|->date\|dailyLogs" resources/views/reports/budget.blade.php`
Expected: it reads `$summary[...]` / `$summary['trend']` (fine). If it iterates `$budget->dailyLogs` and prints `->planned`/`->actual`, change those to read from `$summary['trend']` buckets instead. If it only uses `$summary`, no change.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/Reports/Generators/BudgetReportGenerator.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "refactor(fs): budget report uses BudgetActualService (matches dashboard)"
```

---

## Task 6: Fix the seeder so daily logs are visible

**Files:**
- Modify: `backend/database/seeders/FoodServiceDemoSeeder.php:278-286`

- [ ] **Step 1: Rewrite the daily-log loop**

Replace the seeding loop (the `for` over days with `BudgetDailyLog::create`) with:

```php
        // Log every day from the 1st up to today. Manual cash logs use spent/log_date;
        // the dashboard's "actual" now comes from consumption (served days), so these
        // sit on top as hand-entered non-PO spends.
        for ($d = $start->copy(); $d->lte(Carbon::now()); $d->addDay()) {
            $spent = round($avgDay * (mt_rand(88, 109) / 100), 2);
            BudgetDailyLog::create([
                'budget_id' => $budget->id,
                'log_date'  => $d->toDateString(),
                'spent'     => $spent,
            ]);
        }
```

> `$planned`/`$avgDay` are still computed above; `$avgDay` is reused here, `$planned` is no longer needed — remove the now-dead `$planned = round($avgDay, 2);` line if it sits inside this loop.

- [ ] **Step 2: Re-seed and verify visibility via tinker**

Run:
```bash
php artisan migrate:fresh --seed --seeder=FoodServiceDemoSeeder
php artisan tinker --execute="echo \App\Models\BudgetDailyLog::whereNotNull('log_date')->count() . ' logs with log_date; ' . \App\Models\BudgetDailyLog::whereNotNull('spent')->count() . ' with spent';"
```
Expected: both counts equal (every seeded row has `log_date` and `spent`) and > 0.

> If the project DB seed normally runs `php artisan db:seed` with a parent seeder, use that instead; `migrate:fresh --seed --seeder=` is the targeted form. Confirm the demo seeder name first.

- [ ] **Step 3: Commit**

```bash
git add backend/database/seeders/FoodServiceDemoSeeder.php
git commit -m "fix(fs): seed budget daily logs with log_date+spent so they render"
```

---

## Task 7: Frontend — source badge + cash-flow chip

**Files:**
- Modify: `frontend/services/budgetService.ts:32-43`
- Modify: `frontend/app/(rnd)/food-service/budget/page.tsx`

- [ ] **Step 1: Extend the type**

In `frontend/services/budgetService.ts`, add two fields to `BudgetSummary`:

```ts
export interface BudgetSummary {
  planned: number;
  actual: number;
  variance: number;
  variance_pct: number;
  trend: TrendPoint[];
  range: { start: string; end: string; granularity: string };
  source: "consumption" | "purchases";
  cash_flow: number;
  allocated: number;
  budget_per_head_day: number | null;
  population: number | null;
}
```

- [ ] **Step 2: Show the badge + chip on the dashboard**

In `frontend/app/(rnd)/food-service/budget/page.tsx`, inside the `{summary && ( <> ... </> )}` block, just below the KPI cards grid (after the `</div>` that closes the `grid grid-cols-2 sm:grid-cols-4 gap-3` of KpiCards, near the existing over/under-budget pill), add:

```tsx
                <div className="flex flex-wrap items-center gap-2 text-[11px]">
                  <span className={`font-bold px-2.5 py-1 rounded-lg border ${summary.source === "consumption" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-amber-50 text-amber-700 border-amber-200"}`}>
                    {summary.source === "consumption" ? "Actual from meals served" : "Estimated from purchases"}
                  </span>
                  <span className="font-semibold px-2.5 py-1 rounded-lg border bg-zinc-50 text-zinc-600 border-zinc-200">
                    Cash disbursed (POs): {peso(summary.cash_flow)}
                  </span>
                </div>
```

- [ ] **Step 3: Update the page subtitle to stop claiming "from received purchase orders"**

In the same file, change the header `<p>` (line ~136) text from:
`Set yearly / per-head budgets and track real spend (from received purchase orders) against them over any range.`
to:
`Set yearly / per-head budgets and track real spend — from meals actually served (or estimated from purchases until a day is served) — against them over any range.`

- [ ] **Step 4: Typecheck**

Run: `cd ../frontend && npx tsc --noEmit`
Expected: no new errors in `budgetService.ts` or `budget/page.tsx`.

> The repo's Next.js is non-standard (see `frontend/AGENTS.md`) but these are plain TS/JSX edits to existing patterns — no new framework APIs.

- [ ] **Step 5: Commit**

```bash
git add frontend/services/budgetService.ts "frontend/app/(rnd)/food-service/budget/page.tsx"
git commit -m "feat(fs): budget dashboard shows actual-source badge + cash-flow chip"
```

---

## Task 8: Full-suite regression + browser verification

- [ ] **Step 1: Run the full backend suite**

Run: `php artisan test`
Expected: same baseline as before — all pass except the 2 known pre-existing NCP `'piece'` failures in `RecipeControllerTest` (not in scope). No new failures.

- [ ] **Step 2: Browser-verify the dashboard (optional but recommended)**

Start backend (`php artisan serve`) + seed (`php artisan migrate:fresh --seed --seeder=FoodServiceDemoSeeder`) + frontend dev server (preview_start "frontend" on :3000). Log in as `rnd@nutriscope.local / nutriscope2024!`, open Food Service → Budget. Confirm:
- A "Estimated from purchases" or "Actual from meals served" badge renders.
- "Cash disbursed (POs)" chip shows a value.
- Mark a day served on the menu-cycle page, return to Budget for that range → badge flips to "Actual from meals served" and the actual line reflects served value.
Capture a `preview_screenshot` as proof.

- [ ] **Step 3: Final commit if any verification tweaks were needed** (otherwise skip)

---

## Self-review notes (author)

- **Spec coverage:** §3.4 bullet 1 (actual from consumption) → Tasks 1,3,5. §3.4 "POs cash-flow only" → `cash_flow` in Task 1/3/7. §3.4 fallback+label (decision D) → Task 1 switch + Task 7 badge. §3.4 schema cleanup → Tasks 4,6. Report parity (review finding beyond literal spec) → Task 5. ✓
- **No placeholders:** every code step shows full code. ✓
- **Type consistency:** `dailySeries()` returns `{days, source, cash_flow}` — same keys consumed in Tasks 3 & 5; summary payload `source`/`cash_flow` matches the TS `BudgetSummary` in Task 7. ✓
- **Risk:** if a Blade view or another consumer still reads `budget_daily_logs.planned/actual/date`, the Task 4 drop breaks it — Task 5 step 5 greps the budget Blade; the earlier code-wide grep found only the controller, model, seeder, and report generator as consumers, all covered here.
