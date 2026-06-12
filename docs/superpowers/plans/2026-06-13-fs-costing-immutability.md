# Food-Service Costing & Immutability (Spec 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make receiving a purchase order record what was actually paid into inventory (base-unit last-cost), refresh the catalog price, keep recipe costs fresh, fix unit-mixing bugs, remove `expiry_date`, date spend by receipt, and guarantee PO-derived reports stay frozen when catalog prices change later.

**Architecture:** A new `ReceivingService` owns the receive→stock→cost→catalog→recipe flow (pulled out of the controller). A new pure `FsItem::basePerPurchase()` helper makes the base⇄purchase-unit conversion DRY and reversible. All money-bearing PO reports already read frozen `purchase_order_items` snapshots; we only fix the live-price leaks (inventory valuation) and the supporting integrity bugs. Spec source: `docs/superpowers/specs/2026-06-12-fs-costing-immutability-design.md`.

**Tech Stack:** Laravel 11 (PHP 8.3), MySQL (prod), PHPUnit (pure unit tests only — see Testing Strategy), Next.js (custom build — read `frontend/AGENTS.md` before FE edits), Tailwind.

---

## Testing Strategy (read first)

- **Pure unit tests** (no DB, no `RefreshDatabase`) are the reliable automated layer here — mirror `backend/tests/Unit/FsItemUnitCostTest.php`. Run with `php artisan test --filter=<Name>`.
- **DB-backed feature tests DO NOT RUN** in this repo: `phpunit.xml` uses sqlite `:memory:`, but migration `2026_06_11_000110_decouple_inventory_and_recipes_to_fs_items.php` runs MySQL-only `ALTER TABLE inventory MODIFY COLUMN ... ENUM(...)`, which sqlite rejects — so any `RefreshDatabase` test aborts during migration. (Several existing DB tests, e.g. `FoodServiceRecipeCostTest`, are already stale/broken for this reason.) **Do not add `RefreshDatabase` tests.**
- **DB behavior is verified via `php artisan tinker`** scripts with expected output, per project convention. Each DB task below includes a copy-paste tinker block.
- Run the existing pure suites after each task to catch regressions: `php artisan test --testsuite=Unit`.

All backend commands run from `backend/`. Commit after every task.

---

## File Structure

**Backend — create:**
- `app/Services/FSS/ReceivingService.php` — receive orchestration + pure `normalizeLine()`.
- `database/migrations/2026_06_13_000001_add_received_date_and_drop_expiry.php` — schema deltas.
- `tests/Unit/FsItemBasePerPurchaseTest.php`, `tests/Unit/ReceivingServiceNormalizeTest.php`.

**Backend — modify:**
- `app/Models/FsItem.php` — add `basePerPurchase()`, refactor `getUnitCostAttribute()`.
- `app/Models/PurchaseOrder.php` — `received_date` + `recalcTotal()`.
- `app/Models/Inventory.php` — drop `expiry_date` from fillable/casts.
- `app/Models/FoodServiceRecipe.php` — add `recalculateForItems()`.
- `app/Http/Controllers/FSS/PurchaseOrderController.php` — use `ReceivingService`, stamp `received_date`, `recalcTotal()`; delete `restockFrom()`.
- `app/Http/Controllers/FSS/InventoryController.php` — remove `COALESCE` unit-mix; base-unit upsert on store/update; drop expiry.
- `app/Http/Controllers/FSS/FsItemController.php` — recompute recipes on catalog price change; `priceTrend()`.
- `app/Http/Controllers/FSS/FoodServiceRecipeController.php` — validate ingredient units; block delete when referenced.
- `app/Http/Controllers/FSS/BudgetController.php` — bucket actual by `received_date`; double-count warning.
- `app/Http/Requests/FSS/StoreInventoryRequest.php`, `UpdateInventoryRequest.php` — drop `expiry_date`.
- `app/Http/Resources/InventoryResource.php` — drop `expiry_date`.
- `app/Services/Reports/Generators/InventoryReportGenerator.php` — value at stored last-cost.
- `database/seeders/FoodServiceDemoSeeder.php` — drop `expiry_date` writes.
- `routes/api.php` — add price-trend route.

**Backend — delete:** `database/seeders/InventorySeeder.php`, `database/seeders/InventoryDemoSeeder.php` (stale `food_item_id` schema).

**Frontend — modify:** `frontend/services/inventoryService.ts`, `frontend/app/(rnd)/food-service/inventory/page.tsx` — remove `expiry_date`.

---

## Task 1: `FsItem::basePerPurchase()` + DRY `unit_cost`

**Files:**
- Modify: `app/Models/FsItem.php`
- Test: `tests/Unit/FsItemBasePerPurchaseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\FsItem;
use Tests\TestCase;

class FsItemBasePerPurchaseTest extends TestCase
{
    public function test_physical_units_use_converter(): void
    {
        $item = new FsItem(['purchase_unit' => 'kg', 'base_unit' => 'g']);
        $this->assertEqualsWithDelta(1000.0, $item->basePerPurchase(), 1e-6);
    }

    public function test_same_unit_is_one(): void
    {
        $item = new FsItem(['purchase_unit' => 'pc', 'base_unit' => 'pc']);
        $this->assertSame(1.0, $item->basePerPurchase());
    }

    public function test_count_pack_uses_units_per_purchase(): void
    {
        $item = new FsItem(['purchase_unit' => 'pack', 'base_unit' => 'pc', 'units_per_purchase' => 100]);
        $this->assertEqualsWithDelta(100.0, $item->basePerPurchase(), 1e-6);
    }

    public function test_misconfigured_returns_zero(): void
    {
        $item = new FsItem(['purchase_unit' => 'pack', 'base_unit' => 'pc']); // no units_per_purchase
        $this->assertSame(0.0, $item->basePerPurchase());
    }

    public function test_unit_cost_round_trips_through_base_per_purchase(): void
    {
        // ₱80/kg → 0.08/g; 0.08 × 1000 = ₱80 back
        $item = new FsItem(['purchase_price' => 80, 'purchase_unit' => 'kg', 'base_unit' => 'g']);
        $this->assertEqualsWithDelta(0.08, $item->unit_cost, 1e-6);
        $this->assertEqualsWithDelta(80.0, $item->unit_cost * $item->basePerPurchase(), 1e-4);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FsItemBasePerPurchaseTest`
Expected: FAIL — `Call to undefined method App\Models\FsItem::basePerPurchase()`.

- [ ] **Step 3: Add `basePerPurchase()` and refactor `getUnitCostAttribute()`**

In `app/Models/FsItem.php`, replace the whole `getUnitCostAttribute()` method with:

```php
    /**
     * Base units contained in ONE purchase unit (e.g. 1000 g per kg, or
     * units_per_purchase for count packs). 1.0 for same/empty units; 0.0 when
     * misconfigured (degrade, never throw inside a list view).
     */
    public function basePerPurchase(): float
    {
        $from = (string) $this->purchase_unit;
        $to   = (string) $this->base_unit;

        if ($from === '' || $to === '' || UnitConverter::normalize($from) === UnitConverter::normalize($to)) {
            return 1.0;
        }
        if (UnitConverter::isKnown($from) && UnitConverter::isKnown($to)) {
            return UnitConverter::convert(1, $from, $to);
        }
        return (float) ($this->units_per_purchase ?? 0);
    }

    /** Cost of ONE base_unit (₱ per gram, etc.), derived from how the item is bought. */
    public function getUnitCostAttribute(): float
    {
        $n = $this->basePerPurchase();
        return $n > 0 ? round((float) $this->purchase_price / $n, 6) : 0.0;
    }
```

(`UnitConverter` is already imported at the top of the file.)

- [ ] **Step 4: Run new + existing unit-cost tests**

Run: `php artisan test --filter=FsItem`
Expected: PASS — both `FsItemBasePerPurchaseTest` and the existing `FsItemUnitCostTest` (the refactor is behavior-preserving: same-unit → price, kg→g → price/1000, pack→pc → price/units_per_purchase, misconfigured → 0.0).

- [ ] **Step 5: Commit**

```bash
git add app/Models/FsItem.php tests/Unit/FsItemBasePerPurchaseTest.php
git commit -m "feat(fs): add FsItem::basePerPurchase and DRY unit_cost"
```

---

## Task 2: `ReceivingService::normalizeLine()` (pure)

**Files:**
- Create: `app/Services/FSS/ReceivingService.php`
- Test: `tests/Unit/ReceivingServiceNormalizeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\FSS\ReceivingService;
use Tests\TestCase;

class ReceivingServiceNormalizeTest extends TestCase
{
    public function test_base_unit_line_is_unchanged(): void
    {
        // Suggested-list flow: line already in base (g) at ₱/g.
        [$qty, $cost] = ReceivingService::normalizeLine(500.0, 'g', 0.08, 'g');
        $this->assertEqualsWithDelta(500.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(0.08, $cost, 1e-6);
    }

    public function test_purchase_unit_line_is_converted_to_base(): void
    {
        // Manual PO: 25 kg at ₱280/kg, base unit g → 25000 g at ₱0.28/g.
        [$qty, $cost] = ReceivingService::normalizeLine(25.0, 'kg', 280.0, 'g');
        $this->assertEqualsWithDelta(25000.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(0.28, $cost, 1e-6);
    }

    public function test_unknown_unit_is_treated_as_base(): void
    {
        // 'tray' is not a known physical unit → degrade, don't throw.
        [$qty, $cost] = ReceivingService::normalizeLine(10.0, 'tray', 240.0, 'pc');
        $this->assertEqualsWithDelta(10.0, $qty, 1e-6);
        $this->assertEqualsWithDelta(240.0, $cost, 1e-6);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReceivingServiceNormalizeTest`
Expected: FAIL — class `App\Services\FSS\ReceivingService` not found.

- [ ] **Step 3: Create the service with the pure method only**

```php
<?php

namespace App\Services\FSS;

use App\Models\FoodServiceRecipe;
use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\PurchaseOrder;
use App\Support\UnitConverter;
use Illuminate\Support\Facades\Log;

class ReceivingService
{
    /**
     * Normalize a PO line (qty + per-line-unit price) into base-unit terms.
     * Pure (no DB). Returns [qtyBase, perBaseCost]. Unknown/unconvertible units
     * degrade to "treat the line as base units" rather than throwing.
     *
     * @return array{0:float,1:float}
     */
    public static function normalizeLine(float $qty, string $lineUnit, float $lineUnitPrice, string $baseUnit): array
    {
        $from = UnitConverter::normalize($lineUnit);
        $to   = UnitConverter::normalize($baseUnit);

        if ($from === '' || $to === '' || $from === $to
            || ! UnitConverter::isKnown($from) || ! UnitConverter::isKnown($to)) {
            return [$qty, $lineUnitPrice];
        }

        $basePerLine = UnitConverter::convert(1, $from, $to);
        if ($basePerLine <= 0) {
            return [$qty, $lineUnitPrice];
        }
        return [$qty * $basePerLine, $lineUnitPrice / $basePerLine];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReceivingServiceNormalizeTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/FSS/ReceivingService.php tests/Unit/ReceivingServiceNormalizeTest.php
git commit -m "feat(fs): add ReceivingService::normalizeLine pure helper"
```

---

## Task 3: Migration — add `received_date`, drop `expiry_date`

**Files:**
- Create: `database/migrations/2026_06_13_000001_add_received_date_and_drop_expiry.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->date('received_date')->nullable()->after('order_date');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('received_date');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('unit');
        });
    }
};
```

- [ ] **Step 2: Run the migration (MySQL dev DB)**

Run: `php artisan migrate`
Expected: `INFO  Running migrations.` then the new migration name with `DONE`. (If `received_date`/`expiry_date` errors mention an unknown column on rollback, ignore — only `up` runs here.)

- [ ] **Step 3: Verify the schema changed**

Run:
```bash
php artisan tinker --execute="dump(Schema::hasColumn('purchase_orders','received_date'), Schema::hasColumn('inventory','expiry_date'));"
```
Expected: `true` then `false`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_13_000001_add_received_date_and_drop_expiry.php
git commit -m "feat(fs): add purchase_orders.received_date, drop inventory.expiry_date"
```

---

## Task 4: Drop `expiry_date` from model, resource, requests

**Files:**
- Modify: `app/Models/Inventory.php:18`, `app/Http/Resources/InventoryResource.php:34`, `app/Http/Requests/FSS/StoreInventoryRequest.php:19`, `app/Http/Requests/FSS/UpdateInventoryRequest.php:16`

- [ ] **Step 1: Remove from `Inventory` model**

In `app/Models/Inventory.php`, delete `'expiry_date',` from `$fillable` and delete the `'expiry_date' => 'date',` line from `$casts`.

- [ ] **Step 2: Remove from `InventoryResource`**

In `app/Http/Resources/InventoryResource.php`, delete the line:
```php
            'expiry_date'              => $this->expiry_date?->toDateString(),
```

- [ ] **Step 3: Remove from both requests**

In `StoreInventoryRequest.php` delete `'expiry_date' => ['nullable', 'date'],`.
In `UpdateInventoryRequest.php` delete `'expiry_date' => ['nullable', 'date'],`.

- [ ] **Step 4: Verify nothing else references it on the backend**

Run: `git grep -n "expiry_date" -- backend/app`
Expected: no output (all backend app references removed; seeder handled in Task 12, frontend in Task 13).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Inventory.php app/Http/Resources/InventoryResource.php app/Http/Requests/FSS/StoreInventoryRequest.php app/Http/Requests/FSS/UpdateInventoryRequest.php
git commit -m "refactor(fs): remove expiry_date from inventory model/resource/requests"
```

---

## Task 5: `PurchaseOrder` — `received_date` + `recalcTotal()`

**Files:**
- Modify: `app/Models/PurchaseOrder.php`

- [ ] **Step 1: Add fillable, cast, and method**

In `app/Models/PurchaseOrder.php`: add `'received_date'` to `$fillable`; add `'received_date' => 'date',` to `$casts` (create a `$casts` array if absent); and add:

```php
    /** Single source of truth: total_amount is always the sum of its line items. */
    public function recalcTotal(): void
    {
        $this->total_amount = (float) $this->items()->sum('total_value');
        $this->save();
    }
```

- [ ] **Step 2: Verify via tinker**

Run:
```bash
php artisan tinker --execute="\$po = App\Models\PurchaseOrder::with('items')->first(); \$po->recalcTotal(); dump((float)\$po->total_amount === (float)\$po->items->sum('total_value'));"
```
Expected: `true` (or `No PO yet` if the DB is empty — seed first with `php artisan db:seed --class=FoodServiceDemoSeeder`).

- [ ] **Step 3: Commit**

```bash
git add app/Models/PurchaseOrder.php
git commit -m "feat(fs): PurchaseOrder received_date cast + recalcTotal()"
```

---

## Task 6: `FoodServiceRecipe::recalculateForItems()`

**Files:**
- Modify: `app/Models/FoodServiceRecipe.php`

- [ ] **Step 1: Add the batch recompute method**

In `app/Models/FoodServiceRecipe.php`, add `use Illuminate\Support\Facades\Log;` at the top, then add:

```php
    /**
     * Recompute the cached cost of every recipe that uses any of the given
     * fs_items. One bad recipe is logged, not allowed to abort the batch.
     *
     * @param array<int,int> $fsItemIds
     */
    public static function recalculateForItems(array $fsItemIds): void
    {
        if (! $fsItemIds) {
            return;
        }
        $recipeIds = FoodServiceRecipeIngredient::whereIn('fs_item_id', $fsItemIds)
            ->pluck('food_service_recipe_id')->unique();

        foreach (static::whereIn('id', $recipeIds)->get() as $recipe) {
            try {
                $recipe->recalculateCost();
            } catch (\Throwable $e) {
                Log::warning('recalculateForItems: recipe recompute failed', [
                    'recipe' => $recipe->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
```

(`FoodServiceRecipeIngredient` is in the same namespace — no import needed.)

- [ ] **Step 2: Verify via tinker**

Run:
```bash
php artisan tinker --execute="\$ing = App\Models\FoodServiceRecipeIngredient::first(); App\Models\FoodServiceRecipe::recalculateForItems([\$ing->fs_item_id]); dump('ok', App\Models\FoodServiceRecipe::find(\$ing->food_service_recipe_id)->cost);"
```
Expected: `"ok"` then a non-negative decimal string (the recipe's recomputed cost). No exception.

- [ ] **Step 3: Commit**

```bash
git add app/Models/FoodServiceRecipe.php
git commit -m "feat(fs): FoodServiceRecipe::recalculateForItems batch recompute"
```

---

## Task 7: `ReceivingService::receive()` + wire into PO update

**Files:**
- Modify: `app/Services/FSS/ReceivingService.php`, `app/Http/Controllers/FSS/PurchaseOrderController.php`

- [ ] **Step 1: Add `receive()` to the service**

Append this method to `ReceivingService` (the `use` imports added in Task 2 already cover it):

```php
    /**
     * Receive a PO: for each catalog line, add base-unit qty to stock, store the
     * paid ₱/base-unit as last-cost, refresh the catalog purchase_price, then
     * recompute every recipe that uses a touched item. Caller wraps this in a
     * transaction. Free-text lines (no fs_item_id) are skipped + logged.
     */
    public function receive(PurchaseOrder $purchaseOrder): void
    {
        $touched = [];

        foreach ($purchaseOrder->items as $item) {
            if (! $item->fs_item_id) {
                Log::info('ReceivingService: skipped free-text PO line', [
                    'po' => $purchaseOrder->id, 'description' => $item->description,
                ]);
                continue;
            }

            $fs = FsItem::find($item->fs_item_id);
            if (! $fs) {
                Log::warning('ReceivingService: fs_item missing', ['fs_item_id' => $item->fs_item_id]);
                continue;
            }

            [$qtyBase, $perBaseCost] = self::normalizeLine(
                (float) $item->qty, (string) $item->unit, (float) $item->unit_price, (string) $fs->base_unit
            );

            $inv = Inventory::firstOrNew(['fs_item_id' => $fs->id]);
            if (! $inv->exists) {
                $inv->item_type = $fs->kind ?? 'ingredient';
                $inv->quantity_in_stock = 0;
            }
            $inv->unit = $fs->base_unit;
            $inv->quantity_in_stock = (float) $inv->quantity_in_stock + $qtyBase;
            $inv->unit_price = round($perBaseCost, 2); // ₱ per base unit (last cost)
            $inv->save();

            $basePerPurchase = $fs->basePerPurchase();
            if ($basePerPurchase > 0) {
                $fs->purchase_price = round($perBaseCost * $basePerPurchase, 2);
                $fs->save();
            }

            $touched[$fs->id] = true;
        }

        if ($touched) {
            FoodServiceRecipe::recalculateForItems(array_keys($touched));
        }
    }
```

- [ ] **Step 2: Replace `restockFrom` usage in the controller**

In `app/Http/Controllers/FSS/PurchaseOrderController.php`:

1. Add `use App\Services\FSS\ReceivingService;` near the other imports.
2. Replace the `update()` method body with:

```php
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, ReceivingService $receiving): JsonResponse
    {
        $validated = $request->validated();
        $previousStatus = $purchaseOrder->status;

        DB::transaction(function () use ($purchaseOrder, $validated, $previousStatus, $receiving) {
            $purchaseOrder->update($validated);

            if (($validated['status'] ?? null) === 'received' && $previousStatus !== 'received') {
                $purchaseOrder->received_date = now()->toDateString();
                $purchaseOrder->save();
                $receiving->receive($purchaseOrder->load('items'));
            }
        });

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->fresh()->load(self::RELATIONS))]);
    }
```

3. **Delete** the entire private `restockFrom()` method (lines ~155–172) and remove the now-unused `use App\Models\Inventory;` import **only if** no other method references `Inventory` (the `store`/`generatePos` methods do not — confirm with `git grep -n "Inventory" app/Http/Controllers/FSS/PurchaseOrderController.php`; if `ReceivingService` is the only path, drop the import).

- [ ] **Step 3: Tinker verification — full receive flow**

Run (seed first if empty: `php artisan db:seed --class=FoodServiceDemoSeeder`):
```bash
php artisan tinker --execute="
use App\Models\PurchaseOrder; use App\Models\Inventory; use App\Models\FsItem; use App\Services\FSS\ReceivingService;
\$po = PurchaseOrder::with('items')->where('status','received')->first();
\$item = \$po->items->firstWhere('fs_item_id','!=',null);
\$fs = FsItem::find(\$item->fs_item_id);
\$before = optional(Inventory::where('fs_item_id',\$fs->id)->first())->quantity_in_stock ?? 0;
\$po2 = PurchaseOrder::create(['fss_user_id'=>\$po->fss_user_id,'po_number'=>'TEST-'.time(),'order_date'=>now()->toDateString(),'status'=>'draft','total_amount'=>0]);
\$po2->items()->create(['fs_item_id'=>\$fs->id,'description'=>\$fs->name,'qty'=>10,'unit'=>\$fs->base_unit,'unit_price'=>0.5,'total_value'=>5]);
(new ReceivingService)->receive(\$po2->load('items'));
\$inv = Inventory::where('fs_item_id',\$fs->id)->first();
dump(['added_10_base'=> (float)\$inv->quantity_in_stock - (float)\$before === 10.0, 'last_cost'=> (float)\$inv->unit_price, 'unit_is_base'=> \$inv->unit === \$fs->base_unit]);
\$po2->delete();
"
```
Expected: `added_10_base => true`, `last_cost => 0.5`, `unit_is_base => true`.

- [ ] **Step 4: Run the unit suite (no regressions)**

Run: `php artisan test --testsuite=Unit`
Expected: PASS (existing + Task 1/2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/FSS/ReceivingService.php app/Http/Controllers/FSS/PurchaseOrderController.php
git commit -m "feat(fs): receive PO -> base-unit last-cost + catalog refresh + recipe recompute"
```

---

## Task 8: Recompute `total_amount` on PO item changes; received_date dating

**Files:**
- Modify: `app/Http/Controllers/FSS/PurchaseOrderController.php`

- [ ] **Step 1: Call `recalcTotal()` after item writes**

In `PurchaseOrderController::store()`, after the `foreach ($items as $item) { $po->items()->create([...]); }` loop, replace the manual `total_amount` default with a recompute so the line items are the single source of truth:

```php
            // (remove the earlier $data['total_amount'] = ... default if present)
            $po->recalcTotal();
```

In `generatePos()`, after each PO's items loop (inside the transaction), add `$po->recalcTotal();` before `$created[] = $po->id;`.

- [ ] **Step 2: Tinker verification**

Run:
```bash
php artisan tinker --execute="
use App\Models\PurchaseOrder;
\$po = PurchaseOrder::create(['fss_user_id'=>1,'po_number'=>'TT-'.time(),'order_date'=>now()->toDateString(),'status'=>'draft','total_amount'=>0]);
\$po->items()->create(['description'=>'x','qty'=>2,'unit'=>'pc','unit_price'=>5,'total_value'=>10]);
\$po->items()->create(['description'=>'y','qty'=>1,'unit'=>'pc','unit_price'=>3,'total_value'=>3]);
\$po->recalcTotal();
dump((float)\$po->fresh()->total_amount === 13.0);
\$po->delete();
"
```
Expected: `true`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/FSS/PurchaseOrderController.php
git commit -m "fix(fs): total_amount is always sum(line items)"
```

---

## Task 9: Inventory rows — remove the `unit_price` unit-mix; stored-cost valuation

**Files:**
- Modify: `app/Http/Controllers/FSS/InventoryController.php`, `app/Services/Reports/Generators/InventoryReportGenerator.php`

- [ ] **Step 1: Stop overlaying per-base `inv.unit_price` into the per-purchase column**

In `app/Http/Controllers/FSS/InventoryController.php`, in `unionFor()` change the `$catalogCols` line:

```php
        // BEFORE: COALESCE(inv.unit_price, f.purchase_price) AS purchase_price
        // AFTER:  f.purchase_price (catalog kept current by receiving; inv.unit_price is per-base and must not feed the per-purchase column)
        $catalogCols = "f.id AS item_id, f.name, f.category, f.kind AS item_type,
                        f.base_unit, f.purchase_unit, f.purchase_price, f.units_per_purchase,
                        inv.id AS inventory_id, inv.quantity_in_stock, inv.unit,
                        inv.minimum_stock_threshold,
                        NULL AS recipe_cost, NULL AS recipe_servings";
```

**Also remove `inv.expiry_date` from the SECOND union branch** (the recipe SELECT in `unionFor()` — the `food_service_recipes` block also lists `inv.expiry_date`), and from the empty-set guard `$union` fallback string, since the column no longer exists. Confirm none remain: `git grep -n expiry_date app/Http/Controllers/FSS/InventoryController.php` → no output. (`decorateRow()` also builds an `'expiry_date'` key in its returned array — delete that key too.)

- [ ] **Step 2: Value the Inventory report at stored last-cost**

In `app/Services/Reports/Generators/InventoryReportGenerator.php`, change the cost line:

```php
                // stored last-cost (₱/base) first; fall back to live catalog only for never-received items
                $cost = $inv->unit_price !== null ? (float) $inv->unit_price : ($inv->fsItem?->unit_cost ?? 0.0);
```

- [ ] **Step 3: Tinker verification — rows endpoint shape is intact**

Run:
```bash
php artisan tinker --execute="
\$c = new App\Http\Controllers\FSS\InventoryController();
\$req = Illuminate\Http\Request::create('/','GET',['type'=>'ingredient','per_page'=>3,'page'=>1]);
\$json = json_decode(\$c->rows(\$req)->getContent(), true);
dump(array_keys(\$json), \$json['data'][0] ?? 'no rows');
"
```
Expected: keys `['data','meta','stats']`; the first row has `unit_price`, `unit_cost`, `base_unit` and **no** `expiry_date`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FSS/InventoryController.php app/Services/Reports/Generators/InventoryReportGenerator.php
git commit -m "fix(fs): drop inventory unit-price unit-mix; value inventory report at last-cost"
```

---

## Task 10: Inventory manual entry — base-unit + upsert (finding #6)

**Files:**
- Modify: `app/Http/Controllers/FSS/InventoryController.php`

- [ ] **Step 1: Convert manual entries to base unit and upsert**

In `InventoryController::store()`, replace the body with a base-unit-normalizing upsert (prevents kg+g mixing and the unique-constraint 500 when a received row already exists):

```php
    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Force base-unit storage for catalog items (ingredient/supply).
        if (($data['item_type'] ?? null) !== 'recipe' && ! empty($data['fs_item_id'])) {
            $fs = FsItem::find($data['fs_item_id']);
            if ($fs) {
                [$qtyBase] = \App\Services\FSS\ReceivingService::normalizeLine(
                    (float) $data['quantity_in_stock'], (string) ($data['unit'] ?? ''), 0.0, (string) $fs->base_unit
                );
                $data['quantity_in_stock'] = $qtyBase;
                $data['unit'] = $fs->base_unit;
            }
        }

        $key = ! empty($data['fs_item_id'])
            ? ['fs_item_id' => $data['fs_item_id']]
            : ['recipe_id' => $data['recipe_id']];

        $inventory = Inventory::updateOrCreate($key, $data);
        Cache::flush();

        return response()->json(['data' => new InventoryResource($inventory->load(self::RELATIONS))], 201);
    }
```

(`FsItem` is already imported in this controller.)

- [ ] **Step 2: Tinker verification — kg entry stored as base grams, no duplicate row**

Run:
```bash
php artisan tinker --execute="
use App\Models\FsItem; use App\Models\Inventory; use App\Http\Controllers\FSS\InventoryController; use App\Http\Requests\FSS\StoreInventoryRequest;
\$fs = FsItem::where('base_unit','g')->where('purchase_unit','kg')->first() ?? FsItem::where('base_unit','g')->first();
\$req = StoreInventoryRequest::create('/','POST',['item_type'=>'ingredient','fs_item_id'=>\$fs->id,'quantity_in_stock'=>2,'unit'=>'kg']);
\$req->setContainer(app()); \$req->validateResolved();
\$c = new InventoryController(); \$resp = json_decode(\$c->store(\$req)->getContent(), true);
dump(['unit'=>\$resp['data']['unit'], 'qty'=>\$resp['data']['quantity_in_stock'], 'rows'=>Inventory::where('fs_item_id',\$fs->id)->count()]);
"
```
Expected: `unit => 'g'`, `qty => '2000.00'` (2 kg → 2000 g), `rows => 1` (upsert, not duplicate).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/FSS/InventoryController.php
git commit -m "fix(fs): manual inventory entries stored in base unit via upsert"
```

---

## Task 11: Recipe ingredient unit validation (finding #5)

**Files:**
- Modify: `app/Http/Controllers/FSS/FoodServiceRecipeController.php`
- Test: `tests/Unit/RecipeIngredientUnitTest.php`

- [ ] **Step 1: Write the failing test for a pure compatibility helper**

```php
<?php

namespace Tests\Unit;

use App\Http\Controllers\FSS\FoodServiceRecipeController;
use Tests\TestCase;

class RecipeIngredientUnitTest extends TestCase
{
    public function test_same_dimension_is_compatible(): void
    {
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('g', 'g'));
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('kg', 'g'));   // mass↔mass
        $this->assertTrue(FoodServiceRecipeController::unitCompatible('cup', 'mL')); // volume↔volume
    }

    public function test_count_vs_mass_is_incompatible(): void
    {
        // "2 eggs" (pc) against a gram base would silently cost 2 as 2 grams.
        $this->assertFalse(FoodServiceRecipeController::unitCompatible('pc', 'g'));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=RecipeIngredientUnitTest`
Expected: FAIL — `unitCompatible()` undefined.

- [ ] **Step 3: Add the helper and enforce it in store/update**

In `FoodServiceRecipeController`, add `use App\Support\UnitConverter;` and `use App\Models\FsItem;`, then add:

```php
    /**
     * An ingredient quantity can only be costed if its unit is the same dimension
     * as the item's base_unit (mass↔mass, volume↔volume) or exactly equal. Count
     * units (pc/pack) must match a count base exactly — never cross to mass/volume.
     */
    public static function unitCompatible(string $ingredientUnit, string $baseUnit): bool
    {
        $a = UnitConverter::normalize($ingredientUnit);
        $b = UnitConverter::normalize($baseUnit);
        if ($a === '' || $b === '' || $a === $b) {
            return true;
        }
        return UnitConverter::isKnown($a) && UnitConverter::isKnown($b);
    }
```

Then, in **both** `store()` and `update()`, after validation and before persisting ingredients, reject incompatible lines:

```php
        foreach ($data['ingredients'] ?? [] as $ing) {
            $base = FsItem::whereKey($ing['fs_item_id'])->value('base_unit');
            if ($base && ! self::unitCompatible($ing['unit'] ?? $base, $base)) {
                abort(422, "Ingredient unit '{$ing['unit']}' is not compatible with base unit '{$base}' for item #{$ing['fs_item_id']}.");
            }
        }
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=RecipeIngredientUnitTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FSS/FoodServiceRecipeController.php tests/Unit/RecipeIngredientUnitTest.php
git commit -m "feat(fs): validate recipe ingredient unit is dimensionally compatible"
```

---

## Task 12: Block deletes of referenced recipes; recipe recompute on catalog edit (finding #8 + §5.4)

**Files:**
- Modify: `app/Http/Controllers/FSS/FoodServiceRecipeController.php`, `app/Http/Controllers/FSS/FsItemController.php`

- [ ] **Step 1: Guard recipe deletion**

In `FoodServiceRecipeController::destroy()`, refuse when the recipe is still used by a menu cycle:

```php
    public function destroy(FoodServiceRecipe $foodServiceRecipe): JsonResponse
    {
        $usedBy = \App\Models\MenuCycleDay::where('recipe_id', $foodServiceRecipe->id)->count();
        if ($usedBy > 0) {
            abort(409, "Can't delete: this recipe is used by {$usedBy} menu-cycle slot(s). Remove it from the cycle(s) first.");
        }
        $foodServiceRecipe->delete();
        return response()->json(null, 204);
    }
```

- [ ] **Step 2: Recompute recipes when a catalog price changes**

In `FsItemController::update()`, broaden the validation to accept price/unit fields and recompute dependent recipes when they change:

```php
    public function update(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'category'           => ['nullable', 'string', 'max:100'],
            'purchase_price'     => ['sometimes', 'numeric', 'min:0'],
            'purchase_unit'      => ['sometimes', 'string', 'max:20'],
            'base_unit'          => ['sometimes', 'string', 'max:20'],
            'units_per_purchase' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $priceTouched = array_intersect(array_keys($data), ['purchase_price', 'purchase_unit', 'base_unit', 'units_per_purchase']) !== [];

        $fsItem->update($data);
        if ($priceTouched) {
            \App\Models\FoodServiceRecipe::recalculateForItems([$fsItem->id]);
        }
        Cache::flush();

        return response()->json(['data' => $fsItem->fresh()]);
    }
```

- [ ] **Step 3: Tinker verification**

Run:
```bash
php artisan tinker --execute="
use App\Models\FsItem; use App\Models\FoodServiceRecipe; use App\Models\FoodServiceRecipeIngredient;
\$ing = FoodServiceRecipeIngredient::first(); \$fs = FsItem::find(\$ing->fs_item_id);
\$before = FoodServiceRecipe::find(\$ing->food_service_recipe_id)->cost;
\$fs->update(['purchase_price'=> (float)\$fs->purchase_price + 1]);
FoodServiceRecipe::recalculateForItems([\$fs->id]);
\$after = FoodServiceRecipe::find(\$ing->food_service_recipe_id)->cost;
dump(['changed'=> \$before !== \$after, 'before'=>\$before, 'after'=>\$after]);
"
```
Expected: `changed => true` (cost moved with the price).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FSS/FoodServiceRecipeController.php app/Http/Controllers/FSS/FsItemController.php
git commit -m "feat(fs): block deletes of in-use recipes; recompute recipe cost on catalog price edit"
```

---

## Task 13: Budget — bucket actual by received_date; double-count warning (§5.6 / §5.8)

**Files:**
- Modify: `app/Http/Controllers/FSS/BudgetController.php`

- [ ] **Step 1: Bucket received-PO spend by `received_date`**

In `BudgetController::summary()`, change the `$poByDay` query to date by receipt (fallback to order date for legacy rows):

```php
        $poByDay = PurchaseOrder::where('status', 'received')
            ->whereBetween(DB::raw('COALESCE(received_date, order_date)'), [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(received_date, order_date) as d, SUM(total_amount) as t')
            ->groupBy('d')->pluck('t', 'd');
```

Add `use Illuminate\Support\Facades\DB;` if not already imported.

- [ ] **Step 2: Warn when a manual daily log overlaps received POs**

In `BudgetController::storeDailyLog()`, after creating `$dailyLog`, compute a soft warning and include it in the response:

```php
        $poCount = PurchaseOrder::where('status', 'received')
            ->whereRaw('COALESCE(received_date, order_date) = ?', [$data['log_date']])
            ->count();

        $warning = $poCount > 0
            ? "{$poCount} received purchase order(s) are already counted on {$data['log_date']}. Manual logs are for non-PO cash spends only."
            : null;
```

Then add `'warning' => $warning,` to the `'data' => [...]` array returned.

- [ ] **Step 3: Tinker verification**

Run:
```bash
php artisan tinker --execute="
use App\Models\PurchaseOrder;
\$d = optional(PurchaseOrder::where('status','received')->first())->received_date
     ?? optional(PurchaseOrder::where('status','received')->first())->order_date;
\$n = PurchaseOrder::where('status','received')->whereRaw('COALESCE(received_date, order_date) = ?', [\$d])->count();
dump(['date'=>(string)\$d, 'received_pos_on_date'=>\$n]);
"
```
Expected: a date and a count ≥ 0 (proves the coalesce query runs without SQL error).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/FSS/BudgetController.php
git commit -m "feat(fs): budget actual dated by receipt; warn on PO/manual-log double-count"
```

---

## Task 14: Purchase-price trend endpoint (§5.9)

**Files:**
- Modify: `app/Http/Controllers/FSS/FsItemController.php`, `routes/api.php`
- Test: `tests/Unit/PriceTrendShapeTest.php`

- [ ] **Step 1: Write a failing test for the pure summary shaper**

```php
<?php

namespace Tests\Unit;

use App\Http\Controllers\FSS\FsItemController;
use Tests\TestCase;

class PriceTrendShapeTest extends TestCase
{
    public function test_summary_computes_min_max_latest_avg(): void
    {
        $points = [
            ['date' => '2026-01-01', 'unit_price' => 10.0],
            ['date' => '2026-02-01', 'unit_price' => 20.0],
            ['date' => '2026-03-01', 'unit_price' => 30.0],
        ];
        $s = FsItemController::summarizeTrend($points);
        $this->assertSame(10.0, $s['min']);
        $this->assertSame(30.0, $s['max']);
        $this->assertSame(30.0, $s['latest']);
        $this->assertEqualsWithDelta(20.0, $s['avg'], 1e-6);
    }

    public function test_empty_series_is_zeroed(): void
    {
        $s = FsItemController::summarizeTrend([]);
        $this->assertSame(['min' => 0.0, 'max' => 0.0, 'latest' => 0.0, 'avg' => 0.0], $s);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=PriceTrendShapeTest`
Expected: FAIL — `summarizeTrend()` undefined.

- [ ] **Step 3: Add the pure shaper + the endpoint**

In `FsItemController`, add `use App\Models\PurchaseOrder;` and `use Illuminate\Http\Request;` (Request already imported), then:

```php
    /** @param array<int,array{date:string,unit_price:float}> $points */
    public static function summarizeTrend(array $points): array
    {
        if (! $points) {
            return ['min' => 0.0, 'max' => 0.0, 'latest' => 0.0, 'avg' => 0.0];
        }
        $last   = $points[array_key_last($points)];
        $prices = array_map(fn ($p) => (float) $p['unit_price'], $points);
        return [
            'min'    => min($prices),
            'max'    => max($prices),
            'latest' => (float) $last['unit_price'],
            'avg'    => round(array_sum($prices) / count($prices), 6),
        ];
    }

    /** Purchase-price trend for one catalog item, derived from frozen received-PO lines. */
    public function priceTrend(Request $request, FsItem $fsItem): JsonResponse
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);
        $start = $data['start'] ?? now()->subMonths(6)->toDateString();
        $end   = $data['end'] ?? now()->toDateString();

        $rows = PurchaseOrder::query()
            ->join('purchase_order_items', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.status', 'received')
            ->where('purchase_order_items.fs_item_id', $fsItem->id)
            ->whereRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) BETWEEN ? AND ?', [$start, $end])
            ->orderByRaw('COALESCE(purchase_orders.received_date, purchase_orders.order_date)')
            ->get([
                \DB::raw('COALESCE(purchase_orders.received_date, purchase_orders.order_date) as date'),
                'purchase_order_items.unit_price as unit_price',
            ])
            ->map(fn ($r) => ['date' => (string) $r->date, 'unit_price' => (float) $r->unit_price])
            ->all();

        return response()->json(['data' => ['points' => $rows] + self::summarizeTrend($rows)]);
    }
```

Fix the `latest` line to be valid PHP (use a local): replace the `'latest' => ...` line in `summarizeTrend` with:

```php
        $last = $points[array_key_last($points)];
        // ... then in the returned array:
        'latest' => (float) $last['unit_price'],
```

(Place `$last = $points[array_key_last($points)];` right after the empty-guard.)

Add `use Illuminate\Support\Facades\DB;` for the `\DB::raw` call (or reference `\Illuminate\Support\Facades\DB::raw`).

- [ ] **Step 4: Register the route**

In `routes/api.php`, under the `// FS Items (catalog) routes` comment, add above the existing `patch`:

```php
    Route::get('fs-items/{fsItem}/price-trend', [FsItemController::class, 'priceTrend']);
```

- [ ] **Step 5: Run the test + tinker-smoke the endpoint**

Run: `php artisan test --filter=PriceTrendShapeTest`
Expected: PASS.

Run:
```bash
php artisan tinker --execute="
\$fs = App\Models\FsItem::first();
\$c = new App\Http\Controllers\FSS\FsItemController();
\$req = Illuminate\Http\Request::create('/','GET',[]);
dump(json_decode(\$c->priceTrend(\$req, \$fs)->getContent(), true));
"
```
Expected: `data` with `points` (array, possibly empty) + `min/max/latest/avg` keys, no SQL error.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FSS/FsItemController.php routes/api.php tests/Unit/PriceTrendShapeTest.php
git commit -m "feat(fs): purchase-price trend endpoint from frozen PO history"
```

---

## Task 15: Seeder cleanup

**Files:**
- Delete: `database/seeders/InventorySeeder.php`, `database/seeders/InventoryDemoSeeder.php`
- Modify: `database/seeders/FoodServiceDemoSeeder.php`

- [ ] **Step 1: Delete the stale seeders**

```bash
git rm database/seeders/InventorySeeder.php database/seeders/InventoryDemoSeeder.php
```

(They use the dropped `food_item_id` / `item_type='food_item'` schema and are not referenced by `DatabaseSeeder`.)

- [ ] **Step 2: Remove `expiry_date` from the live demo seeder**

In `database/seeders/FoodServiceDemoSeeder.php::seedInventory()`:
- In the ingredient `$stock` rows, drop the expiry-offset element from each tuple and update the `foreach` destructuring from `[$name, $qty, $min, $exp, $notes]` to `[$name, $qty, $min, $notes]`, and delete the `'expiry_date' => ...` line in the ingredient `Inventory::create([...])`.
- The supply and recipe `Inventory::create` blocks do not set `expiry_date` — leave them.

(Concretely: each ingredient tuple like `['Rice', 80000, 20000, 60, null]` becomes `['Rice', 80000, 20000, null]`.)

- [ ] **Step 3: Verify the seeder runs clean**

Run: `php artisan db:seed --class=FoodServiceDemoSeeder`
Expected: `FoodServiceDemoSeeder: FS demo data seeded.` with no `Unknown column 'expiry_date'` error.

- [ ] **Step 4: Confirm no `expiry_date` left in seeders**

Run: `git grep -n expiry_date -- backend/database`
Expected: no output.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/
git commit -m "chore(fs): delete stale inventory seeders; drop expiry_date from FS demo seeder"
```

---

## Task 16: Frontend — remove `expiry_date`

**Files:**
- Modify: `frontend/services/inventoryService.ts`, `frontend/app/(rnd)/food-service/inventory/page.tsx`

> Read `frontend/AGENTS.md` first. These are type/field removals only — no Next.js APIs touched.

- [ ] **Step 1: Remove from the service types/payloads**

In `frontend/services/inventoryService.ts`, remove the `expiry_date` field from the `InventoryRow` type and from the `upsertInventory` payload type/body (search the file for `expiry_date`).

- [ ] **Step 2: Remove from the page**

In `frontend/app/(rnd)/food-service/inventory/page.tsx`, in `handleSave`, delete the line:
```ts
          expiry_date:             row.expiry_date ?? null,
```

- [ ] **Step 3: Verify no references remain + typecheck**

Run: `git grep -n expiry_date -- frontend` → expected: no output.
Run (from `frontend/`): `npx tsc --noEmit`
Expected: no type errors referencing `expiry_date`.

- [ ] **Step 4: Commit**

```bash
git add frontend/services/inventoryService.ts "frontend/app/(rnd)/food-service/inventory/page.tsx"
git commit -m "refactor(fs): remove expiry_date from inventory UI"
```

---

## Task 17: Full regression sweep

- [ ] **Step 1: Run the whole unit suite**

Run (from `backend/`): `php artisan test --testsuite=Unit`
Expected: PASS — includes new tests (`FsItemBasePerPurchaseTest`, `ReceivingServiceNormalizeTest`, `RecipeIngredientUnitTest`, `PriceTrendShapeTest`) and unchanged existing ones (`FsItemUnitCostTest`, `ProcurementServiceTest`, `MenuCycleCostServiceTest`, `BudgetServiceTest`, `UnitConverterTest`, report tests).

- [ ] **Step 2: Re-seed and smoke the whole loop in tinker**

Run: `php artisan db:seed --class=FoodServiceDemoSeeder`
Then:
```bash
php artisan tinker --execute="
use App\Models\PurchaseOrder; use App\Models\Inventory;
\$po = PurchaseOrder::with('items')->where('status','received')->first();
dump(['has_received_date'=> \$po->received_date !== null || \$po->order_date !== null,
      'inv_rows'=> Inventory::count(),
      'any_last_cost'=> Inventory::whereNotNull('unit_price')->exists()]);
"
```
Expected: counts > 0, no exceptions.

- [ ] **Step 3: Frontend typecheck**

Run (from `frontend/`): `npx tsc --noEmit`
Expected: clean (no new errors from these changes).

- [ ] **Step 4: Commit any final touch-ups**

```bash
git add -A
git commit -m "test(fs): Spec 1 regression sweep green" --allow-empty
```

---

## Self-Review notes (coverage map)

- §5.1 ReceivingService → Tasks 2, 7. §5.2 basePerPurchase → Task 1. §5.3 unit-mix removal → Task 9. §5.4 recipe freshness → Tasks 6, 12. §5.5 inventory valuation → Task 9. §5.6 received_date → Tasks 3, 5, 13. §5.7 total_amount → Tasks 5, 8. §5.8 double-count warning → Task 13. §5.9 price trend → Task 14. §5.10 unit integrity (#5/#6/#8/#11) → Tasks 4, 10, 11, 12 + expiry across 3/4/9/15/16. Seeder cleanup → Task 15.
- **Deferred to later specs (NOT in this plan):** menu-report snapshotting (#1), procurement net-of-stock/purchase-units (#2/#4), budget-from-consumption + `budget_daily_logs` schema merge (#7), supplies consumption (#10) — Spec 6; consumption deduction — Spec 2.
- **Error handling is explicit per task:** transactional receive (Task 7), free-text/missing-item skips logged (Task 7), divide-by-zero guards (Tasks 1, 7), recipe recompute isolation (Task 6), unit-compatibility 422 (Task 11), delete 409 guard (Task 12), upsert avoids unique-violation 500 (Task 10), soft budget warning (Task 13), empty-series guard (Task 14).
