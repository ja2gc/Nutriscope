# App-Wide Audit Trail — Backend (Spec 5, Part 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Subject-centric change history — every edit to a sensitive model records who/when/what-changed via `spatie/laravel-activitylog`, with **clinical models logging field names only (no PHI values)** and **operational models logging full before/after**. Plus: restrict access logging to mutations, expose a per-record history endpoint, and correct the security doc.

**Architecture:** A shared `AuditsChanges` trait wraps spatie's `LogsActivity` — logs the model's `fillable` allow-list, `logOnlyDirty`, `dontSubmitEmptyLogs`, log name `audit`. Clinical models set `$auditRedactValues = true`; the trait's `tapActivity()` then strips before/after **values** while keeping the changed **field names** (Decision A — keeps PHI out of `activity_log`). Causer auto-captured from the auth user (User gets `CausesActivity`). `AuditMiddleware` drops GET noise (Decision B). A small `ActivityController` returns a subject's history for the high-value models first (Decision D: Patient, PurchaseOrder, Inventory).

**Tech Stack:** Laravel 11, `spatie/laravel-activitylog ^4.12` (installed; `activity_log` table migrated), PHPUnit (sqlite).

---

## Spec reference

`docs/superpowers/specs/2026-06-12-audit-trail-design.md`. Decisions **A** (clinical fields-only) + **B** (mutations-only) locked. **C** (retention) → use spatie's built-in `php artisan activitylog:clean` + documented window (no custom job now). **D** (surfacing breadth) → history endpoint for Patient, PurchaseOrder, Inventory this round; other models are instrumented (logging) but their endpoints/UI come later. **Frontend History panel = Part 2 (separate plan).**

## Conventions

Work on `main`; commits authored by jared only (git config = `jared <jaredabriol2@gmail.com>`), **NO `Co-Authored-By`**, no `--author`. One test file: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php`. Full: `php artisan test` (baseline: 2 flaky pre-existing NCP `'piece'` failures, unrelated).

## File structure

| File | Responsibility | Action |
|------|----------------|--------|
| `app/Models/Concerns/AuditsChanges.php` | shared trait: spatie config + value redaction | **Create** |
| `app/Models/User.php` | `use CausesActivity` (causer relations) | Modify |
| operational models (`PurchaseOrder`, `ShoppingList`, `FsItem`, `Inventory`, `FoodServiceRecipe`, `MenuCycle`, `Budget`, `MealPrepLog`) | `use AuditsChanges` (full values) | Modify |
| clinical models (`Patient`, `NcpRecord`, `Assessment`, `Diagnosis`, `Intervention`, `Monitoring`, `MealPlan`) | `use AuditsChanges` + `$auditRedactValues = true` | Modify |
| `app/Http/Middleware/AuditMiddleware.php` | skip non-mutating methods | Modify |
| `app/Http/Controllers/ActivityController.php` | per-subject history endpoint | **Create** |
| `routes/api.php` | activity routes (Patient / PO / Inventory) | Modify |
| `docs/security/security.md` | correct the audit claim | Modify |
| `tests/Feature/AuditTrailTest.php` | trait, redaction, no-op, endpoint, middleware | **Create** |

---

## Task 1: The `AuditsChanges` trait

**Files:**
- Create: `backend/app/Models/Concerns/AuditsChanges.php`
- Modify: `backend/app/Models/Inventory.php` (first operational subject) + `backend/app/Models/User.php`
- Test: `backend/tests/Feature/AuditTrailTest.php`

- [ ] **Step 1: Write the failing test (operational, full values)**

Create `backend/tests/Feature/AuditTrailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\FsItem;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $fss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fss = User::factory()->create(['role' => 'FSS', 'password' => Hash::make('password')]);
    }

    public function test_operational_edit_logs_full_values_with_causer(): void
    {
        $this->actingAs($this->fss);
        $fs = FsItem::factory()->create();
        $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);

        $inv->update(['quantity_in_stock' => 25]);

        $activity = Activity::where('subject_type', Inventory::class)->where('subject_id', $inv->id)
            ->where('event', 'updated')->latest()->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->fss->id, $activity->causer_id);
        $this->assertEquals(25, (float) $activity->properties['attributes']['quantity_in_stock']);
        $this->assertEquals(10, (float) $activity->properties['old']['quantity_in_stock']);
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_operational_edit_logs_full_values_with_causer`
Expected: FAIL — no activity (trait not applied).

- [ ] **Step 3: Create the trait**

Create `backend/app/Models/Concerns/AuditsChanges.php`:

```php
<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared audit instrumentation (Spec 5). Logs the model's fillable allow-list,
 * only-dirty, no empty logs, under the 'audit' log name; causer is the auth user.
 *
 * Clinical models set `protected bool $auditRedactValues = true;` — we then strip
 * the before/after VALUES (keeping the changed field NAMES) so PHI never lands in
 * activity_log (Decision A). Operational models log full values.
 */
trait AuditsChanges
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getFillable())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('audit');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if (! ($this->auditRedactValues ?? false)) {
            return;
        }

        $props = $activity->properties;
        foreach (['attributes', 'old'] as $bag) {
            if (! isset($props[$bag]) || ! is_array($props[$bag])) {
                continue;
            }
            $props[$bag] = array_map(fn () => '••• redacted', $props[$bag]);
        }
        $activity->properties = $props;
    }
}
```

> spatie 4.x: `tapActivity(Activity $activity, string $eventName)` is the documented hook; `properties` is a Collection — assigning an array back is fine (it re-casts). Verify the contract path with `grep -rn "function tapActivity" vendor/spatie/laravel-activitylog/src`.

- [ ] **Step 4: Apply to Inventory + User**

In `backend/app/Models/Inventory.php`, add `use App\Models\Concerns\AuditsChanges;` (top) and add the trait to the `use` inside the class (e.g. `use HasFactory, AuditsChanges;`).

In `backend/app/Models/User.php`, add `use Spatie\Activitylog\Traits\CausesActivity;` and add `CausesActivity` to the class `use` list (`use HasApiTokens, HasFactory, Notifiable, SoftDeletes, CausesActivity;`).

- [ ] **Step 5: Run it — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_operational_edit_logs_full_values_with_causer`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Models/Concerns/AuditsChanges.php backend/app/Models/Inventory.php backend/app/Models/User.php backend/tests/Feature/AuditTrailTest.php
git commit -m "feat(audit): AuditsChanges trait + instrument Inventory + User causer"
```

---

## Task 2: Clinical redaction + no-op guard

**Files:**
- Modify: `backend/app/Models/Patient.php` (representative clinical model)
- Test: `backend/tests/Feature/AuditTrailTest.php`

- [ ] **Step 1: Write the failing tests**

Add to `AuditTrailTest` (add `use App\Models\Patient;` at top):

```php
public function test_clinical_edit_logs_field_names_but_redacts_values(): void
{
    $rnd = User::factory()->create(['role' => 'RND', 'password' => Hash::make('password')]);
    $this->actingAs($rnd);

    $patient = Patient::factory()->create();
    $field = collect($patient->getFillable())->first(fn ($f) => $f !== 'rnd_user_id' && $patient->{$f} !== null) ?? $patient->getFillable()[0];
    $patient->update([$field => 'CHANGED-PHI-VALUE']);

    $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Patient::class)
        ->where('subject_id', $patient->id)->where('event', 'updated')->latest()->first();

    $this->assertNotNull($activity);
    $this->assertArrayHasKey($field, $activity->properties['attributes']); // field name kept
    $this->assertSame('••• redacted', $activity->properties['attributes'][$field]); // value stripped
    $this->assertStringNotContainsString('CHANGED-PHI-VALUE', json_encode($activity->properties));
}

public function test_noop_save_writes_no_activity(): void
{
    $this->actingAs($this->fss);
    $fs = FsItem::factory()->create();
    $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);
    \Spatie\Activitylog\Models\Activity::query()->delete();

    $inv->update(['quantity_in_stock' => 10]); // same value → not dirty

    $this->assertSame(0, \Spatie\Activitylog\Models\Activity::where('subject_type', Inventory::class)->where('event', 'updated')->count());
}
```

> The clinical test picks a real fillable field. If `Patient` has a non-string column first, the `'CHANGED-PHI-VALUE'` assignment may type-error — pick a string field explicitly (e.g. `'full_name'` or whatever exists; check `grep -n "fillable" -A8 app/Models/Patient.php`).

- [ ] **Step 2: Run — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_clinical_edit_logs_field_names_but_redacts_values`
Expected: FAIL — Patient not instrumented.

- [ ] **Step 3: Instrument Patient with redaction**

In `backend/app/Models/Patient.php`, add `use App\Models\Concerns\AuditsChanges;`, add `AuditsChanges` to the class `use`, and add the property:

```php
    /** Clinical model — log which fields changed, never the PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;
```

- [ ] **Step 4: Run — expect pass (both new tests)**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php`
Expected: all pass (operational full-value, clinical redacted, no-op silent).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Models/Patient.php backend/tests/Feature/AuditTrailTest.php
git commit -m "feat(audit): clinical models log field names only (PHI redacted)"
```

---

## Task 3: Instrument the remaining models

**Files:** the remaining operational + clinical models.

- [ ] **Step 1: Operational models — full values**

For each of `PurchaseOrder`, `ShoppingList`, `FsItem`, `FoodServiceRecipe`, `MenuCycle`, `Budget`, `MealPrepLog`: add `use App\Models\Concerns\AuditsChanges;` (import) and add `AuditsChanges` to the class `use` line. No redaction property.

- [ ] **Step 2: Clinical models — redacted**

For each of `NcpRecord`, `Assessment`, `Diagnosis`, `Intervention`, `Monitoring`, `MealPlan`: add the import + trait **and** `protected bool $auditRedactValues = true;`.

> Verify each class name/path with `ls app/Models | grep -iE "ncprecord|assessment|diagnosis|intervention|monitoring|mealplan"`. If a model already `use`s `LogsActivity` directly, remove that to avoid a trait conflict (only `AuditsChanges` should bring it in).

- [ ] **Step 3: Smoke-test one of each newly added**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php`
Expected: still green (existing tests exercise the trait; adding it to more models can't break them). Optionally add a quick `PurchaseOrder` update assertion mirroring Task 1.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Models
git commit -m "feat(audit): instrument remaining operational + clinical models"
```

---

## Task 4: Access logging — mutations only (Decision B)

**Files:**
- Modify: `backend/app/Http/Middleware/AuditMiddleware.php`
- Test: `backend/tests/Feature/AuditTrailTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_access_log_skips_reads_logs_mutations(): void
{
    $this->actingAs($this->fss);

    \Spatie\Activitylog\Models\Activity::query()->delete();
    $this->getJson('/api/fss/inventory');   // read → no access log
    $accessAfterGet = \Spatie\Activitylog\Models\Activity::where('log_name', 'audit')
        ->where('description', 'like', 'Accessed%')->count();
    $this->assertSame(0, $accessAfterGet);

    $fs = FsItem::factory()->create();
    $this->postJson('/api/fss/inventory', ['fs_item_id' => $fs->id, 'quantity_in_stock' => 5, 'unit' => 'g']); // mutation
    $accessAfterPost = \Spatie\Activitylog\Models\Activity::where('log_name', 'audit')
        ->where('description', 'like', 'Accessed%')->count();
    $this->assertGreaterThanOrEqual(1, $accessAfterPost);
}
```

> Confirm the inventory store route + payload shape (`grep -n "inventory" routes/api.php`; check `StoreInventoryRequest`). Adjust the POST body to satisfy validation, or assert against any known mutating endpoint. The point is GET→0, mutation→≥1 access log.

- [ ] **Step 2: Run — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_access_log_skips_reads_logs_mutations`
Expected: FAIL — current middleware logs the GET too.

- [ ] **Step 3: Restrict the middleware**

In `AuditMiddleware::handle`, guard the access log by HTTP method:

```php
        $response = $next($request);

        // Decision B (Spec 5): log mutations only — routine GET reads are noise.
        if ($request->user() && ! $request->isMethodSafe()) {
            activity($logName)
                ->causedBy($request->user())
                ->withProperties([
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                ])
                ->log("Accessed " . $request->path());
        }

        return $response;
```

(`Request::isMethodSafe()` is true for GET/HEAD/OPTIONS/TRACE.)

- [ ] **Step 4: Run — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_access_log_skips_reads_logs_mutations`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Middleware/AuditMiddleware.php backend/tests/Feature/AuditTrailTest.php
git commit -m "feat(audit): access log records mutations only, drops GET noise"
```

---

## Task 5: Per-record history endpoint (Decision D: Patient, PO, Inventory)

**Files:**
- Create: `backend/app/Http/Controllers/ActivityController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/AuditTrailTest.php`

- [ ] **Step 1: Write the failing test**

```php
public function test_subject_history_endpoint_returns_that_records_changes(): void
{
    $this->actingAs($this->fss);
    $fs = FsItem::factory()->create();
    $inv = Inventory::factory()->create(['fs_item_id' => $fs->id, 'quantity_in_stock' => 10]);
    $inv->update(['quantity_in_stock' => 25]);

    $other = Inventory::factory()->create(['fs_item_id' => FsItem::factory()->create()->id, 'quantity_in_stock' => 1]);
    $other->update(['quantity_in_stock' => 2]);

    $res = $this->getJson("/api/fss/inventory/{$inv->id}/activity");
    $res->assertOk();

    $events = collect($res->json('data'));
    $this->assertGreaterThanOrEqual(1, $events->count());
    $this->assertTrue($events->every(fn ($e) => $e['subject_id'] === $inv->id)); // only this subject
}
```

- [ ] **Step 2: Run — expect failure**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_subject_history_endpoint_returns_that_records_changes`
Expected: FAIL — no route.

- [ ] **Step 3: Create the controller**

Create `backend/app/Http/Controllers/ActivityController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Per-record change history (Spec 5). Returns the audit timeline for one subject
 * model — who, when, what changed (values already PHI-redacted at write time for
 * clinical models). Authorization rides on the route group's role middleware.
 */
class ActivityController extends Controller
{
    public function forSubject(Model $subject): JsonResponse
    {
        $items = Activity::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('causer')
            ->latest()->limit(100)->get()
            ->map(fn (Activity $a) => [
                'id'          => $a->id,
                'event'       => $a->event,
                'description' => $a->description,
                'subject_id'  => $a->subject_id,
                'causer'      => $a->causer?->name ?? 'system',
                'changes'     => [
                    'old' => $a->properties['old'] ?? [],
                    'new' => $a->properties['attributes'] ?? [],
                ],
                'created_at'  => $a->created_at,
            ]);

        return response()->json(['data' => $items]);
    }
}
```

- [ ] **Step 4: Add the routes (Patient / PO / Inventory)**

In `routes/api.php`, import `use App\Http\Controllers\ActivityController;`. In the **FSS** group add:

```php
    Route::get('inventory/{inventory}/activity', [ActivityController::class, 'forSubject']);
    Route::get('purchase-orders/{purchase_order}/activity', [ActivityController::class, 'forSubject']);
```

In the **RND** group (where patient routes live) add:

```php
    Route::get('patients/{patient}/activity', [ActivityController::class, 'forSubject']);
```

> The route-model-binding variable name must match the apiResource param (`{inventory}`, `{purchase_order}`, `{patient}`). `forSubject(Model $subject)` works because Laravel injects the bound model as the single route parameter regardless of name — verify by running the test; if binding doesn't inject as `$subject`, type-hint the concrete models in three thin methods instead.

- [ ] **Step 5: Run — expect pass**

Run: `php vendor/bin/phpunit tests/Feature/AuditTrailTest.php --filter test_subject_history_endpoint_returns_that_records_changes`
Expected: PASS. If it fails on binding, split into `inventory(Inventory $inventory)`, `purchaseOrder(PurchaseOrder $purchaseOrder)`, `patient(Patient $patient)` methods each calling a shared private `history($model)`.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/ActivityController.php backend/routes/api.php backend/tests/Feature/AuditTrailTest.php
git commit -m "feat(audit): per-record history endpoint (inventory, PO, patient)"
```

---

## Task 6: Correct security.md + document retention

**Files:**
- Modify: `docs/security/security.md` (the audit-logging claim, ~line 13)

- [ ] **Step 1: Fix the claim**

Replace the inaccurate line with an accurate description:

```
- **Audit logging:** change-level history via spatie/laravel-activitylog on sensitive
  clinical and food-service models — created/updated/deleted with causer and dirty
  field diffs. Clinical models log changed field NAMES only (PHI values redacted);
  operational models log full before/after. Access logging records mutating requests
  (POST/PUT/PATCH/DELETE). NOTE: activity_log is an app-level trail, not a tamper-proof
  forensic store. Retention: prune with `php artisan activitylog:clean`
  (configure `activitylog.delete_records_older_than_days`).
```

> Read the surrounding lines first and match the doc's bullet style. If the file uses different phrasing/section headers, adapt.

- [ ] **Step 2: Commit**

```bash
git add docs/security/security.md
git commit -m "docs(security): correct audit-logging claim to match implementation"
```

---

## Task 7: Full-suite regression

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`
Expected: all green except the 2 known flaky NCP `'piece'` failures. **Watch for new failures** — adding `LogsActivity` to many models means any test that creates/updates them now also writes activity rows; that should be harmless, but a test asserting exact row counts on `activity_log` (unlikely) could shift. Fix any fallout.

- [ ] **Step 2: Commit any fixes** (if needed; otherwise skip)

---

## Self-review notes (author)

- **Spec coverage:** §3.1 instrument models → Tasks 1–3. §3.2 causer → User `CausesActivity` (system-sentinel for job/AI actions deferred — documented as a known gap; controller-driven mutations get the auth causer). §3.3 read API → Task 5 (UI = Part 2). §3.4 + Decision B mutations-only → Task 4. Decision A clinical redaction → Task 2 (`tapActivity`). Decision C retention → spatie `activitylog:clean` documented (Task 6). Decision D → endpoint on Patient/PO/Inventory (Task 5). security.md → Task 6. ✓
- **Deferred (named):** frontend History panel (Part 2); system-sentinel causer for queued/AI actions (null causer renders as "system" in the endpoint already, so no null gaps in the UI — acceptable interim); retention auto-schedule (command exists, scheduling left to ops).
- **No placeholders:** full code per step; the "verify field/route/binding" notes are real checks, not vague TODOs.
- **Risk:** `logOnly(getFillable())` may include bulky/JSON fillable columns (e.g. report `parameters`); acceptable for an internal trail. Redaction is all-or-nothing per clinical model (Decision A is fields-only, so that's correct). If a clinical model has a genuinely non-sensitive field worth keeping in cleartext, that's a future refinement, not MVP.
```
