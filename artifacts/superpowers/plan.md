# Live USDA Meal Planning + Full Nutrient Display — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Show all nutrients (macros + all micros, grouped) on the food library edit page; (2) Allow RND to add USDA foods, library foods, and recipes directly to meal plans — nutrients stored as a snapshot, library import optional.

**Architecture:**
- Food library edit page: already has `micronutrients` from the API — frontend-only change to display and edit them organized by group (Minerals / Vitamins / Fatty Acids).
- Meal plan items: add `fdc_id` nullable column so a meal plan item can reference a USDA food without a `food_items` record. On save, `nutrient_snapshot` is auto-populated server-side from whichever source was used (library food, live USDA, or recipe). Calculations always run off `nutrient_snapshot`.
- `nutrient_snapshot` stores `serving_size` alongside nutrients so quantity-based scaling works: `actual = value × (quantity / serving_size)`.
- New `UsdaController::preview()` endpoint fetches nutrient data without saving (Redis-cached via existing UsdaService::fetch()).
- New `MealPlanItemController` handles item CRUD, nested under meal plan days. `buildSnapshot()` covers all three source types: `food_item_id`, `fdc_id`, `recipe_id`.
- Intervention page replaced with real meal plan builder (week view, food picker modal with Library / USDA / Recipes tabs).

**Tech Stack:** Laravel 13.8 (PHP 8.3), Next.js 16, TypeScript, MySQL, Redis (UsdaService cache), Tailwind CSS, Lucide React

---

## Architecture: Food Service Integration (future-proofed, not implemented now)

`food_items` is the **shared master catalog** for both RND and FSS. The data model already supports this — no structural changes needed now.

```
food_items (master catalog — already exists)
  ├── Clinical fields (RND view): calories, protein, carbs, fat, micronutrients, allergens, serving_size
  ├── unit_price  ← already in DB, hidden from RND UI, editable by FSS via inventory
  └── usda_fdc_id, category, serving_unit

inventory (FSS operational layer — already linked)
  ├── food_item_id FK → food_items   ← already exists, unique per food
  ├── quantity_in_stock, unit
  ├── expiry_date, usage_rate
  └── minimum_stock_threshold, notes
```

**What this enables without any DB changes:**
- RND adds a food to the library → FSS can immediately create an inventory record for it
- FSS sets `unit_price` on `food_items` via the inventory UI (RND never sees this field)
- Recipes built from library foods have ingredient cost data (`ingredient.food_item.unit_price`) → menu cycle costing works automatically via `Recipe::recalculateTotals()`
- RND meal plans reference the same library foods used in FSS menu cycles — single source of truth

**What FSS inventory UI will need (build later):**
- Inventory index: pull `food_item.name`, `food_item.category`, `food_item.unit_price` alongside stock fields
- "Add to Inventory" flow: search food library → create inventory record + set unit_price on the food_item
- FSS cannot add foods directly — they pick from the library (RND curates clinical data, FSS adds pricing)
- Menu cycle builder: uses recipes (which use library foods), costing comes from `unit_price`

**Note on the unique constraint:** `inventory` has `unique('food_item_id')` — one inventory slot per food. Suitable for a district hospital. If multi-supplier tracking is needed later, this constraint gets dropped and a `supplier_id` FK is added to `inventory`.

---

## File Map

**Create:**
- `backend/database/migrations/YYYY_MM_DD_add_fdc_id_to_meal_plan_items.php`
- `backend/app/Http/Controllers/RND/MealPlanItemController.php`
- `backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php`
- `backend/app/Http/Resources/MealPlanItemResource.php`
- `backend/tests/Feature/MealPlanItemControllerTest.php`
- `backend/tests/Feature/UsdaPreviewTest.php`
- `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/route.ts`
- `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/[itemId]/route.ts`
- `frontend/app/api/rnd/usda/preview/[fdcId]/route.ts`
- `frontend/services/mealPlanService.ts`

**Modify:**
- `backend/app/Models/MealPlanItem.php` — add `fdc_id` to $fillable
- `backend/app/Http/Controllers/RND/UsdaController.php` — add `preview()` method
- `backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php` — add `recipe_id` validation
- `backend/routes/api.php` — register MealPlanItemController routes + preview route
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` — replace placeholder with real meal plan builder
- `frontend/app/(rnd)/food-library/foods/[id]/page.tsx` — full nutrient display + editing
- `frontend/services/foodLibraryService.ts` — add `previewUsdaFood()` function + `UsdaPreviewResult` type

---

## Task 1: Full Nutrient Display on Food Edit Page (frontend only)

**Files:**
- Modify: `frontend/app/(rnd)/food-library/foods/[id]/page.tsx`

No backend changes — `FoodItemResource` already returns `micronutrients` as a JSON object and `StoreFoodItemRequest` already accepts `micronutrients` as a nullable array.

**Nutrient groups to display (keys match `UsdaService::MICRO_IDS` and seeder convention):**

```
Minerals: sodium, potassium, phosphate, calcium, iron, magnesium, zinc, copper, manganese, selenium, iodine
Vitamins: vitamin_a, vitamin_c, vitamin_d, vitamin_e, vitamin_k, vitamin_b1, vitamin_b2, vitamin_b3, vitamin_b6, vitamin_b12, folate
Fatty acids / other: fiber, cholesterol, omega3
```

**Behaviour:**
- USDA food (`usda_fdc_id` set): macros editable, micros read-only (badge "USDA-sourced")
- Manual food (`usda_fdc_id` null): macros editable, micros editable (numeric inputs, all optional)
- All foods: micros displayed grouped, even if values are 0 or absent (show "—" for absent)
- On save for manual food: include `micronutrients` object in the PUT payload

- [ ] **Step 1: Add micronutrient state and groups constant**

Replace the existing state declarations and add below the `SERVING_UNITS` constant:

```tsx
const NUTRIENT_GROUPS = {
  Minerals: ['sodium','potassium','phosphate','calcium','iron','magnesium','zinc','copper','manganese','selenium','iodine'],
  Vitamins: ['vitamin_a','vitamin_c','vitamin_d','vitamin_e','vitamin_k','vitamin_b1','vitamin_b2','vitamin_b3','vitamin_b6','vitamin_b12','folate'],
  'Fatty Acids & Other': ['fiber','cholesterol','omega3'],
} as const;

const NUTRIENT_UNITS: Record<string, string> = {
  sodium:'mg', potassium:'mg', phosphate:'mg', calcium:'mg', iron:'mg',
  magnesium:'mg', zinc:'mg', copper:'mg', manganese:'mg', selenium:'mcg', iodine:'mcg',
  vitamin_a:'mcg', vitamin_c:'mg', vitamin_d:'mcg', vitamin_e:'mg', vitamin_k:'mcg',
  vitamin_b1:'mg', vitamin_b2:'mg', vitamin_b3:'mg', vitamin_b6:'mg', vitamin_b12:'mcg', folate:'mcg',
  fiber:'g', cholesterol:'mg', omega3:'g',
};
```

Add state inside the component (after existing state declarations):

```tsx
const [micros, setMicros] = useState<Record<string, string>>({});
```

- [ ] **Step 2: Populate micros state on load**

Inside the `.then((f) => { ... })` block, after setting allergens:

```tsx
const microMap: Record<string, string> = {};
Object.values(NUTRIENT_GROUPS).flat().forEach((key) => {
  microMap[key] = f.micronutrients?.[key] != null ? String(f.micronutrients[key]) : '';
});
setMicros(microMap);
```

- [ ] **Step 3: Include micros in handleSubmit for manual foods**

Inside `handleSubmit`, after building the base payload, add:

```tsx
const micronutrients: Record<string, number> = {};
if (!food?.usda_fdc_id) {
  Object.entries(micros).forEach(([k, v]) => {
    const n = parseFloat(v);
    if (!isNaN(n) && n >= 0) micronutrients[k] = n;
  });
}
await updateFoodItem(id, {
  name: name.trim(),
  calories: parseFloat(calories),
  category: category || null,
  protein: protein ? parseFloat(protein) : null,
  carbs: carbs ? parseFloat(carbs) : null,
  fat: fat ? parseFloat(fat) : null,
  serving_size: servingSize ? parseFloat(servingSize) : null,
  serving_unit: servingUnit || null,
  allergens,
  ...(!food?.usda_fdc_id && { micronutrients }),
});
```

- [ ] **Step 4: Replace the micronutrient section in the JSX**

Remove the existing conditional `{food?.usda_fdc_id && (...)}` block at line 153–166. Replace the entire `Nutritional Values` card section (lines 145–167) with:

```tsx
<div className="bg-white border border-zinc-200 rounded-2xl p-6 space-y-5 shadow-sm">
  <div className="flex items-center justify-between">
    <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Nutritional Values (per serving)</h3>
    {food?.usda_fdc_id && (
      <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full uppercase tracking-wider">USDA-sourced</span>
    )}
  </div>

  {/* Macros — always editable */}
  <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div><Label>Calories (kcal) <Required /></Label><input type="number" value={calories} onChange={(e) => setCalories(e.target.value)} min="0" step="0.01" className={inputCls} required /></div>
    <div><Label>Protein (g)</Label><input type="number" value={protein} onChange={(e) => setProtein(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
    <div><Label>Carbs (g)</Label><input type="number" value={carbs} onChange={(e) => setCarbs(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
    <div><Label>Fat (g)</Label><input type="number" value={fat} onChange={(e) => setFat(e.target.value)} min="0" step="0.01" className={inputCls} /></div>
  </div>

  {/* Micronutrients — grouped */}
  {Object.entries(NUTRIENT_GROUPS).map(([group, keys]) => (
    <div key={group}>
      <p className="text-[9px] font-extrabold text-zinc-400 uppercase tracking-widest mb-2">{group}</p>
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        {keys.map((key) => (
          <div key={key}>
            <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-wider mb-1">
              {key.replace(/_/g, ' ')} <span className="normal-case font-normal">({NUTRIENT_UNITS[key]})</span>
            </label>
            {food?.usda_fdc_id ? (
              <p className="text-sm font-semibold text-zinc-700 px-3 py-2 bg-zinc-50 border border-zinc-200 rounded-lg">
                {micros[key] !== '' ? micros[key] : <span className="text-zinc-300">—</span>}
              </p>
            ) : (
              <input
                type="number" min="0" step="0.001"
                value={micros[key] ?? ''}
                onChange={(e) => setMicros((prev) => ({ ...prev, [key]: e.target.value }))}
                className={inputCls}
                placeholder="—"
              />
            )}
          </div>
        ))}
      </div>
    </div>
  ))}
</div>
```

- [ ] **Step 5: Start dev server and verify**

```bash
cd frontend && npm run dev
```

Navigate to `/food-library`, click edit on a USDA-imported food → all micros show read-only grouped. Click edit on a manually-added food → micros show editable inputs. Save a manual food with some micro values → reload and verify values persist.

- [ ] **Step 6: Commit**

```bash
git add frontend/app/\(rnd\)/food-library/foods/\[id\]/page.tsx
git commit -m "feat: show all micronutrients grouped and editable on food library edit page"
```

---

## Task 2: Migration — add fdc_id to meal_plan_items

**Files:**
- Create: `backend/database/migrations/YYYY_MM_DD_add_fdc_id_to_meal_plan_items.php`
- Modify: `backend/app/Models/MealPlanItem.php`

- [ ] **Step 1: Generate migration**

```bash
cd backend && php artisan make:migration add_fdc_id_to_meal_plan_items --table=meal_plan_items
```

- [ ] **Step 2: Fill the migration**

Open the generated file. Replace the `up()` and `down()` bodies:

```php
public function up(): void
{
    Schema::table('meal_plan_items', function (Blueprint $table) {
        $table->string('fdc_id', 20)->nullable()->after('recipe_id');
    });
}

public function down(): void
{
    Schema::table('meal_plan_items', function (Blueprint $table) {
        $table->dropColumn('fdc_id');
    });
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

Expected: `Migrating: YYYY_MM_DD_add_fdc_id_to_meal_plan_items` → `Migrated`

- [ ] **Step 4: Update MealPlanItem model**

In `backend/app/Models/MealPlanItem.php`, add `'fdc_id'` to `$fillable`:

```php
protected $fillable = [
    'meal_plan_day_id', 'food_item_id', 'recipe_id', 'fdc_id',
    'quantity', 'unit', 'nutrient_snapshot', 'ai_suggested',
];
```

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/ backend/app/Models/MealPlanItem.php
git commit -m "feat: add fdc_id column to meal_plan_items for live USDA food lookup"
```

---

## Task 3: Backend — UsdaController preview endpoint (TDD)

**Files:**
- Modify: `backend/app/Http/Controllers/RND/UsdaController.php`
- Modify: `backend/routes/api.php`
- Create: `backend/tests/Feature/UsdaPreviewTest.php`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Feature/UsdaPreviewTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsdaPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function rndUser(): User
    {
        return User::factory()->create(['role' => 'rnd']);
    }

    public function test_preview_returns_nutrient_data_without_saving(): void
    {
        $user = $this->rndUser();

        $this->mock(UsdaService::class, function ($mock) {
            $mock->shouldReceive('fetch')->with(331960)->once()->andReturn([
                'fdc_id'         => 331960,
                'name'           => 'Chicken, broilers or fryers, breast',
                'calories'       => 165.0,
                'protein'        => 31.0,
                'carbs'          => 0.0,
                'fat'            => 3.6,
                'micronutrients' => ['sodium' => 74.0, 'potassium' => 256.0],
            ]);
        });

        $response = $this->actingAs($user)
            ->getJson('/api/rnd/usda/preview/331960');

        $response->assertOk()
            ->assertJsonPath('data.fdc_id', 331960)
            ->assertJsonPath('data.calories', 165.0)
            ->assertJsonPath('data.micronutrients.sodium', 74.0);

        $this->assertDatabaseCount('food_items', 0);
    }

    public function test_preview_rejects_non_numeric_fdc_id(): void
    {
        $user = $this->rndUser();

        $response = $this->actingAs($user)
            ->getJson('/api/rnd/usda/preview/../../etc/passwd');

        $response->assertStatus(404);
    }

    public function test_preview_requires_authentication(): void
    {
        $this->getJson('/api/rnd/usda/preview/331960')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
php artisan test tests/Feature/UsdaPreviewTest.php
```

Expected: FAIL — `preview` method does not exist / route not found.

- [ ] **Step 3: Add the route**

In `backend/routes/api.php`, inside the RND middleware group after the existing USDA routes:

```php
Route::get('usda/preview/{fdcId}', [UsdaController::class, 'preview'])
    ->where('fdcId', '[0-9]+');
```

The `where` constraint rejects non-numeric IDs at the routing layer (addresses the path traversal test).

- [ ] **Step 4: Add preview() to UsdaController**

In `backend/app/Http/Controllers/RND/UsdaController.php`, add method:

```php
/**
 * GET /api/rnd/usda/preview/{fdcId}
 * Returns full nutrient data for a USDA food without saving it to food_items.
 */
public function preview(int $fdcId): JsonResponse
{
    $data = $this->usdaService->fetch($fdcId);
    return response()->json(['data' => $data]);
}
```

Confirm the constructor already injects `UsdaService $usdaService` — it does from the existing import endpoint.

- [ ] **Step 5: Run tests — confirm they pass**

```bash
php artisan test tests/Feature/UsdaPreviewTest.php
```

Expected: 3 passed.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/RND/UsdaController.php backend/routes/api.php backend/tests/Feature/UsdaPreviewTest.php
git commit -m "feat: add USDA preview endpoint — returns nutrients without importing to library"
```

---

## Task 4: Backend — MealPlanItemController (TDD)

**Files:**
- Create: `backend/app/Http/Controllers/RND/MealPlanItemController.php`
- Create: `backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php`
- Create: `backend/app/Http/Resources/MealPlanItemResource.php`
- Create: `backend/tests/Feature/MealPlanItemControllerTest.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Write the failing tests**

Create `backend/tests/Feature/MealPlanItemControllerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\Intervention;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Models\Patient;
use App\Models\User;
use App\Services\UsdaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setup_plan(): array
    {
        $rnd = User::factory()->create(['role' => 'rnd']);
        $patient = Patient::factory()->create();
        $ncp = NcpRecord::factory()->create(['patient_id' => $patient->id, 'rnd_user_id' => $rnd->id]);
        $intervention = Intervention::factory()->create(['ncp_record_id' => $ncp->id]);
        $plan = MealPlan::factory()->create([
            'intervention_id' => $intervention->id,
            'patient_id'      => $patient->id,
        ]);
        $day = MealPlanDay::factory()->create(['meal_plan_id' => $plan->id]);
        return compact('rnd', 'ncp', 'plan', 'day');
    }

    private function url(array $ctx, ?int $itemId = null): string
    {
        $base = "/api/rnd/ncp-records/{$ctx['ncp']->id}/meal-plans/{$ctx['plan']->id}/days/{$ctx['day']->id}/items";
        return $itemId ? "{$base}/{$itemId}" : $base;
    }

    public function test_index_lists_items_for_a_day(): void
    {
        $ctx = $this->setup_plan();
        MealPlanItem::factory()->count(3)->create(['meal_plan_day_id' => $ctx['day']->id]);

        $this->actingAs($ctx['rnd'])
            ->getJson($this->url($ctx))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_with_library_food_populates_snapshot(): void
    {
        $ctx = $this->setup_plan();
        $food = FoodItem::factory()->create([
            'calories' => 165.0, 'protein' => 31.0, 'carbs' => 0.0, 'fat' => 3.6,
            'micronutrients' => ['sodium' => 74],
        ]);

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'food_item_id' => $food->id,
                'quantity'     => 150,
                'unit'         => 'g',
            ]);

        $response->assertCreated();
        $item = MealPlanItem::first();
        $this->assertNotNull($item->nutrient_snapshot);
        $this->assertEquals(165.0, $item->nutrient_snapshot['calories']);
        $this->assertEquals(74, $item->nutrient_snapshot['micronutrients']['sodium']);
    }

    public function test_store_with_fdc_id_fetches_usda_and_populates_snapshot(): void
    {
        $ctx = $this->setup_plan();

        $this->mock(UsdaService::class, function ($mock) {
            $mock->shouldReceive('fetch')->with(331960)->once()->andReturn([
                'fdc_id'         => 331960,
                'name'           => 'Chicken breast',
                'calories'       => 165.0,
                'protein'        => 31.0,
                'carbs'          => 0.0,
                'fat'            => 3.6,
                'micronutrients' => ['sodium' => 74],
            ]);
        });

        $response = $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'fdc_id'   => '331960',
                'quantity' => 100,
                'unit'     => 'g',
            ]);

        $response->assertCreated();
        $item = MealPlanItem::first();
        $this->assertEquals('331960', $item->fdc_id);
        $this->assertNull($item->food_item_id);
        $this->assertEquals(165.0, $item->nutrient_snapshot['calories']);
        $this->assertDatabaseCount('food_items', 0); // not imported
    }

    public function test_store_rejects_both_food_item_id_and_fdc_id(): void
    {
        $ctx = $this->setup_plan();
        $food = FoodItem::factory()->create();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'food_item_id' => $food->id,
                'fdc_id'       => '331960',
                'quantity'     => 100,
                'unit'         => 'g',
            ])
            ->assertUnprocessable();
    }

    public function test_store_rejects_neither_food_item_id_nor_fdc_id(): void
    {
        $ctx = $this->setup_plan();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), ['quantity' => 100, 'unit' => 'g'])
            ->assertUnprocessable();
    }

    public function test_store_rejects_non_numeric_fdc_id(): void
    {
        $ctx = $this->setup_plan();

        $this->actingAs($ctx['rnd'])
            ->postJson($this->url($ctx), [
                'fdc_id'   => 'abc123!',
                'quantity' => 100,
                'unit'     => 'g',
            ])
            ->assertUnprocessable();
    }

    public function test_destroy_removes_item(): void
    {
        $ctx = $this->setup_plan();
        $item = MealPlanItem::factory()->create(['meal_plan_day_id' => $ctx['day']->id]);

        $this->actingAs($ctx['rnd'])
            ->deleteJson($this->url($ctx, $item->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('meal_plan_items', ['id' => $item->id]);
    }

    public function test_requires_authentication(): void
    {
        $ctx = $this->setup_plan();
        $this->getJson($this->url($ctx))->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test — confirm it fails**

```bash
php artisan test tests/Feature/MealPlanItemControllerTest.php
```

Expected: FAIL — routes not found.

- [ ] **Step 3: Create StoreMealPlanItemRequest**

Create `backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php`:

```php
<?php

namespace App\Http\Requests\RND;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'food_item_id' => 'sometimes|nullable|integer|exists:food_items,id',
            'fdc_id'       => 'sometimes|nullable|string|regex:/^\d{1,10}$/',
            'recipe_id'    => 'sometimes|nullable|integer|exists:recipes,id',
            'quantity'     => 'required|numeric|min:0.01',
            'unit'         => 'required|string|max:50',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $sources = array_filter([
                $this->input('food_item_id'),
                $this->input('fdc_id'),
                $this->input('recipe_id'),
            ], fn($v) => filled($v));

            if (count($sources) > 1) {
                $v->errors()->add('food_item_id', 'Provide exactly one of: food_item_id, fdc_id, or recipe_id.');
            }
            if (count($sources) === 0) {
                $v->errors()->add('food_item_id', 'One of food_item_id, fdc_id, or recipe_id is required.');
            }
        });
    }
}
```

- [ ] **Step 4: Create MealPlanItemResource**

Create `backend/app/Http/Resources/MealPlanItemResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'meal_plan_day_id'  => $this->meal_plan_day_id,
            'food_item_id'      => $this->food_item_id,
            'fdc_id'            => $this->fdc_id,
            'recipe_id'         => $this->recipe_id,
            'quantity'          => $this->quantity,
            'unit'              => $this->unit,
            'nutrient_snapshot' => $this->nutrient_snapshot,
            'ai_suggested'      => $this->ai_suggested,
            'source'            => $this->fdc_id ? 'usda' : ($this->food_item_id ? 'library' : 'recipe'),
        ];
    }
}
```

- [ ] **Step 5: Create MealPlanItemController**

Create `backend/app/Http/Controllers/RND/MealPlanItemController.php`:

```php
<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreMealPlanItemRequest;
use App\Http\Resources\MealPlanItemResource;
use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanDay;
use App\Models\MealPlanItem;
use App\Models\NcpRecord;
use App\Services\UsdaService;
use Illuminate\Http\JsonResponse;

class MealPlanItemController extends Controller
{
    public function __construct(private UsdaService $usdaService) {}

    /** GET /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items */
    public function index(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $items = MealPlanItem::where('meal_plan_day_id', $day->id)->get();
        return response()->json(['data' => MealPlanItemResource::collection($items)]);
    }

    /** POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items */
    public function store(StoreMealPlanItemRequest $request, NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day): JsonResponse
    {
        $snapshot = $this->buildSnapshot($request);

        $item = MealPlanItem::create([
            'meal_plan_day_id'  => $day->id,
            'food_item_id'      => $request->input('food_item_id'),
            'fdc_id'            => $request->input('fdc_id'),
            'quantity'          => $request->quantity,
            'unit'              => $request->unit,
            'nutrient_snapshot' => $snapshot,
        ]);

        return response()->json(['data' => new MealPlanItemResource($item)], 201);
    }

    /** DELETE /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item} */
    public function destroy(NcpRecord $ncpRecord, MealPlan $mealPlan, MealPlanDay $day, MealPlanItem $item): JsonResponse
    {
        $item->delete();
        return response()->json(null, 204);
    }

    private function buildSnapshot(StoreMealPlanItemRequest $request): array
    {
        // Live USDA food (not in library)
        if ($request->filled('fdc_id')) {
            $data = $this->usdaService->fetch((int) $request->input('fdc_id'));
            return array_merge($data, ['serving_size' => 100, 'serving_unit' => 'g']);
        }

        // Recipe from library
        if ($request->filled('recipe_id')) {
            $recipe = \App\Models\Recipe::findOrFail($request->input('recipe_id'));
            return [
                'name'           => $recipe->name,
                'calories'       => (float) $recipe->total_calories,
                'protein'        => (float) $recipe->total_protein,
                'carbs'          => (float) $recipe->total_carbs,
                'fat'            => (float) $recipe->total_fat,
                'micronutrients' => $recipe->micronutrients ?? [],
                'serving_size'   => $recipe->servings ?? 1,
                'serving_unit'   => 'serving',
            ];
        }

        // Library food
        $food = FoodItem::findOrFail($request->input('food_item_id'));
        return [
            'fdc_id'         => $food->usda_fdc_id,
            'name'           => $food->name,
            'calories'       => (float) $food->calories,
            'protein'        => (float) $food->protein,
            'carbs'          => (float) $food->carbs,
            'fat'            => (float) $food->fat,
            'micronutrients' => $food->micronutrients ?? [],
            'serving_size'   => (float) ($food->serving_size ?? 100),
            'serving_unit'   => $food->serving_unit ?? 'g',
        ];
    }
}
```

- [ ] **Step 6: Register routes**

In `backend/routes/api.php`, inside the RND middleware group, add after the existing meal-plan routes:

```php
Route::get(
    'ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items',
    [MealPlanItemController::class, 'index']
);
Route::post(
    'ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items',
    [MealPlanItemController::class, 'store']
);
Route::delete(
    'ncp-records/{ncpRecord}/meal-plans/{mealPlan}/days/{day}/items/{item}',
    [MealPlanItemController::class, 'destroy']
);
```

Add the import at the top of api.php:

```php
use App\Http\Controllers\RND\MealPlanItemController;
```

- [ ] **Step 7: Run tests — confirm all pass**

```bash
php artisan test tests/Feature/MealPlanItemControllerTest.php
```

Expected: 8 passed.

- [ ] **Step 8: Run full test suite**

```bash
php artisan test
```

Expected: all existing tests still pass.

- [ ] **Step 9: Commit**

```bash
git add backend/app/Http/Controllers/RND/MealPlanItemController.php \
        backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php \
        backend/app/Http/Resources/MealPlanItemResource.php \
        backend/routes/api.php \
        backend/tests/Feature/MealPlanItemControllerTest.php
git commit -m "feat: add MealPlanItemController with library and live USDA food support"
```

---

## Task 5: Frontend — Next.js proxy routes + service

**Files:**
- Create: `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/[itemId]/route.ts`
- Create: `frontend/app/api/rnd/usda/preview/[fdcId]/route.ts`
- Create: `frontend/services/mealPlanService.ts`
- Modify: `frontend/services/foodLibraryService.ts` — add `previewUsdaFood()`

- [ ] **Step 1: Create meal plan items proxy — collection route**

Create `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const BACKEND = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

type Ctx = { params: Promise<{ ncpId: string; mealPlanId: string; dayId: string }> };

async function forward(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const url = `${BACKEND}/api/rnd/${path}`;
  const res = await fetch(url, {
    method: req.method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: req.method !== 'GET' ? await req.text() : undefined,
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function GET(req: NextRequest, { params }: Ctx) {
  const { ncpId, mealPlanId, dayId } = await params;
  return forward(req, `ncp-records/${ncpId}/meal-plans/${mealPlanId}/days/${dayId}/items`);
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpId, mealPlanId, dayId } = await params;
  return forward(req, `ncp-records/${ncpId}/meal-plans/${mealPlanId}/days/${dayId}/items`);
}
```

- [ ] **Step 2: Create meal plan items proxy — single item route**

Create `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/[mealPlanId]/days/[dayId]/items/[itemId]/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const BACKEND = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

type Ctx = { params: Promise<{ ncpId: string; mealPlanId: string; dayId: string; itemId: string }> };

async function forward(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${BACKEND}/api/rnd/${path}`, {
    method: req.method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  if (res.status === 204) return new NextResponse(null, { status: 204 });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function DELETE(req: NextRequest, { params }: Ctx) {
  const { ncpId, mealPlanId, dayId, itemId } = await params;
  return forward(req, `ncp-records/${ncpId}/meal-plans/${mealPlanId}/days/${dayId}/items/${itemId}`);
}
```

- [ ] **Step 3: Create USDA preview proxy**

Create `frontend/app/api/rnd/usda/preview/[fdcId]/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const BACKEND = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

type Ctx = { params: Promise<{ fdcId: string }> };

export async function GET(req: NextRequest, { params }: Ctx) {
  const { fdcId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${BACKEND}/api/rnd/usda/preview/${encodeURIComponent(fdcId)}`, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
```

- [ ] **Step 4: Add previewUsdaFood to foodLibraryService.ts**

In `frontend/services/foodLibraryService.ts`, add after the existing `UsdaSearchResult` type:

```ts
export interface UsdaPreviewResult {
  fdc_id: number;
  name: string;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
  micronutrients: Record<string, number>;
}
```

And add the function after `importUsdaFood`:

```ts
export async function previewUsdaFood(fdcId: number): Promise<UsdaPreviewResult> {
  const res = await fetch(`/api/rnd/usda/preview/${fdcId}`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'USDA preview failed.');
  }
  const data = await res.json();
  return data.data ?? data;
}
```

- [ ] **Step 5: Create mealPlanService.ts**

Create `frontend/services/mealPlanService.ts`:

```ts
export interface NutrientSnapshot {
  fdc_id?: number | null;
  name: string;
  calories: number;
  protein: number;
  carbs: number;
  fat: number;
  micronutrients: Record<string, number>;
  serving_size: number;   // base amount the nutrients are measured per
  serving_unit: string;   // 'g' | 'ml' | 'serving' — for display only
}

export interface MealPlanItem {
  id: number;
  meal_plan_day_id: number;
  food_item_id: number | null;
  fdc_id: string | null;
  recipe_id: number | null;
  quantity: string;
  unit: string;
  nutrient_snapshot: NutrientSnapshot | null;
  ai_suggested: boolean;
  source: 'library' | 'usda' | 'recipe';
}

export interface MealPlanDay {
  id: number;
  meal_plan_id: number;
  day_of_week: 'Monday' | 'Tuesday' | 'Wednesday' | 'Thursday' | 'Friday' | 'Saturday' | 'Sunday';
  meal_type: 'breakfast' | 'am_snack' | 'lunch' | 'pm_snack' | 'dinner';
  flagged: boolean;
}

export interface MealPlan {
  id: number;
  intervention_id: number;
  patient_id: number;
  week_start_date: string;
  generation_type: 'manual' | 'auto';
  status: 'draft' | 'active';
  days: MealPlanDay[];
}

const base = (ncpId: string, planId: number, dayId: number) =>
  `/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/days/${dayId}/items`;

export async function fetchMealPlanItems(ncpId: string, planId: number, dayId: number): Promise<MealPlanItem[]> {
  const res = await fetch(base(ncpId, planId, dayId), { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error('Failed to fetch meal plan items.');
  const data = await res.json();
  return data.data ?? [];
}

export async function addMealPlanItem(
  ncpId: string,
  planId: number,
  dayId: number,
  payload: { food_item_id?: number; fdc_id?: string; quantity: number; unit: string }
): Promise<MealPlanItem> {
  const res = await fetch(base(ncpId, planId, dayId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Failed to add meal plan item.');
  }
  const data = await res.json();
  return data.data ?? data;
}

export async function removeMealPlanItem(
  ncpId: string,
  planId: number,
  dayId: number,
  itemId: number
): Promise<void> {
  const res = await fetch(`${base(ncpId, planId, dayId)}/${itemId}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  });
  if (!res.ok && res.status !== 204) throw new Error('Failed to remove meal plan item.');
}

export async function fetchMealPlans(ncpId: string): Promise<MealPlan[]> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
    headers: { Accept: 'application/json' },
  });
  if (!res.ok) throw new Error('Failed to fetch meal plans.');
  const data = await res.json();
  return data.data ?? [];
}

export async function createMealPlan(
  ncpId: string,
  payload: { week_start_date: string; generation_type?: string }
): Promise<MealPlan> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Failed to create meal plan.');
  }
  const data = await res.json();
  return data.data ?? data;
}
```

- [ ] **Step 6: Commit**

```bash
git add frontend/app/api/rnd/ncp-records/ \
        frontend/app/api/rnd/usda/preview/ \
        frontend/services/mealPlanService.ts \
        frontend/services/foodLibraryService.ts
git commit -m "feat: add Next.js proxy routes and service layer for meal plan items and USDA preview"
```

---

## Task 6: Frontend — Meal Plan Builder Page

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`

This replaces the placeholder with a real meal plan builder. The page has two tabs:
1. **Intervention Goals** — existing static placeholder content (preserved)
2. **Meal Plan** — week view with 7-day / 5-meal grid, food picker modal

The food picker modal has two tabs:
- **Library** — searches `food-items`, shows name + macros, "Add" button
- **USDA** — searches usda, shows name + macros, "Add to Plan" button (no import)

USDA-sourced items in the plan show a "From USDA" badge + "Save to Library" button (calls `importUsdaFood`).

Daily macro totals are summed from `nutrient_snapshot.calories`, `.protein`, `.carbs`, `.fat` across all items in all meal slots for that day, scaled by quantity relative to serving_size.

- [ ] **Step 1: Write the full page**

Replace `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` with:

```tsx
"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Salad, User, Plus, X, Search, Loader2, Database, Leaf, Trash2, BookmarkPlus } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchMealPlans, createMealPlan, fetchMealPlanItems, addMealPlanItem, removeMealPlanItem,
  MealPlan, MealPlanDay, MealPlanItem,
} from "@/services/mealPlanService";
import { fetchFoodItems, searchUsda, importUsdaFood, previewUsdaFood, FoodItem, UsdaSearchResult } from "@/services/foodLibraryService";
import { fetchRecipes, Recipe } from "@/services/foodLibraryService";

const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as const;
const MEAL_TYPES = ['breakfast','am_snack','lunch','pm_snack','dinner'] as const;
const MEAL_LABELS: Record<string, string> = {
  breakfast: 'Breakfast', am_snack: 'AM Snack', lunch: 'Lunch', pm_snack: 'PM Snack', dinner: 'Dinner',
};

type PageParams = { patientId: string; ncpId: string };

export default function NcpInterventionPage({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const isPlaceholder = patientId === 'select-patient' || ncpId === 'select-ncp';

  const [tab, setTab] = useState<'goals' | 'mealplan'>('goals');
  const [plans, setPlans] = useState<MealPlan[]>([]);
  const [activePlan, setActivePlan] = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay] = useState<string>(DAYS[0]);
  const [itemsByDayMeal, setItemsByDayMeal] = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

  // Food picker modal state
  const [pickerOpen, setPickerOpen] = useState(false);
  const [pickerTarget, setPickerTarget] = useState<{ dayId: number; mealType: string } | null>(null);
  const [pickerTab, setPickerTab] = useState<'library' | 'usda' | 'recipes'>('library');
  const [libraryQuery, setLibraryQuery] = useState('');
  const [libraryResults, setLibraryResults] = useState<FoodItem[]>([]);
  const [usdaQuery, setUsdaQuery] = useState('');
  const [usdaResults, setUsdaResults] = useState<UsdaSearchResult[]>([]);
  const [recipeQuery, setRecipeQuery] = useState('');
  const [recipeResults, setRecipeResults] = useState<Recipe[]>([]);
  const [pickerLoading, setPickerLoading] = useState(false);
  const [adding, setAdding] = useState<number | string | null>(null);
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);

  const loadPlans = useCallback(async () => {
    setLoadingPlans(true);
    try {
      const data = await fetchMealPlans(ncpId);
      setPlans(data);
      if (data.length > 0) setActivePlan(data[0]);
    } finally {
      setLoadingPlans(false);
    }
  }, [ncpId]);

  useEffect(() => {
    if (!isPlaceholder && tab === 'mealplan') loadPlans();
  }, [isPlaceholder, tab, loadPlans]);

  const loadItems = useCallback(async (plan: MealPlan) => {
    const map: Record<string, MealPlanItem[]> = {};
    await Promise.all(
      plan.days.map(async (day) => {
        const items = await fetchMealPlanItems(ncpId, plan.id, day.id);
        map[`${day.day_of_week}-${day.meal_type}`] = items;
      })
    );
    setItemsByDayMeal(map);
  }, [ncpId]);

  useEffect(() => {
    if (activePlan) loadItems(activePlan);
  }, [activePlan, loadItems]);

  const handleCreatePlan = async () => {
    setCreatingPlan(true);
    try {
      const monday = getThisMonday();
      const plan = await createMealPlan(ncpId, { week_start_date: monday, generation_type: 'manual' });
      setPlans((p) => [plan, ...p]);
      setActivePlan(plan);
    } finally {
      setCreatingPlan(false);
    }
  };

  const openPicker = (dayId: number, mealType: string) => {
    setPickerTarget({ dayId, mealType });
    setPickerOpen(true);
    setPickerTab('library');
    setLibraryQuery(''); setLibraryResults([]);
    setUsdaQuery('');    setUsdaResults([]);
    setRecipeQuery('');  setRecipeResults([]);
  };

  const searchLibrary = async (q: string) => {
    setLibraryQuery(q);
    if (q.length < 2) { setLibraryResults([]); return; }
    setPickerLoading(true);
    try {
      const res = await fetchFoodItems(q);
      setLibraryResults(res.data);
    } finally { setPickerLoading(false); }
  };

  const searchUsdaFoods = async (q: string) => {
    setUsdaQuery(q);
    if (q.length < 2) { setUsdaResults([]); return; }
    setPickerLoading(true);
    try {
      const res = await searchUsda(q);
      setUsdaResults(res);
    } finally { setPickerLoading(false); }
  };

  const addFromLibrary = async (food: FoodItem) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.id);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, {
        food_item_id: food.id, quantity: 1, unit: 'serving',
      });
      const key = keyForDayId(pickerTarget.dayId, activePlan);
      if (key) setItemsByDayMeal((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
    } finally { setAdding(null); }
  };

  const searchRecipes = async (q: string) => {
    setRecipeQuery(q);
    if (q.length < 2) { setRecipeResults([]); return; }
    setPickerLoading(true);
    try {
      const res = await fetchRecipes(q);
      setRecipeResults(res.data);
    } finally { setPickerLoading(false); }
  };

  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(`recipe-${recipe.id}`);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, {
        recipe_id: recipe.id, quantity: 1, unit: 'serving',
      });
      const key = keyForDayId(pickerTarget.dayId, activePlan);
      if (key) setItemsByDayMeal((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
    } finally { setAdding(null); }
  };

  const addFromUsda = async (food: UsdaSearchResult) => {
    if (!pickerTarget || !activePlan) return;
    setAdding(food.fdc_id);
    try {
      const item = await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, {
        fdc_id: String(food.fdc_id), quantity: 100, unit: 'g',
      });
      const key = keyForDayId(pickerTarget.dayId, activePlan);
      if (key) setItemsByDayMeal((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
    } finally { setAdding(null); }
  };

  const removeItem = async (dayId: number, itemId: number, mealType: string) => {
    if (!activePlan) return;
    await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    const key = keyForDayId(dayId, activePlan);
    if (key) setItemsByDayMeal((prev) => ({
      ...prev,
      [key]: (prev[key] ?? []).filter((i) => i.id !== itemId),
    }));
  };

  const saveToLibrary = async (item: MealPlanItem) => {
    if (!item.fdc_id) return;
    setSavingToLibrary(item.fdc_id);
    try {
      await importUsdaFood(parseInt(item.fdc_id));
    } finally { setSavingToLibrary(null); }
  };

  const dayTotals = (day: string, plan: MealPlan) => {
    let cal = 0, prot = 0, carb = 0, fat = 0;
    MEAL_TYPES.forEach((mt) => {
      (itemsByDayMeal[`${day}-${mt}`] ?? []).forEach((item) => {
        const s = item.nutrient_snapshot;
        if (!s) return;
        // Scale nutrients by actual quantity vs the base serving_size in the snapshot
        const scale = s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
        cal  += s.calories * scale;
        prot += s.protein  * scale;
        carb += s.carbs    * scale;
        fat  += s.fat      * scale;
      });
    });
    return { cal: Math.round(cal), prot: Math.round(prot), carb: Math.round(carb), fat: Math.round(fat) };
  };

  if (isPlaceholder) return <PlaceholderState />;

  return (
    <div className="space-y-6 font-sans">
      <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
        <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
        <span className="text-zinc-300">/</span>
        <span className="font-bold text-zinc-650">NCP Cycle</span>
        <span className="text-zinc-300">/</span>
        <span className="font-bold text-zinc-650">Nutrition Intervention</span>
      </div>

      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600" />
          Step 3: Nutrition Intervention
        </h2>
      </div>

      {/* Tab bar */}
      <div className="flex gap-1 border-b border-zinc-200">
        {(['goals','mealplan'] as const).map((t) => (
          <button key={t} onClick={() => setTab(t)}
            className={`px-4 py-2 text-xs font-bold uppercase tracking-wider border-b-2 transition-colors cursor-pointer ${
              tab === t ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-zinc-400 hover:text-zinc-600'
            }`}>
            {t === 'goals' ? 'Intervention Goals' : 'Meal Plan'}
          </button>
        ))}
      </div>

      {tab === 'goals' && <InterventionGoalsTab />}

      {tab === 'mealplan' && (
        <div className="space-y-5">
          {/* Plan selector / create */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              {plans.map((p) => (
                <button key={p.id} onClick={() => setActivePlan(p)}
                  className={`px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    activePlan?.id === p.id
                      ? 'bg-emerald-600 text-white border-emerald-600'
                      : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-400'
                  }`}>
                  Week of {p.week_start_date}
                </button>
              ))}
              {loadingPlans && <Loader2 className="h-3.5 w-3.5 animate-spin text-zinc-400" />}
            </div>
            <Button variant="primary" loading={creatingPlan} onClick={handleCreatePlan} className="w-auto px-4 py-2 text-xs">
              <Plus className="h-3.5 w-3.5 mr-1" /> New Week Plan
            </Button>
          </div>

          {!activePlan && !loadingPlans && (
            <div className="bg-zinc-50 border border-zinc-200 rounded-2xl p-10 text-center">
              <p className="text-xs text-zinc-400">No meal plans yet. Create one above.</p>
            </div>
          )}

          {activePlan && (
            <>
              {/* Day selector */}
              <div className="flex gap-1 flex-wrap">
                {DAYS.map((d) => {
                  const t = dayTotals(d, activePlan);
                  return (
                    <button key={d} onClick={() => setSelectedDay(d)}
                      className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer ${
                        selectedDay === d
                          ? 'bg-emerald-600 text-white border-emerald-600'
                          : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
                      }`}>
                      <span className="block">{d.slice(0,3)}</span>
                      {t.cal > 0 && <span className="block font-normal opacity-80">{t.cal} kcal</span>}
                    </button>
                  );
                })}
              </div>

              {/* Day totals bar */}
              {(() => { const t = dayTotals(selectedDay, activePlan); return t.cal > 0 ? (
                <div className="flex gap-4 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-xl text-xs">
                  <Macro label="Energy" value={t.cal} unit="kcal" />
                  <Macro label="Protein" value={t.prot} unit="g" />
                  <Macro label="Carbs" value={t.carb} unit="g" />
                  <Macro label="Fat" value={t.fat} unit="g" />
                </div>
              ) : null; })()}

              {/* Meal slots for selected day */}
              <div className="space-y-3">
                {MEAL_TYPES.map((mt) => {
                  const day = activePlan.days.find((d) => d.day_of_week === selectedDay && d.meal_type === mt);
                  if (!day) return null;
                  const items = itemsByDayMeal[`${selectedDay}-${mt}`] ?? [];
                  return (
                    <div key={mt} className="bg-white border border-zinc-200 rounded-2xl p-4 space-y-2 shadow-sm">
                      <div className="flex items-center justify-between">
                        <h4 className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">{MEAL_LABELS[mt]}</h4>
                        <button onClick={() => openPicker(day.id, mt)}
                          className="flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-800 cursor-pointer">
                          <Plus className="h-3 w-3" /> Add Food
                        </button>
                      </div>
                      {items.length === 0 && (
                        <p className="text-[10px] text-zinc-300 italic">No foods added</p>
                      )}
                      {items.map((item) => (
                        <MealItemRow key={item.id} item={item}
                          onRemove={() => removeItem(day.id, item.id, mt)}
                          onSaveToLibrary={() => saveToLibrary(item)}
                          savingToLibrary={savingToLibrary === item.fdc_id}
                        />
                      ))}
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>
      )}

      {/* Food Picker Modal */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between p-4 border-b border-zinc-100">
              <h3 className="text-sm font-extrabold text-zinc-900">Add Food</h3>
              <button onClick={() => setPickerOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>

            {/* Picker tabs */}
            <div className="flex gap-1 px-4 pt-3 flex-wrap">
              {([
                { key: 'library',  label: 'Library',     icon: <Database className="h-3 w-3 inline mr-1" /> },
                { key: 'recipes',  label: 'Recipes',      icon: <Salad className="h-3 w-3 inline mr-1" /> },
                { key: 'usda',     label: 'USDA Search',  icon: <Leaf className="h-3 w-3 inline mr-1" /> },
              ] as const).map(({ key, label, icon }) => (
                <button key={key} onClick={() => setPickerTab(key)}
                  className={`px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    pickerTab === key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-zinc-50 text-zinc-500 border-zinc-200'
                  }`}>
                  {icon}{label}
                </button>
              ))}
            </div>

            <div className="p-4 space-y-3 overflow-y-auto flex-1">
              {pickerTab === 'library' ? (
                <>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={libraryQuery}
                      onChange={(e) => searchLibrary(e.target.value)}
                      placeholder="Search food library..." autoFocus
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {libraryResults.map((food) => (
                    <div key={food.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div>
                        <p className="text-xs font-semibold text-zinc-800">{food.name}</p>
                        <p className="text-[10px] text-zinc-400">{food.calories} kcal · P {food.protein}g · C {food.carbs}g · F {food.fat}g</p>
                      </div>
                      <Button variant="primary" loading={adding === food.id} onClick={() => addFromLibrary(food)}
                        className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                    </div>
                  ))}
                  {libraryQuery.length >= 2 && !pickerLoading && libraryResults.length === 0 && (
                    <p className="text-[10px] text-zinc-400 text-center">No results in library.</p>
                  )}
                </>
              ) : (
                <>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={usdaQuery}
                      onChange={(e) => searchUsdaFoods(e.target.value)}
                      placeholder="Search USDA database..." autoFocus
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
                  <p className="text-[9px] text-zinc-400">Foods added from USDA are not saved to your library.</p>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {usdaResults.map((food) => (
                    <div key={food.fdc_id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div>
                        <p className="text-xs font-semibold text-zinc-800">{food.name}</p>
                        <p className="text-[10px] text-zinc-400">{food.calories} kcal · P {food.protein}g · C {food.carbs}g · F {food.fat}g</p>
                      </div>
                      <Button variant="primary" loading={adding === food.fdc_id} onClick={() => addFromUsda(food)}
                        className="w-auto px-3 py-1.5 text-[10px]">Add to Plan</Button>
                    </div>
                  ))}
                  {usdaQuery.length >= 2 && !pickerLoading && usdaResults.length === 0 && (
                    <p className="text-[10px] text-zinc-400 text-center">No USDA results found.</p>
                  )}
                </>
              )}

              {pickerTab === 'recipes' && (
                <>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                    <input type="text" value={recipeQuery}
                      onChange={(e) => searchRecipes(e.target.value)}
                      placeholder="Search recipes..." autoFocus
                      className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
                  </div>
                  {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                  {recipeResults.map((recipe) => (
                    <div key={recipe.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                      <div>
                        <p className="text-xs font-semibold text-zinc-800">{recipe.name}</p>
                        <p className="text-[10px] text-zinc-400">
                          {recipe.total_calories} kcal · P {recipe.total_protein}g · C {recipe.total_carbs}g · F {recipe.total_fat}g
                          {recipe.servings ? ` · ${recipe.servings} servings` : ''}
                        </p>
                      </div>
                      <Button variant="primary" loading={adding === `recipe-${recipe.id}`} onClick={() => addFromRecipe(recipe)}
                        className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                    </div>
                  ))}
                  {recipeQuery.length >= 2 && !pickerLoading && recipeResults.length === 0 && (
                    <p className="text-[10px] text-zinc-400 text-center">No recipes found.</p>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function MealItemRow({ item, onRemove, onSaveToLibrary, savingToLibrary }: {
  item: MealPlanItem;
  onRemove: () => void;
  onSaveToLibrary: () => void;
  savingToLibrary: boolean;
}) {
  const s = item.nutrient_snapshot;
  return (
    <div className="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-zinc-50 group">
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-1.5">
          <span className="text-xs font-medium text-zinc-800 truncate">{s?.name ?? 'Unknown food'}</span>
          {item.source === 'usda' && (
            <span className="flex-shrink-0 text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded-full uppercase tracking-wider">USDA</span>
          )}
        </div>
        {s && (
          <p className="text-[10px] text-zinc-400">{item.quantity}{item.unit} · {Math.round(s.calories)} kcal · P {s.protein}g · C {s.carbs}g · F {s.fat}g</p>
        )}
      </div>
      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        {item.source === 'usda' && (
          <button onClick={onSaveToLibrary} disabled={savingToLibrary} title="Save to Library"
            className="p-1.5 rounded-lg text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer transition-colors">
            {savingToLibrary ? <Loader2 className="h-3 w-3 animate-spin" /> : <BookmarkPlus className="h-3 w-3" />}
          </button>
        )}
        <button onClick={onRemove} title="Remove"
          className="p-1.5 rounded-lg text-zinc-400 hover:text-red-600 hover:bg-red-50 cursor-pointer transition-colors">
          <Trash2 className="h-3 w-3" />
        </button>
      </div>
    </div>
  );
}

function Macro({ label, value, unit }: { label: string; value: number; unit: string }) {
  return (
    <div>
      <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label}</p>
      <p className="text-sm font-extrabold text-zinc-900">{value}<span className="text-[9px] font-normal text-zinc-500 ml-0.5">{unit}</span></p>
    </div>
  );
}

function InterventionGoalsTab() {
  return (
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div className="lg:col-span-2 bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
        <h3 className="text-sm font-bold text-zinc-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <Salad className="h-4 w-4 text-emerald-600" /> Intervention Formulation & Targets
        </h3>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-center">
          {['Energy Target','Protein','Carbs','Fat'].map((label) => (
            <div key={label} className="bg-white border border-zinc-200 p-2.5 rounded-lg">
              <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider block">{label}</span>
              <span className="text-sm font-extrabold text-zinc-800">-- {label === 'Energy Target' ? 'kcal' : 'g'}</span>
            </div>
          ))}
        </div>
        <p className="text-xs text-zinc-400 mt-4">Intervention goal editor coming soon.</p>
      </div>
    </div>
  );
}

function PlaceholderState() {
  return (
    <div className="space-y-6 font-sans">
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" /> Step 3: Nutrition Intervention
        </h2>
      </div>
      <div className="bg-white border border-zinc-250 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <User className="h-8 w-8" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">
          Navigate to the NCP Patients directory and select a patient to start their Nutrition Care Process.
        </p>
        <div className="mt-6">
          <Link href="/ncp/patients"
            className="inline-flex px-4 py-2.5 bg-zinc-950 hover:bg-zinc-900 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors">
            Go to Patients Directory
          </Link>
        </div>
      </div>
    </div>
  );
}

function getThisMonday(): string {
  const d = new Date();
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return d.toISOString().split('T')[0];
}

function keyForDayId(dayId: number, plan: MealPlan): string | null {
  const day = plan.days.find((d) => d.id === dayId);
  return day ? `${day.day_of_week}-${day.meal_type}` : null;
}
```

- [ ] **Step 2: Start dev server and verify**

```bash
cd frontend && npm run dev
```

Navigate to a patient's NCP → Intervention page. Verify:
- "Intervention Goals" tab shows the existing placeholder content
- "Meal Plan" tab shows "New Week Plan" button
- Clicking "New Week Plan" creates a plan (API call to POST /api/rnd/ncp-records/.../meal-plans)
- Day pills appear (Mon–Sun)
- Meal slots show "Add Food" button
- Clicking "Add Food" opens the picker modal
- Library tab searches food-items, USDA tab searches USDA
- Adding a library food or USDA food adds a row to the slot with macro summary
- USDA-sourced items show "USDA" badge and bookmark icon (Save to Library)
- Removing an item removes the row
- Daily macro totals update in the day pills

- [ ] **Step 3: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/page.tsx
git commit -m "feat: replace intervention placeholder with full meal plan builder — library + live USDA food picker"
```

---

## Also needed: Next.js proxy routes for meal plans (GET/POST)

Check if `frontend/app/api/rnd/ncp-records/[ncpId]/meal-plans/route.ts` exists. If not, create it before Task 6 Step 2:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const BACKEND = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpId: string }> };

async function forward(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${BACKEND}/api/rnd/${path}`, {
    method: req.method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: req.method !== 'GET' ? await req.text() : undefined,
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}

export async function GET(req: NextRequest, { params }: Ctx) {
  const { ncpId } = await params;
  return forward(req, `ncp-records/${ncpId}/meal-plans`);
}

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpId } = await params;
  return forward(req, `ncp-records/${ncpId}/meal-plans`);
}
```

---

## Execution Order

1. Task 1 — Food library full nutrient display (frontend, standalone, zero deps)
2. Task 2 — Migration + model update (backend DB change)
3. Task 3 — USDA preview endpoint (backend, TDD)
4. Task 4 — MealPlanItemController (backend, TDD)
5. Task 5 — Next.js proxies + services (frontend, no UI yet)
6. Task 6 — Meal plan builder page (frontend, needs Tasks 4+5)

## Workflow Tokens
- Plan: `artifacts/superpowers/plan.md` (this file)
- Execution: `artifacts/superpowers/execution.md`
- Review: `artifacts/superpowers/review.md`
- Finish: `artifacts/superpowers/finish.md`
