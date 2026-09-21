# Clinical Census, Prescription, PES Drafting, and Intervention Revision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Project rules prohibit subagents/worktrees unless the owner separately authorizes them. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved low-friction clinical workflow: structured Assessment data, cycle-owned census categories, sourced maternal prescription modifiers, fluid-free meal scaling, bounded PES drafts, intervention history through Monitoring, and a compact Nutrition Intervention Plan.

**Architecture:** Extend existing Assessment, Intervention, Monitoring, MealPlan, report, audit, and UI paths instead of creating parallel workflows. Keep one current Intervention for compatibility and add immutable revision snapshots for history. Keep clinical calculations deterministic; AI receives only eligible, de-identified evidence and may draft wording, never decide eligibility.

**Tech Stack:** Laravel 13/PHP 8.4/PHPUnit 12, MySQL-compatible forward migrations, Next.js 16/React 19/TypeScript/Vitest, Dompdf, existing Anthropic adapter, Docker Compose/GitHub deployment, deployed browser QA.

**Approved design authority:** `docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md`

---

## Canonical fresh-session prompt

Copy this whole block into one fresh Codex session:

```text
Work in C:\Users\jared\Documents\Nutriscope. Implement the approved clinical/census/AI/intervention plan at:
docs/superpowers/plans/2026-09-21-clinical-census-ai-intervention-implementation.md

Read `.agents/AGENTS.md` first, then only the rule files, nested AGENTS files, and skills it routes for this scope. Read the approved design completely:
docs/superpowers/specs/2026-09-21-clinical-census-ai-intervention-design.md

Inspect current Git and code before editing. Preserve every unrelated dirty file; `docs/logic/intervention-goals.md` contains owner-approved work that the plan explicitly reconciles. Execute the implementation plan in order using TDD and its verification/deployment gates. Do not reopen settled decisions or redo the older deployed fixes listed in the plan. Keep UI changes minimal and progressively disclosed.

Finish the complete plan: focused and full checks, real PDF inspection, existing docs/storyboard reconciliation, task-only commits, push `main`, revision parity, deployment through the current workflow, deployed health/revision checks, and live-browser QA with fictional data. Inventory errors before batch-fixing and rerun the full scoped sweep until clean. Report local, pushed, deployed, and live-accepted evidence separately.
```

## Fixed scope and non-goals

- Final census values are centralized once: `Cardiovascular`, `Renal`, `Diabetes`, `Obesity`, `Malnutrition`, `Surgery / Trauma`, `Liver`, `Cancer`, `Pregnancy / Lactation`, `Other`. Selecting `Other` reveals `Specify category`; its free text remains clinical context while census output aggregates one `Other` bucket.
- No extra non-maternal maintenance additions. Goal/stage output is final; maternal modifier is calculated automatically.
- No new food recall, Filipino/restaurant database, OCR reconstruction, recipe display field, preparation steps, menu-plan revision entity, or USDA note.
- No automatic category inference from physician text, PES diagnosis, or intervention goal.
- No AI diagnosis eligibility from free-form model reasoning. Deterministic rules choose candidates; model only drafts supplied eligible PES wording.
- No production real-patient external AI without explicit environment enablement following hospital privacy/security approval.

## File map

New focused backend files:

- `backend/app/Support/PrimaryDiagnosisCategory.php` — one category allow-list and labels.
- `backend/app/Support/WeightChangePeriod.php` — legacy parser and formatter.
- `backend/app/Services/Diagnosis/PesEvidenceBuilder.php` — compact de-identified evidence packet.
- `backend/app/Services/Diagnosis/PesRuleCatalog.php` — versioned, sourced deterministic rule cards.
- `backend/app/Services/Diagnosis/PesEligibilityService.php` — zero-to-three eligible candidates.
- `backend/app/Services/Diagnosis/PesSuggestionService.php` — fingerprint/cache/provider validation/dismissal orchestration.
- `backend/app/Services/InterventionRevisionService.php` — transactional snapshots and active revision links.
- `backend/app/Models/InterventionRevision.php` and factory — immutable revision record.
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

## Task 1: Freeze sourced clinical and UI contracts

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

## Task 2: Add structured Assessment, category, maternal, and demo data

**Files:**
- Create: `backend/database/migrations/2026_09_21_000001_add_clinical_classification_to_assessments.php`
- Create: `backend/database/migrations/2026_09_21_000002_add_demo_marker_to_patients.php`
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

Use Laravel generators, then keep generated filenames above:

```powershell
php artisan make:migration add_clinical_classification_to_assessments --table=assessments --no-interaction
php artisan make:migration add_demo_marker_to_patients --table=patients --no-interaction
```

Assessment migration adds nullable `weight_change_period_value` unsigned small integer, nullable `weight_change_period_unit` string(10), nullable indexed `primary_diagnosis_category` string(40), nullable `primary_diagnosis_other` string(160), and expands/migrates maternal status without dropping legacy columns. Patient migration adds indexed boolean `is_demo` default false. Backfill only safely parseable weight periods and map `pregnant` to `pregnant_unspecified`; do not infer real-patient category.

- [ ] **Step 4: Implement centralized support classes**

`PrimaryDiagnosisCategory` exposes `values(): array`, `isAllowed(?string): bool`, and constants for all eight values. `WeightChangePeriod` exposes `parseLegacy(?string): ?array` and `format(?int, ?string): ?string`, accepting only explicit forms such as `3 weeks`, `1 month`, and `6 months`.

- [ ] **Step 5: Wire model, validation, resource, and factory**

Add fields/casts/audit field names. Keep PHI value redaction. `is_demo` is guarded from normal patient request payloads and omitted from ordinary patient resources. Update requests with conditional rules and explicit attribute labels. Update factory defaults without making historical category inference.

- [ ] **Step 6: Run migration and focused tests**

```powershell
php artisan migrate --no-interaction
php artisan test --compact tests/Feature/AssessmentSaveTest.php tests/Unit/AssessmentModelTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit schema and Assessment contract**

```powershell
git add -- backend/database/migrations/2026_09_21_000001_add_clinical_classification_to_assessments.php backend/database/migrations/2026_09_21_000002_add_demo_marker_to_patients.php backend/app/Support/PrimaryDiagnosisCategory.php backend/app/Support/WeightChangePeriod.php backend/app/Models/Assessment.php backend/app/Models/Patient.php backend/app/Http/Requests/RND/StoreAssessmentRequest.php backend/app/Http/Requests/RND/UpdateAssessmentRequest.php backend/app/Http/Resources/AssessmentResource.php backend/database/factories/AssessmentFactory.php backend/tests/Feature/AssessmentSaveTest.php backend/tests/Unit/AssessmentModelTest.php
git commit -m "feat(clinical): structure assessment classification"
```

## Task 3: Update Assessment UI with minimal progressive controls

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

## Task 4: Rebuild census classification and deterministic demo data

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

Assert each non-deleted cycle counts once; category comes from that cycle's Assessment; null legacy value groups as `Unclassified`; free-text physician diagnosis never becomes a bucket; `Other` free text remains private; Maria is `Diabetes`; both Roberto cycles are `Malnutrition`; both patients are `is_demo=true`; running PatientSeeder twice creates no duplicate patients/cycles/revisions/reports.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/MonthlyDemographicCensusTest.php tests/Unit/Reports/DemographicCensusTest.php tests/Feature/PersonNameBackendFlowTest.php
```

- [ ] **Step 3: Update generator and monthly basis**

Increment `DemographicCensusGenerator::BASIS_VERSION` from 2 to 3. Replace cycle patient `medical_diagnosis` projection with Assessment category fallback `Unclassified`. Rename output key to `by_primary_diagnosis_category` and Blade heading to `By Primary Diagnosis Category`. Rebuild completed period data only through existing basis-version path; never mutate prepared report bytes.

- [ ] **Step 4: Seed and narrowly backfill demo identities**

Set explicit values in PatientSeeder. Migration identifies only stable demo hospital numbers already owned by the seeder, updates their patient marker/categories idempotently, and does not recreate clinical graphs or touch unknown records.

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

## Task 5: Apply sourced maternal modifiers and remove stress workflow

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

## Task 6: Remove fluid from meal-plan matching while preserving guidance

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

## Task 7: Replace broad AI diagnosis generation with bounded PES drafts

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

Test positive/negative/missing/disqualified evidence, maximum three, zero-result success, duplicate exclusion, dismissed persistence, fingerprint invalidation, provider malformed content, unknown evidence/source rejection, auth, no PHI keys, token-bounded payload, `is_demo` access, and real-patient flag default false.

- [ ] **Step 3: Confirm tests fail**

```powershell
Set-Location backend
php artisan test --compact tests/Unit/PesEligibilityServiceTest.php tests/Feature/PesSuggestionTest.php tests/Feature/AiServiceTest.php
```

- [ ] **Step 4: Implement persistence and configuration**

`pes_suggestion_states` stores `ncp_record_id`, fingerprint, catalog version, validated response JSON, dismissed candidate IDs, provider metadata without prompt/clinical content, timestamps, and unique `(ncp_record_id, fingerprint, catalog_version)`. Add `AI_DIAGNOSIS_REAL_PATIENTS_ENABLED=false` to example/config. Controller allows provider call only for `patient.is_demo` or enabled flag.

- [ ] **Step 5: Implement deterministic pipeline**

Evidence builder excludes name/code/hospital number/address/physician/attachments/exact DOB and bounds RND summary length. Eligibility selects candidates before AI. AI receives only matching evidence and compact matching cards. Validator rejects any returned problem/evidence/source not supplied. Cache unchanged fingerprint. Existing audited approval route remains authoritative.

- [ ] **Step 6: Simplify UI**

Rename to `Assessment-based PES drafts`. Remove numeric confidence and endless Generate behavior. Show at most three cards with concise Evidence used and Source, plus Edit/Accept/Dismiss. Show cached state, refresh only after Assessment changed, successful zero-result message, and short external-AI-unavailable message while manual PES stays present.

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

## Task 8: Add immutable intervention revision snapshots

**Files:**
- Create: `backend/database/migrations/2026_09_21_000005_create_intervention_revisions.php`
- Create: `backend/database/migrations/2026_09_21_000006_link_intervention_revisions.php`
- Create: `backend/app/Models/InterventionRevision.php`
- Create: `backend/database/factories/InterventionRevisionFactory.php`
- Create: `backend/app/Services/InterventionRevisionService.php`
- Modify: `backend/app/Models/Intervention.php`
- Modify: `backend/app/Models/Monitoring.php`
- Modify: `backend/app/Models/MealPlan.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/app/Http/Controllers/RND/MealPlanController.php`
- Modify: `backend/app/Http/Resources/InterventionResource.php`
- Modify: `backend/app/Http/Resources/MonitoringResource.php`
- Modify: `backend/app/Http/Resources/MealPlanResource.php`
- Create: `backend/tests/Feature/InterventionRevisionTest.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`
- Modify: `backend/tests/Feature/MealPlanControllerTest.php`

- [ ] **Step 1: Write failing revision invariants**

Assert initial save creates version 1; explicit edits before first Monitoring update version-1 snapshot; after first Monitoring, direct Intervention mutation is rejected with workflow message; service creates sequential immutable versions in a transaction; rollback leaves current row/history unchanged; existing rows receive one `legacy_baseline`; new meal plan links active revision; multiple plans share revision; public UUID and authorization rules hold.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/InterventionRevisionTest.php tests/Feature/NcpInterventionTest.php tests/Feature/MealPlanControllerTest.php
```

- [ ] **Step 3: Implement schema**

Revision table fields: public ID, intervention FK, nullable monitoring FK, unsigned version, effective timestamp, reason, actor user FK, source enum/string, immutable JSON snapshot, timestamps, unique `(intervention_id, version)`. Add nullable revision FK to monitorings and meal_plans. Backfill one baseline per existing intervention and link existing records without inventing prior versions.

- [ ] **Step 4: Implement one service**

`snapshotFields()` returns every clinically relevant current Intervention field. `createInitial()`, `updateInitialBeforeMonitoring()`, `reviseFromMonitoring()`, and `activeFor()` own transactions, row locks, sequencing, current-row updates, and links. Controllers do not duplicate snapshot logic.

- [ ] **Step 5: Wire current consumers**

Meal creation assigns active revision. Resources expose only authorized public revision metadata and snapshots needed by UI/reports. Audit values remain redacted while version/source/reason field names and safe metadata are recorded.

- [ ] **Step 6: Run focused tests and commit**

Run Step 2 command; expect PASS. Then stage named files only and commit:

```powershell
git commit -m "feat(clinical): preserve intervention revisions"
```

## Task 9: Add optional intervention revision to Monitoring

**Files:**
- Modify: `backend/app/Http/Requests/RND/StoreMonitoringRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateMonitoringRequest.php`
- Modify: `backend/app/Http/Controllers/RND/MonitoringController.php`
- Modify: `backend/tests/Feature/NcpMonitoringTest.php`
- Modify: `backend/tests/Feature/InterventionRevisionTest.php`
- Modify: `frontend/services/monitoringService.ts`
- Modify: `frontend/services/monitoringService.test.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/LogVisitForm.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/EncounterLog.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`

- [ ] **Step 1: Write failing atomic workflow tests**

Normal visit payload produces no revision. Optional `intervention_revision` requires effective date, reason, and valid complete Intervention snapshot. Save creates Monitoring + revision + current Intervention update atomically. Validation/provider/DB failure rolls all three back. Timeline returns revision version/reason/effective date under originating visit.

- [ ] **Step 2: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/NcpMonitoringTest.php tests/Feature/InterventionRevisionTest.php
Set-Location ../frontend
npm test -- services/monitoringService.test.ts
```

- [ ] **Step 3: Implement nested validated payload and transaction**

Use existing Intervention field rules for nested revision data. MonitoringController delegates to InterventionRevisionService inside the same audited transaction as visit creation and notification/appointment lifecycle.

- [ ] **Step 4: Implement progressive UI**

Keep Log Visit unchanged by default. Add one collapsed `Revise intervention` action. Only when selected, prefill current prescription/education/counseling/barriers/strategies/session/goal/stage/follow-up fields and show required effective date/reason. EncounterLog shows compact read-only `Intervention revised · Version N` row.

- [ ] **Step 5: Run focused tests, mobile-width component test, and commit**

Run Step 2 commands plus `npx tsc --noEmit`; expect PASS. Commit named files:

```powershell
git commit -m "feat(monitoring): revise interventions in visits"
```

## Task 10: Render historical reports and compact Nutrition Intervention Plan portions

**Files:**
- Modify: `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php`
- Modify: `backend/app/Services/Reports/Generators/NcpSummaryGenerator.php`
- Modify: `backend/resources/views/reports/patient-menu-plan.blade.php`
- Modify: `backend/resources/views/reports/ncp-summary.blade.php`
- Modify: `backend/database/seeders/ReportTemplateSeeder.php`
- Modify: `backend/tests/Feature/PatientMenuPlanGeneratorTest.php`
- Modify: `backend/tests/Feature/NcpSummaryReportTest.php`
- Modify: `backend/tests/Unit/PatientMenuPlanViewContractTest.php`
- Modify: `frontend/components/reports/ReportsBrowser.tsx`
- Modify: `frontend/components/reports/PatientsNcpTab.tsx`
- Modify: `frontend/components/reports/patient-ncp-navigation.test.ts`

- [ ] **Step 1: Write failing report contracts**

Assert patient-facing title `Nutrition Intervention Plan`; internal `patient_menu_plan` identity unchanged; selected meal plan reads linked revision snapshot; NCP Summary lists v1 then dated revisions with Monitoring reason; prepared report bytes stay frozen; patient plan shows final prescription, short maternal note, daily fluid guidance, education/counseling/patient-facing strategies, menu, and compact portion details; no preparation steps, `Recipe Details`, vague `medium piece`, or USDA note.

- [ ] **Step 2: Define precise portion formatter in generator**

For each dish/food, derive from saved meal-plan quantity and existing ingredient/unit conversions:

```php
[
    'dish' => $dishName,
    'food' => $ingredientName,
    'household_measure' => $verifiedMeasureOrNull,
    'metric_amount' => $gramsOrMilliliters,
    'metric_unit' => $gramsOrMillilitersUnit,
]
```

If conversion is absent, omit household measure and show precise g/mL. Deduplicate identical dish-plus-portion blocks without disconnecting menu slots.

- [ ] **Step 3: Confirm failures**

```powershell
Set-Location backend
php artisan test --compact tests/Feature/PatientMenuPlanGeneratorTest.php tests/Feature/NcpSummaryReportTest.php tests/Unit/PatientMenuPlanViewContractTest.php
Set-Location ../frontend
npm test -- components/reports/patient-ncp-navigation.test.ts
```

- [ ] **Step 4: Implement generators/views/catalog wording**

Use revision snapshot when linked, legacy baseline otherwise. Keep archived prepared bytes untouched. Replace recipe preparation section with compact page-break-safe portion blocks. Only patient-intended Intervention fields render; internal clinical notes do not leak.

- [ ] **Step 5: Generate and visually inspect real PDFs**

Use normal report preparation with fictional seeded Maria and Roberto plans. Render every PDF page to images using the PDF skill/tooling. Check title, identity, revision, targets, maternal/fluid note when applicable, portion accuracy, menu linkage, clipping, overlap, orphan headings, whitespace, and page count.

- [ ] **Step 6: Run focused tests and commit**

Run Step 3 commands; expect PASS. Stage named files and commit:

```powershell
git commit -m "feat(reports): render nutrition intervention history"
```

## Task 11: Reconcile existing documentation and storyboard

**Files:**
- Modify: `docs/modules/rnd.md`
- Modify: `docs/FAQ.md`
- Modify: `docs/module-workflow-flowchart.md`
- Modify: `docs/modules/Flowcharts/Clinical Care (NCP) Operations.md`
- Modify: `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`
- Modify: `Storyboarding/RND NCP Video Storyboard.md`

- [ ] **Step 1: Search stale workflow wording**

```powershell
rg -n "Patient Menu Plan|By Diagnosis|stress factor|3 months|Recipe Details|pregnant|malnutrition|fluid.*scal|AI.*diagnos" docs Storyboarding
```

- [ ] **Step 2: Update existing documents only**

Document visible Assessment category/duration/maternal controls, final prescription and fluid boundary, bounded PES drafts/manual fallback, optional Monitoring revision, revision-aware reports, Nutrition Intervention Plan title/portions, frozen archives, fictional-demo AI gate, and exact live demo sequence. Preserve already correct patient-code/login/report-performance/census-history narrative.

- [ ] **Step 3: Check Mermaid, links, numbering, and stale labels**

```powershell
rg -n "Patient Menu Plan|By Diagnosis|Recipe Details|stress_factor|fixed 3 months" docs/modules docs/FAQ.md docs/module-workflow-flowchart.md "Storyboarding/RND NCP Video Storyboard.md"
git diff --check -- docs Storyboarding
```

Expected: remaining old internal identifiers are explicitly labeled internal; no stale visible wording or formatting error.

- [ ] **Step 4: Commit docs**

```powershell
git add -- docs/modules/rnd.md docs/FAQ.md docs/module-workflow-flowchart.md "docs/modules/Flowcharts/Clinical Care (NCP) Operations.md" docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md "Storyboarding/RND NCP Video Storyboard.md"
git commit -m "docs: update clinical intervention workflow"
```

## Task 12: Full verification, delivery, deployment, and live acceptance

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

- [ ] **Step 4: Re-run deterministic seed and PDF acceptance**

On confirmed disposable local DB, seed twice and verify no duplicate graph. Generate both report types for representative cycles and visually inspect every rendered page. Record artifact paths and exact pass/fail observations.

- [ ] **Step 5: Regression-test previously completed baseline**

Verify tests still cover random patient code search/header-only display, centered hover logo login panel, absent Patients NCP helper, earliest/current census cycle counts including April, top-right Edit actions, lazy/cached report rendering, and page-break behavior.

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

- [ ] **Step 8: Inspect production before state-changing deployment**

Use current Compose/workflow and redacted operational checks. Inspect workflow status, host/container health, CPU, memory, swap, disk, network, running image/revision, and logs without reading secrets. Label deployment as state-changing before triggering it.

- [ ] **Step 9: Deploy and verify release layers**

Deploy through existing `main` GitHub workflow. Verify release job and migrations exit successfully, containers run expected revision/images, `/up` is healthy, and public app serves expected revision. Build success alone is insufficient.

- [ ] **Step 10: Run deployed live-browser sweep with fictional demo data**

At `https://nutriscope.live`, test desktop 1440 px and mobile 390/375 px:

1. Existing login branding hover/fit and patient-code search/header baseline.
2. Assessment quantity/unit, category/Other, maternal status, save/reload, validation, no stress control, no fixed three-month text.
3. Census earliest month/current month, cycle counts, category buckets, and frozen archived report behavior.
4. Intervention goal/stage progressive disclosure, baseline/modifier/final calculation panel, final maternal prescription, and separate fluid guidance.
5. Meal generation/scaling with no fluid target-match influence.
6. PES drafts: demo-only availability, zero-to-three result, evidence/source, cache, dismiss, Assessment-change refresh, edit/accept, manual fallback, and real-patient-disabled behavior where safely testable.
7. Normal Monitoring visit with no extra burden; optional Revise intervention; timeline revision history; prior revision immutability.
8. Multiple meal plans linked to one revision and a new plan linked to a newer revision.
9. Real Nutrition Intervention Plan and NCP Summary preview/download, all pages, correct revision, concise portions, no prep/USDA note, no clipping/overlap/orphan heading.
10. Browser console/network errors, horizontal overflow, keyboard/focus/labels, and report preview performance.

Inventory every observed error first, group by root cause, batch-fix with tests, redeploy, and rerun the whole scoped sweep until clean.

- [ ] **Step 11: Final evidence report**

Report separately: changed; local tests; generated PDF inspection; committed/pushed revision parity; deploy/release/migration/container/health evidence; deployed URLs/scenarios; live browser pass/fail; any deliberately deferred scope. Never describe an unverified layer as complete.

## Completion matrix

- Structured weight duration: Tasks 2–3, 12.
- Primary cycle category and multiple-diagnosis rule: Tasks 2–4, 12.
- Existing/demo backfill and frozen report distinction: Task 4, 12.
- Stress removal, PAL/TEE, maternal modifiers, stage progressive disclosure: Tasks 1, 3, 5, 12.
- Fluid guidance outside scaling: Task 6, 10, 12.
- Source-gated token-efficient PES drafts and privacy gate: Task 7, 12.
- Monitoring-owned immutable intervention revisions and multiple plans: Tasks 8–10, 12.
- Nutrition Intervention Plan title and compact portions: Task 10, 12.
- Existing docs/storyboard reconciliation: Task 11.
- Previously completed login/patient ID/census/card/PDF performance fixes preserved: Task 12 regression pass.
- Push, deploy, health, and live browser acceptance: Task 12.
