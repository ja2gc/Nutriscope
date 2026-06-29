# Intervention Goal Clinical Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make intervention goal selection and prescription autofill clinically safer by validating goal/stage inputs, aligning docs/spec/runtime values, adding lab-aware safety warnings, and fixing misleading UI labels.

**Architecture:** Keep `NutritionPrescriptionService` as the authoritative numeric engine. Add a small validation/safety layer around goal/stage selection and assessment labs instead of embedding lab logic into every formula branch. Keep frontend preview as a mirror only, with backend autofill response driving persisted values.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, Next.js/TypeScript, Vitest/Node tests, Laravel Boost for schema/docs checks.

---

## Investigation Findings

Current calculator inputs:
- `backend/app/Http/Controllers/RND/InterventionController.php:28` builds metrics from assessment/patient only.
- `backend/app/Http/Controllers/RND/InterventionController.php:75` maps `physical_activity_level` to PAL.
- `backend/app/Http/Controllers/RND/InterventionController.php:89` passes pregnancy/lactation status.
- `backend/app/Http/Controllers/RND/InterventionController.php:96` only warns for edema; it does not block or use dry weight.
- `backend/app/Services/NutritionPrescriptionService.php:189` to `backend/app/Services/NutritionPrescriptionService.php:318` contains every adult goal branch.
- `frontend/lib/nutritionCalculations.ts:225` to `frontend/lib/nutritionCalculations.ts:338` mirrors backend branches for live preview.

Current lab usage:
- Labs are stored in `biochemical_data`, exposed by `backend/app/Http/Resources/AssessmentResource.php:72`, and typed in `frontend/services/assessmentService.ts:61`.
- Labs are used by risk scoring, AI diagnosis, and monitoring: `backend/app/Services/RiskScoreCalculator.php:64`, `backend/app/Services/LabFlagService.php:49`, `backend/app/Services/MonitoringPlanService.php:144`.
- Labs are not used by intervention autofill calories/macros/fluid.
- `docs/logic/recommend-avoid.md:11` says lab values refine recommendations, but `backend/app/Services/RecommendService.php` only reads clinical rules by condition/stage. This is not implemented.

Clinical answer on labs:
- No current authoritative formula should change energy/protein/carbs/fat solely from hemoglobin, calcium, albumin, glucose, HbA1c, potassium, or phosphate.
- Labs should influence safety gates, warnings, displayed micronutrients, monitoring indicators, and possibly recommendations.
- Renal stage selection needs renal clinical data such as GFR/creatinine/URR context, but the current schema has creatinine and URR, not GFR.
- Refeeding safety needs potassium/phosphate/magnesium. Potassium and phosphate exist; magnesium is missing from `biochemical_data`.
- High calcium such as calcium 13 should raise an alert, but should not directly raise calorie targets.

Safety gaps found:
- `backend/app/Http/Requests/RND/StoreInterventionRequest.php:17` and `backend/app/Http/Requests/RND/UpdateInterventionRequest.php:17` accept any `goal_type` and `disease_stage` string.
- Unknown goal types fall into the default branch at `backend/app/Services/NutritionPrescriptionService.php:318`, which can silently compute a generic plan.
- Wrong stages within a valid goal often fall back to defaults inside a branch.
- Latest docs say CKD stage 1-2 sodium and cardiac mild sodium are 2000 mg, but runtime/spec still use 2300 mg:
  `backend/app/Services/NutritionPrescriptionService.php:196`, `frontend/lib/nutritionCalculations.ts:232`, `docs/logic/prescription-targets.json:103`, `docs/logic/prescription-targets.json:187`.
- Frontend goal selector labels weight-loss stages with Western BMI bands at `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.ts:52`, while classifier uses Asia-Pacific bands at `frontend/lib/nutritionCalculations.ts:407`.
- Severe refeeding branches return very low energy and fixed protein; carbs can become `0`. This is mathematically from the current formula, but clinically confusing in the UI.

## File Map

- `docs/logic/intervention-goals.md` - clinical source-of-truth prose; update only if clinical owner approves changed targets.
- `docs/logic/prescription-targets.json` - machine-readable target spec and golden cases; update before backend/frontend runtime changes.
- `backend/app/Services/NutritionPrescriptionService.php` - authoritative prescription engine.
- `frontend/lib/nutritionCalculations.ts` - frontend live-preview mirror.
- `backend/app/Http/Controllers/RND/InterventionController.php` - collects assessment/patient inputs and returns autofill response.
- `backend/app/Http/Requests/RND/StoreInterventionRequest.php` - validates intervention create payload.
- `backend/app/Http/Requests/RND/UpdateInterventionRequest.php` - validates intervention update payload.
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.ts` - UI goal/stage catalog and labels.
- `frontend/lib/interventionGoalState.ts` - maps backend autofill result into form state and displayed micros.
- `backend/app/Services/LabFlagService.php` - single lab range/flag source.
- `backend/app/Services/MonitoringPlanService.php` - tracks abnormal labs and goal-relevant labs after intervention.
- `backend/app/Services/RecommendService.php` - recommendation engine; currently not lab-aware.
- `backend/database/seeders/ClinicalRulesSeeder.php` - current condition/stage nutrient rules.
- `backend/tests/Unit/NutritionPrescriptionServiceTest.php` - backend golden-vector and plausibility tests.
- `backend/tests/Feature/NcpInterventionTest.php` - intervention endpoint tests.
- `frontend/lib/nutritionCalculations.test.ts` - frontend formula/classification tests.
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.test.ts` - frontend goal catalog tests.
- `frontend/lib/interventionGoalState.test.ts` - prescription form mapping tests.

---

### Task 1: Add Shared Goal/Stage Catalog

**Files:**
- Create: `backend/app/Support/InterventionGoalCatalog.php`
- Modify: `backend/app/Http/Requests/RND/StoreInterventionRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateInterventionRequest.php`
- Test: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Add failing endpoint tests for invalid goal and invalid stage**

Add tests to `backend/tests/Feature/NcpInterventionTest.php`:

```php
public function test_intervention_rejects_unknown_goal_type(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);
    $this->diagnosis($ncp);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
            'goal_type' => 'bad_goal',
            'disease_stage' => 'stage_1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['goal_type']);
}

public function test_intervention_rejects_stage_that_does_not_belong_to_goal(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);
    $this->diagnosis($ncp);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention", [
            'goal_type' => 'weight_gain',
            'disease_stage' => 'stage_1',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['disease_stage']);
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `php artisan test --filter=NcpInterventionTest`

Expected: new validation tests fail because current requests accept arbitrary strings.

- [ ] **Step 3: Create catalog**

Create `backend/app/Support/InterventionGoalCatalog.php`:

```php
<?php

namespace App\Support;

final class InterventionGoalCatalog
{
    /** @return array<string,array<int,string>> */
    public static function stagesByGoal(): array
    {
        return [
            'renal_diet' => ['stage_1', 'stage_2', 'stage_3', 'stage_4', 'stage_5_predialysis', 'hemodialysis', 'peritoneal'],
            'diabetic_control' => ['stage_1', 'stage_2', 'stage_3'],
            'cardiac_diet' => ['mild', 'moderate', 'severe'],
            'weight_loss' => ['overweight', 'class_1', 'class_2', 'class_3'],
            'weight_gain' => ['mild', 'moderate', 'severe'],
            'high_protein' => ['mild_stress', 'moderate_stress', 'severe_stress', 'burns'],
            'liver_disease' => ['compensated', 'decompensated', 'encephalopathy_grade_1_2', 'encephalopathy_grade_3_4'],
            'malnutrition' => ['moderate', 'severe'],
            'custom' => [],
        ];
    }

    /** @return array<int,string> */
    public static function goals(): array
    {
        return array_keys(self::stagesByGoal());
    }

    public static function stageBelongsToGoal(?string $goalType, ?string $stage): bool
    {
        if ($goalType === null || $goalType === '') {
            return true;
        }

        $stages = self::stagesByGoal()[$goalType] ?? null;
        if ($stages === null) {
            return false;
        }

        if ($stages === []) {
            return $stage === null || $stage === '';
        }

        return $stage !== null && in_array($stage, $stages, true);
    }
}
```

- [ ] **Step 4: Use catalog in form requests**

In both intervention request files, add:

```php
use App\Support\InterventionGoalCatalog;
use Illuminate\Validation\Rule;
```

Replace goal/stage rules with:

```php
'goal_type' => ['nullable', 'string', Rule::in(InterventionGoalCatalog::goals())],
'disease_stage' => [
    'nullable',
    'string',
    function (string $attribute, mixed $value, \Closure $fail): void {
        if (! InterventionGoalCatalog::stageBelongsToGoal($this->input('goal_type'), $value)) {
            $fail('The selected disease stage is not valid for the selected intervention goal.');
        }
    },
],
```

- [ ] **Step 5: Verify**

Run: `php artisan test --filter=NcpInterventionTest`

Expected: all tests pass.

---

### Task 2: Block Invalid Autofill Requests

**Files:**
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Test: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Add failing tests**

Add:

```php
public function test_autofill_rejects_unknown_goal_type(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);

    Assessment::forceCreate([
        'ncp_record_id' => $ncp->id,
        'weight' => 70.0,
        'height' => 170.0,
    ]);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention/autofill", [
            'goal_type' => 'bad_goal',
            'disease_stage' => 'stage_1',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid intervention goal or disease stage.');
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test --filter=autofill_rejects_unknown_goal_type`

Expected: FAIL because current autofill reaches the generic default branch.

- [ ] **Step 3: Validate before calculation**

In `InterventionController::autofill`, after missing `goal_type` check, add:

```php
if (! \App\Support\InterventionGoalCatalog::stageBelongsToGoal($goalType, $stage)) {
    return response()->json([
        'message' => 'Invalid intervention goal or disease stage.',
        'calculation_status' => 'invalid_goal_stage',
    ], 422);
}
```

- [ ] **Step 4: Verify**

Run: `php artisan test --filter=NcpInterventionTest`

Expected: all intervention feature tests pass.

---

### Task 3: Align Sodium Targets Across Docs, JSON, PHP, and TS

**Files:**
- Modify: `docs/logic/prescription-targets.json`
- Modify: `backend/app/Services/NutritionPrescriptionService.php`
- Modify: `frontend/lib/nutritionCalculations.ts`
- Test: `backend/tests/Unit/NutritionPrescriptionServiceTest.php`
- Test: `frontend/lib/nutritionCalculations.test.ts`

- [ ] **Step 1: Decide target source**

Use the latest clinical doc as authority:
- CKD stage 1 and 2 sodium: 2000 mg.
- Cardiac mild sodium: 2000 mg.

- [ ] **Step 2: Update runtime constants**

Change backend sodium maps:

```php
$sodium = [
    'stage_1' => 2000, 'stage_2' => 2000, 'stage_3' => 2000, 'stage_4' => 2000,
    'stage_5_predialysis' => 1500, 'hemodialysis' => 1500, 'peritoneal' => 2000,
];

$sodium = ['mild' => 2000, 'moderate' => 2000, 'severe' => 1500];
```

Change frontend maps the same way in `frontend/lib/nutritionCalculations.ts`.

- [ ] **Step 3: Update JSON spec and golden cases**

In `docs/logic/prescription-targets.json`, update:

```json
"stage_1": { "sodium_max_mg": 2000 }
"stage_2": { "sodium_max_mg": 2000 }
"mild": { "sodium_max_mg": 2000 }
```

Update any golden expected `sodium_max_mg` values affected by renal stage 1/2 and cardiac mild cases.

- [ ] **Step 4: Verify parity**

Run: `php artisan test --filter=NutritionPrescriptionServiceTest`

Run: `npm test -- nutritionCalculations.test.ts` from `frontend` if that is the repo's test command; if unavailable, run the existing frontend test command from `frontend/package.json`.

Expected: backend and frontend formula tests pass with the same target values.

---

### Task 4: Fix Asia-Pacific Weight-Loss Labels

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.ts`
- Test: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/goals.test.ts`

- [ ] **Step 1: Add/update test**

Assert stage labels use AP ranges:

```ts
import { describe, expect, test } from "vitest";
import { GOALS } from "./goals";

describe("GOALS", () => {
  test("weight-loss stages use Asia-Pacific BMI labels", () => {
    const weightLoss = GOALS.find((goal) => goal.value === "weight_loss");
    expect(weightLoss?.stages).toEqual([
      { value: "overweight", label: "Overweight (BMI 23-24.9)" },
      { value: "class_1", label: "Obese Class I (BMI 25-29.9)" },
      { value: "class_2", label: "Obese Class II (BMI 30-34.9)" },
      { value: "class_3", label: "Obese Class II, severe (BMI >=35)" },
    ]);
  });
});
```

- [ ] **Step 2: Update labels**

Set the four labels in `goals.ts` to the exact strings in the test.

- [ ] **Step 3: Verify**

Run frontend test command for `goals.test.ts`.

Expected: GOALS test passes.

---

### Task 5: Add Lab-Aware Prescription Safety Warnings

**Files:**
- Create: `backend/app/Services/InterventionSafetyReviewService.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `frontend/services/interventionService.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Test: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Add failing backend test for refeeding labs**

Add:

```php
public function test_autofill_returns_refeeding_lab_warnings_for_low_electrolytes(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);

    $assessment = Assessment::forceCreate([
        'ncp_record_id' => $ncp->id,
        'weight' => 48.0,
        'height' => 174.0,
        'physical_activity_level' => 'moderate',
    ]);
    $assessment->biochemicalData()->create([
        'potassium' => 3.1,
        'phosphate' => 2.1,
        'calcium' => 13.0,
        'hemoglobin' => 10.0,
    ]);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention/autofill", [
            'goal_type' => 'malnutrition',
            'disease_stage' => 'severe',
        ])
        ->assertOk()
        ->assertJsonPath('data.calculation_status', 'warning')
        ->assertJsonFragment(['key' => 'low_potassium'])
        ->assertJsonFragment(['key' => 'low_phosphate'])
        ->assertJsonFragment(['key' => 'high_calcium']);
}
```

- [ ] **Step 2: Create safety review service**

Create `backend/app/Services/InterventionSafetyReviewService.php`:

```php
<?php

namespace App\Services;

use App\Models\Assessment;

class InterventionSafetyReviewService
{
    /** @return array<int,array{key:string,severity:string,message:string}> */
    public function warnings(string $goalType, ?string $stage, Assessment $assessment): array
    {
        $labs = $assessment->biochemicalData;
        if (! $labs) {
            return $this->missingLabWarnings($goalType, $stage);
        }

        $warnings = [];

        if (in_array($goalType, ['weight_gain', 'malnutrition'], true) && $stage === 'severe') {
            if ($labs->potassium !== null && (float) $labs->potassium < 3.5) {
                $warnings[] = ['key' => 'low_potassium', 'severity' => 'critical', 'message' => 'Low potassium increases refeeding risk; replace and monitor before advancing feeds.'];
            }
            if ($labs->phosphate !== null && (float) $labs->phosphate < 2.5) {
                $warnings[] = ['key' => 'low_phosphate', 'severity' => 'critical', 'message' => 'Low phosphate increases refeeding risk; replace and monitor before advancing feeds.'];
            }
            if ($labs->potassium === null) {
                $warnings[] = ['key' => 'missing_potassium', 'severity' => 'warning', 'message' => 'Potassium is missing; refeeding protocol requires electrolyte monitoring.'];
            }
            if ($labs->phosphate === null) {
                $warnings[] = ['key' => 'missing_phosphate', 'severity' => 'warning', 'message' => 'Phosphate is missing; refeeding protocol requires electrolyte monitoring.'];
            }
        }

        if ($goalType === 'renal_diet') {
            if ($labs->potassium !== null && (float) $labs->potassium > 5.1) {
                $warnings[] = ['key' => 'high_potassium', 'severity' => 'critical', 'message' => 'High potassium should trigger strict potassium restriction and clinician review.'];
            }
            if ($labs->phosphate !== null && (float) $labs->phosphate > 4.5) {
                $warnings[] = ['key' => 'high_phosphate', 'severity' => 'warning', 'message' => 'High phosphate should trigger phosphate restriction and binder/renal review as applicable.'];
            }
        }

        if ($labs->calcium !== null && (float) $labs->calcium > 10.3) {
            $warnings[] = ['key' => 'high_calcium', 'severity' => 'warning', 'message' => 'High calcium is abnormal; review calcium/vitamin D sources and medical causes.'];
        }

        if ($goalType === 'diabetic_control') {
            if ($labs->glucose !== null && (float) $labs->glucose > 125) {
                $warnings[] = ['key' => 'high_glucose', 'severity' => 'warning', 'message' => 'High glucose supports stricter carbohydrate distribution and monitoring.'];
            }
            if ($labs->hba1c !== null && (float) $labs->hba1c >= 6.5) {
                $warnings[] = ['key' => 'high_hba1c', 'severity' => 'warning', 'message' => 'Elevated HbA1c supports diabetic-control monitoring and individualized carbohydrate planning.'];
            }
        }

        if ($goalType === 'high_protein' && $labs->creatinine !== null && (float) $labs->creatinine > 1.2) {
            $warnings[] = ['key' => 'high_creatinine', 'severity' => 'warning', 'message' => 'Renal impairment may conflict with high-protein targets; verify kidney status before confirming.'];
        }

        return $warnings;
    }

    /** @return array<int,array{key:string,severity:string,message:string}> */
    private function missingLabWarnings(string $goalType, ?string $stage): array
    {
        if (in_array($goalType, ['weight_gain', 'malnutrition'], true) && $stage === 'severe') {
            return [
                ['key' => 'missing_refeeding_labs', 'severity' => 'warning', 'message' => 'Refeeding protocol requires potassium and phosphate monitoring; labs are not recorded.'],
            ];
        }

        return [];
    }
}
```

- [ ] **Step 3: Attach warnings to autofill**

In `InterventionController::autofill`, load labs and append:

```php
$assessment = $ncpRecord->assessment()->with('biochemicalData')->first();
```

Inject `InterventionSafetyReviewService $safety` into the method, then after `$rx`:

```php
$warnings = $safety->warnings($goalType, $stage, $assessment);
if ($warnings !== []) {
    $rx['calculation_status'] = 'warning';
    $rx['safety_warnings'] = $warnings;
}
```

- [ ] **Step 4: Expose warnings in frontend service**

Update `AutofillResult`:

```ts
calculation_status?: "ok" | "warning" | "incomplete" | "invalid_goal_stage";
safety_warnings?: { key: string; severity: "warning" | "critical"; message: string }[];
```

- [ ] **Step 5: Render warning text near prescription note**

In intervention page, when backend response includes warnings:

```ts
const warningText = (be.safety_warnings ?? []).map((warning) => warning.message).join(" ");
setPrescNote([be.note, be.edema_warning, warningText].filter(Boolean).join(" "));
```

- [ ] **Step 6: Verify**

Run: `php artisan test --filter=NcpInterventionTest`

Run: `npx tsc --noEmit` from repo root if configured, or from `frontend` with the existing TypeScript command.

Expected: backend test passes and frontend types compile.

---

### Task 6: Make Refeeding Macros Clinically Legible

**Files:**
- Modify: `backend/app/Services/NutritionPrescriptionService.php`
- Modify: `frontend/lib/nutritionCalculations.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`
- Test: `backend/tests/Unit/NutritionPrescriptionServiceTest.php`
- Test: `frontend/lib/nutritionCalculations.test.ts`

- [ ] **Step 1: Add tests documenting current risk**

Add a backend test:

```php
public function test_severe_refeeding_prescription_marks_progression_phase(): void
{
    $svc = new NutritionPrescriptionService();

    $rx = $svc->autofill('malnutrition', 'severe', [
        'weightKg' => 48.0,
        'heightCm' => 174.0,
        'ageYears' => 35,
        'sex' => 'Male',
        'isAdult' => true,
        'activityFactor' => 1.55,
    ]);

    $this->assertSame(360, $rx['energy_kcal']);
    $this->assertSame('refeeding_start', $rx['feeding_phase']);
}
```

- [ ] **Step 2: Add `feeding_phase` metadata without changing current numbers**

For severe `weight_gain` and severe `malnutrition` return arrays, add:

```php
'feeding_phase' => 'refeeding_start',
'target_energy_kcal_range' => [
    (int) round($working * 30),
    (int) round($working * 35),
],
```

Mirror in TypeScript `Prescription` type and severe branches.

- [ ] **Step 3: Show phase in the prescription form**

In `NutritionPrescriptionForm.tsx`, add a compact warning when note includes refeeding or when form state later carries `feeding_phase`:

```tsx
<p className="text-[11px] text-amber-700">
  Refeeding start values are not full needs. Advance toward the target range only with electrolyte monitoring.
</p>
```

- [ ] **Step 4: Verify**

Run backend formula tests and frontend calculation tests.

Expected: existing energy stays `360` for 48 kg severe refeeding start, but response makes the phase explicit.

---

### Task 7: Add Lab-Aware Recommendation Refinement

**Files:**
- Modify: `backend/app/Services/RecommendService.php`
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Test: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Add failing test**

Add a test where high potassium produces a potassium limit even if goal is not renal:

```php
public function test_recommendations_include_lab_refinements_for_abnormal_potassium(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);
    $assessment = Assessment::forceCreate(['ncp_record_id' => $ncp->id, 'weight' => 70.0, 'height' => 170.0]);
    $assessment->biochemicalData()->create(['potassium' => 5.8]);

    Intervention::forceCreate([
        'ncp_record_id' => $ncp->id,
        'goal_type' => 'custom',
    ]);

    $response = $this->actingAs($rnd, 'sanctum')
        ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");

    $response->assertOk();
    $this->assertContains('potassium', array_column($response->json('data.limits'), 'tag'));
}
```

- [ ] **Step 2: Implement explicit lab refinements**

Add optional parameter to `RecommendService::getRecommendations`:

```php
public function getRecommendations(array $conditions, ?array $stages = null, array $labFlags = []): array
```

After clinical rules, append:

```php
if (($labFlags['potassium']['status'] ?? null) === 'HIGH') {
    $limits[] = [
        'tag' => 'potassium',
        'condition' => 'lab:hyperkalemia',
        'reason' => 'High serum potassium requires dietary potassium review.',
        'threshold' => 2000,
        'unit' => 'mg',
    ];
}
if (($labFlags['phosphate']['status'] ?? null) === 'HIGH') {
    $limits[] = [
        'tag' => 'phosphate',
        'condition' => 'lab:hyperphosphatemia',
        'reason' => 'High serum phosphate requires phosphate restriction review.',
        'threshold' => 800,
        'unit' => 'mg',
    ];
}
if (($labFlags['glucose']['status'] ?? null) === 'HIGH' || ($labFlags['hba1c']['status'] ?? null) === 'HIGH') {
    $limits[] = [
        'tag' => 'simple_sugar',
        'condition' => 'lab:hyperglycemia',
        'reason' => 'High glucose marker supports stricter simple-sugar avoidance.',
        'threshold' => 0,
        'unit' => '',
    ];
}
if (($labFlags['albumin']['status'] ?? null) === 'LOW') {
    $recommend[] = [
        'tag' => 'protein',
        'condition' => 'lab:hypoalbuminemia',
        'reason' => 'Low albumin supports protein adequacy review with inflammation context.',
    ];
}
```

- [ ] **Step 3: Pass flags from controller**

In `InterventionController::recommendations`, load assessment labs and call `LabFlagService`:

```php
$assessment = $ncpRecord->assessment()->with('biochemicalData')->first();
$labFlags = [];
if ($assessment?->biochemicalData) {
    $labFlags = app(\App\Services\LabFlagService::class)->flag(
        $assessment->biochemicalData->toArray(),
        $ncpRecord->patient?->sex
    );
}

$result = app(\App\Services\RecommendService::class)
    ->getRecommendations($conditions, $stages, $labFlags);
```

- [ ] **Step 4: Verify**

Run: `php artisan test --filter=NcpInterventionTest`

Expected: lab refinement test passes and existing recommendation tests still pass.

---

### Task 8: Add Missing Magnesium Tracking Decision

**Files:**
- Modify: `docs/logic/intervention-goals.md`
- Create: `backend/database/migrations/YYYY_MM_DD_HHMMSS_add_magnesium_to_biochemical_data.php`
- Modify: `backend/app/Models/BiochemicalData.php`
- Modify: `backend/app/Http/Requests/RND/StoreAssessmentRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateAssessmentRequest.php`
- Modify: `backend/app/Http/Resources/AssessmentResource.php`
- Modify: `backend/app/Services/LabFlagService.php`
- Modify: `frontend/services/assessmentService.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`
- Test: `backend/tests/Feature/LabFlagServiceTest.php`
- Test: `frontend/services/biochemical.test.ts`

- [ ] **Step 1: Add migration**

Use `php artisan make:migration add_magnesium_to_biochemical_data`.

Migration body:

```php
Schema::table('biochemical_data', function (Blueprint $table) {
    $table->decimal('magnesium', 6, 2)->nullable()->after('phosphate');
});
```

Down:

```php
Schema::table('biochemical_data', function (Blueprint $table) {
    $table->dropColumn('magnesium');
});
```

- [ ] **Step 2: Add model/request/resource fields**

Add `magnesium` to fillable, casts, request validation, and resource output using the same pattern as `phosphate`.

- [ ] **Step 3: Add lab range**

In `LabFlagService::ranges`, add:

```php
'magnesium' => ['low' => 1.7, 'high' => 2.2],
```

- [ ] **Step 4: Add frontend field**

In assessment lab field list, add:

```ts
{ key: "magnesium", label: "Magnesium", unit: "mg/dL", sexDiff: false, low: 1.7, high: 2.2, note: "Low magnesium increases refeeding risk." }
```

- [ ] **Step 5: Verify**

Run backend lab flag tests and frontend biochemical tests.

Expected: magnesium can be stored, returned, flagged, and displayed.

---

### Task 9: Mark Calculation Inputs as Required and Add Helper Notes

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`
- Modify: `frontend/services/assessmentService.ts`
- Modify: `backend/app/Http/Requests/RND/StoreAssessmentRequest.php`
- Modify: `backend/app/Http/Requests/RND/UpdateAssessmentRequest.php`
- Test: `frontend/lib/assessmentPageCalcs.test.ts`
- Test: `backend/tests/Feature/AssessmentSaveTest.php`

- [ ] **Step 1: Define non-lab calculation inputs**

Use this list as the required/helper-note scope. Lab fields are intentionally excluded because they are not needed every day for prescription math.

Required for prescription autofill:
- `assessment.weight` - energy, fluid, BMI, %IBW, BMR/TEE.
- `assessment.height` - IBW, BMI, BMR/TEE.
- `patients.dob` - age for adult/pediatric branch and BMR.
- `patients.sex` - IBW and BMR equation.
- `assessment.physical_activity_level` - TEE for TEE-based goals.

Required only for specific calculations or safety flags:
- `assessment.usual_weight` - weight-change and weight-loss percentage.
- `assessment.weight_loss_period` - malnutrition/refeeding risk context.
- `assessment.edema_present` - warns that measured weight may be unreliable.
- `assessment.pregnancy_lactation_status` - pregnancy/lactation energy and protein add-ons.
- `assessment.stress_factor` - currently stored but not consumed by `NutritionPrescriptionService`; helper note must say it is documentation/reserved unless the engine is changed.
- `assessment.muac_mm`, `assessment.waist_cm`, `assessment.hip_cm` - assessment/diagnosis context, not prescription autofill inputs.

- [ ] **Step 2: Add failing frontend expectations for helper metadata**

If a component test does not exist for the assessment form, add a pure exported metadata helper near the field definitions instead of testing rendered UI. Add this to `page.tsx`:

```ts
export const CALCULATION_INPUT_HELPERS = {
  weight: { required: true, hint: "Required for nutrition prescription" },
  height: { required: true, hint: "Required for nutrition prescription" },
  physical_activity_level: { required: true, hint: "Required for TEE-based prescriptions" },
  usual_weight: { required: false, hint: "Needed for weight-change and malnutrition context" },
  weight_loss_period: { required: false, hint: "Needed for malnutrition/refeeding risk context" },
  edema_present: { required: false, hint: "Flags measured weight as unreliable" },
  pregnancy_lactation_status: { required: false, hint: "Applies PDRI pregnancy/lactation add-ons when relevant" },
  stress_factor: { required: false, hint: "Document only; not applied to current autofill formulas" },
  muac_mm: { required: false, hint: "Assessment/diagnosis context; not used by autofill" },
  waist_cm: { required: false, hint: "Central-obesity context; not used by autofill" },
  hip_cm: { required: false, hint: "WHR context; not used by autofill" },
} as const;
```

Add a frontend test:

```ts
import { describe, expect, test } from "vitest";
import { CALCULATION_INPUT_HELPERS } from "../app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page";

describe("assessment calculation input helpers", () => {
  test("marks only non-lab prescription inputs as required", () => {
    expect(CALCULATION_INPUT_HELPERS.weight.required).toBe(true);
    expect(CALCULATION_INPUT_HELPERS.height.required).toBe(true);
    expect(CALCULATION_INPUT_HELPERS.physical_activity_level.required).toBe(true);
    expect("albumin" in CALCULATION_INPUT_HELPERS).toBe(false);
    expect("phosphate" in CALCULATION_INPUT_HELPERS).toBe(false);
  });
});
```

- [ ] **Step 3: Extend the `Field` component**

Change the local `Field` signature in `page.tsx`:

```tsx
function Field({
  label,
  children,
  span,
  hint,
  required = false,
  helper,
}: {
  label: string;
  children: React.ReactNode;
  span?: number;
  hint?: string;
  required?: boolean;
  helper?: string;
}) {
  return (
    <div className={span ? `col-span-${span}` : ""} style={span ? { gridColumn: `span ${span}` } : undefined}>
      <label className="block text-[10px] font-bold text-warm-500 uppercase tracking-wider mb-1.5">
        {label}
        {required && <span className="ml-1 text-red-500" aria-label="required">*</span>}
        {hint && <span className="ml-1.5 text-emerald-500 font-semibold normal-case tracking-normal">- {hint}</span>}
      </label>
      {children}
      {helper && <p className="mt-1 text-[10px] leading-relaxed text-warm-400">{helper}</p>}
    </div>
  );
}
```

- [ ] **Step 4: Apply helper notes to calculation inputs**

Update field usages:

```tsx
<Field
  label="Weight (kg)"
  required={CALCULATION_INPUT_HELPERS.weight.required}
  hint={CALCULATION_INPUT_HELPERS.weight.hint}
  helper="Used for kcal/kg goals, fluid, BMI, %IBW, BMR and TEE. Use dry weight when edema is present."
>
```

```tsx
<Field
  label="Height (cm)"
  required={CALCULATION_INPUT_HELPERS.height.required}
  hint={CALCULATION_INPUT_HELPERS.height.hint}
  helper="Used for IBW, BMI and BMR. Confirm measured height or a reliable estimate."
>
```

```tsx
<Field
  label="Physical Activity Level (PAL)"
  required={CALCULATION_INPUT_HELPERS.physical_activity_level.required}
  hint={CALCULATION_INPUT_HELPERS.physical_activity_level.hint}
  helper="Drives TEE-based goals such as diabetic control, cardiac diet, weight loss and non-severe weight gain."
>
```

```tsx
<Field
  label="Usual Weight (kg)"
  hint={CALCULATION_INPUT_HELPERS.usual_weight.hint}
  helper="Used to calculate percent weight change. Not required for basic autofill, but important for malnutrition context."
>
```

```tsx
<Field
  label="Weight Loss Period"
  hint={CALCULATION_INPUT_HELPERS.weight_loss_period.hint}
  helper="Clarifies whether weight loss meets malnutrition/refeeding-risk time windows."
>
```

```tsx
<Field
  label="Edema Present"
  hint={CALCULATION_INPUT_HELPERS.edema_present.hint}
  helper="If yes, prescription should be confirmed against dry weight because measured weight can overestimate needs."
>
```

```tsx
<Field
  label="Pregnancy / Lactation"
  hint={CALCULATION_INPUT_HELPERS.pregnancy_lactation_status.hint}
  helper="Adds pregnancy/lactation energy and protein only when applicable."
>
```

```tsx
<Field
  label="Stress Factor"
  hint={CALCULATION_INPUT_HELPERS.stress_factor.hint}
  helper="Current autofill does not multiply by this value. High-protein and disease-specific flat-rate goals already include stress assumptions."
>
```

- [ ] **Step 5: Add backend validation for required calculation fields at autofill boundary**

Do not make lab fields required. Keep assessment save flexible, but keep the existing autofill requirement checks strict:

```php
if (! $assessment || $assessment->weight === null) {
    $missingFields[] = 'weight';
}
if (! $assessment || $assessment->height === null) {
    $missingFields[] = 'height';
}
```

Extend missing field check for PAL only when a TEE-based goal is selected:

```php
$teeBasedGoals = ['diabetic_control', 'cardiac_diet', 'weight_loss'];
if (in_array($goalType, $teeBasedGoals, true) && (! $assessment || ! $assessment->physical_activity_level)) {
    $missingFields[] = 'physical_activity_level';
}
if ($goalType === 'weight_gain' && $stage !== 'severe' && (! $assessment || ! $assessment->physical_activity_level)) {
    $missingFields[] = 'physical_activity_level';
}
```

Add a feature test in `NcpInterventionTest.php`:

```php
public function test_autofill_requires_activity_level_for_tee_based_goals(): void
{
    $rnd = $this->rnd();
    $patient = $this->patient();
    $ncp = $this->ncpRecord($patient, $rnd);

    Assessment::forceCreate([
        'ncp_record_id' => $ncp->id,
        'weight' => 70.0,
        'height' => 170.0,
        'physical_activity_level' => null,
    ]);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/intervention/autofill", [
            'goal_type' => 'weight_loss',
            'disease_stage' => 'class_1',
        ])
        ->assertStatus(422)
        ->assertJsonPath('missing_fields', ['physical_activity_level']);
}
```

- [ ] **Step 6: Verify**

Run:

```bash
php artisan test --filter=NcpInterventionTest
npx tsc --noEmit
```

Expected: autofill blocks missing PAL only for TEE-based goals, assessment UI clearly marks calculation inputs, and lab fields remain optional.

---

## Verification Commands

Run after implementation:

```bash
php artisan test --filter=NutritionPrescriptionServiceTest
php artisan test --filter=NcpInterventionTest
php artisan test --filter=LabFlagServiceTest
```

Frontend:

```bash
npx tsc --noEmit
npm test -- nutritionCalculations.test.ts
npm test -- goals.test.ts
npm test -- interventionGoalState.test.ts
```

If the frontend package uses a different script, inspect `frontend/package.json` and use the existing test command.

## Execution Notes

- Do not change calorie/protein formulas based only on labs unless the clinical owner explicitly changes `docs/logic/intervention-goals.md`.
- Treat labs as warnings, recommendation refinements, displayed micronutrients, and monitoring indicators.
- Keep severe refeeding calories low as the start phase, but make that phase explicit so it is not mistaken for full needs.
- Do not overwrite unrelated assessment/risk-score work currently present in the worktree.

## Execution Audit - 2026-06-29

Status: implemented and verified.

Actual implementation notes:
- Shared goal/stage validation was implemented with `backend/config/clinical.php` and `backend/app/Support/InterventionGoalCatalog.php`.
- Autofill validation and refeeding lab warnings were implemented in `backend/app/Http/Controllers/RND/InterventionController.php`; no API route definitions were changed.
- The planned standalone safety service was not created because the warning logic is currently small and controller-local. Extract later if more lab/goal rules are added.
- Severe `malnutrition` and `weight_gain` still start at 5-10 kcal/kg/day; responses now include `feeding_phase = refeeding_start` and `target_energy_kcal_range`.
- Magnesium is now persisted, validated, serialized, flaggable, and monitorable for severe refeeding.
- Lab results remain optional input fields. Available abnormal labs now create warnings/recommendation refinements instead of silently being ignored.
- Assessment required/helper notes cover non-lab calculation inputs only.

Source-of-truth alignment:
- `docs/logic/prescription-targets.json`, `backend/app/Services/NutritionPrescriptionService.php`, and `frontend/lib/nutritionCalculations.ts` now agree on CKD stage 1/2 sodium and cardiac mild sodium at 2000 mg.
- `docs/logic/intervention-goals.md` now documents the refeeding runtime metadata and magnesium tracking decision.
- Asia-Pacific weight-loss labels now match the AP cut points used by nutritional-status classification.

Verification completed:
- `php artisan test --filter=NcpInterventionTest` - passed, 23 tests.
- `php artisan test --filter=NutritionPrescriptionServiceTest` - passed, 92 tests.
- `php artisan test --filter=LabFlagServiceTest` - passed, 4 tests.
- `php artisan test` - passed, 722 tests.
- `npm test -- goals.test.ts` - passed.
- `node --experimental-strip-types --test lib/assessmentPageCalcs.test.ts` - passed, 14 tests.
- `node --experimental-strip-types --test lib/nutritionCalculations.test.ts` - passed, 31 tests.
- `npm test -- interventionGoalState.test.ts` - passed.
- `npm test` - passed, 28 files / 78 tests.
- `npm run lint` - passed with existing warnings only.
- `npx tsc --noEmit` - passed.
- `php -l` on edited PHP files - passed.
- `git diff --check` - passed with line-ending warnings only.
