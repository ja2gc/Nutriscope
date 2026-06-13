# Purchase-Unit Procurement (Spec 6 #4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Present and round procurement quantities in whole **purchase units** (kg/sacks/packs the vendor actually sells), while keeping base units (g/mL) as the internal source of truth for stock and cost.

**Architecture:** Add `purchase_qty` / `purchase_unit` / `purchase_price` columns to `shopping_list_items` and `purchase_order_items`. The shopping-list generator converts each net base-unit need into whole packs via `ceil(net_base / FsItem::basePerPurchase())` (Decision B: whole-pack overage is carried as leftover stock — it bakes into the base `qty` and gets consumed next cycle by the net-of-stock logic from #2). Base fields (`qty`/`unit`/`unit_price`) stay populated for stock math, so receiving and inventory are unchanged in principle. Vendor-facing surfaces (AIR/Statement PDF, procurement UI) print the purchase fields. Receiving prefers the purchase fields when present (exact `purchase_qty × basePerPurchase`), falling back to the existing base-unit normalisation for legacy/free-text lines.

**Tech Stack:** Laravel 11, Eloquent, PHPUnit (sqlite tests / MySQL dev), Carbon. Frontend: Next.js + TypeScript.

---

## Spec reference

`docs/superpowers/specs/2026-06-12-fs-procurement-accuracy-and-snapshots-design.md` §3.2 (#4), §4 (data model), §5-B (overage). **Decision B locked: overage carried as leftover stock** (no separate "buffer" line). Builds on #2 (net-of-stock, done) and Spec 1's `FsItem::basePerPurchase()`.

## Key invariant (consistency check used throughout)

For a line with packs `P`, `bpp = basePerPurchase()`, catalog `purchase_price = PP`:
- `purchase_qty = P`, `purchase_unit = fs.purchase_unit`, `purchase_price = PP`
- base `qty = P × bpp` (rounded base actually bought), `unit = fs.base_unit`
- base `unit_price = PP / bpp` (₱ per base unit = `fs.unit_cost`)
- `total = P × PP = qty × unit_price` ✓ (both axes agree)

Degrade path (`bpp ≤ 0`, missing fs_item, or free-text line): leave `purchase_*` NULL, keep base `qty = net`, `unit_price = avg` (current behaviour).

## File structure

| File | Responsibility | Action |
|------|----------------|--------|
| `database/migrations/2026_06_14_000003_add_purchase_units_to_procurement_items.php` | Add nullable `purchase_qty`/`purchase_unit`/`purchase_price` to both item tables | **Create** |
| `app/Models/ShoppingListItem.php` | fillable + casts for new cols | Modify |
| `app/Models/PurchaseOrderItem.php` | fillable + casts for new cols | Modify |
| `app/Http/Controllers/FSS/ShoppingListController.php` (`generate`) | Round to whole packs, populate purchase_* + rounded base qty | Modify |
| `app/Http/Controllers/FSS/PurchaseOrderController.php` (`generatePos`, `store`) | Carry purchase_* SL→PO and on manual create | Modify |
| `app/Http/Resources/ShoppingListResource.php` | Expose purchase_* on items | Modify |
| `app/Http/Resources/PurchaseOrderResource.php` | Expose purchase_* on items | Modify |
| `app/Services/FSS/ReceivingService.php` (`receive`) | Prefer purchase_qty × bpp when present | Modify |
| `app/Services/Reports/Generators/ProcurementPackGenerator.php` | AIR/Statement print purchase units | Modify |
| `frontend/services/procurementService.ts` | Add purchase_* to item types | Modify |
| `frontend/app/(rnd)/food-service/procurement/page.tsx` | Show Buy-qty + pack-unit columns | Modify |
| `tests/Feature/FoodServiceOpsTest.php` | rounding, receiving-from-purchase, pack-pass-through | Modify |

**Conventions:** work on `main`; commits authored by jared only (git config = `jared <jaredabriol2@gmail.com>`), **NO `Co-Authored-By`**, do not pass `--author`. Run one file: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php`. Full: `php artisan test` (baseline has 1 flaky pre-existing NCP `'piece'` failure in `RecipeControllerTest` — unrelated, ignore).

---

## Task 1: Migration + models for purchase columns

**Files:**
- Create: `backend/database/migrations/2026_06_14_000003_add_purchase_units_to_procurement_items.php`
- Modify: `backend/app/Models/ShoppingListItem.php`, `backend/app/Models/PurchaseOrderItem.php`

- [ ] **Step 1: Write the migration**

Create `backend/database/migrations/2026_06_14_000003_add_purchase_units_to_procurement_items.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec 6 #4: procurement is presented/rounded in whole PURCHASE units.
     * These columns are the vendor-facing view (what AIR/Statement print, what
     * the buyer orders). Base qty/unit/unit_price stay the source of truth for
     * stock + cost. Nullable + back-filled lazily for in-flight rows.
     */
    public function up(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('purchase_qty', 10, 2)->nullable()->after('unit');
                $t->string('purchase_unit')->nullable()->after('purchase_qty');
                $t->decimal('purchase_price', 10, 2)->nullable()->after('purchase_unit');
            });
        }
    }

    public function down(): void
    {
        foreach (['shopping_list_items', 'purchase_order_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['purchase_qty', 'purchase_unit', 'purchase_price']);
            });
        }
    }
};
```

- [ ] **Step 2: Add to ShoppingListItem**

In `backend/app/Models/ShoppingListItem.php`, set `$fillable` to:

```php
    protected $fillable = [
        'shopping_list_id', 'fs_item_id', 'ingredient_name',
        'qty', 'unit', 'supplier_id', 'unit_price', 'total',
        'purchase_qty', 'purchase_unit', 'purchase_price',
    ];
```

(If the model has no `$casts`, add one; otherwise add these keys.)

```php
    protected $casts = [
        'qty'            => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'total'          => 'decimal:2',
        'purchase_qty'   => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];
```

- [ ] **Step 3: Add to PurchaseOrderItem**

In `backend/app/Models/PurchaseOrderItem.php`, append the three to `$fillable`:

```php
    protected $fillable = [
        'purchase_order_id', 'fs_item_id', 'description',
        'qty', 'unit', 'unit_price', 'total_value',
        'purchase_qty', 'purchase_unit', 'purchase_price',
    ];
```

and add to `$casts`:

```php
        'purchase_qty'   => 'decimal:2',
        'purchase_price' => 'decimal:2',
```

- [ ] **Step 4: Migrate (sqlite test DB will pick it up via RefreshDatabase) — sanity**

Run: `php artisan migrate --force`
Expected: `2026_06_14_000003_add_purchase_units_to_procurement_items ... DONE`.

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_06_14_000003_add_purchase_units_to_procurement_items.php backend/app/Models/ShoppingListItem.php backend/app/Models/PurchaseOrderItem.php
git commit -m "feat(fs): add purchase_qty/unit/price columns to procurement items"
```

---

## Task 2: Shopping-list generator rounds to whole packs

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/ShoppingListController.php` (the `foreach ($acc as $id => $row)` loop inside `generate`)
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/FoodServiceOpsTest.php`. It needs a menu cycle whose planned usage of one fs_item exceeds one pack, and a `basePerPurchase` of 1000 (kg→g). Use the existing helpers; adapt fs_item attrs to your factory (verify `FsItemFactory` fields with `grep -n "definition" -A20 database/factories/FsItemFactory.php`).

```php
public function test_generate_rounds_to_whole_purchase_units(): void
{
    // 1 kg sack = 1000 g base; planned need 1300 g → must buy 2 sacks (2000 g).
    $fs = FsItem::factory()->create([
        'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg',
        'purchase_price' => 50, 'units_per_purchase' => null,
    ]);
    Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 0, 'unit' => 'g']);

    $cycle = MenuCycle::factory()->create(['population' => 1]);
    $recipe = FoodServiceRecipe::factory()->create(['serving_unit' => 'g', 'serving_size' => 1300]);
    FoodServiceRecipeIngredient::factory()->create([
        'food_service_recipe_id' => $recipe->id, 'fs_item_id' => $fs->id,
        'quantity' => 1300, 'unit' => 'g',
    ]);
    MenuCycleDay::factory()->create([
        'menu_cycle_id' => $cycle->id, 'day_of_week' => 'Monday',
        'meal_type' => 'lunch', 'food_service_recipe_id' => $recipe->id, 'servings' => 1,
    ]);

    $response = $this->actingAs($this->fss)->postJson('/api/fss/shopping-lists/generate', [
        'menu_cycle_id' => $cycle->id,
        'start_date' => '2026-06-08', 'end_date' => '2026-06-08', // a Monday
    ]);

    $response->assertCreated();
    $item = collect($response->json('data.items'))->firstWhere('fs_item_id', $fs->id);
    $this->assertNotNull($item, 'Rice line should be present');
    $this->assertEqualsWithDelta(2,    (float) $item['purchase_qty'], 0.01); // ceil(1300/1000)
    $this->assertSame('kg', $item['purchase_unit']);
    $this->assertEqualsWithDelta(50,   (float) $item['purchase_price'], 0.01);
    $this->assertEqualsWithDelta(2000, (float) $item['qty'], 0.01);          // 2 sacks × 1000 g base
    $this->assertEqualsWithDelta(100,  (float) $item['total'], 0.01);        // 2 × ₱50
}
```

> Adjust recipe/ingredient/day factory field names to match this repo's factories before running (check `FoodServiceRecipeFactory`, `FoodServiceRecipeIngredientFactory`, `MenuCycleDayFactory`). If `MenuCycleCostService::usageForDays` keys differ, the route still returns items keyed by `fs_item_id` — assert on that.

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_generate_rounds_to_whole_purchase_units`
Expected: FAIL — `purchase_qty` is null/absent and `qty` is 1300 (un-rounded).

- [ ] **Step 3: Implement rounding in `generate`**

In `backend/app/Http/Controllers/FSS/ShoppingListController.php`, replace the body of the `foreach ($acc as $id => $row)` loop with:

```php
            foreach ($acc as $id => $row) {
                $covered = (float) ($onHand[$id] ?? 0) + (float) ($inTransit[$id] ?? 0);
                $net     = max(0.0, (float) $row['qty'] - $covered);
                if ($net <= 0) {
                    continue; // fully covered by stock on hand + open orders → nothing to buy
                }

                $fs  = $fsItems[$id] ?? null;
                $bpp = $fs ? $fs->basePerPurchase() : 0.0;

                if ($fs && $bpp > 0) {
                    // Round UP to whole purchase units; overage is carried as leftover
                    // stock (Decision B) and netted out next cycle by #2.
                    $packs        = (int) ceil($net / $bpp);
                    $baseBought   = $packs * $bpp;
                    $purchasePrice = (float) $fs->purchase_price;
                    $unitPrice    = $bpp > 0 ? round($purchasePrice / $bpp, 4) : 0.0; // ₱/base
                    $line = [
                        'fs_item_id'      => $id,
                        'ingredient_name' => $row['name'],
                        'qty'             => round($baseBought, 2),
                        'unit'            => $fs->base_unit ?? $row['unit'],
                        'supplier_id'     => $fs->default_supplier_id,
                        'unit_price'      => $unitPrice,
                        'total'           => round($packs * $purchasePrice, 2),
                        'purchase_qty'    => $packs,
                        'purchase_unit'   => $fs->purchase_unit,
                        'purchase_price'  => round($purchasePrice, 2),
                    ];
                } else {
                    // Degrade: no valid pack conversion — keep base-unit netting.
                    $unitPrice = $row['qty'] > 0 ? round($row['total'] / $row['qty'], 4) : 0;
                    $line = [
                        'fs_item_id'      => $id,
                        'ingredient_name' => $row['name'],
                        'qty'             => round($net, 2),
                        'unit'            => $row['unit'],
                        'supplier_id'     => $fs?->default_supplier_id,
                        'unit_price'      => $unitPrice,
                        'total'           => round($net * $unitPrice, 2),
                        'purchase_qty'    => null,
                        'purchase_unit'   => null,
                        'purchase_price'  => null,
                    ];
                }

                $list->items()->create($line);
            }
```

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_generate_rounds_to_whole_purchase_units`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/FSS/ShoppingListController.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): shopping-list generate rounds to whole purchase units (Spec 6 #4)"
```

---

## Task 3: Expose purchase fields in resources + carry SL→PO

**Files:**
- Modify: `backend/app/Http/Resources/ShoppingListResource.php`
- Modify: `backend/app/Http/Resources/PurchaseOrderResource.php`
- Modify: `backend/app/Http/Controllers/FSS/PurchaseOrderController.php` (`generatePos`, `store`)
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Add to ShoppingListResource item map**

In `ShoppingListResource.php`, inside the item map add after `'total'`:

```php
                'total'           => $item->total,
                'purchase_qty'    => $item->purchase_qty,
                'purchase_unit'   => $item->purchase_unit,
                'purchase_price'  => $item->purchase_price,
```

- [ ] **Step 2: Add to PurchaseOrderResource item map**

In `PurchaseOrderResource.php`, inside the items map add after `'total_value'`:

```php
                'total_value' => $i->total_value,
                'purchase_qty'    => $i->purchase_qty,
                'purchase_unit'   => $i->purchase_unit,
                'purchase_price'  => $i->purchase_price,
```

- [ ] **Step 3: Carry purchase_* in `generatePos`**

In `PurchaseOrderController::generatePos`, extend the `$po->items()->create([...])` call:

```php
                    $po->items()->create([
                        'fs_item_id'  => $it->fs_item_id,
                        'description' => $it->ingredient_name,
                        'qty'         => $it->qty,
                        'unit'        => $it->unit,
                        'unit_price'  => $it->unit_price,
                        'total_value' => $it->total,
                        'purchase_qty'   => $it->purchase_qty,
                        'purchase_unit'  => $it->purchase_unit,
                        'purchase_price' => $it->purchase_price,
                    ]);
```

- [ ] **Step 4: Carry purchase_* in manual `store`**

In `PurchaseOrderController::store`, extend the item create with optional pass-through:

```php
                $po->items()->create([
                    'fs_item_id'  => $item['fs_item_id'] ?? null,
                    'description' => $item['description'] ?? 'Item',
                    'qty'         => $item['qty'],
                    'unit'        => $item['unit'] ?? 'unit',
                    'unit_price'  => $item['unit_price'],
                    'total_value' => $item['qty'] * $item['unit_price'],
                    'purchase_qty'   => $item['purchase_qty'] ?? null,
                    'purchase_unit'  => $item['purchase_unit'] ?? null,
                    'purchase_price' => $item['purchase_price'] ?? null,
                ]);
```

> If `StorePurchaseOrderRequest` validates `items.*` keys with a strict allow-list, add `items.*.purchase_qty` (nullable numeric), `items.*.purchase_unit` (nullable string), `items.*.purchase_price` (nullable numeric). Check with `grep -n "items" app/Http/Requests/FSS/StorePurchaseOrderRequest.php`.

- [ ] **Step 5: Write the SL→PO pass-through test**

```php
public function test_generate_pos_carries_purchase_units(): void
{
    $supplier = Supplier::factory()->create();
    $list = ShoppingList::create([
        'fss_user_id' => $this->fss->id, 'name' => 'L', 'list_date' => '2026-06-08',
        'list_type' => 'suggested', 'status' => 'draft',
    ]);
    $list->items()->create([
        'ingredient_name' => 'Rice', 'qty' => 2000, 'unit' => 'g', 'supplier_id' => $supplier->id,
        'unit_price' => 0.05, 'total' => 100, 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
    ]);

    $response = $this->actingAs($this->fss)->postJson("/api/fss/shopping-lists/{$list->id}/generate-pos");
    $response->assertCreated();

    $this->assertDatabaseHas('purchase_order_items', [
        'description' => 'Rice', 'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
    ]);
}
```

> Confirm the generate-pos route path with `grep -n "generate-pos\|generatePos" routes/api.php`.

- [ ] **Step 6: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_generate_pos_carries_purchase_units`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Resources/ShoppingListResource.php backend/app/Http/Resources/PurchaseOrderResource.php backend/app/Http/Controllers/FSS/PurchaseOrderController.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): expose + carry purchase units shopping-list → purchase order"
```

---

## Task 4: Receiving prefers purchase fields

**Files:**
- Modify: `backend/app/Services/FSS/ReceivingService.php` (`receive`)
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_receiving_uses_purchase_qty_times_base_per_purchase(): void
{
    $fs = FsItem::factory()->create([
        'name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50,
    ]);
    Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 0, 'unit' => 'g']);

    $po = PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'status' => 'draft']);
    $po->items()->create([
        'fs_item_id' => $fs->id, 'description' => 'Rice',
        'qty' => 2000, 'unit' => 'g', 'unit_price' => 0.05, 'total_value' => 100,
        'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
    ]);

    $this->actingAs($this->fss)->patchJson("/api/fss/purchase-orders/{$po->id}", ['status' => 'received'])
        ->assertOk();

    // 2 kg × 1000 g/kg = 2000 g added; last-cost ₱50/1000 = ₱0.05/g (stored rounded to 2dp → 0.05).
    $this->assertDatabaseHas('inventory', ['fs_item_id' => $fs->id, 'quantity_in_stock' => 2000]);
}
```

> Verify the inventory table name (`inventory` vs `inventories`) and the qty column with `grep -n "Schema::create" -A2 database/migrations/*inventor*`. Adjust `assertDatabaseHas` table accordingly.

- [ ] **Step 2: Run it — expect pass or fail**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_receiving_uses_purchase_qty_times_base_per_purchase`
Note: with base `qty=2000, unit='g'` the EXISTING `normalizeLine` already yields 2000 g, so this may PASS before the change. That's fine — it pins behaviour. Proceed to make receiving explicitly prefer purchase fields (more robust when base `qty`/`unit` drift), then re-run.

- [ ] **Step 3: Make `receive` prefer purchase fields**

In `ReceivingService::receive`, replace the `[$qtyBase, $perBaseCost] = self::normalizeLine(...)` line with:

```php
            $bpp = $fs->basePerPurchase();
            if ($item->purchase_qty !== null && $bpp > 0) {
                // Vendor-unit line (Spec 6 #4): exact base = packs × base-per-pack.
                $qtyBase     = (float) $item->purchase_qty * $bpp;
                $perBaseCost = $bpp > 0 ? (float) $item->purchase_price / $bpp : 0.0;
            } else {
                // Legacy / free-text line: normalise the base-unit fields.
                [$qtyBase, $perBaseCost] = self::normalizeLine(
                    (float) $item->qty, (string) $item->unit, (float) $item->unit_price, (string) $fs->base_unit
                );
            }
```

Then a few lines down, the existing `$basePerPurchase = $fs->basePerPurchase();` is now redundant — reuse `$bpp`:

```php
            if ($bpp > 0) {
                $fs->purchase_price = round($perBaseCost * $bpp, 2);
                $fs->save();
            }
```

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_receiving_uses_purchase_qty_times_base_per_purchase`
Expected: PASS.

- [ ] **Step 5: Run the existing receiving tests — no regression**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php`
Expected: all green (the legacy receiving tests use base-unit lines with `purchase_qty=null` → fall through to `normalizeLine`).

- [ ] **Step 6: Commit**

```bash
git add backend/app/Services/FSS/ReceivingService.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): receiving reads purchase_qty × base-per-pack when present"
```

---

## Task 5: AIR / Statement print purchase units

**Files:**
- Modify: `backend/app/Services/Reports/Generators/ProcurementPackGenerator.php` (`buildPack`)
- Test: `backend/tests/Feature/FoodServiceOpsTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_procurement_pack_prints_purchase_units(): void
{
    $fs = FsItem::factory()->create(['name' => 'Rice', 'base_unit' => 'g', 'purchase_unit' => 'kg', 'purchase_price' => 50]);
    $po = PurchaseOrder::factory()->create(['fss_user_id' => $this->fss->id, 'status' => 'received', 'order_date' => '2026-06-08']);
    $po->items()->create([
        'fs_item_id' => $fs->id, 'description' => 'Rice',
        'qty' => 2000, 'unit' => 'g', 'unit_price' => 0.05, 'total_value' => 100,
        'purchase_qty' => 2, 'purchase_unit' => 'kg', 'purchase_price' => 50,
    ]);

    $report = new \App\Models\Report(['type' => 'procurement_pack', 'parameters' => ['purchase_order_id' => $po->id]]);
    $data = (new \App\Services\Reports\Generators\ProcurementPackGenerator())->data($report);

    $pack = $data['packs'][0];
    $this->assertEqualsWithDelta(2, (float) $pack['air_items'][0]['quantity'], 0.01); // packs, not 2000 g
    $this->assertSame('kg', $pack['air_items'][0]['unit']);
    $this->assertEqualsWithDelta(50, (float) $pack['statement_items'][0]['unit_price'], 0.01); // ₱/pack
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_procurement_pack_prints_purchase_units`
Expected: FAIL — currently prints base `qty` (2000) and base `unit_price` (0.05).

- [ ] **Step 3: Print purchase units in `buildPack`**

In `ProcurementPackGenerator::buildPack`, change the two maps to prefer purchase fields:

```php
        $airItems = $po->items->values()->map(fn ($it, $i) => [
            'item_no'     => $i + 1,
            'unit'        => $it->purchase_unit ?? $it->unit,
            'description' => $it->description ?? $it->fsItem?->name,
            'quantity'    => $it->purchase_qty ?? $it->qty,
        ])->all();

        $statementItems = $po->items->map(fn ($it) => [
            'qty'         => $it->purchase_qty ?? $it->qty,
            'unit'        => $it->purchase_unit ?? $it->unit,
            'item'        => $it->description ?? $it->fsItem?->name,
            'unit_price'  => $it->purchase_price ?? $it->unit_price,
            'total_value' => $it->total_value,
        ])->all();
```

(`total_value` is unchanged — `purchase_qty × purchase_price == qty × unit_price` by the invariant, so the grand total is identical.)

- [ ] **Step 4: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/FoodServiceOpsTest.php --filter test_procurement_pack_prints_purchase_units`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Services/Reports/Generators/ProcurementPackGenerator.php backend/tests/Feature/FoodServiceOpsTest.php
git commit -m "feat(fs): AIR/Statement print whole purchase units, not base grams"
```

---

## Task 6: Frontend — show purchase units

**Files:**
- Modify: `frontend/services/procurementService.ts`
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`

- [ ] **Step 1: Extend the item types**

In `frontend/services/procurementService.ts`, add to `ShoppingListItem`:

```ts
  total: string | null;
  purchase_qty: string | null;
  purchase_unit: string | null;
  purchase_price: string | null;
```

and to `POItem`:

```ts
export interface POItem { id: number; fs_item_id: number | null; description: string; qty: string; unit: string; unit_price: string; total_value: string; purchase_qty: string | null; purchase_unit: string | null; purchase_price: string | null }
```

- [ ] **Step 2: Show a "Buy" column on the shopping-list table**

In `procurement/page.tsx`, in the shopping-list items table, add a cell after the `ingredient_name` cell (line ~95) that shows the purchase quantity when present:

```tsx
                <td className="px-3 py-2 font-semibold text-zinc-800">{it.ingredient_name}</td>
                <td className="px-3 py-2 text-zinc-600">
                  {it.purchase_qty ? `${num(it.purchase_qty)} ${it.purchase_unit ?? ""}` : <span className="text-zinc-300">—</span>}
                </td>
```

Add a matching `<th>Buy</th>` header to that table's header row (find the `<thead>` for this table and insert after the item/name header).

- [ ] **Step 3: Show "Buy" on the PO items table**

In the PO items table, after the `description` cell (line ~190) add:

```tsx
                <td className="px-3 py-2 font-semibold text-zinc-800">{i.description}</td>
                <td className="px-3 py-2 text-zinc-600">
                  {i.purchase_qty ? `${num(i.purchase_qty)} ${i.purchase_unit ?? ""}` : <span className="text-zinc-300">—</span>}
                </td>
```

Add a matching `<th>Buy</th>` to that table's header. Optionally relabel the existing base `qty`/`unit` columns' header to "Base qty" / "Base unit" for clarity (small text edit).

- [ ] **Step 4: Typecheck**

Run: `cd ../frontend && npx tsc --noEmit 2>&1 | grep -iE "procurement"`
Expected: no errors in the procurement files.

- [ ] **Step 5: Commit**

```bash
git add frontend/services/procurementService.ts "frontend/app/(rnd)/food-service/procurement/page.tsx"
git commit -m "feat(fs): procurement UI shows whole purchase units (Buy column)"
```

---

## Task 7: Full-suite regression + browser verification

- [ ] **Step 1: Full backend suite**

Run: `php artisan test`
Expected: all green except the 1 known flaky pre-existing NCP `'piece'` failure in `RecipeControllerTest`. No new failures.

- [ ] **Step 2: Re-seed + browser-verify (recommended)**

`php artisan migrate --force` (applies 000003), `php artisan db:seed --class=FoodServiceDemoSeeder --force`, ensure backend (`php artisan serve` :8000) + frontend preview (:3000). Log in `rnd@nutriscope.local / nutriscope2024!`, open Food Service → Procurement. Generate a list from a cycle; confirm the **Buy** column shows whole packs (e.g. "2 kg") and the base qty still shows grams. Generate POs; confirm the PO items carry the Buy column. Snapshot/preview_eval for proof.

- [ ] **Step 3: Update the spec status line**

In `docs/superpowers/specs/2026-06-12-fs-procurement-accuracy-and-snapshots-design.md` §3.2, prepend `— ✅ IMPLEMENTED <date>` and note Decision B resolved. Commit:

```bash
git add docs/superpowers/specs/2026-06-12-fs-procurement-accuracy-and-snapshots-design.md
git commit -m "docs(fs): mark Spec 6 #4 purchase-unit procurement implemented"
```

---

## Self-review notes (author)

- **Spec coverage:** §3.2 bullet 1 (ceil to whole packs) → Task 2. bullet 2 (both purchase + base on lines; receiving reads purchase_qty × bpp) → Tasks 1,3,4. bullet 3 (overage = leftover, netted by #2) → Decision B baked into base `qty` (Task 2). §4 data model → Task 1. AIR/Statement print purchase units → Task 5. ✓
- **Decision B** locked = leftover stock; no buffer line. ✓
- **Invariant** `purchase_qty × purchase_price == qty × unit_price` holds in Task 2 construction → `total_value` unchanged in Task 5, so grand totals and the budget cash_flow are unaffected. ✓
- **No placeholders:** every code step shows full code. The factory-field caveats (Task 2 step 1, Task 4 step 1) are real verification asks, not vague TODOs.
- **Risk:** degrade path (`bpp ≤ 0`) keeps the exact current behaviour, so misconfigured items don't regress. Legacy PO lines (`purchase_qty=null`) keep flowing through `normalizeLine` in receiving — no migration of in-flight POs needed (§6.2).
