# Food-Service Consumption (Spec 2, backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the food-service loop's stock-OUT half — completing a service day deducts that day's planned ingredients from inventory at last-cost, snapshotted for exact reversal, with a block-on-shortfall guardrail. Also rework the "smart" shopping list to sum the actual selected days instead of a proportional average.

**Architecture:** A **derived** service calendar (no table): `completeDay(MenuCycle, serviceDate)` resolves the date's weekday to that cycle's meal slots live, reuses the menu-cost engine to build required base-unit usage, pre-flight cover-checks against `inventory.quantity_in_stock`, then deducts and writes an immutable `meal_prep_logs` + `meal_prep_log_lines` snapshot. `meal_prep_logs` (currently orphaned) is reshaped to key on `(menu_cycle_id, service_date)`. The same per-day usage builder powers the date-range procurement rework. Spec source: `docs/superpowers/specs/2026-06-12-fs-consumption-design.md`.

**Tech Stack:** Laravel 13 (PHP 8.3), MySQL, PHPUnit (pure unit tests only — no sqlite driver in this env; DB logic verified via `php artisan tinker`). Run tests with `php vendor/bin/phpunit <file>` (NOT `php artisan test`, whose JSON wrapper mis-matches `--filter`).

**Decisions locked (Spec 2 §9 + this session):** service calendar = **derived on demand**; shortfall = **block + specific alert**; completion granularity = **whole service day**; consumption value = **stored last-cost** (`inventory.unit_price`, fallback catalog `unit_cost`); reversal restores **snapshot** quantities (not a recompute). Procurement day-range fix **bundled here**.

**Scope note:** This plan is the **backend engine + API + procurement-backend rework**. Frontend wiring (menu-cycle "Mark served" UI, usage-log view, procurement date-range picker) is **Phase 2 — a separate plan**.

---

## Testing Strategy

Same as Spec 1: pure unit tests for the math (the shared usage builder); `php artisan tinker` scripts with expected output for all DB flows (deduction, reversal, idempotency, cover-check). No `RefreshDatabase` (sqlite driver absent). All backend commands run from `backend/`. Absolute paths for `Write`. Commit after every task.

---

## File Structure

**Backend — create:**
- `database/migrations/2026_06_14_000001_reshape_meal_prep_logs_for_consumption.php`
- `app/Models/MealPrepLogLine.php`
- `app/Services/FSS/ConsumptionService.php`
- `app/Http/Controllers/FSS/MealPrepLogController.php`
- `tests/Unit/MenuCycleUsageForDaysTest.php`

**Backend — modify:**
- `app/Services/MenuCycleCostService.php` — extract `entriesForDays()`; add `usageForDays()`.
- `app/Models/MealPrepLog.php` — reshape fillable/casts/relations.
- `app/Http/Controllers/FSS/ShoppingListController.php` — date-range `generate()`.
- `routes/api.php` — consumption routes.

---

## Task 1: Extract a shared per-day usage builder in `MenuCycleCostService`

**Files:**
- Modify: `app/Services/MenuCycleCostService.php`
- Test: `tests/Unit/MenuCycleUsageForDaysTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\MenuCycleCostService;
use Tests\TestCase;

class MenuCycleUsageForDaysTest extends TestCase
{
    public function test_usage_for_entries_returns_base_unit_quantities(): void
    {
        // One Monday lunch: rice 5000 g / 50 servings, scaled to 100 heads → 10000 g.
        $entries = [[
            'day_of_week' => 'Monday', 'meal_type' => 'lunch', 'servings_override' => null,
            'recipe' => [
                'servings' => 50,
                'ingredients' => [
                    ['fs_item_id' => 1, 'name' => 'Rice', 'quantity' => 5000, 'unit' => 'g', 'base_unit' => 'g', 'unit_cost' => 0.052],
                ],
            ],
        ]];

        $usage = MenuCycleCostService::aggregate($entries, 100)['ingredient_usage'];

        $this->assertCount(1, $usage);
        $this->assertSame(1, $usage[0]['fs_item_id']);
        $this->assertEqualsWithDelta(10000.0, $usage[0]['quantity'], 1e-6);
        $this->assertSame('g', $usage[0]['unit']);
    }
}
```

- [ ] **Step 2: Run test to verify it passes already (guards the contract we depend on)**

Run: `php vendor/bin/phpunit tests/Unit/MenuCycleUsageForDaysTest.php`
Expected: PASS — confirms `aggregate()`'s `ingredient_usage` shape (`fs_item_id`, `quantity` in base unit, `unit`) that consumption relies on. (This test pins the contract before we extract the day→entry mapping.)

- [ ] **Step 3: Extract `entriesForDays()` from `forCycle()` and add `usageForDays()`**

In `app/Services/MenuCycleCostService.php`, replace the body of `forCycle()` so the day→entry mapping becomes a reusable static, and add a convenience method. Find the `forCycle()` method and refactor:

```php
    public static function forCycle(MenuCycle $cycle, ?int $population = null): array
    {
        $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');

        return self::aggregate(self::entriesForDays($cycle->days), $population ?? (int) $cycle->population);
    }

    /**
     * Map a collection of MenuCycleDay models into the plain-array entries that
     * aggregate() consumes. Shared by the planner (forCycle), consumption
     * (ConsumptionService), and procurement so their math can never diverge.
     * Requires days.recipe.ingredients.fsItem and days.fsItem to be loaded.
     *
     * @param  \Illuminate\Support\Collection $days
     */
    public static function entriesForDays($days): array
    {
        return $days
            ->filter(fn ($day) => $day->recipe !== null || $day->fsItem !== null)
            ->map(function ($day) {
                $base = [
                    'day_of_week'       => $day->day_of_week,
                    'meal_type'         => $day->meal_type,
                    'servings_override' => $day->servings_override,
                ];

                if ($day->recipe !== null) {
                    return $base + [
                        'recipe' => [
                            'servings'    => (int) $day->recipe->servings,
                            'ingredients' => $day->recipe->ingredients
                                ->filter(fn ($ing) => $ing->fsItem !== null)
                                ->map(fn ($ing) => [
                                    'fs_item_id' => $ing->fs_item_id,
                                    'name'       => $ing->fsItem->name,
                                    'quantity'   => (float) $ing->quantity,
                                    'unit'       => $ing->unit,
                                    'base_unit'  => $ing->fsItem->base_unit,
                                    'unit_cost'  => $ing->fsItem->unit_cost,
                                ])->values()->all(),
                        ],
                    ];
                }

                return $base + [
                    'item' => [
                        'fs_item_id' => $day->fs_item_id,
                        'name'       => $day->fsItem->name,
                        'unit'       => $day->fsItem->base_unit,
                        'unit_cost'  => $day->fsItem->unit_cost,
                        'quantity'   => (float) ($day->quantity ?: 1),
                    ],
                ];
            })->values()->all();
    }

    /** Required base-unit ingredient usage for a subset of days at a target headcount. */
    public static function usageForDays($days, int $target): array
    {
        return self::aggregate(self::entriesForDays($days), $target)['ingredient_usage'];
    }
```

Delete the now-duplicated inline mapping that previously lived inside `forCycle()`.

- [ ] **Step 4: Run the menu-cost suite (no behavior change)**

Run: `php vendor/bin/phpunit tests/Unit/MenuCycleCostServiceTest.php tests/Unit/MenuCycleUsageForDaysTest.php`
Expected: PASS — `forCycle()` is behavior-preserving (it now delegates to `entriesForDays()`).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/MenuCycleCostService.php backend/tests/Unit/MenuCycleUsageForDaysTest.php
git commit -m "refactor(fs): extract MenuCycleCostService::entriesForDays + usageForDays"
```

---

## Task 2: Reshape `meal_prep_logs`, create `meal_prep_log_lines`

**Files:**
- Create: `database/migrations/2026_06_14_000001_reshape_meal_prep_logs_for_consumption.php`

- [ ] **Step 1: Write the migration**

`meal_prep_logs` is orphaned (no controller/route/data), so we drop and recreate it in the consumption shape, then add the snapshot child.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meal_prep_log_lines');
        Schema::dropIfExists('meal_prep_logs');

        Schema::create('meal_prep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_cycle_id')->constrained('menu_cycles')->cascadeOnDelete();
            $table->date('service_date');
            $table->enum('status', ['completed', 'reversed'])->default('completed');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->boolean('has_shortfall')->default(false);
            $table->timestamps();

            $table->unique(['menu_cycle_id', 'service_date']);
        });

        Schema::create('meal_prep_log_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_prep_log_id')->constrained('meal_prep_logs')->cascadeOnDelete();
            $table->foreignId('fs_item_id')->constrained('fs_items')->cascadeOnDelete();
            $table->decimal('qty_base', 12, 2);     // deducted, in base unit
            $table->string('unit', 20);
            $table->decimal('unit_cost', 12, 6);     // ₱/base at time of consumption (snapshot)
            $table->decimal('line_value', 12, 2);
            $table->decimal('shortfall_qty', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_prep_log_lines');
        Schema::dropIfExists('meal_prep_logs');

        // Restore the original orphaned shape.
        Schema::create('meal_prep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fss_user_id')->references('id')->on('users');
            $table->foreignId('menu_cycle_day_id')->constrained()->references('id')->on('menu_cycle_days');
            $table->decimal('prepared_quantity', 8, 2)->nullable();
            $table->enum('status', ['done', 'pending'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 2: Run it**

Run: `php artisan migrate --force`
Expected: the migration name with `DONE`.

- [ ] **Step 3: Verify schema**

Run:
```bash
php artisan tinker --execute="dump(Schema::hasColumn('meal_prep_logs','service_date'), Schema::hasColumn('meal_prep_logs','menu_cycle_id'), Schema::hasTable('meal_prep_log_lines'));"
```
Expected: `true`, `true`, `true`.

- [ ] **Step 4: Commit**

```bash
git add backend/database/migrations/2026_06_14_000001_reshape_meal_prep_logs_for_consumption.php
git commit -m "feat(fs): reshape meal_prep_logs for consumption; add meal_prep_log_lines"
```

---

## Task 3: Models — `MealPrepLog` reshape + `MealPrepLogLine`

**Files:**
- Modify: `app/Models/MealPrepLog.php`
- Create: `app/Models/MealPrepLogLine.php`

- [ ] **Step 1: Rewrite `MealPrepLog`**

Replace the whole class body of `app/Models/MealPrepLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPrepLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_cycle_id', 'service_date', 'status',
        'completed_by', 'completed_at', 'total_value', 'has_shortfall',
    ];

    protected $casts = [
        'service_date'  => 'date',
        'completed_at'  => 'datetime',
        'total_value'   => 'decimal:2',
        'has_shortfall' => 'boolean',
    ];

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MealPrepLogLine::class);
    }
}
```

- [ ] **Step 2: Create `MealPrepLogLine`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPrepLogLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_prep_log_id', 'fs_item_id', 'qty_base', 'unit', 'unit_cost', 'line_value', 'shortfall_qty',
    ];

    protected $casts = [
        'qty_base'      => 'decimal:2',
        'unit_cost'     => 'decimal:6',
        'line_value'    => 'decimal:2',
        'shortfall_qty' => 'decimal:2',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(MealPrepLog::class, 'meal_prep_log_id');
    }

    public function fsItem(): BelongsTo
    {
        return $this->belongsTo(FsItem::class, 'fs_item_id');
    }
}
```

- [ ] **Step 3: Lint**

Run: `php -l app/Models/MealPrepLog.php && php -l app/Models/MealPrepLogLine.php`
Expected: No syntax errors in both.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Models/MealPrepLog.php backend/app/Models/MealPrepLogLine.php
git commit -m "feat(fs): MealPrepLog reshape + MealPrepLogLine snapshot model"
```

---

## Task 4: `ConsumptionService` — completeDay + reverseDay

**Files:**
- Create: `app/Services/FSS/ConsumptionService.php`

- [ ] **Step 1: Write the service**

```php
<?php

namespace App\Services\FSS;

use App\Models\Inventory;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Services\MenuCycleCostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumptionService
{
    private const EPS = 1e-6;

    /**
     * Complete a whole service day: deduct every meal slot's planned base-unit
     * ingredients from inventory at stored last-cost, snapshotting each line.
     * Idempotent per (cycle, date); blocks (422) on any shortfall before touching stock.
     */
    public function completeDay(MenuCycle $cycle, string $serviceDate, ?int $populationOverride = null): MealPrepLog
    {
        $weekday = Carbon::parse($serviceDate)->format('l');

        return DB::transaction(function () use ($cycle, $serviceDate, $populationOverride, $weekday) {
            if (MealPrepLog::where('menu_cycle_id', $cycle->id)
                ->where('service_date', $serviceDate)
                ->where('status', 'completed')->exists()) {
                abort(422, "Service day {$serviceDate} is already completed for this cycle.");
            }

            $cycle->loadMissing('days.recipe.ingredients.fsItem', 'days.fsItem');
            $days = $cycle->days->where('day_of_week', $weekday);
            if ($days->isEmpty()) {
                abort(422, "No menu slots planned for {$weekday}.");
            }

            $target = $populationOverride ?? (int) $cycle->population;
            $usage  = MenuCycleCostService::usageForDays($days, $target); // [{fs_item_id,name,unit,quantity,cost}]

            $invByItem = Inventory::whereIn('fs_item_id', array_column($usage, 'fs_item_id'))
                ->lockForUpdate()->get()->keyBy('fs_item_id');

            // Pre-flight cover check — block before any deduction.
            $short = [];
            foreach ($usage as $u) {
                $have = (float) optional($invByItem[$u['fs_item_id']] ?? null)->quantity_in_stock;
                if ($have + self::EPS < (float) $u['quantity']) {
                    $short[] = "{$u['name']}: need " . round($u['quantity'], 2) . " {$u['unit']}, have " . round($have, 2);
                }
            }
            if ($short) {
                abort(422, 'Insufficient stock to serve ' . $weekday . ' — fix upstream (receive the PO or adjust headcount). Short: ' . implode('; ', $short));
            }

            $log = MealPrepLog::create([
                'menu_cycle_id' => $cycle->id,
                'service_date'  => $serviceDate,
                'status'        => 'completed',
                'completed_by'  => Auth::id(),
                'completed_at'  => now(),
                'total_value'   => 0,
                'has_shortfall' => false,
            ]);

            $total = 0.0;
            foreach ($usage as $u) {
                $inv      = $invByItem[$u['fs_item_id']];
                $unitCost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);
                $qty      = (float) $u['quantity'];

                $inv->quantity_in_stock = (float) $inv->quantity_in_stock - $qty;
                $inv->save();

                $lineValue = round($qty * $unitCost, 2);
                $log->lines()->create([
                    'fs_item_id'    => $u['fs_item_id'],
                    'qty_base'      => $qty,
                    'unit'          => $u['unit'],
                    'unit_cost'     => $unitCost,
                    'line_value'    => $lineValue,
                    'shortfall_qty' => 0,
                ]);
                $total += $lineValue;
            }

            $log->update(['total_value' => round($total, 2)]);

            return $log->load('lines');
        });
    }

    /** Un-complete a day: add back exactly the snapshot quantities (never a recompute). */
    public function reverseDay(MealPrepLog $log): MealPrepLog
    {
        if ($log->status === 'reversed') {
            abort(422, 'This service day is already reversed.');
        }

        return DB::transaction(function () use ($log) {
            foreach ($log->lines as $line) {
                $inv = Inventory::where('fs_item_id', $line->fs_item_id)->lockForUpdate()->first();
                if ($inv) {
                    $inv->quantity_in_stock = (float) $inv->quantity_in_stock + (float) $line->qty_base;
                    $inv->save();
                }
            }
            $log->update(['status' => 'reversed']);

            return $log->fresh('lines');
        });
    }
}
```

- [ ] **Step 2: Lint**

Run: `php -l app/Services/FSS/ConsumptionService.php`
Expected: No syntax errors.

- [ ] **Step 3: Tinker — complete, idempotency, reverse, block-on-shortfall**

Run (seed first if needed: `php artisan db:seed --class=FoodServiceDemoSeeder`):
```bash
php artisan tinker --execute="
use App\Models\MenuCycle; use App\Models\Inventory; use App\Models\MealPrepLog; use App\Services\FSS\ConsumptionService;
use Illuminate\Support\Facades\Auth;
\$cycle = MenuCycle::where('is_active',1)->first();
\$svc = new ConsumptionService();
\$date = \Carbon\Carbon::parse(\$cycle->week_start_date)->next(\Carbon\Carbon::MONDAY)->toDateString();
// snapshot stock of one Monday ingredient
\$cycle->loadMissing('days.recipe.ingredients.fsItem','days.fsItem');
\$mon = \$cycle->days->where('day_of_week','Monday')->first();
\$fsId = optional(optional(\$mon->recipe)->ingredients->first())->fs_item_id ?? \$mon->fs_item_id;
\$before = (float) optional(Inventory::where('fs_item_id',\$fsId)->first())->quantity_in_stock;
try {
  \$log = \$svc->completeDay(\$cycle, \$date, 50);
  \$after = (float) Inventory::where('fs_item_id',\$fsId)->first()->quantity_in_stock;
  dump(['completed'=>true,'lines'=>\$log->lines->count(),'total_value'=>(float)\$log->total_value,'stock_dropped'=>\$before > \$after]);
  // idempotency
  try { \$svc->completeDay(\$cycle, \$date, 50); dump('IDEMPOTENCY FAILED'); } catch (\Throwable \$e) { dump(['idempotent_blocked'=>true]); }
  // reverse restores
  \$svc->reverseDay(\$log->fresh('lines'));
  \$restored = (float) Inventory::where('fs_item_id',\$fsId)->first()->quantity_in_stock;
  dump(['reversed_restored'=> abs(\$restored - \$before) < 0.01]);
} catch (\Throwable \$e) {
  dump(['blocked_message'=>\$e->getMessage()]);
}
"
```
Expected: `completed=>true`, lines > 0, `stock_dropped=>true`, `idempotent_blocked=>true`, `reversed_restored=>true`. (If it instead shows `blocked_message` about insufficient stock, that's the cover-check firing — re-seed for full stock and re-run.)

- [ ] **Step 4: Commit**

```bash
git add backend/app/Services/FSS/ConsumptionService.php
git commit -m "feat(fs): ConsumptionService completeDay (block-on-shortfall) + reverseDay"
```

---

## Task 5: API — `MealPrepLogController` + routes

**Files:**
- Create: `app/Http/Controllers/FSS/MealPrepLogController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Models\MealPrepLog;
use App\Models\MenuCycle;
use App\Services\FSS\ConsumptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealPrepLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from'          => ['nullable', 'date'],
            'to'            => ['nullable', 'date', 'after_or_equal:from'],
            'menu_cycle_id' => ['nullable', 'integer'],
        ]);

        $logs = MealPrepLog::with('lines', 'menuCycle:id,name', 'completedBy:id,name')
            ->when($data['menu_cycle_id'] ?? null, fn ($q, $id) => $q->where('menu_cycle_id', $id))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->where('service_date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->where('service_date', '<=', $d))
            ->orderByDesc('service_date')->get();

        return response()->json(['data' => $logs]);
    }

    public function complete(Request $request, MenuCycle $menuCycle, ConsumptionService $consumption): JsonResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date'],
            'population'   => ['nullable', 'integer', 'min:1'],
        ]);

        $log = $consumption->completeDay($menuCycle, $data['service_date'], $data['population'] ?? null);

        return response()->json(['data' => $log], 201);
    }

    public function reverse(MealPrepLog $mealPrepLog, ConsumptionService $consumption): JsonResponse
    {
        return response()->json(['data' => $consumption->reverseDay($mealPrepLog->load('lines'))]);
    }
}
```

- [ ] **Step 2: Register routes**

In `routes/api.php`, inside the `fss` group (near the Menu Cycles routes), add:

```php
    // Consumption (meal prep / service-day completion)
    Route::get('meal-prep-logs', [MealPrepLogController::class, 'index']);
    Route::post('menu-cycles/{menuCycle}/complete-day', [MealPrepLogController::class, 'complete']);
    Route::post('meal-prep-logs/{mealPrepLog}/reverse', [MealPrepLogController::class, 'reverse']);
```

Add `use App\Http\Controllers\FSS\MealPrepLogController;` at the top of `routes/api.php` if controllers are imported there (match the existing import style; if the file uses fully-qualified inline references, follow that instead).

- [ ] **Step 3: Lint + route smoke**

Run: `php -l app/Http/Controllers/FSS/MealPrepLogController.php && php artisan route:list --path=fss/meal-prep 2>&1 | tail -5`
Expected: no syntax errors; the three routes listed (`meal-prep-logs`, `complete-day`, `reverse`).

- [ ] **Step 4: Commit**

```bash
git add backend/app/Http/Controllers/FSS/MealPrepLogController.php backend/routes/api.php
git commit -m "feat(fs): meal-prep-log API (complete-day, reverse, index)"
```

---

## Task 6: Procurement — date-range "smart" list (review finding §3b)

**Files:**
- Modify: `app/Http/Controllers/FSS/ShoppingListController.php`

- [ ] **Step 1: Replace proportional `generate()` with date-range summation**

In `ShoppingListController::generate()`, swap the `days_span` proportional logic for an exact sum over the actual dates. Replace the method's validation + computation:

```php
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'menu_cycle_id' => ['required', 'integer', 'exists:menu_cycles,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'name'          => ['nullable', 'string', 'max:255'],
        ]);

        $cycle = MenuCycle::with('days.recipe.ingredients.fsItem', 'days.fsItem')->findOrFail($data['menu_cycle_id']);

        // Sum the ACTUAL planned days across the range (not a proportional average).
        $acc = []; // fs_item_id => ['name','unit','qty','total']
        $cursor = \Carbon\Carbon::parse($data['start_date']);
        $end    = \Carbon\Carbon::parse($data['end_date']);
        $spanDays = $cursor->diffInDays($end) + 1;

        for (; $cursor->lte($end); $cursor->addDay()) {
            $days = $cycle->days->where('day_of_week', $cursor->format('l'));
            if ($days->isEmpty()) {
                continue;
            }
            foreach (MenuCycleCostService::usageForDays($days, (int) $cycle->population) as $u) {
                $id = $u['fs_item_id'];
                $acc[$id] ??= ['name' => $u['name'], 'unit' => $u['unit'], 'qty' => 0.0, 'total' => 0.0];
                $acc[$id]['qty']   += (float) $u['quantity'];
                $acc[$id]['total'] += (float) $u['cost'];
            }
        }

        $fsItems = FsItem::whereIn('id', array_keys($acc))->get()->keyBy('id');

        $list = DB::transaction(function () use ($data, $cycle, $acc, $fsItems, $spanDays) {
            $list = ShoppingList::create([
                'fss_user_id'   => Auth::id(),
                'menu_cycle_id' => $cycle->id,
                'name'          => $data['name'] ?? "Suggested — {$cycle->name} ({$data['start_date']}→{$data['end_date']})",
                'list_date'     => now()->toDateString(),
                'period_start'  => $data['start_date'],
                'period_end'    => $data['end_date'],
                'days_span'     => $spanDays,
                'list_type'     => 'suggested',
                'status'        => 'draft',
            ]);

            foreach ($acc as $id => $row) {
                $unitPrice = $row['qty'] > 0 ? round($row['total'] / $row['qty'], 4) : 0;
                $list->items()->create([
                    'fs_item_id'      => $id,
                    'ingredient_name' => $row['name'],
                    'qty'             => round($row['qty'], 2),
                    'unit'            => $row['unit'],
                    'supplier_id'     => ($fsItems[$id] ?? null)?->default_supplier_id,
                    'unit_price'      => $unitPrice,
                    'total'           => round($row['total'], 2),
                ]);
            }

            return $list;
        });

        return response()->json(['data' => new ShoppingListResource($list->load('items'))], 201);
    }
```

Remove the now-unused `use App\Services\ProcurementService;` and `use App\Services\MenuCycleCostService;`-via-`MenuCycleCostService::forCycle` path **only if** no other method in the controller uses them (`generate` is the sole user — confirm with `git grep -n "ProcurementService\|MenuCycleCostService" app/Http/Controllers/FSS/ShoppingListController.php`; keep `MenuCycleCostService` since `usageForDays` now uses it). `ProcurementService` becomes unused by this controller; leave the class in place (it stays unit-tested) but drop its import here.

- [ ] **Step 2: Lint + tinker verification (exact day sum, not average)**

Run:
```bash
php -l app/Http/Controllers/FSS/ShoppingListController.php && php artisan tinker --execute="
use App\Models\MenuCycle; use App\Http\Controllers\FSS\ShoppingListController; use Illuminate\Http\Request;
\$cycle = MenuCycle::where('is_active',1)->first();
\$start = \Carbon\Carbon::parse(\$cycle->week_start_date)->next(\Carbon\Carbon::TUESDAY)->toDateString();
\$end   = \Carbon\Carbon::parse(\$start)->addDays(2)->toDateString(); // Tue→Thu
\$req = Request::create('/','POST',['menu_cycle_id'=>\$cycle->id,'start_date'=>\$start,'end_date'=>\$end]);
\$c = new ShoppingListController();
\$json = json_decode(\$c->generate(\$req)->getContent(), true);
dump(['items'=>count(\$json['data']['items']), 'period'=>[\$json['data']['period_start'] ?? null, \$json['data']['period_end'] ?? null]]);
"
```
Expected: a list with items (the union of Tue+Wed+Thu ingredients) and the period echoed. No SQL error.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Controllers/FSS/ShoppingListController.php
git commit -m "feat(fs): smart shopping list sums actual selected days (Tue-Thu) not a proportional average"
```

---

## Task 7: Regression sweep

- [ ] **Step 1: Pure unit tests for touched engines**

Run: `php vendor/bin/phpunit tests/Unit/MenuCycleCostServiceTest.php tests/Unit/MenuCycleUsageForDaysTest.php tests/Unit/ProcurementServiceTest.php tests/Unit/RecipeScalerTest.php tests/Unit/UnitConverterTest.php`
Expected: PASS (engine refactor is behavior-preserving).

- [ ] **Step 2: Full-loop tinker smoke (receive → serve → reverse)**

Run: `php artisan db:seed --class=FoodServiceDemoSeeder` then:
```bash
php artisan tinker --execute="
use App\Models\MealPrepLog; use App\Models\MealPrepLogLine; use App\Models\Inventory;
dump([
  'prep_logs'=> MealPrepLog::count(),
  'log_lines'=> MealPrepLogLine::count(),
  'negative_stock_rows'=> Inventory::where('quantity_in_stock','<',0)->count(),
]);
"
```
Expected: counts ≥ 0; `negative_stock_rows => 0` (block-on-shortfall prevents negatives).

- [ ] **Step 3: Commit**

```bash
git commit --allow-empty -m "test(fs): Spec 2 backend regression sweep green"
```

---

## Self-Review notes (coverage map)

- §4.1 schema → Task 2. §4.2 completeDay (idempotency, servings, usage build, cover-check, deduct+snapshot, total) → Tasks 1, 4. §4.3 reverseDay → Task 4. §4.4 API → Task 5. §3b procurement day-range → Tasks 1, 6. Shared usage builder (anti-divergence) → Task 1.
- **Error handling per task:** transactional complete/reverse + row locks (Task 4), idempotency 422 (Task 4), block-on-shortfall 422 with named items (Task 4), no-slots-for-weekday 422 (Task 4), already-reversed 422 (Task 4), validated request inputs (Task 5), empty-range days skipped (Task 6).
- **Deferred — Phase 2 (separate frontend plan):** menu-cycle "Mark served" date picker + shortfall toast; usage-log view with reverse; procurement page date-range picker replacing `days_span`. The `days_span`→`start_date/end_date` API change (Task 6) **breaks the current procurement page call** until Phase 2 lands — note for sequencing.
- **Not in this plan (later specs):** supplies depletion (#10, Spec 6); plan-vs-actual/waste charts (Spec 3, consume `meal_prep_log_lines`); audit of complete/reverse (Spec 5).
