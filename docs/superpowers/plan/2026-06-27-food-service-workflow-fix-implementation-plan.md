# Food Service Workflow Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Food Service procurement follow the approved plan exactly: inventory is a catalog, food shopping quantities scale only from list-level estimated population, supplies lists are separate, POs freeze structure, and seeders use the same workflow.

**Architecture:** Keep `fs_items` as the reference catalog and `shopping_lists.procurement_track` as the food/supplies split. Food lists are system-generated from menu recipes/items and become scalable only after `shopping_lists.estimate_population` is set. Supplies lists are manual, have no date span or headcount, and convert into supplies POs independently.

**Tech Stack:** Laravel 13.11, PHP 8.4, MySQL, PHPUnit 12, Next.js React/TypeScript, Tailwind UI.

---

## Approved Data/Input Whitelist

### Inventory catalog (`fs_items`)
- `name`
- `kind`: `ingredient`, `supply`
- `category`
- `default_supplier_id`
- `default_supplier_locked_at`
- `default_supplier_locked_by`
- `base_unit`
- `purchase_unit` for ingredients only
- `purchase_price`
- `units_per_purchase` for ingredients only
- `is_active` backend only

Single items belong to `kind = ingredient`. Do not expose `ready_to_eat` in Food Service catalog inputs, filters, badges, tabs, or seed data.

Do not show or accept user inputs for `quantity_in_stock`, low-stock thresholds, expiry dates, usage rate, received date, stock status, or restock quantity in catalog create/edit UI.

### Food shopping list (`shopping_lists.procurement_track = food`)
- Header inputs: `period_start`, `period_end`, `estimate_population`
- Calculated header values: `days_span`, `estimated_total`, `estimated_budget_per_head_per_day`
- Item fields: `fs_item_id`, `ingredient_name`, `baseline_servings`, `baseline_quantity`, `scaled_quantity`, `scaled_unit`, `qty`, `unit`, `supplier_id`, `unit_price`, `total`, `purchase_qty`, `purchase_unit`, `purchase_price`, `vendor_locked_at`, `vendor_locked_by`

User can edit `supplier_id`, `unit_price`, and vendor lock before conversion. User cannot edit generated ingredient quantity or unit. `estimate_population` is the only scaling input.

### Supplies list (`shopping_lists.procurement_track = supplies`)
- Header inputs: `name`, `list_date`
- Item inputs: `fs_item_id` for supply items only, `qty`, `supplier_id`, `unit_price`
- Calculated item values: `ingredient_name`, `unit`, `total`

Do not show or accept date span, menu cycle, estimated population, or per-head/day values on supplies lists.

### Purchase orders
- PO fields: `procurement_track`, `shopping_list_id`, `po_number`, `order_date`, `total_amount`, `status`, `lifecycle_status`, `converted_at`, `completed_at`, `structural_locked_at`, `final_locked_at`
- Vendor group fields: `supplier_id`, `or_number`, `status`, `total_amount`, `received_at`
- PO item fields: `fs_item_id`, `description`, `qty`, `unit`, `unit_price`, `total_value`, `purchase_qty`, `purchase_unit`, `purchase_price`
- Attachment fields: `type`, `path`, `caption`
- Correction fields: `purchase_order_item_id`, `old_unit_price`, `new_unit_price`, `old_purchase_price`, `new_purchase_price`, `corrected_by`, `corrected_at`, `reason`

Only `unit_price` and `purchase_price` are editable during open execution, and every edit writes `purchase_order_item_corrections`.

---

## File Structure

- `backend/app/Services/FSS/ShoppingListPopulationService.php`: source of truth for food-list generation and list-level population scaling.
- `backend/app/Http/Controllers/FSS/ShoppingListController.php`: HTTP workflow for food generation, supplies creation, estimate update, list item add/edit/delete.
- `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php`: store validation, including supplies-track prohibition rules.
- `backend/app/Http/Requests/FSS/UpdateShoppingListRequest.php`: update validation and blocking structural edits after conversion.
- `backend/app/Http/Resources/ShoppingListResource.php`: calculated totals returned to UI.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php`: conversion into one PO grouped by vendor, with food/supplies track copied.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`: food vs supplies completion and snapshots.
- `backend/database/seeders/FoodServiceDemoSeeder.php`: demo data must call real list/PO flow.
- `backend/tests/Feature/FoodShoppingListGenerationTest.php`: list generation and estimate scaling tests.
- `backend/tests/Feature/FoodServiceOpsTest.php`: supplies-track and PO lifecycle feature tests.
- `frontend/services/procurementService.ts`: TypeScript API types and payloads for tracks.
- `frontend/app/(rnd)/food-service/procurement/page.tsx`: food/supplies tabs, live estimate save, read-only generated quantities.
- `frontend/services/fsCatalogService.ts`: catalog create/edit payloads.
- `frontend/app/(rnd)/food-service/inventory/page.tsx`: catalog UI; keep only approved catalog fields.

---

### Task 1: Backend Test For Food List-Level Population Scaling

**Files:**
- Modify: `backend/tests/Feature/FoodShoppingListGenerationTest.php`
- Test: `backend/tests/Feature/FoodShoppingListGenerationTest.php`

- [ ] **Step 1: Write failing test**

Add this method to `FoodShoppingListGenerationTest`:

```php
public function test_list_level_estimate_population_scales_generated_quantities_without_menu_day_estimate(): void
{
    $fsItem = FsItem::factory()->create([
        'name' => 'Yakult',
        'base_unit' => 'piece',
        'purchase_unit' => 'pack',
        'purchase_price' => 100,
        'units_per_purchase' => 10,
    ]);
    $cycle = MenuCycle::factory()->create([
        'rnd_user_id' => $this->rnd->id,
        'week_start_date' => '2026-06-15',
        'cycle_days' => 7,
    ]);
    $day = MenuCycleDay::factory()->create([
        'menu_cycle_id' => $cycle->id,
        'day_of_week' => 'Monday',
        'fs_item_id' => $fsItem->id,
        'quantity' => 2,
        'estimate_population' => null,
    ]);

    $response = $this->actingAs($this->rnd)
        ->postJson('/api/fss/shopping-lists/generate', [
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
        ])
        ->assertCreated()
        ->assertJsonPath('data.estimate_population', null);

    $listId = $response->json('data.id');

    $this->actingAs($this->rnd)
        ->patchJson("/api/fss/shopping-lists/{$listId}", ['estimate_population' => 25])
        ->assertOk()
        ->assertJsonPath('data.estimate_population', 25)
        ->assertJsonPath('data.estimated_budget_per_head_per_day', 20);

    $this->assertDatabaseHas('shopping_list_items', [
        'shopping_list_id' => $listId,
        'fs_item_id' => $fsItem->id,
        'qty' => 50,
        'purchase_qty' => 5,
        'total' => 500,
    ]);

    $this->assertDatabaseHas('menu_cycle_days', [
        'id' => $day->id,
        'estimate_population' => null,
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodShoppingListGenerationTest.php --filter=list_level_estimate_population
```

Expected: FAIL because generation still blocks when `menu_cycle_days.estimate_population` is missing or item quantities do not recalculate from `shopping_lists.estimate_population`.

- [ ] **Step 3: Implement minimal backend scaling**

Modify `backend/app/Services/FSS/ShoppingListPopulationService.php`.

Change `planRange()` signature:

```php
public function planRange(Carbon|string $startDate, Carbon|string $endDate, ?int $population = null): array
```

Remove the `missingPopulation` checks from `planRange()`:

```php
// Delete this block from planRange().
if ($plannedDays->contains(fn ($day) => (int) ($day->estimate_population ?? 0) <= 0)) {
    $missingPopulation[] = $date;
    $missingByDate[$date] = 'estimated population not set for this day';
    continue;
}
```

Call usage with the list-level fallback:

```php
foreach (MenuCycleCostService::usageForDays($plannedDays, $population) as $usage) {
    $id = $usage['fs_item_id'];
    $acc[$id] ??= [
        'name' => $usage['name'],
        'unit' => $usage['unit'],
        'qty' => 0.0,
        'total' => 0.0,
    ];
    $acc[$id]['qty'] += (float) $usage['quantity'];
    $acc[$id]['total'] += (float) $usage['cost'];
}
```

Return no missing population dates:

```php
return [
    'items' => $this->purchaseRows($acc),
    'uncovered_dates' => array_values(array_unique($uncovered)),
    'missing_population_dates' => [],
    'missing_items_by_date' => $missingByDate,
];
```

Update `cascadePopulation()` so it updates the list and item rows only, not `menu_cycle_days.estimate_population`:

```php
public function cascadePopulation(ShoppingList $list, int $population): void
{
    if (! $list->period_start || ! $list->period_end || $list->isSupplies()) {
        return;
    }

    DB::transaction(function () use ($list, $population) {
        $list->update([
            'estimate_population' => $population,
            'estimate_population_updated_at' => now(),
        ]);

        $this->syncItems($list->fresh());
    });
}
```

Update `syncItems()`:

```php
$plan = $this->planRange($list->period_start, $list->period_end, (int) ($list->estimate_population ?? 0));
```

Remove this exception from `syncItems()`:

```php
if ($plan['missing_population_dates'] !== []) {
    throw ValidationException::withMessages([
        'estimate_population' => 'Menu plan dates with assigned items require estimate_population before recalculation.',
    ]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodShoppingListGenerationTest.php --filter=list_level_estimate_population
```

Expected: PASS.

---

### Task 2: Backend Generate Food List Without Partial Lists

**Files:**
- Modify: `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- Modify: `backend/tests/Feature/FoodShoppingListGenerationTest.php`

- [ ] **Step 1: Keep failing/coverage tests**

Existing tests must remain:

```php
public function test_missing_dates_block_creation_and_report_per_date_reasons(): void
public function test_fully_covered_span_creates_food_track_list(): void
```

Update `test_missing_dates_block_creation_and_report_per_date_reasons()` so only missing cycle/menu items block generation, not missing population.

- [ ] **Step 2: Implement controller generate logic**

In `backend/app/Http/Controllers/FSS/ShoppingListController.php`, update `generate()`:

```php
$plan = app(ShoppingListPopulationService::class)->planRange($cursor, $end);
$missingDates = $plan['uncovered_dates'];

if ($missingDates !== []) {
    sort($missingDates);
    return response()->json([
        'message' => 'Shopping list blocked - every date in the span must have a menu cycle and menu items.',
        'missing_dates' => $missingDates,
        'missing_items_by_date' => $plan['missing_items_by_date'],
    ], 422);
}
```

Do not include `missing_population_dates` in generation blocking.

- [ ] **Step 3: Run generation tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodShoppingListGenerationTest.php
```

Expected: all tests in file PASS.

---

### Task 3: Backend Supplies Track

**Files:**
- Modify: `backend/tests/Feature/FoodServiceOpsTest.php`
- Modify: `backend/app/Http/Requests/FSS/StoreShoppingListRequest.php`
- Modify: `backend/app/Http/Requests/FSS/UpdateShoppingListRequest.php`
- Modify: `backend/app/Http/Controllers/FSS/ShoppingListController.php`
- Modify: `backend/app/Services/FSS/PurchaseOrderLifecycleService.php`

- [ ] **Step 1: Add failing supplies-track test**

Add this method to `FoodServiceOpsTest`:

```php
public function test_supplies_list_is_manual_supply_only_and_converts_to_supplies_po(): void
{
    $supplier = Supplier::factory()->create();
    $supply = FsItem::factory()->create([
        'kind' => 'supply',
        'name' => 'Paper meal box',
        'base_unit' => 'pc',
        'purchase_unit' => 'pc',
        'purchase_price' => 3,
        'units_per_purchase' => 1,
        'default_supplier_id' => $supplier->id,
    ]);
    $ingredient = FsItem::factory()->create(['kind' => 'ingredient']);

    $response = $this->actingAs($this->rnd)
        ->postJson('/api/fss/shopping-lists', [
            'name' => 'June supplies',
            'list_type' => 'manual',
            'procurement_track' => 'supplies',
            'list_date' => '2026-06-27',
        ])
        ->assertCreated()
        ->assertJsonPath('data.procurement_track', 'supplies')
        ->assertJsonPath('data.period_start', null)
        ->assertJsonPath('data.estimate_population', null);

    $listId = $response->json('data.id');

    $this->actingAs($this->rnd)
        ->postJson("/api/fss/shopping-lists/{$listId}/items", [
            'fs_item_id' => $ingredient->id,
            'qty' => 10,
            'unit' => 'kg',
            'unit_price' => 10,
        ])
        ->assertStatus(422);

    $this->actingAs($this->rnd)
        ->postJson("/api/fss/shopping-lists/{$listId}/items", [
            'fs_item_id' => $supply->id,
            'qty' => 50,
            'unit_price' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.ingredient_name', 'Paper meal box')
        ->assertJsonPath('data.item_type', 'supply')
        ->assertJsonPath('data.unit', 'pc')
        ->assertJsonPath('data.total', '150.00');

    $this->actingAs($this->rnd)
        ->postJson("/api/fss/shopping-lists/{$listId}/approve")
        ->assertCreated();

    $this->assertDatabaseHas('purchase_orders', [
        'shopping_list_id' => $listId,
        'procurement_track' => 'supplies',
        'lifecycle_status' => 'open_execution',
    ]);
    $this->assertDatabaseHas('program_project_activities', [
        'activity' => 'Food Service Supplies',
        'estimated_total_cost' => 150,
    ]);
}
```

- [ ] **Step 2: Verify test fails**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodServiceOpsTest.php --filter=supplies_list_is_manual_supply_only
```

Expected: FAIL until request/controller derives supply unit and PPA activity correctly.

- [ ] **Step 3: Store request validation**

In `StoreShoppingListRequest`, keep supplies fields prohibited:

```php
'period_start' => [$isSupplies ? 'prohibited' : 'nullable', 'date'],
'period_end' => [$isSupplies ? 'prohibited' : 'nullable', 'date', 'after_or_equal:period_start'],
'days_span' => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:60'],
'estimate_population' => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:0'],
```

In `UpdateShoppingListRequest`, add `procurement_track` and prohibit food-only fields for supplies:

```php
$isSupplies = $this->route('shoppingList')?->procurement_track === 'supplies'
    || $this->input('procurement_track') === 'supplies';

return [
    'name' => ['nullable', 'string', 'max:255'],
    'list_date' => ['nullable', 'date'],
    'list_type' => ['nullable', 'string', 'in:manual,suggested'],
    'procurement_track' => ['nullable', 'string', 'in:food,supplies'],
    'status' => ['nullable', 'string', 'in:draft,converted'],
    'period_start' => [$isSupplies ? 'prohibited' : 'nullable', 'date'],
    'period_end' => [$isSupplies ? 'prohibited' : 'nullable', 'date', 'after_or_equal:period_start'],
    'days_span' => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:60'],
    'estimate_population' => [$isSupplies ? 'prohibited' : 'nullable', 'integer', 'min:0'],
];
```

- [ ] **Step 4: Store item supply defaults**

In `ShoppingListController::storeItem()`, derive supply list unit and price only from approved supply fields:

```php
if ($shoppingList->isSupplies()) {
    if (! $fsItem || $fsItem->kind !== 'supply') {
        return response()->json(['message' => 'Supplies lists only accept supply catalog items.'], 422);
    }

    $qty = (float) $data['qty'];
    $unitPrice = array_key_exists('unit_price', $data)
        ? (float) $data['unit_price']
        : (float) $fsItem->unit_cost;

    $item = $shoppingList->items()->create([
        'fs_item_id' => $fsItem->id,
        'ingredient_name' => $fsItem->name,
        'qty' => $qty,
        'unit' => $fsItem->base_unit,
        'supplier_id' => $data['supplier_id'] ?? $fsItem->default_supplier_id,
        'unit_price' => $unitPrice,
        'total' => round($qty * $unitPrice, 2),
        'purchase_qty' => $qty,
        'purchase_unit' => $fsItem->base_unit,
        'purchase_price' => $unitPrice,
    ]);

    return response()->json(['data' => [
        'id' => $item->id,
        'fs_item_id' => $item->fs_item_id,
        'ingredient_name' => $item->ingredient_name,
        'qty' => $item->qty,
        'unit' => $item->unit,
        'supplier_id' => $item->supplier_id,
        'unit_price' => $item->unit_price,
        'total' => $item->total,
        'purchase_qty' => $item->purchase_qty,
        'purchase_unit' => $item->purchase_unit,
        'purchase_price' => $item->purchase_price,
        'item_type' => 'supply',
    ]], 201);
}
```

- [ ] **Step 5: PPA activity per track**

In `PurchaseOrderLifecycleService::createPpaSnapshot()`, set activity by track:

```php
$activity = $shoppingList->isSupplies()
    ? 'Food Service Supplies'
    : 'Food Subsistence for Patients';
```

Use `$activity` in `ProgramProjectActivity::create([...])`.

- [ ] **Step 6: Run supplies test**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodServiceOpsTest.php --filter=supplies_list_is_manual_supply_only
```

Expected: PASS.

---

### Task 4: Frontend API Types And Payloads

**Files:**
- Modify: `frontend/services/procurementService.ts`

- [ ] **Step 1: Update create shopping list payload**

Change `createShoppingList()` payload type:

```ts
export async function createShoppingList(payload: {
  name: string;
  list_date?: string | null;
  list_type?: "manual" | "suggested";
  procurement_track?: "food" | "supplies";
  status?: "draft" | "converted";
  estimate_population?: number | null;
}): Promise<ShoppingList> {
```

- [ ] **Step 2: Add vendor lock to item update payload**

Change `updateListItem()`:

```ts
export async function updateListItem(
  itemId: number,
  patch: { supplier_id?: number | null; qty?: number; unit_price?: number; vendor_locked?: boolean },
): Promise<{ id: number; supplier_id: number | null; qty: string; unit_price: string; total: string; vendor_locked?: boolean }> {
```

- [ ] **Step 3: Run TypeScript check**

Run:

```bash
cd frontend
npx tsc --noEmit
```

Expected: existing compile status plus no new errors from `procurementService.ts`.

---

### Task 5: Procurement UI Tabs And Read-Only Food Quantities

**Files:**
- Modify: `frontend/app/(rnd)/food-service/procurement/page.tsx`

- [ ] **Step 1: Split top-level tabs**

Change tab state:

```ts
const [tab, setTab] = useState<"food-lists" | "supplies-lists" | "pos" | "suppliers">("food-lists");
```

Change tab labels:

```tsx
{([
  ["food-lists", "Food Shopping List"],
  ["supplies-lists", "Supplies List"],
  ["pos", "Purchase Orders"],
  ["suppliers", "Suppliers"],
] as const).map(([k, label]) => (
```

Filter lists:

```ts
const foodLists = lists.filter((list) => (list.procurement_track ?? "food") === "food");
const suppliesLists = lists.filter((list) => list.procurement_track === "supplies");
```

- [ ] **Step 2: Create supplies list with supplies track**

Change `createManualList()`:

```ts
async function createManualList() {
  if (!newListName.trim()) return;
  const created = await createShoppingList({
    name: newListName.trim(),
    list_type: "manual",
    procurement_track: "supplies",
    status: "draft",
    list_date: new Date().toISOString().slice(0, 10),
  });
  setLists((current) => [created, ...current]);
  setNewListName("");
  setTab("supplies-lists");
  setListDetail(created.id);
}
```

- [ ] **Step 3: Save population on Enter**

Change estimated population input:

```tsx
<input
  type="number"
  min={1}
  value={populationDraft}
  onChange={(e) => setPopulationDraft(e.target.value)}
  onKeyDown={(e) => { if (e.key === "Enter") void savePopulation(); }}
  disabled={list.status !== "draft"}
  className="w-28 px-2 py-1.5 text-xs border border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-zinc-50 disabled:text-zinc-400"
/>
```

- [ ] **Step 4: Hide population and per-head panel for supplies lists**

Add helper:

```ts
const isSupplies = list.procurement_track === "supplies";
```

Render population only for food:

```tsx
{!isSupplies && list.period_start && list.period_end && (
  // existing estimated population form
)}
```

Render budget per head/day panel only for food:

```tsx
{!isSupplies && (
  <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
    {/* existing Budget per head / day panel */}
  </div>
)}
```

- [ ] **Step 5: Food items generated quantities read-only**

In list item rows, make quantity input conditional:

```tsx
{list.procurement_track === "food" ? (
  <span className="font-mono text-zinc-700">{num(it.qty).toFixed(2)}</span>
) : (
  <input
    type="number"
    defaultValue={num(it.qty)}
    disabled={list.status === "converted"}
    onBlur={(e) => patchItem(it.id, { qty: parseFloat(e.target.value) })}
    className="w-20 px-2 py-1 border border-zinc-200 rounded focus:outline-none focus:ring-1 focus:ring-emerald-400 disabled:bg-zinc-50 disabled:text-zinc-400"
  />
)}
```

Keep unit as text for both tracks:

```tsx
<td className="px-3 py-2 text-zinc-500">{it.unit}</td>
```

- [ ] **Step 6: Supply add UI only on supplies lists**

Replace item type tabs inside `ListDetail`:

```tsx
{isSupplies && (
  <div className="bg-white border border-zinc-200 rounded-2xl p-4 shadow-sm">
    {/* existing add item form, but force itemTab to "supply" and remove ingredient/supply toggle */}
  </div>
)}
```

Change `searchItems()`:

```ts
const result = await listInventoryRows({
  search: q,
  type: list?.procurement_track === "supplies" ? "supply" : "ingredient",
  per_page: 8,
});
```

Do not render manual add form for food suggested lists.

- [ ] **Step 7: Run TypeScript check**

Run:

```bash
cd frontend
npx tsc --noEmit
```

Expected: no new TypeScript errors from procurement page.

---

### Task 6: Seeder Uses Real Flow And Approved Data

**Files:**
- Modify: `backend/database/seeders/FoodServiceDemoSeeder.php`

- [ ] **Step 1: Update generated food draft lists**

Where seeder creates a suggested food list, use:

```php
$plan = app(\App\Services\FSS\ShoppingListPopulationService::class)
    ->planRange($start, $end, $this->listEstimatePopulation($weekIndex));
```

Create list with:

```php
'procurement_track' => 'food',
'estimate_population' => $this->listEstimatePopulation($weekIndex),
'estimate_population_updated_at' => $weekStart->copy(),
```

- [ ] **Step 2: Add supplies demo list through supplies track**

Add a method:

```php
private function seedSuppliesList(int $rnd): void
{
    $list = ShoppingList::create([
        'rnd_user_id' => $rnd,
        'name' => 'Monthly kitchen supplies',
        'list_date' => now()->toDateString(),
        'list_type' => 'manual',
        'procurement_track' => 'supplies',
        'status' => 'draft',
    ]);

    foreach (['Paper meal box' => 250, 'Dishwashing liquid' => 12, 'Disposable spoon' => 500] as $name => $qty) {
        $item = FsItem::where('name', $name)->where('kind', 'supply')->first();
        if (! $item) {
            continue;
        }

        $list->items()->create([
            'fs_item_id' => $item->id,
            'ingredient_name' => $item->name,
            'qty' => $qty,
            'unit' => $item->base_unit,
            'supplier_id' => $item->default_supplier_id,
            'unit_price' => $item->unit_cost,
            'total' => round($qty * $item->unit_cost, 2),
            'purchase_qty' => $qty,
            'purchase_unit' => $item->base_unit,
            'purchase_price' => $item->unit_cost,
        ]);
    }
}
```

Call `seedSuppliesList($rnd);` after `seedDraftSuggestedList(...)`.

- [ ] **Step 3: Run seeder source test**

Run:

```bash
cd backend
php artisan test tests/Unit/FoodServiceDemoSeederSourceTest.php
```

Expected: PASS.

---

### Task 7: Verification

**Files:**
- No new files.

- [ ] **Step 1: Run targeted backend tests**

Run:

```bash
cd backend
php artisan test tests/Feature/FoodShoppingListGenerationTest.php tests/Feature/FsItemCatalogTest.php tests/Feature/FoodServiceOpsTest.php --filter="list_level_estimate_population|missing_dates_block_creation|fully_covered_span|supplies_list_is_manual_supply_only|vendor_group_or_and_audited_price_correction_only|po_completes_when_all_vendor_receipts"
```

Expected: all selected tests PASS.

- [ ] **Step 2: Run frontend type check**

Run:

```bash
cd frontend
npx tsc --noEmit
```

Expected: no new TypeScript errors from modified procurement/catalog files.

- [ ] **Step 3: Review diff against whitelist**

Run:

```bash
git diff -- backend/app/Services/FSS/ShoppingListPopulationService.php backend/app/Http/Controllers/FSS/ShoppingListController.php backend/app/Http/Requests/FSS/StoreShoppingListRequest.php backend/app/Http/Requests/FSS/UpdateShoppingListRequest.php backend/app/Services/FSS/PurchaseOrderLifecycleService.php backend/database/seeders/FoodServiceDemoSeeder.php frontend/services/procurementService.ts "frontend/app/(rnd)/food-service/procurement/page.tsx"
```

Expected: diff only adds or changes fields listed in "Approved Data/Input Whitelist"; no stock qty, expiry, low-stock threshold, usage rate, or unrelated report/insight work appears.

---

## Self-Review

- Spec coverage: Inventory catalog approved fields, food list scaling, supplies separate tab/track, PO conversion track, seeder workflow, and verification are covered.
- Placeholder scan: no `TBD`, `TODO`, or unspecified "handle edge cases" steps remain.
- Type consistency: `procurement_track`, `estimate_population`, `purchase_qty`, `purchase_unit`, `purchase_price`, `unit_price`, and `total` match existing schema/model names.
