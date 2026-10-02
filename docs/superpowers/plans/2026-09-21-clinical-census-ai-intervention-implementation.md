# Clinical Census, Prescription, PES Drafting, and Dated Intervention Plans Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Project rules prohibit subagents/worktrees unless the owner separately authorizes them. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve the deployed clinical/census/meal-planning baseline and replace intervention revision history with server-paginated, dated, complete Intervention Plans owned by the Intervention page, plus clearer Monitoring records and one printable report per saved plan.

**Architecture:** Treat every `interventions` row as one immutable complete plan with zero or one menu plan. An NCP cycle has many plans and resolves the newest by `created_at DESC, id DESC`; no current/inactive status exists. The Intervention page owns list/view/create. New plans prefill from the newest plan while prescription calculations use the newest complete Monitoring calculation snapshot, falling back to the original Assessment for legacy gaps. Monitoring never creates or edits interventions. Reports resolve the plan's sole menu plan. Existing revision snapshots and extra legacy menu plans are forward-migrated without losing data, then the revision workflow is retired.

**Tech Stack:** Laravel 13/PHP 8.4/PHPUnit 12, MySQL-compatible forward migrations, Next.js 16/React 19/TypeScript/Vitest, Dompdf, existing Anthropic adapter, Docker Compose/GitHub deployment, deployed browser QA.

**Approved design authority:** `docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md`

**Global Constraints:**

- Tasks 1–7 are already deployed baseline. Do not reimplement or reopen them; run only their regression coverage.
- Use TDD for every new behavior. Inventory failures before batch-fixing. Commit only task files.
- No subagents or worktrees unless the owner separately authorizes them.
- No `is_demo` field and no `AI_DIAGNOSIS_REAL_PATIENTS_ENABLED` gate. PES prompting stays compact YAML; provider output stays validated compact JSON.
- One saved row equals one complete immutable Intervention Plan. No revision/version/current/inactive UI or API semantics.
- Newest means `created_at DESC, id DESC`. Every collection is server-paginated.
- Creating a plan copies intervention fields only. Never copy a menu plan automatically.
- Each saved Intervention Plan owns zero or one menu plan. Remove Add Another/selection behavior; the database and API reject a second row.
- Each new Monitoring record stores the complete effective calculation/menu-safety snapshot, prefilling from the latest snapshot and falling back to Assessment. Monitoring never overwrites Assessment or owns intervention actions.
- A Nutrition Intervention Plan PDF exists only for a saved plan with its one menu plan, uses the original report structure, includes the plan date, and renders on 8.5 × 13 inch long bond paper.
- Existing prepared report bytes remain frozen.
- Reuse established components and restrained styling. No decorative icons/cards, redundant summaries, obvious notes, or helper text. Use the established help tooltip only for genuinely error-preventing information.
- Do not add dependencies unless existing libraries cannot meet a verified requirement.

**Review Focus:**

- Migration idempotency: deduplicate equivalent baseline/current snapshots, preserve timestamps, relink every meal plan, and leave no orphan.
- Atomic/concurrent creation: no partial plan; simultaneous saves remain distinct and deterministically ordered.
- Monitoring context completeness: new visits persist every required effective calculation value; legacy null/absent fields fall back per field and never erase Assessment baseline.
- Authorization and identity: public UUID routes must reject cross-patient/cross-NCP access.
- Reports: unsaved/no-meal-plan states cannot generate PDFs; prepared archives remain byte-identical.

---

## Canonical fresh-session prompt

Copy this whole block into one fresh Codex session:

```text
Work in C:\Users\User\Documents\Nutriscope\Nutriscope. Implement the approved clinical/census/AI/intervention plan at:
docs/superpowers/plans/2026-09-21-clinical-census-ai-intervention-implementation.md

Read `.agents/AGENTS.md` first, then only the rule files, nested AGENTS files, and skills it routes for this scope. Read the approved design completely:
docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md

Inspect current Git and code before editing. Preserve every unrelated dirty file; `docs/logic/intervention-goals.md` contains owner-approved work that the plan explicitly reconciles. Execute the implementation plan in order using TDD and its verification/deployment gates. Do not reopen settled decisions or redo the older deployed fixes listed in the plan. Keep UI changes minimal and progressively disclosed.

Finish the complete plan: focused and full checks, real PDF inspection, existing docs/storyboard reconciliation, task-only commits, push `main`, and revision parity. Then ask the owner to trigger deployment and stop. After the owner reports success, verify deployed health/revision and run native-browser QA with fictional data. Inventory errors before batch-fixing and rerun the full scoped sweep until clean. Report local, pushed, deployed, and live-accepted evidence separately.
```

## Fixed scope and non-goals

- Final census values are centralized once: `Cardiovascular`, `Renal`, `Diabetes`, `Obesity`, `Malnutrition`, `Surgery / Trauma`, `Liver`, `Cancer`, `Pregnancy / Lactation`, `Other`. Selecting `Other` reveals `Specify category`; its free text remains clinical context while census output aggregates one `Other` bucket.
- No extra non-maternal maintenance additions. Goal/stage output is final; maternal modifier is calculated automatically.
- No new food recall, Filipino/restaurant database, OCR reconstruction, recipe display field, preparation steps, menu-plan revision entity, or USDA note.
- No automatic category inference from physician text, PES diagnosis, or intervention goal.
- No AI diagnosis eligibility from free-form model reasoning. Deterministic rules choose candidates; model only drafts supplied eligible PES wording.
- No external AI access rule based on demo flags. Keep the existing authorized clinical AI route, deterministic eligibility, de-identification, bounded prompts, strict response validation, and audit controls.

## File map

New focused backend files:

- `backend/app/Support/PrimaryDiagnosisCategory.php` — one category allow-list and labels.
- `backend/app/Support/WeightChangePeriod.php` — legacy parser and formatter.
- `backend/app/Services/Diagnosis/PesEvidenceBuilder.php` — compact de-identified evidence packet.
- `backend/app/Services/Diagnosis/PesRuleCatalog.php` — versioned, sourced deterministic rule cards.
- `backend/app/Services/Diagnosis/PesEligibilityService.php` — zero-to-three eligible candidates.
- `backend/app/Services/Diagnosis/PesSuggestionService.php` — fingerprint/cache/provider validation/dismissal orchestration.
- `backend/app/Services/InterventionPlanService.php` — atomic complete-plan creation and legacy migration support.
- `backend/app/Services/InterventionCalculationContextService.php` — complete Monitoring calculation snapshots, legacy fallback, and source metadata.
- Forward migrations named in Tasks 2, 7, and 8.

Main existing files changed:

- Assessment model, requests, resource, factory, seeder, frontend service/page/calculation inputs.
- NutritionPrescriptionService, InterventionController, TypeScript calculation mirror/panel.
- DemographicCensusGenerator, monthly rebuild service, report view/tests.
- MealPlanService, meal-plan UI target tracker/tests.
- AIService/AiDiagnosisController/request/service/UI/tests.
- Intervention/Monitoring/MealPlan models, requests, resources, controllers/services/UI/tests.
- PatientMenuPlanGenerator, NcpSummaryGenerator, both Blade reports/tests.
- Existing clinical docs, Help/flowcharts, and canonical RND storyboard.

## Delivered baseline: Tasks 1–7

Tasks 1–7 below document already deployed work. Do not repeat their implementation or commits. During final verification, run their focused regression tests and fix only newly observed regressions with a failing test first. Start new implementation at Task 8.

## Task 1: Freeze sourced clinical and UI contracts (delivered; regression only)

**Files:**
- Modify: `docs/logic/intervention-goals.md`
- Modify: `docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md`
- Test: `backend/tests/Unit/AssessmentPhase5Test.php`
- Test: `frontend/app/(rnd)/ncp/assessment-page-ux.test.ts`

- [ ] **Step 1: Inspect current dirty state and source authority**

Run:

```powershell
git status --short
git diff -- docs/logic/intervention-goals.md
rg -n "pregnan|lactat|stress|TEE|PAL|fluid|stage" docs/logic/intervention-goals.md backend/app/Services/NutritionPrescriptionService.php frontend/lib/nutritionCalculations.ts
```

Expected: owner changes remain visible; no file is overwritten or staged.

- [ ] **Step 2: Verify exact primary-source passages before constants**

Open FNRI-DOST PDRI 2015 Summary Tables, revised September 2018, and verify exact table/page for pregnancy/lactation energy, protein, and water additions. Open Academy NCP overview/diagnosis pages for PES workflow boundaries. Record exact issuer/title/version/page or section in `docs/logic/intervention-goals.md`; do not copy large proprietary terminology passages.

- [ ] **Step 3: Reconcile authority wording**

Make `docs/logic/intervention-goals.md` state these exact runtime contracts:

```text
TEE = BMR x PAL
pregnant_t1 = +0 kcal, +27 g protein
pregnant_t2 = +300 kcal, +27 g protein
pregnant_t3 = +300 kcal, +27 g protein
lactating = +500 kcal, +27 g protein
fluid guidance is displayed but excluded from meal scaling
```

State that goal stages are progressively disclosed and goal/stage prescription is final except the automatic maternal modifier. Preserve valid existing source material and history.

- [ ] **Step 4: Run documentation integrity checks**

Run:

```powershell
rg -n "T[B]D|T[O]DO|placeholde[r]" docs/logic/intervention-goals.md docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md
git diff --check -- docs/logic/intervention-goals.md docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md
```

Expected: no placeholder hit and no whitespace error.

- [ ] **Step 5: Commit only reconciled authority files**

```powershell
git add -- docs/logic/intervention-goals.md docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md
git diff --cached --check
git commit -m "docs: lock clinical workflow contracts"
```

## Task 2: Add structured Assessment, category, and maternal data (delivered; regression only)

**Files:**
- Create: `backend/database/migrations/2026_09_21_000001_add_clinical_classification_to_assessments.php`
- Create: `backend/database/migrations/2026_09_21_000011_remove_demo_marker_from_patients.php`
- Create: `backend/app/Support/PrimaryDiagnosisCategory.php`
- Create: `backend/app/Support/WeightChangePeriod.php`
- Modify: `backend/app/Models/Assessment.php`
- Modify: `backend/app/Models/Patient.php`
- Modify: `backend/app/Http/Requests/RND/StoreAssessmentRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateAssessmentRequest.php`
- Modify: `backend/app/Http/Resources/AssessmentResource.php`
- Modify: `backend/database/factories/AssessmentFactory.php`
- Test: `backend/tests/Feature/AssessmentSaveTest.php`
- Test: `backend/tests/Unit/AssessmentModelTest.php`

- [ ] **Step 1: Write failing Assessment contract tests**

Cover:

```php
$allowed = [
    'Cardiovascular',
    'Renal',
    'Diabetes',
    'Obesity',
    'Malnutrition',
    'Surgery / Trauma',
    'Liver',
    'Cancer',
    'Pregnancy / Lactation',
    'Other',
];
```

Assert category required on new save; `Other` requires nonblank details; non-Other clears details; weight value/unit are both present or both absent; value is positive integer; unit is `weeks|months`; maternal status is one of `none|pregnant_t1|pregnant_t2|pregnant_t3|pregnant_unspecified|lactating`; legacy records remain readable; `stress_factor` is ignored by new writes.

- [ ] **Step 2: Run focused tests and confirm failure**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/AssessmentSaveTest.php tests/Unit/AssessmentModelTest.php
```

Expected: failures for missing columns/support contracts.

- [ ] **Step 3: Generate and implement forward migrations**

Use the Laravel generator for the Assessment migration. The deployed cleanup migration removes the superseded temporary demo column; do not recreate it:

```powershell
php artisan make:migration add_clinical_classification_to_assessments --table=assessments --no-interaction
```

Assessment migration adds nullable `weight_change_period_value` unsigned small integer, nullable `weight_change_period_unit` string(10), nullable indexed `primary_diagnosis_category` string(40), nullable `primary_diagnosis_other` string(160), and expands/migrates maternal status without dropping legacy columns. Backfill only safely parseable weight periods and map `pregnant` to `pregnant_unspecified`; do not infer category from physician text. The deployed cleanup removes the superseded temporary patient demo column.

- [ ] **Step 4: Implement centralized support classes**

`PrimaryDiagnosisCategory` exposes `values(): array`, `isAllowed(?string): bool`, and constants for all ten values. `WeightChangePeriod` exposes `parseLegacy(?string): ?array` and `format(?int, ?string): ?string`, accepting only explicit forms such as `3 weeks`, `1 month`, and `6 months`.

- [ ] **Step 5: Wire model, validation, resource, and factory**

Add fields/casts/audit field names. Keep PHI value redaction. Update requests with conditional rules and explicit attribute labels. Update factory defaults without making historical category inference. No demo marker belongs in the patient model, requests, resources, or factories.

- [ ] **Step 6: Run migration and focused tests**

```powershell
php artisan migrate --no-interaction
php artisan test --compact tests/Feature/AssessmentSaveTest.php tests/Unit/AssessmentModelTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit schema and Assessment contract**

```powershell
git add -- backend/database/migrations/2026_09_21_000001_add_clinical_classification_to_assessments.php backend/database/migrations/2026_09_21_000011_remove_demo_marker_from_patients.php backend/app/Support/PrimaryDiagnosisCategory.php backend/app/Support/WeightChangePeriod.php backend/app/Models/Assessment.php backend/app/Models/Patient.php backend/app/Http/Requests/RND/StoreAssessmentRequest.php backend/app/Http/Requests/RND/UpdateAssessmentRequest.php backend/app/Http/Resources/AssessmentResource.php backend/database/factories/AssessmentFactory.php backend/tests/Feature/AssessmentSaveTest.php backend/tests/Unit/AssessmentModelTest.php
git commit -m "feat(clinical): structure assessment classification"
```

## Task 3: Update Assessment UI with minimal progressive controls (delivered; regression only)

**Files:**
- Modify: `frontend/services/assessmentService.ts`
- Modify: `frontend/lib/assessmentCalculationInputs.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/assessment-page-ux.test.ts`
- Modify: `frontend/lib/assessmentSummary.ts`
- Modify: `frontend/lib/assessmentSummary.test.ts`

- [ ] **Step 1: Write failing UI contract tests**

Assert one weight-duration row with number + unit, no second `weight_loss_period` text input, no fixed `3 months`, no visible stress-factor control, required primary category beside but separate from physician diagnosis, conditional Other details, and maternal select with trimester choices. Assert physician diagnosis text stays unchanged.

- [ ] **Step 2: Run focused frontend tests and confirm failure**

```powershell
Set-Location frontend
npm test -- "app/(rnd)/ncp/assessment-page-ux.test.ts" lib/assessmentSummary.test.ts
```

Expected: contract failures for old fields.

- [ ] **Step 3: Update service types and payload**

Use exact fields:

```ts
weight_change_period_value: number | null;
weight_change_period_unit: "weeks" | "months" | null;
primary_diagnosis_category: PrimaryDiagnosisCategory | null;
primary_diagnosis_other: string | null;
pregnancy_lactation_status:
  | "none"
  | "pregnant_t1"
  | "pregnant_t2"
  | "pregnant_t3"
  | "pregnant_unspecified"
  | "lactating";
```

Do not send `stress_factor` or new writes to `weight_loss_period`.

- [ ] **Step 4: Implement compact Assessment controls**

Place category in Clinical / Referral and Screening. Show Other details only after `Other`. Place quantity and unit in one responsive row in Intake / weight history. Show maternal choices only in existing clinical inputs, default `None`, and show one confirmation warning for `pregnant_unspecified`. Keep summary concise.

- [ ] **Step 5: Run focused tests and type check**

```powershell
npm test -- "app/(rnd)/ncp/assessment-page-ux.test.ts" lib/assessmentSummary.test.ts
npx tsc --noEmit
```

Expected: PASS.

- [ ] **Step 6: Commit Assessment UI**

```powershell
git add -- services/assessmentService.ts lib/assessmentCalculationInputs.ts "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx" "app/(rnd)/ncp/assessment-page-ux.test.ts" lib/assessmentSummary.ts lib/assessmentSummary.test.ts
git commit -m "feat(clinical): simplify assessment inputs"
```

## Task 4: Rebuild census classification and deterministic fictional data (delivered; regression only)

**Files:**
- Modify: `backend/database/seeders/PatientSeeder.php`
- Modify: `backend/app/Services/Reports/Generators/DemographicCensusGenerator.php`
- Modify: `backend/app/Services/Reports/StoreMonthlyDemographicCensuses.php`
- Modify: `backend/resources/views/reports/demographic-census.blade.php`
- Modify: `backend/database/seeders/ReportTemplateSeeder.php`
- Create: `backend/database/migrations/2026_09_21_000003_backfill_demo_primary_diagnosis_categories.php`
- Modify: `backend/tests/Feature/MonthlyDemographicCensusTest.php`
- Modify: `backend/tests/Unit/Reports/DemographicCensusTest.php`
- Modify: `backend/tests/Feature/PersonNameBackendFlowTest.php`

- [ ] **Step 1: Write failing census/seeder tests**

Assert each non-deleted cycle counts once; category comes from that cycle's Assessment; null legacy value groups as `Unclassified`; free-text physician diagnosis never becomes a bucket; `Other` free text remains private; Maria is `Diabetes`; both Roberto cycles are `Malnutrition`; running PatientSeeder twice creates no duplicate patients/cycles/plans/reports.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/MonthlyDemographicCensusTest.php tests/Unit/Reports/DemographicCensusTest.php tests/Feature/PersonNameBackendFlowTest.php
```

- [ ] **Step 3: Update generator and monthly basis**

Increment `DemographicCensusGenerator::BASIS_VERSION` from 2 to 3. Replace cycle patient `medical_diagnosis` projection with Assessment category fallback `Unclassified` for structured data and compatibility. The later 2026-10-02 owner override removes diagnosis-category and nutritional-status breakdowns from current generated census output; render only the age/sex matrix and **By Risk Level**. Rebuild completed period data only through the existing basis-version path; never mutate prepared report bytes.

- [ ] **Step 4: Seed and narrowly backfill fictional fixture categories**

Set explicit values in PatientSeeder. Migration identifies only stable fictional hospital numbers already owned by the seeder, updates their Assessment categories idempotently, and does not add patient markers, recreate clinical graphs, or touch unknown records.

- [ ] **Step 5: Run seeder twice and inspect graph**

Use confirmed disposable local test/development DB only:

```powershell
php artisan db:seed --class=PatientSeeder --no-interaction
php artisan db:seed --class=PatientSeeder --no-interaction
php artisan test --compact tests/Feature/MonthlyDemographicCensusTest.php tests/Unit/Reports/DemographicCensusTest.php tests/Feature/PersonNameBackendFlowTest.php
```

Expected: stable counts, April cycle present, no duplicates, tests PASS.

- [ ] **Step 6: Commit census and seed changes**

```powershell
git add -- backend/database/seeders/PatientSeeder.php backend/app/Services/Reports/Generators/DemographicCensusGenerator.php backend/app/Services/Reports/StoreMonthlyDemographicCensuses.php backend/resources/views/reports/demographic-census.blade.php backend/database/seeders/ReportTemplateSeeder.php backend/database/migrations/2026_09_21_000003_backfill_demo_primary_diagnosis_categories.php backend/tests/Feature/MonthlyDemographicCensusTest.php backend/tests/Unit/Reports/DemographicCensusTest.php backend/tests/Feature/PersonNameBackendFlowTest.php
git commit -m "feat(reports): classify census by care cycle"
```

## Task 5: Apply sourced maternal modifiers and remove stress workflow (delivered; regression only)

**Files:**
- Modify: `backend/app/Services/NutritionPrescriptionService.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/tests/Unit/AssessmentPhase5Test.php`
- Modify: `backend/tests/Unit/NutritionPrescriptionServiceTest.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`
- Modify: `frontend/lib/nutritionCalculations.ts`
- Modify: `frontend/lib/nutritionCalculations.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx`

- [ ] **Step 1: Write failing golden tests**

For the same baseline prescription assert modifiers `[0,27]`, `[300,27]`, `[300,27]`, and `[500,27]` for T1/T2/T3/lactating. Assert macros are recalculated from final energy/protein; restricted goal fluid remains unchanged; `pregnant_unspecified` blocks maternal auto-fill with a clear validation result; no stress factor changes TEE; goal selector reveals only selected goal stages.

- [ ] **Step 2: Confirm failures in PHP and TypeScript**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/AssessmentPhase5Test.php tests/Unit/NutritionPrescriptionServiceTest.php tests/Feature/NcpInterventionTest.php
Set-Location ../frontend
npm test -- lib/nutritionCalculations.test.ts "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx" "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.test.ts"
```

- [ ] **Step 3: Implement one mirrored modifier contract**

Backend remains authoritative. Apply modifier after goal baseline, recalculate carbohydrate/fat from final values, include structured trace metadata (`baseline`, `modifier`, `final`, `source_key`), and preserve goal fluid. Keep concise exact source comment such as `FNRI_PDRI_2015_REV_2018_SUMMARY_TABLES` linked to the documented page.

- [ ] **Step 4: Reuse calculation disclosure**

Existing Show/Hide panel renders baseline + modifier = final and source label. Do not create another card or expose arithmetic in patient PDF. Preserve progressive stage disclosure.

- [ ] **Step 5: Run focused suites and commit**

Run commands from Step 2; expect PASS. Then:

```powershell
git add -- backend/app/Services/NutritionPrescriptionService.php backend/app/Http/Controllers/RND/InterventionController.php backend/tests/Unit/AssessmentPhase5Test.php backend/tests/Unit/NutritionPrescriptionServiceTest.php backend/tests/Feature/NcpInterventionTest.php frontend/lib/nutritionCalculations.ts frontend/lib/nutritionCalculations.test.ts "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx" "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx" "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx"
git commit -m "feat(clinical): apply maternal prescription modifiers"
```

## Task 6: Remove fluid from meal-plan matching while preserving guidance (delivered; regression only)

**Files:**
- Modify: `backend/app/Services/MealPlanService.php`
- Modify: `backend/tests/Unit/MealPlanAlgorithmTest.php`
- Modify: `backend/tests/Unit/MealPlanServiceTest.php`
- Modify: `backend/tests/Feature/MealPlanControllerTest.php`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx`
- Modify: `frontend/services/mealPlanService.test.ts`

- [ ] **Step 1: Replace old water expectation with failing exclusion tests**

Assert `water` never appears in candidate/scaling targets, variance, reconciliation, `flagged`, or frontend target bars even when `fluid_ml` exists. Assert nutrient snapshots may still retain `water_g`. Assert prescription UI/report still show `Daily fluid guidance` and restricted-fluid wording.

- [ ] **Step 2: Confirm focused failures**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/MealPlanAlgorithmTest.php tests/Unit/MealPlanServiceTest.php tests/Feature/MealPlanControllerTest.php
Set-Location ../frontend
npm test -- services/mealPlanService.test.ts
```

- [ ] **Step 3: Remove only target influence**

Delete fluid insertion into `$targets` and all water branches used only for scoring/variance/reconciliation. Preserve water metadata mapping/storage. Render fluid separately outside `MacroTrackerBar` with text that food listings do not guarantee beverage intake or a fluid limit.

- [ ] **Step 4: Run focused tests and commit**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/MealPlanAlgorithmTest.php tests/Unit/MealPlanServiceTest.php tests/Feature/MealPlanControllerTest.php
Set-Location ../frontend
npm test -- services/mealPlanService.test.ts
git add -- ../backend/app/Services/MealPlanService.php ../backend/tests/Unit/MealPlanAlgorithmTest.php ../backend/tests/Unit/MealPlanServiceTest.php ../backend/tests/Feature/MealPlanControllerTest.php "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx" "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx" services/mealPlanService.test.ts
git commit -m "fix(meal-plans): exclude fluid from scaling"
```

## Task 7: Replace broad AI diagnosis generation with bounded PES drafts (delivered; regression only)

**Files:**
- Create: `backend/app/Services/Diagnosis/PesEvidenceBuilder.php`
- Create: `backend/app/Services/Diagnosis/PesRuleCatalog.php`
- Create: `backend/app/Services/Diagnosis/PesEligibilityService.php`
- Create: `backend/app/Services/Diagnosis/PesSuggestionService.php`
- Create: `backend/database/migrations/2026_09_21_000004_create_pes_suggestion_states.php`
- Create: `backend/app/Models/PesSuggestionState.php`
- Modify: `backend/config/services.php`
- Modify: `backend/.env.example`
- Modify: `backend/app/Services/AIService.php`
- Modify: `backend/app/Http/Controllers/RND/AiDiagnosisController.php`
- Modify: `backend/app/Http/Requests/RND/AiSuggestDiagnosisRequest.php`
- Modify: `backend/tests/Feature/AiServiceTest.php`
- Create: `backend/tests/Feature/PesSuggestionTest.php`
- Create: `backend/tests/Unit/PesEligibilityServiceTest.php`
- Modify: `frontend/services/diagnosisService.ts`
- Modify: `frontend/services/diagnosisService.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/diagnosis-page-ux.test.ts`

- [ ] **Step 1: Define and test rule-card contract**

Use this shape:

```php
[
    'id' => 'unintended_weight_loss_v1',
    'problem_key' => 'approved_existing_problem_key',
    'required_evidence' => ['weight_loss_percentage', 'weight_change_period'],
    'corroborating_evidence' => ['present_diet', 'appetite', 'intake_status'],
    'disqualifiers' => [],
    'source' => [
        'id' => 'verified-source-id',
        'issuer' => 'verified issuer',
        'title' => 'verified title',
        'version' => 'verified version/date',
        'location' => 'verified page/section',
        'url' => 'verified primary URL',
    ],
]
```

Enable only rule families whose exact criteria are verified and fit current structured data: unintended weight loss, overweight/obesity, swallowing/chewing difficulty, altered GI function, explicit food-medication interaction, and predicted suboptimal intake. Malnutrition, altered labs, knowledge, adherence, food insecurity, and unsupported classes remain manual.

- [ ] **Step 2: Write failing eligibility, privacy, cache, and gate tests**

Test positive/negative/missing/disqualified evidence, maximum three, zero-result success, duplicate exclusion, dismissed persistence, fingerprint invalidation, provider malformed content, unknown evidence/source rejection, authorization, no PHI keys, token-bounded compact YAML input, and validated compact JSON output.

- [ ] **Step 3: Confirm tests fail**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/PesEligibilityServiceTest.php tests/Feature/PesSuggestionTest.php tests/Feature/AiServiceTest.php
```

- [ ] **Step 4: Implement persistence and configuration**

`pes_suggestion_states` stores `ncp_record_id`, fingerprint, catalog version, validated response JSON, dismissed candidate IDs, provider metadata without prompt/clinical content, timestamps, and unique `(ncp_record_id, fingerprint, catalog_version)`. Do not add a demo marker or environment gate.

- [ ] **Step 5: Implement deterministic pipeline**

Evidence builder excludes name/code/hospital number/address/physician/attachments/exact DOB and bounds RND summary length. Eligibility selects candidates before AI. AI receives only matching evidence and compact YAML rule cards/instructions. Validator accepts only the specified compact JSON schema and rejects any returned problem/evidence/source not supplied. Cache unchanged fingerprint. Existing audited approval route remains authoritative.

- [ ] **Step 6: Simplify UI**

Keep the existing **AI Review** tab, **AI Suggestions** panel title, and **Generate AI Suggestions** action. Remove numeric confidence and endless Generate behavior. Show at most three cards, paginated two per page, with concise Evidence used plus Edit/Accept/Dismiss. Keep source provenance in the server-side rule contract and comments, not on the draft card. Editing selects the mapped structured checkboxes and preserves only unmatched detail in notes. Show cached state, refresh only after Assessment changed, successful zero-result message, and short external-AI-unavailable message while manual PES stays present. This owner override changes visible naming only; the bounded Assessment-based pipeline remains authoritative.

- [ ] **Step 7: Run focused backend/frontend tests and commit**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/PesEligibilityServiceTest.php tests/Feature/PesSuggestionTest.php tests/Feature/AiServiceTest.php
Set-Location ../frontend
npm test -- services/diagnosisService.test.ts "app/(rnd)/ncp/diagnosis-page-ux.test.ts"
npx tsc --noEmit
git add -- ../backend/app/Services/Diagnosis ../backend/database/migrations/2026_09_21_000004_create_pes_suggestion_states.php ../backend/app/Models/PesSuggestionState.php ../backend/config/services.php ../backend/.env.example ../backend/app/Services/AIService.php ../backend/app/Http/Controllers/RND/AiDiagnosisController.php ../backend/app/Http/Requests/RND/AiSuggestDiagnosisRequest.php ../backend/tests/Feature/AiServiceTest.php ../backend/tests/Feature/PesSuggestionTest.php ../backend/tests/Unit/PesEligibilityServiceTest.php services/diagnosisService.ts services/diagnosisService.test.ts "app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx" "app/(rnd)/ncp/diagnosis-page-ux.test.ts"
git commit -m "feat(clinical): bound assessment PES drafts"
```

## Task 8: Convert revision snapshots into complete dated plans

**Files:**
- Create: `backend/database/migrations/2026_09_28_000001_convert_intervention_revisions_to_complete_plans.php`
- Create: `backend/app/Services/InterventionPlanService.php`
- Modify: `backend/app/Models/NcpRecord.php`
- Modify: `backend/app/Models/Intervention.php`
- Modify: `backend/app/Models/Monitoring.php`
- Modify: `backend/app/Models/MealPlan.php`
- Modify: `backend/database/factories/InterventionFactory.php`
- Modify: `backend/database/seeders/PatientSeeder.php`
- Create: `backend/tests/Feature/InterventionPlanMigrationTest.php`
- Modify: `backend/tests/Feature/InterventionRevisionTest.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`
- Modify: `backend/tests/Feature/MealPlanControllerTest.php`

- [ ] **Step 1: Write failing migration and model invariants**

Cover empty, single-baseline, duplicate-snapshot, multiple-snapshot, and partially linked legacy fixtures. Assert:

- `interventions` gains unique UUID and nullable `source_monitoring_id`; `meal_plans.intervention_id` becomes unique.
- Every unique normalized legacy snapshot becomes one complete Intervention row.
- The original row represents the earliest snapshot and keeps its original `created_at`; later plans use revision `effective_at`, then revision `created_at` as fallback.
- Equivalent initial/current snapshots do not create duplicates.
- Every legacy menu plan is relinked to a complete plan; no menu plan becomes orphaned and no plan owns more than one.
- Multiple legacy menu plans on one snapshot become separate imported complete plans with identical clinical content and preserved menu rows/timestamps.
- Missing legacy snapshot keys remain null unless the snapshot explicitly contains a value; migration never borrows a later value or invents clinical data.
- Running the migration logic twice produces no additional plan.
- New plan rows receive public UUIDs and stable newest ordering by `created_at DESC, id DESC`.
- `NcpRecord::interventions()` returns all plans and `latestIntervention()` returns the deterministic newest one.
- Saved clinical fields cannot be updated in ordinary application code; replacement requires another plan.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/InterventionPlanMigrationTest.php tests/Feature/InterventionRevisionTest.php tests/Feature/NcpInterventionTest.php tests/Feature/MealPlanControllerTest.php
```

- [ ] **Step 3: Implement one forward migration**

Add `uuid` and nullable `source_monitoring_id` to `interventions`; index `(ncp_record_id, created_at, id)`. Process one intervention under a transaction and lock. Normalize snapshots with the model's clinical field allow-list, map duplicate snapshots, update the original row to the earliest snapshot, insert later complete rows, and relink `meal_plans.intervention_id` from `intervention_revision_id`. When multiple legacy menu plans map to one snapshot, keep the first on that plan and create one additional complete imported plan per extra menu plan using the exact same snapshot and that menu plan's `created_at` as the best available plan date. Then add a unique index on `meal_plans.intervention_id`. Verify no orphan, loss, or multi-menu owner before commit. Keep legacy revision table/FKs read-only for rollback evidence in this release; new code must never write them.

- [ ] **Step 4: Implement the complete-plan model and service**

Use `HasPublicId` on Intervention. Replace `NcpRecord::intervention()` application usage with `interventions()` and `latestIntervention()`. Replace `Intervention::mealPlans()` with `mealPlan(): HasOne`. Remove revision mutation hooks and revision relationships from active model behavior. `InterventionPlanService::create(NcpRecord $ncpRecord, array $attributes, User $actor, ?Monitoring $sourceMonitoring = null): Intervention` validates ownership, writes one complete row in an audited transaction, and never clones a menu plan. Concurrent creates remain complete, distinct, and stably ordered.

- [ ] **Step 5: Update factories and deterministic seed graph**

Factories create UUID-backed complete plans. PatientSeeder creates dated plans directly and links each fictional meal plan to its owning plan. Run the seeder twice on a disposable database and assert no duplicate patient/cycle/plan/meal-plan/report graph. Do not add new patient fields merely to support testing.

- [ ] **Step 6: Run focused tests and commit only Task 8**

Run Step 2 plus `php artisan migrate:fresh --seed` twice on the isolated test database; expect PASS and stable counts. Stage named files only:

```powershell
git commit -m "refactor(clinical): model dated intervention plans"
```

## Task 9: Add paginated plan APIs and Monitoring-aware calculation context

**Files:**
- Create: `backend/app/Services/InterventionCalculationContextService.php`
- Create: `backend/app/Http/Resources/InterventionPlanSummaryResource.php`
- Create: `backend/app/Support/MonitoringVisitType.php`
- Create: `backend/database/migrations/2026_09_28_000002_add_calculation_context_to_monitorings.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/app/Http/Controllers/RND/MonitoringController.php`
- Modify: `backend/app/Http/Requests/RND/StoreInterventionRequest.php`
- Modify: `backend/app/Http/Requests/RND/MonitoringRequest.php`
- Modify: `backend/app/Http/Requests/RND/StoreMonitoringRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateMonitoringRequest.php`
- Modify: `backend/app/Http/Resources/InterventionResource.php`
- Modify: `backend/app/Http/Resources/MonitoringResource.php`
- Modify: `backend/app/Services/NutritionPrescriptionService.php`
- Modify: `backend/app/Models/Monitoring.php`
- Modify: `backend/database/factories/MonitoringFactory.php`
- Modify: `backend/app/Http/Controllers/RND/MealPlanController.php`
- Modify: `backend/app/Http/Resources/MealPlanResource.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`
- Modify: `backend/tests/Feature/NcpMonitoringTest.php`
- Modify: `backend/tests/Feature/MealPlanControllerTest.php`
- Create: `backend/tests/Unit/InterventionCalculationContextServiceTest.php`

- [ ] **Step 1: Write failing API, authorization, context, and race tests**

Assert:

- `GET /api/rnd/ncp-records/{ncp}/interventions` is newest-first, server-paginated, and returns plan date/goal/source-monitoring date/meal-plan availability without status or revision language.
- `GET /api/rnd/ncp-records/{ncp}/interventions/{plan}` returns the complete saved plan only when it belongs to that NCP.
- `POST /api/rnd/ncp-records/{ncp}/interventions` creates one complete immutable row, requires an existing diagnosis, records the newest Monitoring as source, and returns 201.
- Failed validation/DB/audit work leaves no partial plan. Concurrent valid requests produce complete distinct plans with stable order.
- Cross-NCP and cross-patient UUIDs return 404/403 without leaking existence.
- Existing singular `GET /intervention` temporarily resolves the latest plan; singular PATCH no longer mutates saved data.
- New Monitoring saves require a complete effective snapshot: observed date/type, weight, height, edema state, conditional dry weight, physical activity level, pregnancy/lactation status, server-derived BMI, allergies, dietary restrictions, and food dislikes. Goal labs/intake remain optional and contextual.
- The first Monitoring context prefills from Assessment; later contexts prefill from the newest prior Monitoring. Legacy absent/null fields fall back per field to Assessment. Age/sex derive from Patient. No context save mutates Assessment.
- Calculation responses include `source_monitoring_id` and `source_monitoring_date` or explicit original-Assessment source. Context building does not persist any Assessment change.
- New menu plans link directly to the requested/owning Intervention Plan, never write `intervention_revision_id`, and a second menu plan for the same Intervention returns 409 even under concurrent requests.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/NcpInterventionTest.php tests/Feature/NcpMonitoringTest.php tests/Feature/MealPlanControllerTest.php tests/Unit/InterventionCalculationContextServiceTest.php
```

- [ ] **Step 3: Implement resource-safe plan routes**

Register literal routes before the `{intervention}` binding:

```text
GET  /api/rnd/ncp-records/{ncpRecord}/interventions
GET  /api/rnd/ncp-records/{ncpRecord}/interventions/latest
GET  /api/rnd/ncp-records/{ncpRecord}/interventions/{intervention}
POST /api/rnd/ncp-records/{ncpRecord}/interventions
POST /api/rnd/ncp-records/{ncpRecord}/interventions/autofill
GET  /api/rnd/ncp-records/{ncpRecord}/monitorings/context
```

Bind Intervention by UUID, then verify `ncp_record_id`. Limit `per_page` to the established pagination policy. List with `created_at DESC, id DESC`. The detail resource exposes UUIDs, complete clinical fields, timestamps, optional source Monitoring UUID/date, and meal-plan metadata; never raw internal IDs or revision data.

- [ ] **Step 4: Persist complete effective Monitoring calculation snapshots**

Add normalized Monitoring fields: `observed_at`, `visit_type`, `height`, `edema_present`, `dry_weight_kg`, `physical_activity_level`, `pregnancy_lactation_status`, `allergies` JSON, `dietary_restrictions`, and `food_dislikes` JSON. Keep weight, derived BMI, labs, intake, symptoms, progress/decision, and `next_monitoring_date`. `MonitoringVisitType` centralizes `scheduled_follow_up`, `inpatient_review`, `discharge_review`, and `unscheduled_follow_up` plus labels. Require `observed_at <= today`; reuse Assessment anthropometric bounds and PAL/maternal allow-lists; require dry weight with edema; validate arrays/dates. The server recalculates BMI from saved effective height/weight. Remove nested intervention-revision input and writes.

`InterventionCalculationContextService::for(NcpRecord $ncpRecord): array` returns the newest complete effective snapshot plus source UUID/date. `prefillForMonitoring()` uses the newest prior snapshot and falls back per field to Assessment for the first/legacy visit. Age/sex always come from Patient. Adapt intervention autofill and meal generation to this context while keeping `NutritionPrescriptionService` pure. Store delegates to `InterventionPlanService`; the server resolves the source Monitoring. Existing appointment/notification/audit behavior remains.

- [ ] **Step 5: Relink meal-plan creation to explicit saved plan**

Require an authorized saved plan UUID wherever a new/manual/generated menu plan is created. Persist `intervention_id`; leave legacy `intervention_revision_id` null. Reject unsaved, foreign-NCP, cross-patient, and second-plan attempts. Enforce one-to-one through both transaction/lock checks and the unique database index; translate the unique violation into the same 409 response.

- [ ] **Step 6: Run focused tests and commit only Task 9**

Run Step 2, `php artisan route:list --path=api/rnd/ncp-records`, and relevant static analysis; expect PASS. Stage named files only:

```powershell
git commit -m "feat(clinical): add dated intervention plan APIs"
```

## Task 10: Reuse the Intervention UI for plan list, read-only view, and creation

**Files:**
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/interventions/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/interventions/[interventionId]/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/interventions/autofill/route.ts`
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/InterventionPlansTab.tsx`
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/InterventionPlanEditor.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- Modify: `frontend/services/interventionService.ts`
- Create: `frontend/services/interventionService.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Modify: existing Intervention tab/form components only as needed for a shared `mode: "edit" | "readonly"` contract
- Create: `frontend/app/(rnd)/ncp/intervention-plans-ui.test.tsx`

- [ ] **Step 1: Write failing service and UI contracts**

Assert:

- A `Plans` tab exists inside the current Intervention page; no intervention list/action appears in Monitoring.
- The list is server-paginated, newest-first, and shows only creation date and goal summary; no current/inactive/version/revision label.
- Selecting a row updates `?plan=<uuid>` and renders the exact established Food and Nutrient Delivery, Education, Counseling, and Goal Planning UI read-only.
- `Create New Intervention Plan` enters `?mode=new`, prefills every intervention field from the newest saved plan, and does not copy its menu plan.
- Calculation UI identifies `Monitoring — <date>` when the backend used a Monitoring record; otherwise it identifies the original Assessment. No obvious explanatory note is added.
- Save posts one complete plan, then selects the returned UUID with clinical fields read-only and create/load/generate controls available only while it has no menu plan. Once one exists, Add Another/selection controls disappear and create endpoints reject a second. Existing edit/regenerate actions must update that sole row rather than insert another. Cancel creates nothing. A failed save creates no list row/report.
- Allergy/exclusion behavior that prevents a meal-generation mistake remains in the established `InfoHint`/help-icon component; it is not repeated as permanent explanatory card copy.
- Keyboard focus, labels, 390/375 px layout, pagination, loading, empty, validation, and API error states work without horizontal overflow.

- [ ] **Step 2: Confirm RED**

```powershell
Set-Location frontend
npm test -- services/interventionService.test.ts "app/(rnd)/ncp/intervention-plans-ui.test.tsx"
```

- [ ] **Step 3: Implement typed service and same-page state**

Define paginated summary/detail/create/context response types. Keep NCP and plan UUIDs opaque. The page owns `plans`, `selectedPlanId`, `mode`, and pagination. Default detail is the newest saved plan; an NCP with no plan opens editable initial creation. Query state must survive reload/back navigation without creating a parallel screen.

- [ ] **Step 4: Extract one editor and make read-only exact**

Move existing plan fields/tabs into `InterventionPlanEditor`. The same component receives saved values and `mode`; read-only clinical mode uses semantic text/disabled-safe controls without hiding information. Older plans expose no mutation actions. Editable new mode starts from latest plan fields, clears identity/timestamps/menu plan, uses the returned calculation context, and preserves current validation. After save, the newest plan may create/load/generate its sole menu plan; after that, only existing edit/regenerate behavior targeting the same row remains. This never unlocks clinical fields or mutates older plans.

- [ ] **Step 5: Implement compact paginated Plans tab**

Use existing card/table/pagination primitives with compact responsive columns. Show date first, goal second, and a clear row action. Do not add decorative icons, colored status cards, summary copy, status badges, or redundant notes. Use the established help tooltip only if source precedence cannot otherwise be understood and an error is likely.

- [ ] **Step 6: Run focused checks and commit only Task 10**

Run Step 2, then:

```powershell
npx tsc --noEmit
npm run lint -- --file "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx"
```

Inspect desktop and 390/375 px using fictional local data. Stage named files only:

```powershell
git commit -m "feat(clinical): add intervention plans workspace"
```

## Task 11: Restructure Monitoring forms and saved visit logs

**Files:**
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/monitorings/context/route.ts`
- Modify: `frontend/services/monitoringService.ts`
- Modify: `frontend/services/monitoringService.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/LogVisitForm.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/EncounterLog.tsx`
- Create: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/MonitoringVisitDetails.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/monitoring-ui.test.tsx`

- [ ] **Step 1: Write failing backend and UI contracts**

Backend contracts from Task 9 already assert Monitoring contains no intervention mutation and remains newest-first/server-paginated. UI tests assert:

- Form order: Visit Context; Recalculation Measurements and Factors; Goal-relevant Labs; Meal Safety, Intake, and Tolerance; Clinical Progress and Decision; Follow-up.
- Visit context contains observed date and visit type.
- Recalculation fields contain weight, height, edema, conditional dry weight, physical activity, pregnancy/lactation, and read-only server-derived BMI.
- Meal safety contains allergies as hard exclusions, dietary restrictions, and food dislikes.
- The form prefills a complete effective snapshot from the context endpoint; the user edits only changes, but the save payload contains all required effective calculation fields.
- Compact responsive columns; conditional fields remain accessible; no obsolete fields are silently dropped.
- Saved rows collapse to date, visit type, key measurement, and decision.
- Expanding a row uses the same read-only section order and omits entirely empty sections.
- Progress Trends remains separate.
- No green visit card, decorative card/icon, NCP cycle code, artificial-sounding heading/copy, intervention action, revision/version label, or redundant page summary.
- Encounter details use the readable patient identifier already established by the patient header; raw internal IDs are never exposed.

- [ ] **Step 2: Confirm RED**

```powershell
Set-Location frontend
npm test -- services/monitoringService.test.ts "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/monitoring-ui.test.tsx"
```

- [ ] **Step 3: Replace revision types with complete context types**

Delete frontend revision snapshot/input/builders and `Open Intervention after saving`. Add typed effective-context fields and `fetchMonitoringContext()`. Keep UUIDs opaque. Preserve all existing lab/intake/summary/trend types that remain clinically valid.

- [ ] **Step 4: Restructure, do not redesign, the form**

Prefill from `GET /monitorings/context`. Group fields in the approved clinical sequence. Require the effective calculation snapshot while revealing goal-specific labs from the newest saved plan/goal using current rules. Preserve validation, units, conditional dry weight, follow-up appointment behavior, and mobile usability. Use compact columns and neutral borders/backgrounds; no new visual language.

- [ ] **Step 5: Build reusable saved-visit details**

`MonitoringVisitDetails` receives a Monitoring record and mode `summary | full`. The summary yields only the four row facts; full mode renders non-empty approved sections. Keep Progress Trends outside this component. Paginate at the API and preserve the existing page size contract.

- [ ] **Step 6: Run focused checks and commit only Task 11**

Run Step 2 plus `npx tsc --noEmit`. Inspect form and logs at desktop/390/375 px. Stage named files only:

```powershell
git commit -m "refactor(monitoring): organize visit records"
```

## Task 12: Render one long-bond Nutrition Intervention Plan per saved plan

**Files:**
- Modify: `backend/app/Services/Reports/ReportBrowser.php`
- Modify: `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php`
- Modify: `backend/app/Services/Reports/Generators/NcpSummaryGenerator.php`
- Modify: `backend/app/Http/Controllers/ReportController.php`
- Modify: `backend/resources/views/reports/patient-menu-plan.blade.php`
- Modify: `backend/resources/views/reports/ncp-summary.blade.php`
- Modify: shared PDF layout/style files that currently define page size
- Modify: `backend/database/seeders/ReportTemplateSeeder.php`
- Modify: `backend/tests/Feature/PatientMenuPlanGeneratorTest.php`
- Modify: `backend/tests/Feature/NcpSummaryReportTest.php`
- Modify: `backend/tests/Unit/PatientMenuPlanViewContractTest.php`
- Modify: `frontend/services/reportService.ts`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Modify: `frontend/components/reports/PatientsNcpTab.tsx`
- Modify: `frontend/components/reports/patient-ncp-navigation.test.ts`

- [ ] **Step 1: Write failing source, archive, and PDF contracts**

Assert:

- Patient report instances contain one `Nutrition Intervention Plan — <creation date>` item per saved plan, newest-first and server-paginated.
- Each instance carries the saved plan UUID/date and, when present, its sole menu-plan UUID; no selection, revision, or version identity is exposed.
- A saved plan without a menu plan is listed but Preview/Download is disabled and prepare/render/export rejects it with a clear 422.
- Unsaved editor state can never appear in Reports.
- The generator resolves the saved Intervention Plan and its sole MenuPlan directly. New requests use the plan UUID; legacy prepared params containing `meal_plan_id` remain readable/frozen.
- NCP Summary renders only the newest complete plan and no revision/history table.
- Existing prepared report bytes and download behavior remain unchanged.
- All PDF templates use `@page { size: 8.5in 13in; }` with existing orientation rules unless a specific report already requires landscape.
- Nutrition Intervention Plan order is identity/prescription, menu plan, education/counseling/barriers/strategies, then portion details. No forced page break moves the menu early; long intervention text may flow onto the portion-details page without overlap or cutoff.
- No-snack meal plans omit AM/PM snack rows/cells rather than rendering blanks.
- Portion details deduplicate repeated identical food/portion entries and use a three-column print grid on long bond paper; fall back only when a measured content-width test proves a specific block cannot fit without clipping.

- [ ] **Step 2: Confirm RED**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/PatientMenuPlanGeneratorTest.php tests/Feature/NcpSummaryReportTest.php tests/Unit/PatientMenuPlanViewContractTest.php
Set-Location ../frontend
npm test -- components/reports/patient-ncp-navigation.test.ts
```

- [ ] **Step 3: Move report instance identity to complete plans**

Paginate plan-backed instances newest-first with the stable tie-break. Validate plan/sole-menu/patient/NCP ownership on prepare, render, export, archive, view, and download paths. Generate on demand only. Never create a prepared report on plan save. New instance params use the plan UUID; preserve existing stored prepared bytes and legacy meal-plan-param interpretation.

- [ ] **Step 4: Update generators and views**

`PatientMenuPlanGenerator` resolves the plan and its `mealPlan(): HasOne`; `NcpSummaryGenerator` resolves `latestIntervention()`. Remove revision labels/tables. Add the plan creation date to Nutrition Intervention Plan content/title metadata. Render identity/prescription first, the menu immediately next, intervention text fields after the menu, and portion details last. Do not force the menu onto a later page merely because text fields are long. Preserve patient-intended content, maternal note, separate fluid guidance, the existing validated carbs/rice separation, snack configurability, maternal templates, and meal-generation algorithms; regression-test rather than redesign them.

- [ ] **Step 5: Apply one long-bond page contract and inspect real PDFs**

Use the PDF skill/tooling. Generate all seven report types through normal application paths, including one plan with snacks and one without. Render every page to PNG and inspect at actual page bounds for:

- exact 8.5 × 13 inch MediaBox;
- clipped/overlapping text, orphan headings, awkward page breaks, blank snack rows, excessive whitespace, and unreadable columns;
- a forced-long education/counseling fixture proving the menu remains before those fields and the text flows safely toward the portion-details pages;
- correct date, plan/meal ownership, prescription, source notes, meal slots, and deduplicated portions.

Record artifact paths and page-by-page results. Fix defects with a failing contract test first, then regenerate the complete set.

- [ ] **Step 6: Run focused checks and commit only Task 12**

Run Step 2, `npx tsc --noEmit`, and PDF acceptance again. Stage named files only:

```powershell
git commit -m "feat(reports): publish dated intervention plans"
```

## Task 13: Retire active revision consumers and reconcile existing documentation

**Files:**
- Modify/Delete only after `rg` proves each is unused: `backend/app/Services/InterventionRevisionService.php`
- Modify/Delete only after `rg` proves each is unused: `backend/app/Models/InterventionRevision.php`
- Modify: remaining backend/frontend references to `intervention_revision` discovered by the sweep
- Modify: `docs/logic/intervention-goals.md`
- Modify: `docs/modules/rnd.md`
- Modify: `docs/FAQ.md`
- Modify: `docs/module-workflow-flowchart.md`
- Modify: `docs/modules/Flowcharts/Clinical Care (NCP) Operations.md`
- Modify: `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`
- Modify: `Storyboarding/RND NCP Video Storyboard.md`

- [ ] **Step 1: Inventory before deleting or editing**

```powershell
rg -n "InterventionRevision|intervention_revision|activeRevision|revision history|Revise intervention|current intervention|inactive intervention|is_demo|AI_DIAGNOSIS_REAL_PATIENTS_ENABLED" backend frontend docs Storyboarding
```

Classify every hit as active runtime, migration/rollback evidence, historical plan/spec, test fixture, or stale documentation. Do not delete the legacy revision table/migrations in this release. Do not rewrite historical migration files.

- [ ] **Step 2: Write/adjust regression tests before removing active code**

Tests must fail if any new plan/meal/monitoring/report write sets `intervention_revision_id`, if active resources expose revision metadata, or if Monitoring offers intervention creation. Confirm legacy migrated data still reads through complete plans.

- [ ] **Step 3: Remove only proven-dead active consumers**

Delete the service/model/factory only when no runtime route, job, seeder, report, resource, or test factory requires it. Keep legacy schema and migration evidence untouched. Remove transitional singular write routes/proxies after the Intervention frontend uses plural routes. Keep singular GET only if an external compatibility test proves it is still required; otherwise remove it too.

- [ ] **Step 4: Reconcile existing docs and storyboard**

Update existing documents only. Describe: dated complete plans under Intervention; newest-first pagination; read-only prior plans; new-plan prefill from newest plan; complete prefilled Monitoring calculation snapshots with Assessment fallback; no menu-plan copy; zero-or-one menu plan per Intervention; no Add Another/picker; Monitoring-only visit records; one report per saved plan; newest-only NCP Summary; long-bond PDFs; menu-before-intervention-text order; compact no-blank-snack output; current bounded AI behavior without demo/env gates. Preserve correct census, maternal, carbs/rice, seeded-template, login, patient-code, audit, and report-performance material. Add every research source used for clinical behavior to `docs/logic/intervention-goals.md`; do not invent new research for settled behavior.

- [ ] **Step 5: Check stale language, Mermaid, links, and formatting**

```powershell
rg -n "revision history|Revise intervention|current intervention|inactive intervention|fictional-demo AI gate|is_demo|AI_DIAGNOSIS_REAL_PATIENTS_ENABLED|Patient Menu Plan|By Diagnosis|Recipe Details|fixed 3 months" docs/modules docs/FAQ.md docs/module-workflow-flowchart.md "Storyboarding/RND NCP Video Storyboard.md"
git diff --check -- docs Storyboarding backend frontend
```

Remaining legacy identifiers must be explicitly historical/internal. Validate Mermaid blocks and referenced paths.

- [ ] **Step 6: Run focused checks and commit only Task 13**

Run intervention, monitoring, meal-plan, report, frontend service/UI, and documentation checks. Stage only inventoried files:

```powershell
git commit -m "docs: reconcile dated intervention workflow"
```

## Task 14: Full verification, delivery, deployment, and live acceptance

**Files:**
- Verify all task files and generated PDFs; change only files required by found defects.

- [ ] **Step 1: Self-review full diff and scope**

```powershell
git status --short
git diff --check
git diff --stat
git log --oneline --decorate -15
```

Confirm unrelated owner files remain untouched/uncommitted. Compare every design acceptance criterion to a test, rendered artifact, or planned live scenario.

Re-open the approved spec, this entire plan, `docs/logic/intervention-goals.md`, and the conversation-added scope captured in them. Audit every earlier claimed accomplishment against authoritative current evidence rather than prior messages. Build a requirement-to-evidence checklist covering Tasks 1–13, seeders, all report types, docs/storyboard, responsive UI, and deployed workflows. Classify each item as proven, contradicted, incomplete, weak/indirect, or missing. Add every contradicted/incomplete/weak/missing item to the working defect inventory before editing; nothing from the earlier audit is silently dropped.

- [ ] **Step 2: Run backend quality gates**

```powershell
Set-Location backend
vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan route:list --path=api/rnd
php artisan config:clear
php artisan route:cache
php artisan route:clear
php artisan config:cache
php artisan config:clear
```

Expected: Pint clean, full suite PASS, route/cache commands succeed.

- [ ] **Step 3: Run frontend quality gates**

```powershell
Set-Location ../frontend
npm test
npx tsc --noEmit
npm run lint
npm run build
```

Expected: all PASS.

- [ ] **Step 4: Re-run deterministic seed and complete PDF acceptance**

On a confirmed disposable local DB, migrate/seed twice and verify stable patient, NCP, plan, meal-plan, template, and report counts. Generate all seven report types through normal application paths. Include dated plans with/without snacks and maternal/non-maternal examples. Render every page, verify 8.5 × 13 inch MediaBox, and record artifact paths plus exact page-by-page pass/fail observations.

- [ ] **Step 5: Regression-test previously completed baseline**

Verify tests still cover random patient code search/header-only display, centered hover logo login panel, absent Patients NCP helper, earliest/current census cycle counts including April, structured Assessment/maternal calculations, bounded compact PES drafting, carbs/rice separation, snack/no-snack and pregnant/lactating seeded templates, fluid-free scaling, top-right Edit actions, lazy/cached report rendering, compact deduplicated portions, and page-break behavior. Do not redo those deployed implementations.

- [ ] **Step 6: Final review and task-only commit**

Use `superpowers:verification-before-completion`. Fix any discovered issue with focused failing test first. Confirm clean task diff and commit any verification fixes. Never stage unrelated files.

- [ ] **Step 7: Push and prove parity**

```powershell
git push origin main
git fetch origin main
git rev-parse HEAD
git rev-parse origin/main
```

Expected: both revisions identical.

- [ ] **Step 8: Record pushed evidence, then hand deployment to the owner**

Report local checks, PDF inspection, commits, pushed SHA, and `HEAD == origin/main`. Ask the owner to trigger the current deployment workflow and stop. Do not wait, poll, or trigger deployment. Resume only after the owner reports deployment success or failure.

- [ ] **Step 9: After owner confirmation, verify deployed release layers**

Inspect the completed workflow and redacted operational state. Verify release job and migrations exited successfully, containers run the pushed revision/images, `/up` is healthy, public app serves the same revision, and logs show no new scoped errors. Never read secrets. Build success alone is insufficient.

- [ ] **Step 10: Run deployed native-browser sweep with fictional data**

Use the Codex in-app/native browser, not the Playwright plugin. Take screenshots only for decisive pass/fail evidence. At `https://nutriscope.live`, test desktop 1440 px and mobile 390/375 px:

1. Existing login branding hover/fit and patient-code search/header baseline.
2. Assessment quantity/unit, category/Other, maternal status, save/reload, validation, no stress control, no fixed three-month text.
3. Census earliest month/current month, cycle counts, age/sex matrix, risk-only breakdown, omitted ward/diagnosis-category/nutritional-status cards, and frozen archived report behavior.
4. Intervention goal/stage progressive disclosure, baseline/modifier/final calculation panel, final maternal prescription, and separate fluid guidance.
5. Meal generation/scaling with no fluid target-match influence.
6. PES drafts end to end: open the Diagnosis workflow for fictional data; request drafts; verify the real network response and zero-to-three UI; inspect visible Assessment evidence and confirm no visible source label; accept a mapped candidate into the existing structured Diagnosis selections rather than an unrelated `Other` free-text field; edit; dismiss; confirm cached state; change Assessment evidence and confirm refresh/invalidation; verify manual fallback; verify compact request/result behavior, authorization, server-side source validation, and no demo/env gate.
7. Monitoring form section order; saved visit summary/detail; omitted empty sections; pagination; no plan/revision controls; Progress Trends separate.
8. Intervention Plans tab: newest-first pagination, no status, old plan exact read-only UI, new-plan prefill, latest Monitoring calculation source, cancel/no-save behavior, save atomicity, and no copied meal plan.
9. Multiple saved plans with distinct meal plans; Reports one item per saved plan; no-meal-plan disabled state; NCP Summary newest only; prepared archive unchanged.
10. Real Nutrition Intervention Plan and every other PDF: long-bond dimensions, plan date, no blank snack rows, concise/deduplicated portions, no prep/USDA note, no clipping/overlap/orphan heading.
11. Every other user-visible behavior changed by Tasks 1–13, checked against the requirement-to-evidence checklist rather than sampled: validation/reload/error states, patient identifiers, navigation, all report browse/preview/download paths, seed-backed examples, and documentation/storyboard-visible labels.
12. Browser console/network errors, horizontal overflow, keyboard/focus/labels, unnecessary notes/icons/cards, and report preview performance.

Inventory every observed error before editing, group by root cause, batch-fix with focused failing tests, rerun full local gates, push, ask the owner to redeploy, then repeat the entire requirement-mapped live sweep until every item is clean. A passing backend/frontend suite cannot substitute for a missing browser workflow check.

- [ ] **Step 11: Final evidence report**

Report separately: requirement/audit disposition; changed; local tests; generated PDF inspection; committed/pushed revision parity; deploy/release/migration/container/health evidence; deployed URLs/scenarios; live browser pass/fail for every mapped workflow including PES AI; any deliberately deferred scope. Never describe an unverified layer as complete.

## Completion matrix

- Structured weight duration, cycle categories, PAL/TEE, maternal modifiers, bounded PES, fluid-free scaling, carbs/rice separation, and seeded templates: delivered Tasks 1–7; Task 14 regression.
- Complete dated plan schema, migration, deduplication, UUID identity, and meal-plan relink: Task 8.
- Paginated authorized APIs, complete Monitoring calculation snapshots, legacy fallback, and one-menu enforcement: Task 9.
- Plans tab, exact read-only UI reuse, newest-plan prefill, save/cancel behavior, and no meal copy: Task 10.
- Monitoring-only form/log structure, pagination, neutral styling, and no intervention controls: Task 11.
- One saved-plan report each, newest-only NCP Summary, frozen archives, long-bond PDFs, blank-snack removal, and compact portions: Task 12.
- Active revision retirement plus docs/storyboard/research reconciliation: Task 13.
- Focused/full checks, deterministic seeds, real PDF inspection, task commits, push/parity, owner-triggered deployment, deployed health/revision, and native live acceptance: Task 14.
