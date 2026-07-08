# Intervention Calculation Display Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a hidden-by-default inline calculation display for intervention prescriptions, including formulas, substituted values, current prescribed values, and all goal-relevant micronutrients.

**Architecture:** Add a pure trace builder beside existing frontend calculation helpers, render it through a focused panel component inside `NutritionPrescriptionForm`, and pass current goal/metrics state from the Intervention page. Keep backend autofill authoritative and unchanged.

**Tech Stack:** Next.js 16, React 19, TypeScript, Vitest, Tailwind CSS, lucide-react.

---

## Files

- Create: `frontend/lib/prescriptionCalculationTrace.ts`
- Create: `frontend/lib/prescriptionCalculationTrace.test.ts`
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx`
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Modify: `docs/superpowers/plans/2026-07-08-intervention-calculation-display.md`

---

### Task 1: Calculation Trace Builder

**Files:**
- Create: `frontend/lib/prescriptionCalculationTrace.test.ts`
- Create: `frontend/lib/prescriptionCalculationTrace.ts`

- [ ] **Step 1: Write failing trace tests**

Create `frontend/lib/prescriptionCalculationTrace.test.ts` with tests for:

```ts
import { describe, expect, test } from "vitest";
import { buildPrescriptionCalculationTrace } from "./prescriptionCalculationTrace";
import type { PatientMetrics } from "./nutritionCalculations";

const adultMetrics: PatientMetrics = {
  weightKg: 80,
  heightCm: 170,
  ageYears: 40,
  sex: "Male",
  isAdult: true,
  activityFactor: 1.2,
};

test("marks manually changed energy as modified while retaining calculated baseline", () => {
  const trace = buildPrescriptionCalculationTrace({
    goalType: "diabetic_control",
    stage: "stage_2",
    goalLabel: "Diabetic Control",
    stageLabel: "Stage 2",
    metrics: adultMetrics,
    prescription: {
      energy_kcal: "1600",
      protein_g: "",
      carbs_g: "",
      fat_g: "",
      fluid_ml: "",
      displayed_nutrients: ["fiber", "free_sugars"],
      micronutrient_limits: {},
    },
    requiredMicros: ["fiber", "sodium", "free_sugars"],
  });

  const energy = trace.targets.find((row) => row.key === "energy_kcal");
  expect(energy?.status).toBe("modified");
  expect(energy?.calculated?.value).toBeGreaterThan(1600);
  expect(energy?.prescribed?.value).toBe(1600);
  expect(energy?.formula).toContain("TEE");
});

test("includes refeeding monitoring micros without fake numeric targets", () => {
  const trace = buildPrescriptionCalculationTrace({
    goalType: "malnutrition",
    stage: "severe",
    goalLabel: "Malnutrition",
    stageLabel: "Severe",
    metrics: { ...adultMetrics, weightKg: 48, heightCm: 174 },
    prescription: {
      energy_kcal: "360",
      protein_g: "59",
      carbs_g: "",
      fat_g: "",
      fluid_ml: "",
      displayed_nutrients: ["potassium", "phosphate", "magnesium"],
      micronutrient_limits: {},
    },
    requiredMicros: ["potassium", "phosphate", "magnesium"],
  });

  expect(trace.targets.find((row) => row.key === "potassium")?.status).toBe("flagged");
  expect(trace.targets.find((row) => row.key === "phosphate")?.calculated?.text).toContain("monitoring");
  expect(trace.notes.join(" ")).toContain("Refeeding");
});

test("custom goal reports manual rows without formula", () => {
  const trace = buildPrescriptionCalculationTrace({
    goalType: "custom",
    stage: null,
    goalLabel: "Custom Plan",
    stageLabel: undefined,
    metrics: adultMetrics,
    prescription: {
      energy_kcal: "1800",
      protein_g: "",
      carbs_g: "",
      fat_g: "",
      fluid_ml: "",
      displayed_nutrients: ["sodium"],
      micronutrient_limits: { sodium: { max: 2000, unit: "mg" } },
    },
    requiredMicros: [],
  });

  expect(trace.targets.find((row) => row.key === "energy_kcal")?.status).toBe("manual");
  expect(trace.targets.find((row) => row.key === "sodium")?.prescribed?.text).toContain("2000");
});
```

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
cd frontend
npm test -- prescriptionCalculationTrace.test.ts
```

Expected: fail because `prescriptionCalculationTrace` module does not exist.

- [ ] **Step 3: Implement trace builder**

Create `frontend/lib/prescriptionCalculationTrace.ts` exporting:

```ts
export type CalculationTargetStatus =
  | "matches"
  | "modified"
  | "manual"
  | "missing"
  | "flagged";

export interface CalculationTraceValue {
  value?: number;
  unit?: string;
  text: string;
}

export interface CalculationTargetRow {
  key: string;
  label: string;
  unit: string;
  prescribed?: CalculationTraceValue;
  calculated?: CalculationTraceValue;
  formula: string;
  calculation: string;
  status: CalculationTargetStatus;
}
```

Implement `buildPrescriptionCalculationTrace()` using existing helpers from `nutritionCalculations.ts`: `autofillPrescription`, `calcIBW`, `calcAjBW`, `calcPercentIBW`, `calcWorkingWeight`, `calcBmrWeight`, `calcBMR`, `calcTEE`, `microLimitsFromRx`, and `ALL_MICROS`.

- [ ] **Step 4: Run tests to verify GREEN**

Run:

```bash
cd frontend
npm test -- prescriptionCalculationTrace.test.ts
```

Expected: pass.

---

### Task 2: Collapsible Calculation Panel

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx`
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`

- [ ] **Step 1: Write failing component test**

Create a Vitest render-to-static test that imports `PrescriptionCalculationPanel`, renders it with a minimal trace, and asserts:

```ts
expect(html).toContain("Show calculations");
expect(html).toContain("aria-expanded=\"false\"");
```

For this project’s no-DOM test style, use `react-dom/server` and keep the component controlled with `expanded` and `onToggle` props.

- [ ] **Step 2: Run tests to verify RED**

Run:

```bash
cd frontend
npm test -- PrescriptionCalculationPanel.test.tsx
```

Expected: fail because component does not exist.

- [ ] **Step 3: Implement panel component**

Render a compact header and expanded content. Use:

- existing warm/emerald classes
- `font-numeric`
- `text-xs`, `text-sm`, `text-base`
- semantic `<button>`
- `aria-expanded`
- `aria-controls`

- [ ] **Step 4: Wire into `NutritionPrescriptionForm`**

Add optional props:

```ts
calculationTrace?: CalculationTrace | null;
```

Add local state:

```ts
const [showCalculations, setShowCalculations] = useState(false);
```

Render panel below macro inputs when `calculationTrace` exists.

- [ ] **Step 5: Run tests to verify GREEN**

Run:

```bash
cd frontend
npm test -- PrescriptionCalculationPanel.test.tsx
```

Expected: pass.

---

### Task 3: Intervention Page Wiring

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`

- [ ] **Step 1: Write failing integration-oriented test if practical**

If existing page tests can import page-level helpers cleanly, add a narrow test for trace creation. If not practical because `page.tsx` is client/router-heavy, rely on Task 1 + Task 2 tests and keep wiring minimal.

- [ ] **Step 2: Pass trace into form**

In page render, compute:

```ts
const calculationTrace = intervention?.goal_type && patientMetrics
  ? buildPrescriptionCalculationTrace({
      goalType: intervention.goal_type,
      stage: intervention.disease_stage,
      goalLabel,
      stageLabel,
      metrics: patientMetrics,
      prescription,
      requiredMicros,
    })
  : null;
```

Pass `calculationTrace={calculationTrace}` into `NutritionPrescriptionForm`.

- [ ] **Step 3: Run targeted tests**

Run:

```bash
cd frontend
npm test -- prescriptionCalculationTrace.test.ts PrescriptionCalculationPanel.test.tsx
```

Expected: pass.

---

### Task 4: Final Verification

**Files:**
- All changed files.

- [ ] **Step 1: Run full frontend tests**

Run:

```bash
cd frontend
npm test
```

Expected: all tests pass.

- [ ] **Step 2: Run lint**

Run:

```bash
cd frontend
npm run lint
```

Expected: no lint errors.

- [ ] **Step 3: Review diff**

Run:

```bash
git diff --stat
git diff --check
```

Expected: no whitespace errors.

- [ ] **Step 4: Commit**

Run:

```bash
git add frontend/lib/prescriptionCalculationTrace.ts frontend/lib/prescriptionCalculationTrace.test.ts "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx" "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.test.tsx" "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx" "frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx" docs/superpowers/plans/2026-07-08-intervention-calculation-display.md
git commit -m "feat(ncp): show prescription calculations"
```
