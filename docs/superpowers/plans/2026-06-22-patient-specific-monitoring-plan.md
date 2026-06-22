# Patient-Specific Monitoring — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. TDD throughout (failing test → watch fail → minimal pass). Backend changes follow `backend/.agents/skills/laravel-best-practices`. Steps use checkbox syntax.

**Goal:** Build a backend `MonitoringPlanService` that derives each patient's tracked-indicator set (flagged-abnormal labs + prescription + goal + PES-implied + calculated anthropometrics), seeds the assessment as "Visit 1", and serves it at one endpoint that the visit form, charts, tracker, and AI all consume.

**Architecture:** Approach A (backend single source of truth). Reuses `NutritionPrescriptionService` (anthro formulas) and a new shared `LabFlagService` (sex-aware lab ranges, extracted from `AiDiagnosisController`).

**Spec:** `docs/superpowers/specs/2026-06-22-patient-specific-monitoring-design.md`

---

### Task 1: `LabFlagService` (shared lab ranges + flagging) — TDD

**Files:** Create `backend/app/Services/LabFlagService.php`; Test `backend/tests/Feature/LabFlagServiceTest.php`; Modify `backend/app/Http/Controllers/RND/AiDiagnosisController.php`.

- [ ] **Step 1 — failing test** (`LabFlagServiceTest`):

```php
<?php
namespace Tests\Feature;

use App\Services\LabFlagService;
use Tests\TestCase;

class LabFlagServiceTest extends TestCase
{
    public function test_flags_low_high_and_normal_with_sex_ranges(): void
    {
        $svc = new LabFlagService();

        $flags = $svc->flag(['albumin' => 2.8, 'glucose' => 90, 'hemoglobin' => 13.0], 'Male');

        $this->assertSame('LOW', $flags['albumin']['status']);
        $this->assertSame(2.8, $flags['albumin']['value']);
        $this->assertArrayNotHasKey('glucose', $flags);          // in range → not flagged
        $this->assertSame('LOW', $flags['hemoglobin']['status']); // 13.0 < male 13.5
    }

    public function test_female_hemoglobin_range_differs(): void
    {
        $svc = new LabFlagService();
        $flags = $svc->flag(['hemoglobin' => 13.0], 'Female');
        $this->assertArrayNotHasKey('hemoglobin', $flags);        // 13.0 normal for female (>=12.0)
    }
}
```

- [ ] **Step 2 — run, verify RED:** `cd backend && php artisan test --filter LabFlagServiceTest` → FAIL (class missing).

- [ ] **Step 3 — implement** `LabFlagService` with the exact range table from `AiDiagnosisController::aiSuggest` (albumin, hemoglobin, hematocrit, glucose, hba1c, bun, creatinine, sodium, potassium, calcium, phosphate, cholesterol, ldl, hdl, triglycerides; sex-aware for hemoglobin/hematocrit/creatinine/hdl). Public API:

```php
/** @return array<string,array{low:?float,high:?float}> */
public function ranges(?string $sex = null): array { /* sex-aware table */ }

/**
 * @param array<string,mixed> $labs
 * @return array<string,array{value:float,status:string}>  only out-of-range keys
 */
public function flag(array $labs, ?string $sex = null): array { /* LOW/HIGH */ }
```

- [ ] **Step 4 — run, verify GREEN:** `php artisan test --filter LabFlagServiceTest` → PASS.

- [ ] **Step 5 — refactor `AiDiagnosisController`** to inject `LabFlagService` and replace the inline `$ranges`/foreach with `$flagged = $this->labFlags->flag($labs, $sex);` (preserve the existing `abnormal_labs` shape `{value,status}`). Constructor: `public function __construct(private AIService $aiService, private LabFlagService $labFlags) {}`.

- [ ] **Step 6 — run AI diagnosis tests:** `php artisan test --filter AiDiagnosis` → PASS (behavior unchanged).

- [ ] **Step 7 — commit:** `feat(rnd): extract shared LabFlagService from AiDiagnosisController`.

---

### Task 2: `MonitoringPlanService` derivation — TDD

**Files:** Create `backend/app/Services/MonitoringPlanService.php`; Test `backend/tests/Feature/MonitoringPlanServiceTest.php`. Reuse `LabFlagService`, `NutritionPrescriptionService`, and `MonitoringSummaryService` status logic.

- [ ] **Step 1 — make status reusable:** in `MonitoringSummaryService`, change `private function metricStatus(...)` to `public static function metricStatus(...)` (it has no `$this` use beyond `$ranges` arg — verify and pass `LAB_RANGES` default). No behavior change.

- [ ] **Step 2 — failing test** (`MonitoringPlanServiceTest`): create patient (Male), NcpRecord, Assessment with `biochemicalData` (albumin 2.8 = abnormal, weight 50, height 170, bmi computed), Intervention (goal_type `renal_diet`, energy_kcal 1800, protein_g 60), two Monitorings. Assert:

```php
$plan = app(\App\Services\MonitoringPlanService::class)->build($ncp);

// Visit labelling
$this->assertSame('Visit 1', $plan['visits'][0]['label']);
$this->assertSame('assessment', $plan['visits'][0]['type']);
$this->assertSame('Follow-up 1', $plan['visits'][1]['label']);

// Albumin tracked (flagged_abnormal + renal goal), baseline seeded at Visit 1
$albumin = collect($plan['indicators'])->firstWhere('key', 'albumin');
$this->assertNotNull($albumin);
$this->assertContains('flagged_abnormal', $albumin['sources']);
$this->assertSame('Visit 1', $albumin['series'][0]['visit']);
$this->assertSame(2.8, $albumin['series'][0]['value']);

// Intake indicator from prescription with target
$energy = collect($plan['indicators'])->firstWhere('key', 'energy_kcal');
$this->assertSame('intake', $energy['category']);
$this->assertSame(1800.0, $energy['target']);

// PES context present
$this->assertIsArray($plan['pes_statements']);
```

- [ ] **Step 3 — run, verify RED.**

- [ ] **Step 4 — implement `MonitoringPlanService::build(NcpRecord): array`:**
  - Eager-load `assessment.biochemicalData`, `diagnoses`, `intervention`, `monitorings` (prevent N+1 per db-performance rule).
  - `visits` = `[{label:'Visit 1',type:'assessment',date:assessment date}]` + monitorings ordered oldest→newest as `Follow-up {n}`.
  - **Labs:** keys = union of `labFlags->flag(biochem,sex)` keys ∪ `GOAL_LAB_FLAGS[goal_type]` (port the map as a PHP const) ∪ PES-keyword labs (PHP const map per spec). For each: reference from `labFlags->ranges($sex)`, baseline = biochem value (Visit 1), per-visit from monitoring `lab_values[key]`, status via `MonitoringSummaryService::metricStatus`, sources tagged.
  - **Anthro:** weight, bmi, %ibw. Baseline from assessment (`weight`,`bmi`,`ibw_percentage`); per-visit weight/bmi from monitoring; %ibw recomputed via `NutritionPrescriptionService` (`percentIbw(weight, ibw(height,sex))`). source `calculated`.
  - **Intake:** for each set prescription nutrient (`energy_kcal,protein_g,carbs_g,fat_g,fluid_ml` + `displayed_nutrients`): target = prescribed, per-visit actual from monitoring `lab_values`, status from %-of-target (<90 not_met-ish→`not_met`, 90-110 `met`, >110 `met`/over). source `prescription`. No Visit 1 baseline.
  - Dedup indicators by `key`, merging `sources`.
  - Return `visits, pes_statements (diagnoses pluck pes_statement, limit 3), goal_type, nutritional_status, indicators`.

- [ ] **Step 5 — run, verify GREEN.**

- [ ] **Step 6 — commit:** `feat(rnd): MonitoringPlanService derives per-patient tracked indicators`.

---

### Task 3: `GET /ncp-records/{ncpRecord}/monitoring-plan` endpoint — TDD

**Files:** Create `backend/app/Http/Controllers/RND/MonitoringPlanController.php`; Modify `backend/routes/api.php`; Test `backend/tests/Feature/MonitoringPlanEndpointTest.php`.

- [ ] **Step 1 — failing feature test:** RND `actingAs` GETs the route → 200, JSON has `data.indicators`, `data.visits`. A non-RND role → 403 (match sibling route gating). Cross-cycle scoping respected.

- [ ] **Step 2 — run, verify RED.**

- [ ] **Step 3 — implement controller** (thin, <10 lines per routing rule): inject `MonitoringPlanService`, return `response()->json(['data' => $service->build($ncpRecord)])`. Add route inside the existing RND `auth:sanctum` group next to other `ncp-records/{ncpRecord}/...` monitoring routes, using implicit model binding.

- [ ] **Step 4 — run, verify GREEN.**

- [ ] **Step 5 — commit:** `feat(rnd): monitoring-plan endpoint`.

---

### Task 4: AI course-of-action over full trajectory — TDD

**Files:** Modify `backend/app/Services/AIService.php` (`narrateMonitoring`); the controller that builds its input (the monitoring AI-review endpoint); Test `backend/tests/Feature/AiServiceTest.php`.

- [ ] **Step 1 — failing test:** with `Http::fake` + `Http::preventStrayRequests`, call the monitoring AI review for an NCP with a tracked plan; assert (a) returns a non-null narrative string, (b) the request body sent to Anthropic contains the tracked indicator keys and `pes_statements`, and (c) does NOT contain untracked lab keys. (Use `Http::assertSent` to inspect payload.)

- [ ] **Step 2 — run, verify RED.**

- [ ] **Step 3 — implement:** feed `narrateMonitoring` the plan trajectory (tracked indicators' series) + `pes_statements` + `goal_type` + prescription targets + latest statuses. Update the prompt to: course of action across visits, cite specific indicator trends, reference the PES problem, 2–4 sentences, use only provided indicators. Keep `timeout`/`connectTimeout`, token-cap enforcement, and per-visit-count caching.

- [ ] **Step 4 — run, verify GREEN; run full AI suite** `php artisan test --filter "AiService|AiUsageLimit|Monitoring"`.

- [ ] **Step 5 — commit:** `feat(rnd): monitoring AI suggests course of action from full visit trajectory`.

---

### Task 5: Frontend service + types — vitest TDD for mappers

**Files:** Modify `frontend/services/monitoringService.ts`; Create `frontend/services/monitoringPlan.ts` (pure mappers) + `frontend/services/monitoringPlan.test.ts`; Create Next proxy route `frontend/app/api/rnd/ncp-records/[ncpRecordId]/monitoring-plan/route.ts`.

- [ ] **Step 1 — failing vitest** for a pure `planToChartSeries(indicator)` mapper: given an indicator with a Visit 1 + 2 follow-ups, returns chart points `[{visit, value}]` in order, nulls preserved.

- [ ] **Step 2 — run, verify RED** (`npx vitest run services/monitoringPlan.test.ts`).

- [ ] **Step 3 — implement** `MonitoringPlan` TS types + `planToChartSeries` + `fetchMonitoringPlan(ncpId)` in `monitoringService.ts`; add the Next proxy route (mirror existing `screening-document/route.ts` proxy: forward bearer cookie, GET).

- [ ] **Step 4 — run, verify GREEN.**

- [ ] **Step 5 — commit:** `feat(rnd): frontend monitoring-plan fetch + chart mappers`.

---

### Task 6: `CarePlanHeader` + page wiring

**Files:** Create `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/CarePlanHeader.tsx`; Modify monitoring `page.tsx`.

- [ ] **Step 1** — `CarePlanHeader` renders PES statements, goal, prescription targets, flagged-abnormality chips from the plan. No test (presentational); verify via typecheck + preview.
- [ ] **Step 2** — `page.tsx`: add `fetchMonitoringPlan(ncpId)` to the load; pass plan to `CarePlanHeader`, `VisitTrendsChart`, `GoalProgressTracker`, `LogVisitForm`. Render `CarePlanHeader` at top of the Progress Trends tab.
- [ ] **Step 3** — `npx tsc --noEmit` clean.
- [ ] **Step 4 — commit:** `feat(rnd): care-plan header + plan-driven monitoring page`.

---

### Task 7: `LogVisitForm`, `VisitTrendsChart`, `GoalProgressTracker` consume the plan

**Files:** Modify the three `_components`.

- [ ] **Step 1 — `LogVisitForm`:** field list = plan tracked indicators (labs + weight + prescribed intake) instead of `GOAL_LAB_FLAGS`. Weight auto-recomputes BMI/%IBW (existing `nutritionCalculations`).
- [ ] **Step 2 — `VisitTrendsChart`:** plot `indicator.series` (Visit 1 seeded). Reference lines from `indicator.reference`; target line from `indicator.target`.
- [ ] **Step 3 — `GoalProgressTracker`:** rows = plan indicators (baseline/current/reference/status/sparkline).
- [ ] **Step 4** — `npx tsc --noEmit` + `npx vitest run` clean.
- [ ] **Step 5 — commit:** `feat(rnd): plan-driven visit form, trends, and progress tracker`.

---

### Task 8: Full regression + preview verification

- [ ] **Step 1** — `cd backend && php artisan test` → all pass.
- [ ] **Step 2** — `cd frontend && npx vitest run && npx tsc --noEmit` → clean.
- [ ] **Step 3** — preview the monitoring page for a seeded patient; confirm Visit 1 baseline appears in charts, tracked set matches PES/prescription/flagged, AI review button returns a course-of-action narrative.
