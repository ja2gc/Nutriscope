# Food-Service Insights / Analytics (Spec 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A read-only Food-Service **Insights** page with interactive Recharts charts — spend-by-supplier, cost-per-head per menu cycle, and consumption (value served/day + shortfall) — fed by thin aggregation endpoints over existing frozen data. Compliance PDFs stay graph-free (hard rule).

**Architecture:** A new `InsightsController` (FSS) exposes three chart-ready aggregation endpoints, each a pure read over existing tables (received POs, menu-cycle cost via `MenuCycleCostService`, completed `meal_prep_logs`). No new writable state. A new `food-service/insights/page.tsx` renders a date-range control + a grid of Recharts cards reusing the budget page's styling, with loading/empty/error states. Spend uses the same received-PO rule as the budget's `cash_flow` so numbers reconcile across screens.

**Tech Stack:** Laravel 11, Eloquent, PHPUnit (sqlite tests / MySQL dev), Carbon. Frontend: Next.js + TypeScript + Recharts (already in repo).

---

## Spec reference

`docs/superpowers/specs/2026-06-12-fs-insights-analytics-design.md`. Open decisions resolved (§8): one combined page; MVP = 3 new charts (spend-by-supplier, cost-per-head, consumption). Inventory-value-over-time **deferred** (no point-in-time history). Price-trend card **deferred** (no `fs-items` list endpoint exists for an item selector; the per-item endpoint stays available for a later card). Depends on Spec 1 + Spec 2, both done.

## Conventions

Work on `main`; commits authored by jared only (git config = `jared <jaredabriol2@gmail.com>`), **NO `Co-Authored-By`**, do not pass `--author`. One test file: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php`. Full: `php artisan test` (baseline: 2 flaky pre-existing NCP `'piece'` failures in `RecipeControllerTest` — unrelated, ignore). NOTE: local MySQL (:3306) may be down — tests use sqlite and don't need it; live browser verification does.

## Endpoint contract (shared shape)

Every endpoint returns `{"data": {"points": [...], "summary": {...}}}` (matches the frontend `unwrap` envelope). Date params `start`/`end` are optional `date`; default to the current month. Empty range → `points: []` + zeroed summary (never an error).

## File structure

| File | Responsibility | Action |
|------|----------------|--------|
| `app/Http/Controllers/FSS/InsightsController.php` | 3 aggregation endpoints | **Create** |
| `routes/api.php` | 3 routes under the `fss` group | Modify |
| `frontend/services/insightsService.ts` | typed fetchers + series types | **Create** |
| `frontend/app/api/fss/insights/spend-by-supplier/route.ts` | proxy | **Create** |
| `frontend/app/api/fss/insights/cost-per-head/route.ts` | proxy | **Create** |
| `frontend/app/api/fss/insights/consumption/route.ts` | proxy | **Create** |
| `frontend/app/(rnd)/food-service/insights/page.tsx` | Insights page (range + 3 chart cards) | **Create** |
| `frontend/components/layout/Sidebar.tsx` | add "Insights" link under Food Service | Modify |
| `tests/Feature/InsightsControllerTest.php` | endpoint tests | **Create** |

---

## Task 1: spend-by-supplier endpoint

**Files:**
- Create: `backend/app/Http/Controllers/FSS/InsightsController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/InsightsControllerTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/InsightsControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Models\MenuCycleDay;
use App\Models\FsItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InsightsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_spend_by_supplier_groups_received_pos(): void
    {
        $a = Supplier::factory()->create(['name' => 'Veg Co']);
        $b = Supplier::factory()->create(['name' => 'Meat Co']);
        PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-10', 'total_amount' => 300]);
        PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'supplier_id' => $a->id, 'status' => 'received', 'received_date' => '2026-06-11', 'total_amount' => 200]);
        PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'supplier_id' => $b->id, 'status' => 'received', 'received_date' => '2026-06-11', 'total_amount' => 500]);
        // draft must NOT count
        PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'supplier_id' => $b->id, 'status' => 'draft', 'order_date' => '2026-06-11', 'total_amount' => 999]);

        $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/spend-by-supplier?start=2026-06-01&end=2026-06-30');
        $res->assertOk();

        $points = collect($res->json('data.points'))->keyBy('supplier');
        $this->assertEqualsWithDelta(500, (float) $points['Veg Co']['total'], 0.01);
        $this->assertEqualsWithDelta(500, (float) $points['Meat Co']['total'], 0.01);
        $this->assertEqualsWithDelta(1000, (float) $res->json('data.summary.total'), 0.01);
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_spend_by_supplier_groups_received_pos`
Expected: FAIL — 404 / no route.

- [ ] **Step 3: Create the controller with `spendBySupplier`**

Create `backend/app/Http/Controllers/FSS/InsightsController.php`:

```php
<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
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
}
```

- [ ] **Step 4: Add the route**

In `backend/routes/api.php`, inside the `fss` group (near the other reads), add and import the controller:

```php
    // Insights / analytics (read-only, graphs — never in compliance PDFs)
    Route::get('insights/spend-by-supplier', [InsightsController::class, 'spendBySupplier']);
    Route::get('insights/cost-per-head', [InsightsController::class, 'costPerHead']);
    Route::get('insights/consumption', [InsightsController::class, 'consumption']);
```

Add the import at the top with the other FSS controller imports:

```php
use App\Http\Controllers\FSS\InsightsController;
```

(Verify how the file imports FSS controllers — some route files use fully-qualified names inline. Match the existing style: `grep -n "use App\\\\Http\\\\Controllers\\\\FSS" routes/api.php`.)

- [ ] **Step 5: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_spend_by_supplier_groups_received_pos`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/FSS/InsightsController.php backend/routes/api.php backend/tests/Feature/InsightsControllerTest.php
git commit -m "feat(fs): insights spend-by-supplier endpoint"
```

---

## Task 2: cost-per-head endpoint

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/InsightsController.php`
- Test: `backend/tests/Feature/InsightsControllerTest.php`

- [ ] **Step 1: Write the failing test**

Add to `InsightsControllerTest`:

```php
public function test_cost_per_head_reports_average_daily_per_cycle(): void
{
    $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50]); // unit_cost = 0.05/g
    $cycle = MenuCycle::factory()->create(['name' => 'Cycle A', 'population' => 10]);
    // One direct-item day: 1000 g × ₱0.05 = ₱50 total → ₱5/head for that day.
    MenuCycleDay::create([
        'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
        'meal_type' => 'lunch', 'fs_item_id' => $fs->id, 'quantity' => 1000,
    ]);

    $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/cost-per-head');
    $res->assertOk();

    $point = collect($res->json('data.points'))->firstWhere('cycle_id', $cycle->id);
    $this->assertNotNull($point);
    $this->assertEqualsWithDelta(5, (float) $point['cost_per_head'], 0.01); // avg daily ₱/head
}
```

> Direct-item day usage is NOT scaled by population (per `MenuCycleCostService::aggregate`); 1000 g total. Day cost ₱50, `cost_per_head` for Monday = 50/10 = ₱5. With one planned day, the avg-daily-per-head = 5.

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_cost_per_head_reports_average_daily_per_cycle`
Expected: FAIL — no `costPerHead` method (500/route error).

- [ ] **Step 3: Add `costPerHead`**

Add to `InsightsController` (and `use App\Models\MenuCycle; use App\Services\MenuCycleCostService;` at the top):

```php
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
```

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_cost_per_head_reports_average_daily_per_cycle`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/FSS/InsightsController.php backend/tests/Feature/InsightsControllerTest.php
git commit -m "feat(fs): insights cost-per-head endpoint"
```

---

## Task 3: consumption endpoint

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/InsightsController.php`
- Test: `backend/tests/Feature/InsightsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_consumption_rolls_up_completed_logs_by_day(): void
{
    $cycle = MenuCycle::factory()->create();
    MealPrepLog::create(['menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-10', 'status' => 'completed', 'total_value' => 1200, 'has_shortfall' => false]);
    MealPrepLog::create(['menu_cycle_id' => $cycle->id, 'service_date' => '2026-06-11', 'status' => 'completed', 'total_value' => 900,  'has_shortfall' => true]);
    // reversed must NOT count
    $cycle2 = MenuCycle::factory()->create();
    MealPrepLog::create(['menu_cycle_id' => $cycle2->id, 'service_date' => '2026-06-12', 'status' => 'reversed', 'total_value' => 5000, 'has_shortfall' => false]);

    $res = $this->actingAs($this->fss)->getJson('/api/fss/insights/consumption?start=2026-06-01&end=2026-06-30');
    $res->assertOk();

    $points = collect($res->json('data.points'))->keyBy('date');
    $this->assertEqualsWithDelta(1200, (float) $points['2026-06-10']['actual'], 0.01);
    $this->assertTrue((bool) $points['2026-06-11']['shortfall']);
    $this->assertArrayNotHasKey('2026-06-12', $points->all()); // reversed excluded
    $this->assertEqualsWithDelta(2100, (float) $res->json('data.summary.total'), 0.01);
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_consumption_rolls_up_completed_logs_by_day`
Expected: FAIL — no `consumption` method.

- [ ] **Step 3: Add `consumption`**

Add to `InsightsController` (and `use App\Models\MealPrepLog;` at the top):

```php
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
                'total'           => round((float) $rows->sum('actual'), 2),
                'days'            => $rows->count(),
                'shortfall_days'  => $rows->where('shortfall', '>', 0)->count(),
                'range'           => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            ],
        ]]);
    }
```

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php --filter test_consumption_rolls_up_completed_logs_by_day`
Expected: PASS.

- [ ] **Step 5: Run the whole insights test file**

Run: `php vendor/bin/phpunit tests/Feature/InsightsControllerTest.php`
Expected: 3 tests, all pass.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/FSS/InsightsController.php backend/tests/Feature/InsightsControllerTest.php
git commit -m "feat(fs): insights consumption endpoint"
```

---

## Task 4: Frontend service + API proxies

**Files:**
- Create: `frontend/services/insightsService.ts`
- Create: `frontend/app/api/fss/insights/spend-by-supplier/route.ts`
- Create: `frontend/app/api/fss/insights/cost-per-head/route.ts`
- Create: `frontend/app/api/fss/insights/consumption/route.ts`

- [ ] **Step 1: Create the proxies**

Each mirrors the existing proxy pattern (`frontend/app/api/fss/budgets/[id]/summary/route.ts`). Create the three files:

`spend-by-supplier/route.ts`:
```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/insights/spend-by-supplier", { search: new URL(req.url).searchParams });
}
```

`cost-per-head/route.ts`:
```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/insights/cost-per-head", { search: new URL(req.url).searchParams });
}
```

`consumption/route.ts`:
```ts
import { NextRequest } from "next/server";
import { proxy } from "@/lib/laravelProxy";

export async function GET(req: NextRequest) {
  return proxy("/fss/insights/consumption", { search: new URL(req.url).searchParams });
}
```

> Confirm the proxy import path/signature matches the budgets proxy exactly (`cat "frontend/app/api/fss/budgets/[id]/summary/route.ts"`).

- [ ] **Step 2: Create the service**

Create `frontend/services/insightsService.ts`:

```ts
import { apiFetch } from "@/lib/apiFetch";

export interface SupplierSpendPoint { supplier_id: number | null; supplier: string; total: number }
export interface SpendBySupplier { points: SupplierSpendPoint[]; summary: { total: number; range: { start: string; end: string } } }

export interface CostPerHeadPoint { cycle_id: number; cycle: string; cost_per_head: number; population: number }
export interface CostPerHead { points: CostPerHeadPoint[]; summary: { avg: number } }

export interface ConsumptionPoint { date: string; actual: number; shortfall: boolean }
export interface Consumption { points: ConsumptionPoint[]; summary: { total: number; days: number; shortfall_days: number; range: { start: string; end: string } } }

async function unwrap<T>(res: Response, fallback: string): Promise<T> {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error((data as { message?: string }).message ?? fallback);
  return (data as { data: T }).data;
}

const qs = (o: { start?: string; end?: string }) => {
  const p = new URLSearchParams();
  if (o.start) p.set("start", o.start);
  if (o.end) p.set("end", o.end);
  return p.toString();
};

export async function getSpendBySupplier(o: { start?: string; end?: string }): Promise<SpendBySupplier> {
  return unwrap(await apiFetch(`/api/fss/insights/spend-by-supplier?${qs(o)}`), "Failed to load spend by supplier.");
}
export async function getCostPerHead(): Promise<CostPerHead> {
  return unwrap(await apiFetch(`/api/fss/insights/cost-per-head`), "Failed to load cost per head.");
}
export async function getConsumption(o: { start?: string; end?: string }): Promise<Consumption> {
  return unwrap(await apiFetch(`/api/fss/insights/consumption?${qs(o)}`), "Failed to load consumption.");
}
```

> Verify `apiFetch` import path matches the budget service (`grep -n "apiFetch" frontend/services/budgetService.ts`).

- [ ] **Step 3: Typecheck**

Run: `cd ../frontend && npx tsc --noEmit 2>&1 | grep -iE "insights"`
Expected: no errors (page not built yet, so only service/proxy types are checked).

- [ ] **Step 4: Commit**

```bash
git add frontend/services/insightsService.ts frontend/app/api/fss/insights
git commit -m "feat(fs): insights frontend service + api proxies"
```

---

## Task 5: Insights page + sidebar link

**Files:**
- Create: `frontend/app/(rnd)/food-service/insights/page.tsx`
- Modify: `frontend/components/layout/Sidebar.tsx`

- [ ] **Step 1: Create the page**

Create `frontend/app/(rnd)/food-service/insights/page.tsx`. Mirror the budget page's structure (Crumbs, header, date range, Recharts cards, peso/num helpers). Three cards: spend-by-supplier (BarChart), cost-per-head (BarChart by cycle), consumption (LineChart + shortfall dots). Each card handles loading / empty / error.

```tsx
"use client";

import React, { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { BarChart3, RefreshCw, AlertTriangle } from "lucide-react";
import {
  ResponsiveContainer, BarChart, Bar, LineChart, Line, XAxis, YAxis,
  CartesianGrid, Tooltip, Cell,
} from "recharts";
import {
  SpendBySupplier, CostPerHead, Consumption,
  getSpendBySupplier, getCostPerHead, getConsumption,
} from "@/services/insightsService";

const peso = (n: number) => `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const todayISO = () => new Date().toISOString().slice(0, 10);
const monthStartISO = () => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10); };

function Card({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
      <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider mb-4">{title}</h3>
      {children}
    </div>
  );
}

function Empty({ msg }: { msg: string }) {
  return <div className="h-[220px] flex items-center justify-center text-xs text-zinc-400">{msg}</div>;
}

export default function InsightsPage() {
  const [start, setStart] = useState(monthStartISO());
  const [end, setEnd] = useState(todayISO());
  const [spend, setSpend] = useState<SpendBySupplier | null>(null);
  const [cph, setCph] = useState<CostPerHead | null>(null);
  const [cons, setCons] = useState<Consumption | null>(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true); setErr(null);
    try {
      const [s, c, k] = await Promise.all([
        getSpendBySupplier({ start, end }),
        getCostPerHead(),
        getConsumption({ start, end }),
      ]);
      setSpend(s); setCph(c); setCons(k);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Failed to load insights.");
    } finally { setLoading(false); }
  }, [start, end]);
  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/dashboard" className="hover:text-emerald-700">Home</Link><span>/</span>
        <span>Food Service</span><span>/</span><span className="font-bold text-zinc-600">Insights</span>
      </div>

      <div className="border-b border-zinc-200 pb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5"><BarChart3 className="h-5 w-5 text-emerald-600" /> Insights</h2>
          <p className="text-xs text-zinc-500 mt-1">Interactive analytics over real spend, menu cost, and consumption. Separate from the compliance PDFs.</p>
        </div>
        <button onClick={load} className="flex items-center gap-1.5 text-xs text-zinc-500 hover:text-zinc-700 shrink-0"><RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} /> Refresh</button>
      </div>

      <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">From</label><input type="date" value={start} onChange={(e) => setStart(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
        <div><label className="block text-[10px] font-extrabold text-zinc-500 uppercase mb-1">To</label><input type="date" value={end} onChange={(e) => setEnd(e.target.value)} className="w-full px-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500" /></div>
      </div>

      {err && <div className="flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-xl border bg-red-50 text-red-700 border-red-200 w-fit"><AlertTriangle className="h-3.5 w-3.5" /> {err}</div>}

      <Card title="Spend by Supplier (received POs)">
        {!spend || spend.points.length === 0 ? <Empty msg="No received purchase orders in this range." /> : (
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={spend.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="supplier" tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              <Bar dataKey="total" name="Spend" radius={[3, 3, 0, 0]} fill="#059669" />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card title="Cost per Head by Menu Cycle (avg daily)">
        {!cph || cph.points.length === 0 ? <Empty msg="No menu cycles to cost yet." /> : (
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={cph.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
              <XAxis dataKey="cycle" tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
              <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
              <Bar dataKey="cost_per_head" name="₱/head/day" radius={[3, 3, 0, 0]} fill="#0ea5e9" />
            </BarChart>
          </ResponsiveContainer>
        )}
      </Card>

      <Card title="Consumption — Value Served per Day">
        {!cons || cons.points.length === 0 ? <Empty msg="No service days completed in this range." /> : (
          <>
            <ResponsiveContainer width="100%" height={240}>
              <LineChart data={cons.points} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f1f5f9" />
                <XAxis dataKey="date" tick={{ fontSize: 10, fill: "#94a3b8" }} />
                <YAxis tick={{ fontSize: 10, fill: "#94a3b8" }} />
                <Tooltip formatter={(v) => peso(Number(v))} contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Line type="monotone" dataKey="actual" name="Served value" stroke="#059669" strokeWidth={2}
                  dot={(props) => { const p = props.payload as { shortfall?: boolean }; const key = `${props.cx}-${props.cy}`; return <circle key={key} cx={props.cx} cy={props.cy} r={p?.shortfall ? 4 : 2} fill={p?.shortfall ? "#dc2626" : "#059669"} />; }} />
              </LineChart>
            </ResponsiveContainer>
            <p className="text-[10px] text-zinc-400 mt-2">Red dots = a shortfall was recorded that day. {cons.summary.shortfall_days} of {cons.summary.days} day(s) had shortfalls.</p>
          </>
        )}
      </Card>
    </div>
  );
}
```

> The repo's Next.js is non-standard (`frontend/AGENTS.md`), but this only uses existing patterns (client component, Recharts, apiFetch) already proven on the budget page. If the recharts `dot` render-prop type complains, fall back to `dot={{ r: 2 }}` and drop the per-point shortfall styling (keep the summary line).

- [ ] **Step 2: Add the sidebar link**

In `frontend/components/layout/Sidebar.tsx`, find the Food-Service sub-links block (around the `/food-service/budget` link, ~line 352) and add an Insights link following the exact same markup pattern:

```tsx
                <Link
                  href="/food-service/insights"
                  className={/* same classes as the budget link, comparing pathname === "/food-service/insights" */}
                >
                  <span className={`h-1.5 w-1.5 rounded-full ${pathname === "/food-service/insights" ? "bg-emerald-500" : "bg-zinc-700"}`} />
                  Insights
                </Link>
```

Copy the budget link's exact wrapper classes (read the surrounding lines first) so styling matches; only swap the href, label, and the `pathname ===` comparison string.

- [ ] **Step 3: Typecheck**

Run: `cd ../frontend && npx tsc --noEmit 2>&1 | grep -iE "insights|Sidebar"`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add "frontend/app/(rnd)/food-service/insights/page.tsx" frontend/components/layout/Sidebar.tsx
git commit -m "feat(fs): insights page (spend, cost-per-head, consumption) + sidebar link"
```

---

## Task 6: Full-suite regression + browser verification

- [ ] **Step 1: Full backend suite**

Run: `php artisan test`
Expected: all green except the known flaky NCP `'piece'` failures in `RecipeControllerTest`. The 3 new insights tests pass.

- [ ] **Step 2: Browser-verify (needs MySQL up)**

If local MySQL is running: `php artisan serve` :8000 + frontend preview :3000 + seed (`php artisan db:seed --class=FoodServiceDemoSeeder --force`). Log in `rnd@nutriscope.local / nutriscope2024!`, open Food Service → Insights. Confirm: spend-by-supplier bars render, cost-per-head bars render, consumption line renders (seed a completed service day if none — via the menu-cycle ServiceLogPanel — to populate it). Empty ranges show the empty states, not broken charts. Capture a screenshot.

> If MySQL is down, note browser verification deferred — the sqlite suite covers the aggregation logic and tsc covers the page.

---

## Self-review notes (author)

- **Spec coverage:** §2 goal 3 (spend-by-supplier) → Task 1; goal 4 (cost-per-head) → Task 2; goal 5 (plan-vs-actual/shortfall consumption) → Task 3 (actual + shortfall; "planned" overlay deferred — keeps scope tight, honest actual). Goals 1–2 (budget/price trend) already shipped on the budget page / Spec 1 endpoint; not duplicated (decision §8). Goal 6 (inventory value over time) deferred — no history store. §3.2 page → Tasks 4–5. §7.1 reconciliation → spend uses received-PO total (same as budget cash_flow). §5 error handling → empty states + divide-by-zero guards (`population > 0`, `isNotEmpty`). §6 testing → one test per aggregator. ✓
- **No placeholders:** full code in every step; the Sidebar step intentionally says "copy exact classes" because they must match the neighbour — that's a real instruction, not a vague TODO.
- **Type consistency:** endpoint envelope `{data:{points,summary}}` matches `insightsService` `unwrap`; series field names (`supplier`/`total`, `cycle`/`cost_per_head`, `date`/`actual`/`shortfall`) match the page's `dataKey`s. ✓
- **Risk:** cost-per-head loads all cycles with nested eager loads — fine for the demo scale; if cycle count grows, add a limit/date filter. Consumption uses `DATE()` (sqlite/MySQL safe), same fix as the budget series.
