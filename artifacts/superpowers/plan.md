# Intervention Page — Full Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the full 5-tab Intervention page — Goal Selector, Nutrition Prescription (with micronutrient toggle), Recommend/Avoid panel, Meal Plan section (Tab 1), plus Education, Counseling, Goal Planning, and Encounter Context tabs (Tabs 2–5).

**Architecture:** Option B — 5 tabs, Tab 1 has sticky MacroTrackerBar + internal sections (Goal → Prescription → Recommend/Avoid → Meal Plan). Backend recommendations endpoint auto-derives conditions from goal_type. No AI in meal plan generation — if < 5 recipes survive filter, show RND a clear message. Nutrition prescription auto-fills client-side from formulas in docs/logic/intervention-goals.md.

**Tech Stack:** Laravel 13.8, Next.js 16, TypeScript, shadcn/ui, Tailwind CSS v4, Lucide React, claude-haiku-4-5-20251001 (diagnosis + monitoring only — NOT meal plan)

**AI note:** Meal plan AI fallback removed. MealPlanService Step 7 replaced with: if fewer than 5 recipes survive the filter, return a structured error `{insufficient_recipes: true, count: n}` and show RND an actionable message. AI recipe generation lives in the Food Library (Task 13) — not in meal planning.

---

## What already exists (do NOT re-implement)

- `InterventionController` — store / show / update ✓
- `StoreInterventionRequest` / `UpdateInterventionRequest` — all fields ✓
- `InterventionResource` — all fields ✓
- Routes: POST / GET / PATCH `/ncp-records/{ncpRecord}/intervention` ✓
- `POST /ncp-records/{ncpRecord}/intervention/recommend` (MealPlanController) ✓
- `RecommendService::getRecommendations(conditions, stages)` ✓
- `NcpInterventionTest.php` + `RecommendServiceTest.php` ✓
- Meal plan builder (existing page tab — will be extracted into MealPlanSection component) ✓

---

## File Map

**Backend — create / modify:**
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php` — add `recommendations()` method
- Modify: `backend/routes/api.php` — add GET recommendations route
- Modify: `backend/tests/Feature/NcpInterventionTest.php` — add recommendations tests

**Frontend — create:**
- `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/route.ts`
- `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/recommendations/route.ts`
- `frontend/services/interventionService.ts`
- `frontend/lib/nutritionCalculations.ts`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MicronutrientToggle.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/RecommendAvoidPanel.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EducationTab.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/CounselingTab.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalPlanningTab.tsx`
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EncounterContextTab.tsx`

**Frontend — modify:**
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` — full rebuild

---

## Task 1: Backend — Recommendations endpoint (TDD)

**Files:**
- Modify: `backend/app/Http/Controllers/RND/InterventionController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/NcpInterventionTest.php`

- [ ] **Step 1: Write failing tests**

Append to `backend/tests/Feature/NcpInterventionTest.php` inside the class, after existing test methods:

```php
public function test_recommendations_returns_recommend_avoid_for_renal_diet(): void
{
    $rnd     = $this->rnd();
    $patient = $this->patient();
    $ncp     = $this->ncpRecord($patient, $rnd);

    Intervention::forceCreate([
        'ncp_record_id' => $ncp->id,
        'goal_type'     => 'renal_diet',
        'disease_stage' => 'stage_4',
    ]);

    $response = $this->actingAs($rnd, 'sanctum')
        ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['recommend', 'avoid', 'limits'],
        ]);
}

public function test_recommendations_returns_empty_for_custom_goal(): void
{
    $rnd     = $this->rnd();
    $patient = $this->patient();
    $ncp     = $this->ncpRecord($patient, $rnd);

    Intervention::forceCreate([
        'ncp_record_id' => $ncp->id,
        'goal_type'     => 'custom',
    ]);

    $response = $this->actingAs($rnd, 'sanctum')
        ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations");

    $response->assertOk()
        ->assertJsonPath('data.recommend', [])
        ->assertJsonPath('data.avoid', []);
}

public function test_recommendations_returns_404_when_no_intervention(): void
{
    $rnd     = $this->rnd();
    $patient = $this->patient();
    $ncp     = $this->ncpRecord($patient, $rnd);

    $this->actingAs($rnd, 'sanctum')
        ->getJson("/api/rnd/ncp-records/{$ncp->id}/intervention/recommendations")
        ->assertNotFound();
}
```

- [ ] **Step 2: Run tests — confirm they fail**

```bash
cd backend && php artisan test --filter=test_recommendations
```

Expected: FAIL — route not found (404 / route missing).

- [ ] **Step 3: Add route**

In `backend/routes/api.php`, after the existing intervention routes (line ~72):

```php
Route::get('ncp-records/{ncpRecord}/intervention/recommendations', [InterventionController::class, 'recommendations']);
```

- [ ] **Step 4: Add recommendations() method to InterventionController**

```php
/**
 * GET /api/rnd/ncp-records/{ncpRecord}/intervention/recommendations
 * Auto-derives clinical rule conditions from the intervention's goal_type.
 */
public function recommendations(NcpRecord $ncpRecord): JsonResponse
{
    $intervention = $ncpRecord->intervention()->firstOrFail();

    $conditions = $this->mapGoalTypeToConditions($intervention->goal_type ?? '');
    $stages     = $intervention->disease_stage ? [$intervention->disease_stage] : null;

    $result = app(\App\Services\RecommendService::class)
        ->getRecommendations($conditions, $stages);

    return response()->json(['data' => $result]);
}

private function mapGoalTypeToConditions(string $goalType): array
{
    return match ($goalType) {
        'renal_diet'        => ['CKD', 'Renal disease'],
        'diabetic_control'  => ['DM', 'High glucose'],
        'cardiac_diet'      => ['Cardiac', 'Hypertension'],
        'weight_gain'       => ['Malnutrition'],
        'high_protein'      => ['Low albumin', 'Malnutrition'],
        'fluid_restriction' => ['CKD', 'Renal disease'],
        'liver_disease'     => ['Liver disease'],
        'malnutrition'      => ['Malnutrition'],
        default             => [],
    };
}
```

Add `JsonResponse` to imports if not already present:
```php
use Illuminate\Http\JsonResponse;
```

- [ ] **Step 5: Run tests — confirm pass**

```bash
php artisan test --filter=test_recommendations
```

Expected: 3 passed.

- [ ] **Step 6: Run full suite**

```bash
php artisan test
```

Expected: all existing tests still pass.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Http/Controllers/RND/InterventionController.php \
        backend/routes/api.php \
        backend/tests/Feature/NcpInterventionTest.php
git commit -m "feat: add intervention recommendations endpoint — auto-derives conditions from goal_type"
```

---

## Task 2: Frontend — Install shadcn components

**Files:** `frontend/components/ui/` (auto-generated by shadcn CLI)

- [ ] **Step 1: Install required components**

```bash
cd frontend && npx shadcn@latest add select popover checkbox collapsible separator
```

Answer prompts: use defaults (overwrite if asked, TypeScript, tailwind).

- [ ] **Step 2: Verify files exist**

```bash
ls frontend/components/ui/select.tsx frontend/components/ui/popover.tsx \
   frontend/components/ui/checkbox.tsx frontend/components/ui/collapsible.tsx \
   frontend/components/ui/separator.tsx
```

Expected: all 5 files listed.

- [ ] **Step 3: Commit**

```bash
git add frontend/components/ui/
git commit -m "chore: add shadcn select, popover, checkbox, collapsible, separator components"
```

---

## Task 3: Frontend — nutritionCalculations.ts

**Files:**
- Create: `frontend/lib/nutritionCalculations.ts`

This implements all formulas from `docs/logic/intervention-goals.md` as pure TypeScript functions.

- [ ] **Step 1: Create the file**

Create `frontend/lib/nutritionCalculations.ts`:

```ts
// Pure calculation utilities based on docs/logic/intervention-goals.md
// Adult: Mifflin-St Jeor BMR, Hamwi IBW
// Pediatric: Schofield BMR, Holliday-Segar fluid

export type Sex = 'Male' | 'Female';

// ── Adult ──────────────────────────────────────────────────────────────────

/** Hamwi IBW formula. heightCm must be > 0. Returns kg. */
export function calcIBW(heightCm: number, sex: Sex): number {
  const inchesOver5Feet = (heightCm / 2.54) - 60;
  const base = sex === 'Male' ? 48.0 : 45.5;
  const perInch = sex === 'Male' ? 2.7 : 2.2;
  return Math.max(base + perInch * inchesOver5Feet, 30); // floor 30 kg
}

/** Adjusted body weight — use when actual > 120% IBW. Returns kg. */
export function calcAjBW(actualKg: number, ibwKg: number): number {
  return ibwKg + 0.25 * (actualKg - ibwKg);
}

/** %IBW */
export function calcPercentIBW(actualKg: number, ibwKg: number): number {
  return (actualKg / ibwKg) * 100;
}

/**
 * Working weight selection:
 * - >120% IBW → AjBW
 * - 90–120% IBW → IBW
 * - <90% IBW → actual (underweight, use actual for energy; IBW for protein targets)
 */
export function calcWorkingWeight(actualKg: number, ibwKg: number): number {
  const pct = calcPercentIBW(actualKg, ibwKg);
  if (pct > 120) return calcAjBW(actualKg, ibwKg);
  if (pct >= 90) return ibwKg;
  return actualKg;
}

/** Mifflin-St Jeor BMR. Returns kcal/day. */
export function calcBMR(weightKg: number, heightCm: number, ageYears: number, sex: Sex): number {
  const base = 10 * weightKg + 6.25 * heightCm - 5 * ageYears;
  return sex === 'Male' ? base + 5 : base - 161;
}

/** TEE for hospitalized patients (sedentary = 1.2 default). */
export function calcTEE(bmr: number, activityFactor = 1.2): number {
  return bmr * activityFactor;
}

// ── Pediatric ──────────────────────────────────────────────────────────────

/** Schofield equation — weight-based only. ageYears may be fractional. Returns kcal/day. */
export function calcSchofield(weightKg: number, ageYears: number, sex: Sex): number {
  if (sex === 'Male') {
    if (ageYears < 3)  return 59.512 * weightKg - 30.4;
    if (ageYears < 10) return 22.706 * weightKg + 504.3;
    return 17.686 * weightKg + 658.2;
  } else {
    if (ageYears < 3)  return 58.317 * weightKg - 31.1;
    if (ageYears < 10) return 20.315 * weightKg + 485.9;
    return 13.384 * weightKg + 692.8;
  }
}

/** Holliday-Segar fluid maintenance. Returns mL/day. */
export function calcHollidaySegar(weightKg: number): number {
  if (weightKg <= 10) return weightKg * 100;
  if (weightKg <= 20) return 1000 + (weightKg - 10) * 50;
  return 1500 + (weightKg - 20) * 20;
}

/** Pediatric DRI protein g/kg by age. */
export function pediatricProteinPerKg(ageYears: number): number {
  if (ageYears < 0.5)  return 1.52;
  if (ageYears < 1)    return 1.20;
  if (ageYears < 4)    return 1.05;
  if (ageYears < 14)   return 0.95;
  return 0.85;
}

// ── Macro distribution helpers ─────────────────────────────────────────────

/** Given energy + protein_g, fill fat at fatPct% and carbs from remainder. */
function macrosFromEnergyProtein(
  energyKcal: number,
  proteinG: number,
  fatPct = 0.275,
): { carbs_g: number; fat_g: number } {
  const fat_g  = Math.round((energyKcal * fatPct) / 9);
  const carbs_g = Math.max(Math.round((energyKcal - proteinG * 4 - fat_g * 9) / 4), 0);
  return { carbs_g, fat_g };
}

// ── Prescription auto-fill ─────────────────────────────────────────────────

export interface Prescription {
  energy_kcal: number;
  protein_g: number;
  carbs_g: number;
  fat_g: number;
  fluid_ml: number;
  /** Any relevant note (e.g. refeeding protocol warning, fluid formula note). */
  note?: string;
}

export interface PatientMetrics {
  weightKg: number;
  heightCm: number;
  ageYears: number;
  sex: Sex;
  isAdult: boolean; // patients.age_group_category !== 'pediatric'
}

/**
 * Auto-fills a nutrition prescription based on goal_type + disease_stage.
 * All formulas sourced from docs/logic/intervention-goals.md.
 * RND can override any returned value.
 */
export function autofillPrescription(
  goalType: string,
  stage: string | null,
  metrics: PatientMetrics,
): Prescription {
  const { weightKg, heightCm, ageYears, sex, isAdult } = metrics;

  if (!isAdult) return autofillPediatric(goalType, stage, metrics);

  const ibw     = calcIBW(heightCm, sex);
  const working = calcWorkingWeight(weightKg, ibw);
  const bmr     = calcBMR(working, heightCm, ageYears, sex);
  const tee     = calcTEE(bmr);
  const std_fluid = Math.round(working * 32.5);

  switch (goalType) {
    case 'renal_diet': {
      const energy = Math.round(working * 32.5);
      const proteinPerKg: Record<string, number> = {
        stage_1: 0.8, stage_2: 0.8, stage_3: 0.7,
        stage_4: 0.6, stage_5_predialysis: 0.6,
        hemodialysis: 1.2, peritoneal: 1.35,
      };
      const protein_g = Math.round(ibw * (proteinPerKg[stage ?? 'stage_1'] ?? 0.8));
      const fluid_ml  = stage === 'hemodialysis' ? 750 : std_fluid;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml,
        note: stage === 'hemodialysis' ? 'Add prior-day urine output to 750 mL fluid base.' : undefined };
    }

    case 'diabetic_control': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.9);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'cardiac_diet': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.8);
      const fatPct    = stage === 'severe' ? 0.24 : stage === 'moderate' ? 0.26 : 0.28;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g, fatPct), fluid_ml: std_fluid };
    }

    case 'weight_loss': {
      const deficits: Record<string, number> = {
        overweight: 375, class_1: 500, class_2: 625, class_3: 875,
      };
      const deficit   = deficits[stage ?? 'class_1'] ?? 500;
      const floor     = sex === 'Female' ? 1200 : 1500;
      const energy    = Math.max(Math.round(tee - deficit), floor);
      const protein_g = Math.round(ibw * 1.4);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'weight_gain': {
      if (stage === 'severe') {
        const energy    = Math.round(weightKg * 7.5); // refeeding start: ~50% of needs
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid,
          note: 'Refeeding protocol: start at 50% energy, increase 33% every 3–5 days. Monitor electrolytes daily.' };
      }
      const surplus   = stage === 'mild' ? 400 : 625;
      const energy    = Math.round(tee + surplus);
      const protein_g = Math.round(ibw * 1.6);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'high_protein': {
      const energy    = Math.round(working * 27.5);
      const protPerKg: Record<string, number> = {
        mild_stress: 1.1, moderate_stress: 1.35, severe_stress: 1.75, burns: 1.75,
      };
      const protein_g = Math.round(ibw * (protPerKg[stage ?? 'mild_stress'] ?? 1.1));
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'fluid_restriction': {
      const energy    = Math.round(tee);
      const protein_g = Math.round(ibw * 0.8);
      const fluidMap: Record<string, number> = {
        ckd_predialysis: std_fluid,
        ckd_hemodialysis: 750,
        ckd_peritoneal: std_fluid,
        heart_failure_mild: 2000,
        heart_failure_severe: 1250,
        siadh: 750,
      };
      const fluid_ml = fluidMap[stage ?? 'ckd_predialysis'] ?? std_fluid;
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml,
        note: stage === 'ckd_hemodialysis' ? 'Add prior-day urine output to 750 mL fluid base.' : undefined };
    }

    case 'liver_disease': {
      const energy    = Math.round(working * 37.5);
      const protPerKg: Record<string, number> = {
        compensated: 1.35, decompensated: 1.35,
        encephalopathy_grade_1_2: 0.9, encephalopathy_grade_3_4: 0.65,
      };
      const protein_g = Math.round(ibw * (protPerKg[stage ?? 'compensated'] ?? 1.35));
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    case 'malnutrition': {
      if (stage === 'severe') {
        const energy    = Math.round(weightKg * 7.5);
        const protein_g = Math.round(ibw * 1.0);
        return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid,
          note: 'Refeeding protocol applies. Give thiamine 200–300 mg before starting. Monitor phosphate, K, Mg daily.' };
      }
      const energy    = Math.round(working * 32.5);
      const protein_g = Math.round(ibw * 1.35);
      return { energy_kcal: energy, protein_g, ...macrosFromEnergyProtein(energy, protein_g), fluid_ml: std_fluid };
    }

    default:
      return { energy_kcal: Math.round(tee), protein_g: Math.round(ibw * 0.8),
        carbs_g: 0, fat_g: 0, fluid_ml: std_fluid };
  }
}

function autofillPediatric(
  goalType: string,
  stage: string | null,
  { weightKg, ageYears, sex }: PatientMetrics,
): Prescription {
  const bmr      = calcSchofield(weightKg, ageYears, sex);
  const tee      = calcTEE(bmr) + 20; // +20 kcal growth allowance (simplified)
  const fluid_ml = calcHollidaySegar(weightKg);
  const dri_prot = pediatricProteinPerKg(ageYears);

  // Simplified pediatric targets — same goal categories scaled to DRI
  const energy    = Math.round(tee);
  const protein_g = Math.round(weightKg * dri_prot);
  const fat_g     = Math.round((energy * 0.30) / 9);
  const carbs_g   = Math.max(Math.round((energy - protein_g * 4 - fat_g * 9) / 4), 0);

  return { energy_kcal: energy, protein_g, carbs_g, fat_g, fluid_ml };
}

// ── Micronutrient auto-flag ────────────────────────────────────────────────

/** Returns micro keys that should be pre-checked for a given goal_type. */
export const GOAL_MICRO_FLAGS: Record<string, string[]> = {
  renal_diet:        ['potassium', 'phosphate', 'sodium'],
  diabetic_control:  ['fiber'],
  cardiac_diet:      ['sodium', 'cholesterol'],
  weight_loss:       ['fiber'],
  fluid_restriction: ['sodium'],
  liver_disease:     ['sodium'],
  malnutrition:      [],
  weight_gain:       [],
  high_protein:      [],
  custom:            [],
};

export const ALL_MICROS: { key: string; label: string; unit: string }[] = [
  { key: 'sodium',      label: 'Sodium',      unit: 'mg'  },
  { key: 'potassium',   label: 'Potassium',   unit: 'mg'  },
  { key: 'phosphate',   label: 'Phosphorus',  unit: 'mg'  },
  { key: 'calcium',     label: 'Calcium',     unit: 'mg'  },
  { key: 'iron',        label: 'Iron',        unit: 'mg'  },
  { key: 'magnesium',   label: 'Magnesium',   unit: 'mg'  },
  { key: 'zinc',        label: 'Zinc',        unit: 'mg'  },
  { key: 'fiber',       label: 'Fiber',       unit: 'g'   },
  { key: 'cholesterol', label: 'Cholesterol', unit: 'mg'  },
  { key: 'vitamin_a',   label: 'Vitamin A',   unit: 'mcg' },
  { key: 'vitamin_c',   label: 'Vitamin C',   unit: 'mg'  },
  { key: 'vitamin_d',   label: 'Vitamin D',   unit: 'mcg' },
  { key: 'vitamin_b12', label: 'Vitamin B12', unit: 'mcg' },
  { key: 'folate',      label: 'Folate',      unit: 'mcg' },
  { key: 'omega3',      label: 'Omega-3',     unit: 'g'   },
];
```

- [ ] **Step 2: Commit**

```bash
git add frontend/lib/nutritionCalculations.ts
git commit -m "feat: add nutrition calculations utility — BMR, IBW, TEE, Schofield, goal-specific prescription autofill"
```

---

## Task 4: Frontend — Intervention API proxy routes

**Files:**
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/recommendations/route.ts`

- [ ] **Step 1: Create intervention CRUD proxy**

Create `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string }> };

async function proxy(req: NextRequest, path: string) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/${path}`, {
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

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(_req, `ncp-records/${ncpRecordId}/intervention`);
}
export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/intervention`);
}
export async function PATCH(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  return proxy(req, `ncp-records/${ncpRecordId}/intervention`);
}
```

- [ ] **Step 2: Create recommendations proxy**

Create `frontend/app/api/rnd/ncp-records/[ncpRecordId]/intervention/recommendations/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function GET(_req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/ncp-records/${ncpRecordId}/intervention/recommendations`, {
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
```

- [ ] **Step 3: Commit**

```bash
git add frontend/app/api/rnd/ncp-records/
git commit -m "feat: add intervention and recommendations Next.js proxy routes"
```

---

## Task 5: Frontend — interventionService.ts

**Files:**
- Create: `frontend/services/interventionService.ts`

- [ ] **Step 1: Create service**

```ts
export interface MicronutrientLimit {
  max?: number;
  min?: number;
  unit: string;
}

export interface Intervention {
  id: number;
  ncp_record_id: number;
  goal_type: string | null;
  disease_stage: string | null;
  displayed_nutrients: string[] | null;   // micro keys to show
  energy_kcal: string | null;
  protein_g: string | null;
  carbs_g: string | null;
  fat_g: string | null;
  fluid_ml: string | null;
  micronutrient_limits: Record<string, MicronutrientLimit> | null;
  education_notes: string | null;
  counseling_goals: string | null;
  barriers: string | null;
  strategies: string | null;
  session_type: string | null;
  next_followup_date: string | null;
}

export interface RecommendResult {
  recommend: { tag: string; condition: string; reason: string }[];
  avoid:     { tag: string; condition: string; reason: string }[];
  limits:    { tag: string; condition: string; reason: string; threshold: number; unit: string }[];
}

const base = (ncpId: string) => `/api/rnd/ncp-records/${ncpId}/intervention`;

export async function fetchIntervention(ncpId: string): Promise<Intervention | null> {
  const res = await fetch(base(ncpId), { headers: { Accept: 'application/json' } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error('Failed to fetch intervention.');
  const data = await res.json();
  return data.data ?? null;
}

export async function createIntervention(ncpId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await fetch(base(ncpId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Failed to create intervention.');
  }
  return (await res.json()).data;
}

export async function updateIntervention(ncpId: string, payload: Partial<Intervention>): Promise<Intervention> {
  const res = await fetch(base(ncpId), {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.message || 'Failed to update intervention.');
  }
  return (await res.json()).data;
}

export async function fetchRecommendations(ncpId: string): Promise<RecommendResult> {
  const res = await fetch(`${base(ncpId)}/recommendations`, { headers: { Accept: 'application/json' } });
  if (!res.ok) return { recommend: [], avoid: [], limits: [] };
  return (await res.json()).data ?? { recommend: [], avoid: [], limits: [] };
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/services/interventionService.ts
git commit -m "feat: add interventionService — fetch, create, update, recommendations"
```

---

## Task 6: Frontend — GoalSelectorModal

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalSelectorModal.tsx`

- [ ] **Step 1: Create component**

```tsx
"use client";

import { useState } from "react";
import { X, CheckCircle2 } from "lucide-react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/Button";

export interface GoalOption {
  value: string;
  label: string;
  description: string;
  stages?: { value: string; label: string }[];
}

export const GOALS: GoalOption[] = [
  {
    value: "renal_diet",
    label: "Renal Diet",
    description: "CKD — restricts protein, sodium, potassium, phosphorus",
    stages: [
      { value: "stage_1", label: "Stage 1 (GFR ≥90)" },
      { value: "stage_2", label: "Stage 2 (GFR 60–89)" },
      { value: "stage_3", label: "Stage 3 (GFR 30–59)" },
      { value: "stage_4", label: "Stage 4 (GFR 15–29)" },
      { value: "stage_5_predialysis", label: "Stage 5 Pre-dialysis" },
      { value: "hemodialysis", label: "Hemodialysis" },
      { value: "peritoneal", label: "Peritoneal Dialysis" },
    ],
  },
  {
    value: "diabetic_control",
    label: "Diabetic Control",
    description: "DM — carbohydrate distribution, glycemic management",
  },
  {
    value: "cardiac_diet",
    label: "Cardiac Diet",
    description: "HTN / cardiac — sodium, fat, cholesterol restriction",
    stages: [
      { value: "mild", label: "Mild" },
      { value: "moderate", label: "Moderate" },
      { value: "severe", label: "Severe" },
    ],
  },
  {
    value: "weight_loss",
    label: "Weight Loss",
    description: "Caloric deficit, protein-sparing approach",
    stages: [
      { value: "overweight", label: "Overweight (BMI 25–29.9)" },
      { value: "class_1", label: "Obese Class I (BMI 30–34.9)" },
      { value: "class_2", label: "Obese Class II (BMI 35–39.9)" },
      { value: "class_3", label: "Obese Class III (BMI ≥40)" },
    ],
  },
  {
    value: "weight_gain",
    label: "Weight Gain",
    description: "Caloric surplus; refeeding protocol for severe cases",
    stages: [
      { value: "mild", label: "Mild (85–90% IBW)" },
      { value: "moderate", label: "Moderate (70–84% IBW)" },
      { value: "severe", label: "Severe (<70% IBW) — Refeeding protocol" },
    ],
  },
  {
    value: "high_protein",
    label: "High Protein",
    description: "Post-surgery, burns, pressure injuries, low albumin",
    stages: [
      { value: "mild_stress", label: "Mild Stress (1.0–1.2 g/kg)" },
      { value: "moderate_stress", label: "Moderate Stress (1.2–1.5 g/kg)" },
      { value: "severe_stress", label: "Severe Stress (1.5–2.0 g/kg)" },
      { value: "burns", label: "Burns >20% BSA (1.5–2.0 g/kg)" },
    ],
  },
  {
    value: "fluid_restriction",
    label: "Fluid Restriction",
    description: "CKD dialysis, heart failure, SIADH",
    stages: [
      { value: "ckd_predialysis", label: "CKD Pre-dialysis" },
      { value: "ckd_hemodialysis", label: "CKD Hemodialysis" },
      { value: "ckd_peritoneal", label: "CKD Peritoneal" },
      { value: "heart_failure_mild", label: "Heart Failure — Mild (≤2000 mL)" },
      { value: "heart_failure_severe", label: "Heart Failure — Severe (≤1500 mL)" },
      { value: "siadh", label: "SIADH (500–1000 mL)" },
    ],
  },
  {
    value: "liver_disease",
    label: "Liver Disease",
    description: "Cirrhosis stages, hepatic encephalopathy",
    stages: [
      { value: "compensated", label: "Compensated (no ascites)" },
      { value: "decompensated", label: "Decompensated (ascites)" },
      { value: "encephalopathy_grade_1_2", label: "Encephalopathy Grade I–II" },
      { value: "encephalopathy_grade_3_4", label: "Encephalopathy Grade III–IV" },
    ],
  },
  {
    value: "malnutrition",
    label: "Malnutrition",
    description: "High-calorie high-protein; refeeding for severe cases",
    stages: [
      { value: "moderate", label: "Moderate (risk score 2–3)" },
      { value: "severe", label: "Severe (risk score >3) — Refeeding protocol" },
    ],
  },
  {
    value: "custom",
    label: "Custom Plan",
    description: "Manual nutrient targets set by RND",
  },
];

interface Props {
  onConfirm: (goalType: string, stage: string | null) => void;
  onClose: () => void;
  initialGoal?: string | null;
  initialStage?: string | null;
}

export default function GoalSelectorModal({ onConfirm, onClose, initialGoal, initialStage }: Props) {
  const [selected, setSelected] = useState<string>(initialGoal ?? "");
  const [stage, setStage]       = useState<string>(initialStage ?? "");

  const goal = GOALS.find((g) => g.value === selected);

  const handleConfirm = () => {
    if (!selected) return;
    onConfirm(selected, goal?.stages ? stage || null : null);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 flex flex-col max-h-[85vh]">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-100">
          <h2 className="text-sm font-extrabold text-zinc-900 uppercase tracking-wider">Set Intervention Goal</h2>
          <button onClick={onClose} className="text-zinc-400 hover:text-zinc-700 cursor-pointer transition-colors">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 p-6 space-y-4">
          {/* Goal grid */}
          <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Select a goal</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            {GOALS.map((g) => {
              const isSelected = selected === g.value;
              return (
                <button
                  key={g.value}
                  onClick={() => { setSelected(g.value); setStage(""); }}
                  className={`text-left p-3.5 rounded-xl border transition-all cursor-pointer ${
                    isSelected
                      ? "border-emerald-600 bg-emerald-50 ring-2 ring-emerald-500/20"
                      : "border-zinc-200 hover:border-emerald-300 bg-white"
                  }`}
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className={`text-xs font-bold ${isSelected ? "text-emerald-800" : "text-zinc-800"}`}>
                      {g.label}
                    </span>
                    {isSelected && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600 flex-shrink-0 mt-0.5" />}
                  </div>
                  <p className="text-[10px] text-zinc-400 mt-0.5 leading-relaxed">{g.description}</p>
                </button>
              );
            })}
          </div>

          {/* Stage selector — only shown after goal with stages is selected */}
          {goal?.stages && (
            <div className="pt-2 transition-all duration-150">
              <p className="text-[10px] font-bold text-zinc-400 uppercase tracking-widest mb-2">
                Disease Stage / Severity
              </p>
              <Select value={stage} onValueChange={setStage}>
                <SelectTrigger className="w-full text-sm border-zinc-200 focus:ring-emerald-500/20">
                  <SelectValue placeholder="Select stage…" />
                </SelectTrigger>
                <SelectContent>
                  {goal.stages.map((s) => (
                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 px-6 py-4 border-t border-zinc-100">
          <button onClick={onClose}
            className="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-700 cursor-pointer transition-colors">
            Cancel
          </button>
          <Button
            variant="primary"
            onClick={handleConfirm}
            disabled={!selected || (!!goal?.stages && !stage)}
            className="w-auto px-5 py-2 text-xs"
          >
            Apply Goal
          </Button>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/GoalSelectorModal.tsx
git commit -m "feat: add GoalSelectorModal with progressive disclosure stage dropdown"
```

---

## Task 7: Frontend — MicronutrientToggle

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MicronutrientToggle.tsx`

- [ ] **Step 1: Create component**

```tsx
"use client";

import { useState } from "react";
import { FlaskConical } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Checkbox } from "@/components/ui/checkbox";
import { ALL_MICROS } from "@/lib/nutritionCalculations";

interface Props {
  selected: string[];
  onChange: (keys: string[]) => void;
}

export default function MicronutrientToggle({ selected, onChange }: Props) {
  const [open, setOpen] = useState(false);

  const toggle = (key: string) => {
    onChange(
      selected.includes(key) ? selected.filter((k) => k !== key) : [...selected, key]
    );
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-zinc-500 border border-zinc-200 rounded-lg hover:border-emerald-400 hover:text-emerald-700 transition-colors cursor-pointer">
          <FlaskConical className="h-3 w-3" />
          Display Micros
          {selected.length > 0 && (
            <span className="ml-1 bg-emerald-100 text-emerald-700 rounded-full px-1.5 py-0.5 text-[9px] font-bold">
              {selected.length}
            </span>
          )}
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-3" align="start">
        <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-2">
          Choose micronutrients to display
        </p>
        <div className="space-y-1.5 max-h-64 overflow-y-auto">
          {ALL_MICROS.map(({ key, label, unit }) => (
            <label key={key} className="flex items-center gap-2.5 cursor-pointer group py-0.5">
              <Checkbox
                checked={selected.includes(key)}
                onCheckedChange={() => toggle(key)}
                className="data-[state=checked]:bg-emerald-600 data-[state=checked]:border-emerald-600"
              />
              <span className="text-xs text-zinc-700 group-hover:text-zinc-900 transition-colors">
                {label}
              </span>
              <span className="text-[9px] text-zinc-400 ml-auto">{unit}</span>
            </label>
          ))}
        </div>
        {selected.length > 0 && (
          <button onClick={() => onChange([])}
            className="mt-3 text-[9px] font-bold text-zinc-400 hover:text-red-500 transition-colors cursor-pointer">
            Clear all
          </button>
        )}
      </PopoverContent>
    </Popover>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/MicronutrientToggle.tsx
git commit -m "feat: add MicronutrientToggle — popover checklist with goal auto-flagging support"
```

---

## Task 8: Frontend — NutritionPrescriptionForm

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`

- [ ] **Step 1: Create component**

```tsx
"use client";

import { ALL_MICROS } from "@/lib/nutritionCalculations";
import MicronutrientToggle from "./MicronutrientToggle";
import { AlertTriangle } from "lucide-react";

interface PrescriptionValues {
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

interface Props {
  values: PrescriptionValues;
  onChange: (vals: PrescriptionValues) => void;
  onSave: () => void;
  saving: boolean;
  note?: string;
}

const MACROS = [
  { key: "energy_kcal", label: "Energy", unit: "kcal" },
  { key: "protein_g",   label: "Protein", unit: "g"   },
  { key: "carbs_g",     label: "Carbohydrates", unit: "g" },
  { key: "fat_g",       label: "Fat", unit: "g"       },
  { key: "fluid_ml",    label: "Fluid", unit: "mL"    },
] as const;

export default function NutritionPrescriptionForm({ values, onChange, onSave, saving, note }: Props) {
  const setMacro = (key: string, val: string) => onChange({ ...values, [key]: val });
  const setMicros = (keys: string[]) => onChange({ ...values, displayed_nutrients: keys });
  const setMicroLimit = (key: string, field: "max" | "min", val: string) => {
    const micro = ALL_MICROS.find((m) => m.key === key);
    onChange({
      ...values,
      micronutrient_limits: {
        ...values.micronutrient_limits,
        [key]: { ...values.micronutrient_limits[key], [field]: val ? parseFloat(val) : undefined, unit: micro?.unit ?? "" },
      },
    });
  };

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-5">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Nutrition Prescription</h3>
        <MicronutrientToggle selected={values.displayed_nutrients} onChange={setMicros} />
      </div>

      {/* Refeeding / clinical note */}
      {note && (
        <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
          <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
          <p className="text-[10px] text-amber-800 leading-relaxed">{note}</p>
        </div>
      )}

      {/* Macro inputs */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        {MACROS.map(({ key, label, unit }) => (
          <div key={key}>
            <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1">{label}</label>
            <div className="flex items-center border border-zinc-200 rounded-lg overflow-hidden focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20">
              <input
                type="number" min="0" step="0.1"
                value={(values as Record<string, string>)[key] ?? ""}
                onChange={(e) => setMacro(key, e.target.value)}
                className="w-full px-2.5 py-2 text-sm font-mono text-zinc-900 bg-transparent focus:outline-none"
              />
              <span className="px-2 text-[9px] text-zinc-400 font-bold bg-zinc-50 border-l border-zinc-200 whitespace-nowrap">{unit}</span>
            </div>
          </div>
        ))}
      </div>

      {/* Micronutrient limit rows */}
      {values.displayed_nutrients.length > 0 && (
        <div className="space-y-2 pt-2 border-t border-zinc-100">
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Micronutrient Limits</p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {values.displayed_nutrients.map((key) => {
              const micro  = ALL_MICROS.find((m) => m.key === key);
              const limits = values.micronutrient_limits[key] ?? {};
              return (
                <div key={key} className="flex items-center gap-2 p-2.5 bg-zinc-50 border border-zinc-200 rounded-xl">
                  <span className="text-[10px] font-semibold text-zinc-700 w-24 flex-shrink-0">{micro?.label ?? key}</span>
                  <div className="flex items-center gap-1 flex-1">
                    <span className="text-[9px] text-zinc-400">max</span>
                    <input type="number" min="0" step="0.1"
                      value={limits.max ?? ""}
                      onChange={(e) => setMicroLimit(key, "max", e.target.value)}
                      className="w-16 px-2 py-1 text-xs font-mono border border-zinc-200 rounded-lg focus:outline-none focus:border-emerald-500"
                      placeholder="—"
                    />
                    <span className="text-[9px] text-zinc-400">{micro?.unit}</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      <div className="flex justify-end pt-2">
        <button
          onClick={onSave}
          disabled={saving}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors cursor-pointer disabled:opacity-50">
          {saving ? "Saving…" : "Save Prescription"}
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/NutritionPrescriptionForm.tsx
git commit -m "feat: add NutritionPrescriptionForm with micronutrient limit rows"
```

---

## Task 9: Frontend — MacroTrackerBar

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx`

- [ ] **Step 1: Create component**

```tsx
interface MacroTarget { label: string; current: number; target: number; unit: string }

interface Props { targets: MacroTarget[] }

function statusColor(current: number, target: number): string {
  if (target <= 0) return "text-zinc-400";
  const pct = Math.abs(current - target) / target;
  if (pct <= 0.10) return "text-emerald-700";
  if (pct <= 0.20) return "text-amber-600";
  return "text-red-600";
}

export default function MacroTrackerBar({ targets }: Props) {
  if (targets.length === 0) return null;
  return (
    <div className="sticky top-0 z-10 flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2.5 bg-emerald-50 border-b border-emerald-100">
      {targets.map(({ label, current, target, unit }) => (
        <div key={label} className="flex items-baseline gap-1">
          <span className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label}</span>
          <span className={`text-sm font-extrabold font-mono ${statusColor(current, target)}`}>
            {Math.round(current)}
          </span>
          <span className="text-[9px] text-zinc-400">/ {Math.round(target)} {unit}</span>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/MacroTrackerBar.tsx
git commit -m "feat: add sticky MacroTrackerBar with ±10% color coding"
```

---

## Task 10: Frontend — RecommendAvoidPanel

**Files:**
- Create: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/RecommendAvoidPanel.tsx`

- [ ] **Step 1: Create component**

```tsx
import { ThumbsUp, ThumbsDown, AlertCircle } from "lucide-react";
import { RecommendResult } from "@/services/interventionService";

interface Props { data: RecommendResult | null; loading: boolean }

export default function RecommendAvoidPanel({ data, loading }: Props) {
  if (loading) return (
    <div className="h-20 flex items-center justify-center text-xs text-zinc-400">
      Loading recommendations…
    </div>
  );
  if (!data) return null;

  const { recommend, avoid, limits } = data;
  const hasContent = recommend.length > 0 || avoid.length > 0 || limits.length > 0;
  if (!hasContent) return (
    <div className="p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-xs text-zinc-400 text-center">
      No specific dietary restrictions for this goal. Clinical rules will apply to individual food items.
    </div>
  );

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* Recommend */}
      <div className="space-y-2">
        <p className="flex items-center gap-1.5 text-[9px] font-bold text-emerald-600 uppercase tracking-widest">
          <ThumbsUp className="h-3 w-3" /> Recommend
        </p>
        {recommend.length === 0
          ? <p className="text-[10px] text-zinc-300 italic">No specific recommendations.</p>
          : recommend.map((r, i) => (
            <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-emerald-400 bg-emerald-50 rounded-r-xl">
              <div>
                <p className="text-xs font-semibold text-zinc-800">{r.tag}</p>
                <p className="text-[10px] text-zinc-500">{r.reason}</p>
              </div>
            </div>
          ))}
      </div>

      {/* Avoid */}
      <div className="space-y-2">
        <p className="flex items-center gap-1.5 text-[9px] font-bold text-red-500 uppercase tracking-widest">
          <ThumbsDown className="h-3 w-3" /> Avoid
        </p>
        {avoid.length === 0
          ? <p className="text-[10px] text-zinc-300 italic">No specific restrictions.</p>
          : avoid.map((r, i) => (
            <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-red-400 bg-red-50 rounded-r-xl">
              <div>
                <p className="text-xs font-semibold text-zinc-800">{r.tag}</p>
                <p className="text-[10px] text-zinc-500">{r.reason}</p>
              </div>
            </div>
          ))}
      </div>

      {/* Limits */}
      {limits.length > 0 && (
        <div className="md:col-span-2 space-y-2">
          <p className="flex items-center gap-1.5 text-[9px] font-bold text-amber-600 uppercase tracking-widest">
            <AlertCircle className="h-3 w-3" /> Limits
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {limits.map((r, i) => (
              <div key={i} className="flex items-start gap-2 p-2.5 border-l-2 border-amber-400 bg-amber-50 rounded-r-xl">
                <div>
                  <p className="text-xs font-semibold text-zinc-800">{r.tag}
                    <span className="ml-1 text-[9px] font-normal text-zinc-500">≤ {r.threshold} {r.unit}</span>
                  </p>
                  <p className="text-[10px] text-zinc-500">{r.reason}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/RecommendAvoidPanel.tsx
git commit -m "feat: add RecommendAvoidPanel — algorithm-driven recommend/avoid/limits display"
```

---

## Task 11: Frontend — Simple tab content components

**Files:**
- Create: `_components/EducationTab.tsx`
- Create: `_components/CounselingTab.tsx`
- Create: `_components/GoalPlanningTab.tsx`
- Create: `_components/EncounterContextTab.tsx`

- [ ] **Step 1: Create EducationTab**

`frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EducationTab.tsx`:

```tsx
"use client";
interface Props { value: string; onChange: (v: string) => void; onSave: () => void; saving: boolean }

export default function EducationTab({ value, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-4">
      <p className="text-[10px] text-zinc-400 leading-relaxed">
        Record educational topics, handouts given, and key instructions discussed with the patient.
      </p>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={10}
        placeholder="e.g. Discussed importance of low-sodium diet. Provided handout on renal diet food choices. Reviewed portion sizes..."
        className="w-full px-3.5 py-3 text-sm border border-zinc-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
      />
      <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Notes"}
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Create CounselingTab**

`frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/CounselingTab.tsx`:

```tsx
"use client";
interface Props {
  goals: string; barriers: string; strategies: string;
  onChange: (field: 'counseling_goals' | 'barriers' | 'strategies', val: string) => void;
  onSave: () => void; saving: boolean;
}

function Area({ label, hint, value, onChange }: { label: string; hint: string; value: string; onChange: (v: string) => void }) {
  return (
    <div className="space-y-1.5">
      <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">{label}</label>
      <p className="text-[9px] text-zinc-300">{hint}</p>
      <textarea value={value} onChange={(e) => onChange(e.target.value)} rows={4}
        className="w-full px-3.5 py-3 text-sm border border-zinc-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
    </div>
  );
}

export default function CounselingTab({ goals, barriers, strategies, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-4">
      <Area label="Behavioral Goals" hint="Specific, measurable nutrition goals agreed with the patient."
        value={goals} onChange={(v) => onChange('counseling_goals', v)} />
      <Area label="Identified Barriers" hint="Financial, cultural, lifestyle, or knowledge barriers to adherence."
        value={barriers} onChange={(v) => onChange('barriers', v)} />
      <Area label="Strategies" hint="Motivational approaches and action steps to improve adherence."
        value={strategies} onChange={(v) => onChange('strategies', v)} />
      <div className="flex justify-end">
        <button onClick={onSave} disabled={saving}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
          {saving ? "Saving…" : "Save Counseling"}
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Create GoalPlanningTab**

`frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalPlanningTab.tsx`:

```tsx
interface Props {
  goals: string; energy: string; protein: string; carbs: string; fat: string;
}
export default function GoalPlanningTab({ goals, energy, protein, carbs, fat }: Props) {
  return (
    <div className="space-y-5">
      <p className="text-[10px] text-zinc-400">Links behavioral counseling goals to measurable nutrient targets.</p>
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[['Energy', energy, 'kcal'], ['Protein', protein, 'g'], ['Carbs', carbs, 'g'], ['Fat', fat, 'g']].map(([label, val, unit]) => (
          <div key={label} className="bg-emerald-50 border border-emerald-200 p-3 rounded-xl text-center">
            <p className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label} Target</p>
            <p className="text-lg font-extrabold font-mono text-zinc-900 mt-1">{val || '—'}<span className="text-[9px] font-normal text-zinc-500 ml-0.5">{unit}</span></p>
          </div>
        ))}
      </div>
      {goals ? (
        <div className="bg-white border border-zinc-200 rounded-xl p-4 space-y-2">
          <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Behavioral Goals</p>
          <p className="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap">{goals}</p>
        </div>
      ) : (
        <p className="text-xs text-zinc-400 italic">No counseling goals set. Add them in the Counseling tab.</p>
      )}
    </div>
  );
}
```

- [ ] **Step 4: Create EncounterContextTab**

`frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EncounterContextTab.tsx`:

```tsx
"use client";
interface Props {
  sessionType: string; nextFollowup: string;
  onChange: (field: 'session_type' | 'next_followup_date', val: string) => void;
  onSave: () => void; saving: boolean;
}
export default function EncounterContextTab({ sessionType, nextFollowup, onChange, onSave, saving }: Props) {
  return (
    <div className="space-y-5 max-w-md">
      <div className="space-y-1.5">
        <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Session Type</label>
        <select value={sessionType} onChange={(e) => onChange('session_type', e.target.value)}
          className="w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 bg-white cursor-pointer">
          <option value="">Select…</option>
          <option value="Initial Consultation">Initial Consultation</option>
          <option value="Follow-up">Follow-up</option>
        </select>
      </div>
      <div className="space-y-1.5">
        <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Next Follow-up Date</label>
        <input type="date" value={nextFollowup} onChange={(e) => onChange('next_followup_date', e.target.value)}
          className="w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
      </div>
      <button onClick={onSave} disabled={saving}
        className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-50 cursor-pointer">
        {saving ? "Saving…" : "Save Encounter"}
      </button>
    </div>
  );
}
```

- [ ] **Step 5: Commit all tab components**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/
git commit -m "feat: add Education, Counseling, GoalPlanning, EncounterContext tab components"
```

---

## Task 12: Frontend — Page rebuild (5 tabs)

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`

This is the orchestrating page. It:
1. Fetches intervention on mount (creates one if none exists)
2. Fetches assessment to get patient metrics for autofill
3. Manages all intervention state
4. Renders 5 tabs using the components above
5. Tab 1 contains: GoalSelectorModal trigger → NutritionPrescriptionForm → RecommendAvoidPanel → MealPlanSection (existing meal plan builder absorbed here)

- [ ] **Step 1: Replace page.tsx**

Replace `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` with:

```tsx
"use client";

import React, { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { Salad, User, Settings2, CheckCircle2 } from "lucide-react";
import {
  fetchIntervention, createIntervention, updateIntervention,
  fetchRecommendations, Intervention, RecommendResult,
} from "@/services/interventionService";
import { fetchAssessment } from "@/services/assessmentService";
import {
  autofillPrescription, GOAL_MICRO_FLAGS, Prescription, PatientMetrics,
} from "@/lib/nutritionCalculations";
import GoalSelectorModal, { GOALS } from "./_components/GoalSelectorModal";
import NutritionPrescriptionForm from "./_components/NutritionPrescriptionForm";
import MacroTrackerBar from "./_components/MacroTrackerBar";
import RecommendAvoidPanel from "./_components/RecommendAvoidPanel";
import EducationTab from "./_components/EducationTab";
import CounselingTab from "./_components/CounselingTab";
import GoalPlanningTab from "./_components/GoalPlanningTab";
import EncounterContextTab from "./_components/EncounterContextTab";
// Meal plan section re-uses existing logic — inline in this file for now
import MealPlanSection from "./_components/MealPlanSection";

type Tab = "nd" | "education" | "counseling" | "goals" | "encounter";
type PageParams = { patientId: string; ncpId: string };

const TABS: { key: Tab; label: string }[] = [
  { key: "nd",        label: "Food / Nutrient Delivery" },
  { key: "education", label: "Education" },
  { key: "counseling",label: "Counseling" },
  { key: "goals",     label: "Goal Planning" },
  { key: "encounter", label: "Encounter Context" },
];

interface PrescriptionForm {
  energy_kcal: string;
  protein_g: string;
  carbs_g: string;
  fat_g: string;
  fluid_ml: string;
  micronutrient_limits: Record<string, { max?: number; min?: number; unit: string }>;
  displayed_nutrients: string[];
}

const emptyPrescription = (): PrescriptionForm => ({
  energy_kcal: "", protein_g: "", carbs_g: "", fat_g: "", fluid_ml: "",
  micronutrient_limits: {}, displayed_nutrients: [],
});

function interventionToForm(iv: Intervention): PrescriptionForm {
  return {
    energy_kcal: iv.energy_kcal ?? "",
    protein_g:   iv.protein_g   ?? "",
    carbs_g:     iv.carbs_g     ?? "",
    fat_g:       iv.fat_g       ?? "",
    fluid_ml:    iv.fluid_ml    ?? "",
    micronutrient_limits: iv.micronutrient_limits ?? {},
    displayed_nutrients:  iv.displayed_nutrients  ?? [],
  };
}

export default function InterventionPage({ params }: { params: Promise<PageParams> }) {
  const { patientId, ncpId } = use(params);
  const isPlaceholder = patientId === "select-patient" || ncpId === "select-ncp";

  const [tab, setTab]                       = useState<Tab>("nd");
  const [intervention, setIntervention]     = useState<Intervention | null>(null);
  const [loading, setLoading]               = useState(true);
  const [goalModalOpen, setGoalModalOpen]   = useState(false);
  const [prescription, setPrescription]     = useState<PrescriptionForm>(emptyPrescription());
  const [prescNote, setPrescNote]           = useState<string | undefined>(undefined);
  const [recommend, setRecommend]           = useState<RecommendResult | null>(null);
  const [recommendLoading, setRecommendLoading] = useState(false);
  const [saving, setSaving]                 = useState(false);
  const [patientMetrics, setPatientMetrics] = useState<PatientMetrics | null>(null);

  // Text tab fields
  const [educationNotes, setEducationNotes]     = useState("");
  const [counselingGoals, setCounselingGoals]   = useState("");
  const [barriers, setBarriers]                 = useState("");
  const [strategies, setStrategies]             = useState("");
  const [sessionType, setSessionType]           = useState("");
  const [nextFollowup, setNextFollowup]         = useState("");

  const loadIntervention = useCallback(async () => {
    setLoading(true);
    try {
      const iv = await fetchIntervention(ncpId);
      if (iv) {
        setIntervention(iv);
        setPrescription(interventionToForm(iv));
        setEducationNotes(iv.education_notes ?? "");
        setCounselingGoals(iv.counseling_goals ?? "");
        setBarriers(iv.barriers ?? "");
        setStrategies(iv.strategies ?? "");
        setSessionType(iv.session_type ?? "");
        setNextFollowup(iv.next_followup_date ?? "");
        if (iv.goal_type) loadRecommendations();
      }
    } finally {
      setLoading(false);
    }
  }, [ncpId]);

  const loadRecommendations = useCallback(async () => {
    setRecommendLoading(true);
    try {
      const data = await fetchRecommendations(ncpId);
      setRecommend(data);
    } finally {
      setRecommendLoading(false);
    }
  }, [ncpId]);

  const loadMetrics = useCallback(async () => {
    try {
      const assessment = await fetchAssessment(ncpId);
      if (assessment?.weight && assessment?.height) {
        // age derived from patient DOB — approximated here; pass from patient context if available
        setPatientMetrics({
          weightKg: parseFloat(String(assessment.weight)),
          heightCm: parseFloat(String(assessment.height)),
          ageYears: 40, // TODO: derive from patient.dob when patient context is available
          sex: "Male",  // TODO: derive from patient.sex when patient context is available
          isAdult: true,
        });
      }
    } catch { /* assessment may not exist yet */ }
  }, [ncpId]);

  useEffect(() => {
    if (!isPlaceholder) {
      loadIntervention();
      loadMetrics();
    }
  }, [isPlaceholder, loadIntervention, loadMetrics]);

  const ensureIntervention = async (): Promise<Intervention> => {
    if (intervention) return intervention;
    const iv = await createIntervention(ncpId, {});
    setIntervention(iv);
    return iv;
  };

  const handleGoalConfirm = async (goalType: string, stage: string | null) => {
    setGoalModalOpen(false);
    const iv = await ensureIntervention();

    // Auto-flag micros for this goal
    const flagged = GOAL_MICRO_FLAGS[goalType] ?? [];
    const existingDisplayed = prescription.displayed_nutrients;
    const newDisplayed = Array.from(new Set([...existingDisplayed, ...flagged]));

    // Auto-fill prescription if we have patient metrics
    let autofilled: Prescription | null = null;
    if (patientMetrics) {
      autofilled = autofillPrescription(goalType, stage, patientMetrics);
      setPrescNote(autofilled.note);
    }

    const newPresc: PrescriptionForm = {
      ...prescription,
      displayed_nutrients: newDisplayed,
      ...(autofilled ? {
        energy_kcal: String(autofilled.energy_kcal),
        protein_g:   String(autofilled.protein_g),
        carbs_g:     String(autofilled.carbs_g),
        fat_g:       String(autofilled.fat_g),
        fluid_ml:    String(autofilled.fluid_ml),
      } : {}),
    };
    setPrescription(newPresc);

    setSaving(true);
    try {
      const updated = await updateIntervention(ncpId, {
        goal_type: goalType,
        disease_stage: stage,
        displayed_nutrients: newDisplayed,
        ...(autofilled ? {
          energy_kcal: autofilled.energy_kcal,
          protein_g:   autofilled.protein_g,
          carbs_g:     autofilled.carbs_g,
          fat_g:       autofilled.fat_g,
          fluid_ml:    autofilled.fluid_ml,
        } : {}),
      });
      setIntervention(updated);
      await loadRecommendations();
    } finally {
      setSaving(false);
    }
  };

  const savePrescription = async () => {
    setSaving(true);
    try {
      await ensureIntervention();
      const updated = await updateIntervention(ncpId, {
        energy_kcal: prescription.energy_kcal ? parseFloat(prescription.energy_kcal) : null,
        protein_g:   prescription.protein_g   ? parseFloat(prescription.protein_g)   : null,
        carbs_g:     prescription.carbs_g     ? parseFloat(prescription.carbs_g)     : null,
        fat_g:       prescription.fat_g       ? parseFloat(prescription.fat_g)       : null,
        fluid_ml:    prescription.fluid_ml    ? parseFloat(prescription.fluid_ml)    : null,
        micronutrient_limits: prescription.micronutrient_limits,
        displayed_nutrients:  prescription.displayed_nutrients,
      } as Partial<Intervention>);
      setIntervention(updated);
    } finally {
      setSaving(false);
    }
  };

  const saveTextField = async (fields: Partial<Intervention>) => {
    setSaving(true);
    try {
      await ensureIntervention();
      const updated = await updateIntervention(ncpId, fields);
      setIntervention(updated);
    } finally {
      setSaving(false);
    }
  };

  const goalLabel = GOALS.find((g) => g.value === intervention?.goal_type)?.label;
  const stageLabel = GOALS
    .find((g) => g.value === intervention?.goal_type)
    ?.stages?.find((s) => s.value === intervention?.disease_stage)?.label;

  if (isPlaceholder) return <PlaceholderState />;

  return (
    <div className="space-y-0 font-sans">
      {/* Breadcrumb + header */}
      <div className="space-y-4 mb-4">
        <div className="flex items-center gap-2 text-xs font-semibold text-zinc-400 select-none">
          <Link href="/ncp/patients" className="hover:text-emerald-700 transition-colors">Directory</Link>
          <span className="text-zinc-300">/</span>
          <span className="font-bold text-zinc-650">Nutrition Intervention</span>
        </div>
        <div className="border-b border-zinc-200 pb-4">
          <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
            <Salad className="h-5 w-5 text-emerald-600" />
            Step 3: Nutrition Intervention
          </h2>
        </div>
      </div>

      {/* Tab bar */}
      <div className="flex gap-0.5 border-b border-zinc-200 overflow-x-auto">
        {TABS.map(({ key, label }) => (
          <button key={key} onClick={() => setTab(key)}
            className={`px-4 py-2.5 text-[10px] font-bold uppercase tracking-wider border-b-2 whitespace-nowrap transition-colors cursor-pointer ${
              tab === key ? "border-emerald-600 text-emerald-700" : "border-transparent text-zinc-400 hover:text-zinc-600"
            }`}>
            {label}
          </button>
        ))}
      </div>

      {/* Sticky macro tracker — Tab 1 only */}
      {tab === "nd" && (
        <MacroTrackerBar targets={[
          { label: "Energy", current: 0, target: parseFloat(prescription.energy_kcal) || 0, unit: "kcal" },
          { label: "Protein", current: 0, target: parseFloat(prescription.protein_g) || 0, unit: "g" },
          { label: "Carbs", current: 0, target: parseFloat(prescription.carbs_g) || 0, unit: "g" },
          { label: "Fat", current: 0, target: parseFloat(prescription.fat_g) || 0, unit: "g" },
        ]} />
      )}

      {/* Tab content */}
      <div className="pt-5 space-y-6">

        {/* TAB 1 — Food / Nutrient Delivery */}
        {tab === "nd" && (
          <div className="space-y-6">
            {/* [A] Goal selector section */}
            <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Intervention Goal</h3>
                <button onClick={() => setGoalModalOpen(true)}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold text-emerald-700 border border-emerald-300 rounded-lg hover:bg-emerald-50 transition-colors cursor-pointer">
                  <Settings2 className="h-3 w-3" />
                  {intervention?.goal_type ? "Change Goal" : "Set Goal"}
                </button>
              </div>

              {intervention?.goal_type ? (
                <div className="flex items-center gap-2 px-4 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                  <CheckCircle2 className="h-4 w-4 text-emerald-600 flex-shrink-0" />
                  <div>
                    <p className="text-xs font-bold text-emerald-800">{goalLabel}</p>
                    {stageLabel && <p className="text-[10px] text-emerald-600">{stageLabel}</p>}
                  </div>
                </div>
              ) : (
                <p className="text-xs text-zinc-400 italic">No goal set. Click "Set Goal" to begin.</p>
              )}
            </div>

            {/* [B] Prescription */}
            <NutritionPrescriptionForm
              values={prescription}
              onChange={setPrescription}
              onSave={savePrescription}
              saving={saving}
              note={prescNote}
            />

            {/* [C] Recommend / Avoid */}
            {intervention?.goal_type && (
              <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-3">
                <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider">Food Recommendations</h3>
                <p className="text-[9px] text-zinc-400">Algorithm-driven based on goal, clinical rules, and lab values. Not AI-generated.</p>
                <RecommendAvoidPanel data={recommend} loading={recommendLoading} />
              </div>
            )}

            {/* [D] Meal Plan */}
            <MealPlanSection ncpId={ncpId} prescriptionTargets={{
              energy: parseFloat(prescription.energy_kcal) || 0,
              protein: parseFloat(prescription.protein_g) || 0,
              carbs: parseFloat(prescription.carbs_g) || 0,
              fat: parseFloat(prescription.fat_g) || 0,
            }} />
          </div>
        )}

        {/* TAB 2 — Education */}
        {tab === "education" && (
          <EducationTab
            value={educationNotes}
            onChange={setEducationNotes}
            onSave={() => saveTextField({ education_notes: educationNotes } as Partial<Intervention>)}
            saving={saving}
          />
        )}

        {/* TAB 3 — Counseling */}
        {tab === "counseling" && (
          <CounselingTab
            goals={counselingGoals} barriers={barriers} strategies={strategies}
            onChange={(field, val) => {
              if (field === 'counseling_goals') setCounselingGoals(val);
              if (field === 'barriers') setBarriers(val);
              if (field === 'strategies') setStrategies(val);
            }}
            onSave={() => saveTextField({
              counseling_goals: counselingGoals, barriers, strategies,
            } as Partial<Intervention>)}
            saving={saving}
          />
        )}

        {/* TAB 4 — Goal Planning */}
        {tab === "goals" && (
          <GoalPlanningTab
            goals={counselingGoals}
            energy={prescription.energy_kcal}
            protein={prescription.protein_g}
            carbs={prescription.carbs_g}
            fat={prescription.fat_g}
          />
        )}

        {/* TAB 5 — Encounter Context */}
        {tab === "encounter" && (
          <EncounterContextTab
            sessionType={sessionType} nextFollowup={nextFollowup}
            onChange={(field, val) => {
              if (field === 'session_type') setSessionType(val);
              if (field === 'next_followup_date') setNextFollowup(val);
            }}
            onSave={() => saveTextField({
              session_type: sessionType, next_followup_date: nextFollowup || null,
            } as Partial<Intervention>)}
            saving={saving}
          />
        )}
      </div>

      {/* Goal selector modal */}
      {goalModalOpen && (
        <GoalSelectorModal
          onConfirm={handleGoalConfirm}
          onClose={() => setGoalModalOpen(false)}
          initialGoal={intervention?.goal_type}
          initialStage={intervention?.disease_stage}
        />
      )}
    </div>
  );
}

function PlaceholderState() {
  return (
    <div className="space-y-6 font-sans">
      <div className="border-b border-zinc-200 pb-5">
        <h2 className="text-xl font-extrabold text-zinc-950 tracking-tight flex items-center gap-2.5">
          <Salad className="h-5 w-5 text-emerald-600 animate-pulse" />
          Step 3: Nutrition Intervention
        </h2>
      </div>
      <div className="bg-white border border-zinc-200 rounded-2xl p-12 text-center max-w-2xl mx-auto shadow-sm">
        <div className="p-3.5 bg-zinc-50 border border-zinc-200 rounded-2xl w-fit mx-auto text-zinc-400">
          <User className="h-8 w-8" />
        </div>
        <h3 className="text-sm font-bold text-zinc-800 mt-4 uppercase tracking-wider">No Patient Selected</h3>
        <p className="text-xs text-zinc-500 mt-2 leading-relaxed">Navigate to the NCP Patients directory and select a patient.</p>
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
```

- [ ] **Step 2: Create MealPlanSection (extract from old page)**

Create `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`:

Extract the existing meal plan builder logic (plan selector, day pills, meal slots, food picker modal) from the old page.tsx into this component. Accept `ncpId` and `prescriptionTargets` as props. The existing meal plan logic is fully functional — this is a mechanical extract, no logic changes.

```tsx
"use client";
// Full meal plan builder extracted from the previous page.tsx
// Props: ncpId, prescriptionTargets {energy, protein, carbs, fat}
// Contains: plan selector, day pills, daily totals bar, meal slot grid, food picker modal
// Logic is unchanged from the previously working implementation

import React, { useEffect, useState, useCallback } from "react";
import { Plus, X, Search, Loader2, Database, Leaf, Trash2, BookmarkPlus, Salad } from "lucide-react";
import { Button } from "@/components/ui/Button";
import {
  fetchMealPlans, createMealPlan, fetchMealPlanItems, addMealPlanItem, removeMealPlanItem,
  MealPlan, MealPlanItem,
} from "@/services/mealPlanService";
import { fetchFoodItems, fetchRecipes, searchUsda, importUsdaFood, FoodItem, Recipe, UsdaSearchResult } from "@/services/foodLibraryService";

const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as const;
const MEAL_TYPES = ['breakfast','am_snack','lunch','pm_snack','dinner'] as const;
const MEAL_LABELS: Record<string, string> = {
  breakfast:'Breakfast', am_snack:'AM Snack', lunch:'Lunch', pm_snack:'PM Snack', dinner:'Dinner',
};

interface Props {
  ncpId: string;
  prescriptionTargets: { energy: number; protein: number; carbs: number; fat: number };
}

export default function MealPlanSection({ ncpId, prescriptionTargets }: Props) {
  const [plans, setPlans]               = useState<MealPlan[]>([]);
  const [activePlan, setActivePlan]     = useState<MealPlan | null>(null);
  const [selectedDay, setSelectedDay]   = useState<string>(DAYS[0]);
  const [itemsByKey, setItemsByKey]     = useState<Record<string, MealPlanItem[]>>({});
  const [loadingPlans, setLoadingPlans] = useState(false);
  const [creatingPlan, setCreatingPlan] = useState(false);

  const [pickerOpen, setPickerOpen]           = useState(false);
  const [pickerTarget, setPickerTarget]       = useState<{ dayId: number; mealType: string } | null>(null);
  const [pickerTab, setPickerTab]             = useState<'library'|'recipes'|'usda'>('library');
  const [libraryQuery, setLibraryQuery]       = useState('');
  const [libraryResults, setLibraryResults]   = useState<FoodItem[]>([]);
  const [recipeQuery, setRecipeQuery]         = useState('');
  const [recipeResults, setRecipeResults]     = useState<Recipe[]>([]);
  const [usdaQuery, setUsdaQuery]             = useState('');
  const [usdaResults, setUsdaResults]         = useState<UsdaSearchResult[]>([]);
  const [pickerLoading, setPickerLoading]     = useState(false);
  const [adding, setAdding]                   = useState<number | string | null>(null);
  const [savingToLibrary, setSavingToLibrary] = useState<string | null>(null);

  const slotKey = (day: string, mt: string) => `${day}-${mt}`;

  const loadPlans = useCallback(async () => {
    setLoadingPlans(true);
    try {
      const data = await fetchMealPlans(ncpId);
      setPlans(data);
      if (data.length > 0) setActivePlan(data[0]);
    } finally { setLoadingPlans(false); }
  }, [ncpId]);

  useEffect(() => { loadPlans(); }, [loadPlans]);

  const loadItems = useCallback(async (plan: MealPlan) => {
    const map: Record<string, MealPlanItem[]> = {};
    await Promise.all(plan.days.map(async (day) => {
      const items = await fetchMealPlanItems(ncpId, plan.id, day.id);
      map[slotKey(day.day_of_week, day.meal_type)] = items;
    }));
    setItemsByKey(map);
  }, [ncpId]);

  useEffect(() => { if (activePlan) loadItems(activePlan); }, [activePlan, loadItems]);

  const handleCreatePlan = async () => {
    setCreatingPlan(true);
    try {
      const d = new Date(); const day = d.getDay();
      d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
      const plan = await createMealPlan(ncpId, { week_start_date: d.toISOString().split('T')[0], generation_type: 'manual' });
      setPlans((p) => [plan, ...p]);
      setActivePlan(plan);
    } finally { setCreatingPlan(false); }
  };

  const openPicker = (dayId: number, mealType: string) => {
    setPickerTarget({ dayId, mealType }); setPickerOpen(true); setPickerTab('library');
    setLibraryQuery(''); setLibraryResults([]); setRecipeQuery(''); setRecipeResults([]);
    setUsdaQuery(''); setUsdaResults([]);
  };

  const appendItem = (item: MealPlanItem, plan: MealPlan, dayId: number) => {
    const day = plan.days.find((d) => d.id === dayId); if (!day) return;
    const key = slotKey(day.day_of_week, day.meal_type);
    setItemsByKey((prev) => ({ ...prev, [key]: [...(prev[key] ?? []), item] }));
  };

  const addFromLibrary = async (food: FoodItem) => {
    if (!pickerTarget || !activePlan) return; setAdding(food.id);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { food_item_id: food.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const addFromRecipe = async (recipe: Recipe) => {
    if (!pickerTarget || !activePlan) return; setAdding(`recipe-${recipe.id}`);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { recipe_id: recipe.id, quantity: 1, unit: 'serving' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const addFromUsda = async (food: UsdaSearchResult) => {
    if (!pickerTarget || !activePlan) return; setAdding(food.fdc_id);
    try { appendItem(await addMealPlanItem(ncpId, activePlan.id, pickerTarget.dayId, { fdc_id: String(food.fdc_id), quantity: 100, unit: 'g' }), activePlan, pickerTarget.dayId); }
    finally { setAdding(null); }
  };
  const removeItem = async (key: string, dayId: number, itemId: number) => {
    if (!activePlan) return;
    await removeMealPlanItem(ncpId, activePlan.id, dayId, itemId);
    setItemsByKey((prev) => ({ ...prev, [key]: (prev[key] ?? []).filter((i) => i.id !== itemId) }));
  };

  const dayTotals = (day: string) => {
    let cal=0, prot=0, carb=0, fat=0;
    MEAL_TYPES.forEach((mt) => {
      (itemsByKey[slotKey(day, mt)] ?? []).forEach((item) => {
        const s = item.nutrient_snapshot; if (!s) return;
        const scale = s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
        cal += s.calories*scale; prot += s.protein*scale; carb += s.carbs*scale; fat += s.fat*scale;
      });
    });
    return { cal: Math.round(cal), prot: Math.round(prot), carb: Math.round(carb), fat: Math.round(fat) };
  };

  const t = prescriptionTargets;
  const dayT = dayTotals(selectedDay);

  return (
    <div className="bg-white border border-zinc-200 rounded-2xl p-5 shadow-sm space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-extrabold text-zinc-700 uppercase tracking-wider flex items-center gap-2">
          <Salad className="h-4 w-4 text-emerald-600" /> Weekly Meal Plan
        </h3>
        <Button variant="primary" loading={creatingPlan} onClick={handleCreatePlan} className="w-auto px-3 py-1.5 text-[10px]">
          <Plus className="h-3 w-3 mr-1" /> New Week
        </Button>
      </div>

      {/* Plan selector */}
      {plans.length > 0 && (
        <div className="flex gap-1.5 flex-wrap">
          {plans.map((p) => (
            <button key={p.id} onClick={() => setActivePlan(p)}
              className={`px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                activePlan?.id === p.id ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-400'
              }`}>Week of {p.week_start_date}</button>
          ))}
          {loadingPlans && <Loader2 className="h-3.5 w-3.5 animate-spin text-zinc-400" />}
        </div>
      )}

      {!activePlan && !loadingPlans && (
        <div className="bg-zinc-50 border border-zinc-200 rounded-xl p-8 text-center">
          <p className="text-xs text-zinc-400">No meal plans yet. Create one above.</p>
        </div>
      )}

      {activePlan && (
        <>
          {/* Day pills */}
          <div className="flex gap-1.5 flex-wrap">
            {DAYS.map((d) => {
              const tot = dayTotals(d);
              return (
                <button key={d} onClick={() => setSelectedDay(d)}
                  className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer text-center ${
                    selectedDay === d ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
                  }`}>
                  <span className="block">{d.slice(0,3)}</span>
                  {tot.cal > 0 && <span className="block font-normal opacity-80">{tot.cal}kcal</span>}
                </button>
              );
            })}
          </div>

          {/* Day vs target bar */}
          {dayT.cal > 0 && (
            <div className="flex gap-4 px-4 py-2.5 bg-zinc-50 border border-zinc-200 rounded-xl text-xs">
              {([
                { label:'Energy', curr: dayT.cal, tgt: t.energy, unit:'kcal' },
                { label:'Protein', curr: dayT.prot, tgt: t.protein, unit:'g' },
                { label:'Carbs', curr: dayT.carb, tgt: t.carbs, unit:'g' },
                { label:'Fat', curr: dayT.fat, tgt: t.fat, unit:'g' },
              ]).map(({ label, curr, tgt }) => {
                const pct = tgt > 0 ? Math.abs(curr - tgt) / tgt : 0;
                const color = pct <= 0.10 ? 'text-emerald-700' : pct <= 0.20 ? 'text-amber-600' : 'text-red-600';
                return (
                  <div key={label}>
                    <p className="text-[9px] font-bold text-zinc-400 uppercase tracking-wider">{label}</p>
                    <p className={`font-extrabold font-mono ${color}`}>{curr}{tgt > 0 && <span className="text-[9px] font-normal text-zinc-400">/{tgt}</span>}</p>
                  </div>
                );
              })}
            </div>
          )}

          {/* Meal slots */}
          <div className="space-y-2.5">
            {MEAL_TYPES.map((mt) => {
              const day = activePlan.days.find((d) => d.day_of_week === selectedDay && d.meal_type === mt);
              if (!day) return null;
              const key = slotKey(selectedDay, mt);
              const items = itemsByKey[key] ?? [];
              return (
                <div key={mt} className="border border-zinc-100 rounded-xl p-3.5 space-y-2">
                  <div className="flex items-center justify-between">
                    <h4 className="text-[10px] font-extrabold text-zinc-500 uppercase tracking-wider">{MEAL_LABELS[mt]}</h4>
                    <button onClick={() => openPicker(day.id, mt)}
                      className="flex items-center gap-1 text-[10px] font-bold text-emerald-600 hover:text-emerald-800 cursor-pointer">
                      <Plus className="h-3 w-3" /> Add
                    </button>
                  </div>
                  {items.length === 0 && <p className="text-[10px] text-zinc-300 italic">Empty</p>}
                  {items.map((item) => {
                    const s = item.nutrient_snapshot;
                    const scale = s && s.serving_size > 0 ? parseFloat(item.quantity) / s.serving_size : 1;
                    return (
                      <div key={item.id} className="flex items-center justify-between py-1 px-1.5 rounded-lg hover:bg-zinc-50 group">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-1.5">
                            <span className="text-xs font-medium text-zinc-800 truncate">{s?.name ?? '—'}</span>
                            {item.source === 'usda' && <span className="text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-1.5 rounded-full uppercase">USDA</span>}
                          </div>
                          {s && <p className="text-[10px] text-zinc-400">{item.quantity}{item.unit} · {Math.round(s.calories*scale)}kcal · P{Math.round(s.protein*scale)}g · C{Math.round(s.carbs*scale)}g</p>}
                        </div>
                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          {item.source === 'usda' && (
                            <button onClick={() => { if (item.fdc_id) { setSavingToLibrary(item.fdc_id); importUsdaFood(parseInt(item.fdc_id)).finally(() => setSavingToLibrary(null)); } }}
                              disabled={savingToLibrary === item.fdc_id} title="Save to Library"
                              className="p-1.5 rounded text-zinc-400 hover:text-emerald-600 hover:bg-emerald-50 cursor-pointer transition-colors">
                              {savingToLibrary === item.fdc_id ? <Loader2 className="h-3 w-3 animate-spin" /> : <BookmarkPlus className="h-3 w-3" />}
                            </button>
                          )}
                          <button onClick={() => removeItem(key, day.id, item.id)}
                            className="p-1.5 rounded text-zinc-400 hover:text-red-600 hover:bg-red-50 cursor-pointer transition-colors">
                            <Trash2 className="h-3 w-3" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* Food Picker Modal */}
      {pickerOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[80vh]">
            <div className="flex items-center justify-between p-4 border-b border-zinc-100">
              <h3 className="text-sm font-extrabold text-zinc-900">Add Food</h3>
              <button onClick={() => setPickerOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
            </div>
            <div className="flex gap-1 px-4 pt-3">
              {([
                { key: 'library' as const, label: 'Library', Icon: Database },
                { key: 'recipes' as const, label: 'Recipes',  Icon: Salad },
                { key: 'usda' as const,    label: 'USDA',     Icon: Leaf },
              ]).map(({ key, label, Icon }) => (
                <button key={key} onClick={() => setPickerTab(key)}
                  className={`flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold border transition-colors cursor-pointer ${
                    pickerTab === key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-zinc-50 text-zinc-500 border-zinc-200'
                  }`}><Icon className="h-3 w-3" />{label}</button>
              ))}
            </div>
            <div className="p-4 space-y-3 overflow-y-auto flex-1">
              {pickerTab === 'library' && <>
                <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                  <input type="text" value={libraryQuery} autoFocus placeholder="Search library…"
                    onChange={async (e) => { setLibraryQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setLibraryResults((await fetchFoodItems(e.target.value)).data); } finally { setPickerLoading(false); } } else setLibraryResults([]); }}
                    className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                {libraryResults.map((food) => (
                  <div key={food.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                    <div><p className="text-xs font-semibold text-zinc-800">{food.name}</p><p className="text-[10px] text-zinc-400">{food.calories}kcal · P{food.protein}g · C{food.carbs}g · F{food.fat}g</p></div>
                    <Button variant="primary" loading={adding === food.id} onClick={() => addFromLibrary(food)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                  </div>
                ))}
              </>}
              {pickerTab === 'recipes' && <>
                <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                  <input type="text" value={recipeQuery} autoFocus placeholder="Search recipes…"
                    onChange={async (e) => { setRecipeQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setRecipeResults((await fetchRecipes(e.target.value)).data); } finally { setPickerLoading(false); } } else setRecipeResults([]); }}
                    className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                {recipeResults.map((r) => (
                  <div key={r.id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                    <div><p className="text-xs font-semibold text-zinc-800">{r.name}</p><p className="text-[10px] text-zinc-400">{r.total_calories}kcal · P{r.total_protein}g · C{r.total_carbs}g · F{r.total_fat}g{r.servings ? ` · ${r.servings} srv` : ''}</p></div>
                    <Button variant="primary" loading={adding === `recipe-${r.id}`} onClick={() => addFromRecipe(r)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                  </div>
                ))}
              </>}
              {pickerTab === 'usda' && <>
                <div className="relative"><Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-400" />
                  <input type="text" value={usdaQuery} autoFocus placeholder="Search USDA…"
                    onChange={async (e) => { setUsdaQuery(e.target.value); if (e.target.value.length >= 2) { setPickerLoading(true); try { setUsdaResults(await searchUsda(e.target.value)); } finally { setPickerLoading(false); } } else setUsdaResults([]); }}
                    className="w-full pl-9 pr-3 py-2 text-sm border border-zinc-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" /></div>
                <p className="text-[9px] text-zinc-400">USDA foods are not saved to the library unless you bookmark them.</p>
                {pickerLoading && <Loader2 className="h-4 w-4 animate-spin text-zinc-400 mx-auto" />}
                {usdaResults.map((food) => (
                  <div key={food.fdc_id} className="flex items-center justify-between p-3 border border-zinc-100 rounded-xl hover:border-emerald-200 transition-colors">
                    <div><p className="text-xs font-semibold text-zinc-800">{food.name}</p><p className="text-[10px] text-zinc-400">{food.calories}kcal · P{food.protein}g · C{food.carbs}g · F{food.fat}g</p></div>
                    <Button variant="primary" loading={adding === food.fdc_id} onClick={() => addFromUsda(food)} className="w-auto px-3 py-1.5 text-[10px]">Add</Button>
                  </div>
                ))}
              </>}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 3: Verify dev server**

```bash
cd frontend && npm run dev
```

Check:
- Intervention page loads at `/ncp/[patientId]/intervention/[ncpId]`
- 5 tabs render in the tab bar
- "Set Goal" button opens the GoalSelectorModal
- Selecting a goal with stages shows the stage dropdown
- Confirming a goal closes modal, shows goal summary chip, auto-fills prescription
- "Display Micros" button opens the popover checklist
- Goal-relevant micros are pre-checked after goal selection
- Prescription form saves on button click
- RecommendAvoidPanel loads after goal is set
- Meal plan section renders (existing working functionality)
- Education / Counseling / Goal Planning / Encounter tabs render and save

- [ ] **Step 4: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/
git commit -m "feat: full 5-tab intervention page — goal selector, prescription autofill, micros toggle, recommend/avoid, meal plan"
```

---

## Execution Order

1. Task 1 — Backend recommendations endpoint (TDD — fastest, unblocks frontend)
2. Task 2 — shadcn components install
3. Task 3 — nutritionCalculations.ts utility
4. Task 4 — API proxy routes
5. Task 5 — interventionService.ts
6. Task 6 — GoalSelectorModal
7. Task 7 — MicronutrientToggle
8. Task 8 — NutritionPrescriptionForm
9. Task 9 — MacroTrackerBar
10. Task 10 — RecommendAvoidPanel
11. Task 11 — Tab content components
12. Task 12 — Page rebuild + MealPlanSection extract

Tasks 2–5 have no dependencies on each other — can be done in any order.
Tasks 6–11 depend on Tasks 3 and 5.
Task 12 depends on all previous tasks.

---

## Notes

- **AI in meal plan:** removed. If < 5 recipes survive the allergen/restriction filter, MealPlanService returns `{insufficient_recipes: true, count: n}`. Frontend shows: "Only N recipes match this patient's restrictions. Add more goal-appropriate recipes or build the meal plan manually."
- **Patient age/sex in autofill:** currently hardcoded as 40/Male in `loadMetrics()`. Wire from patient context (`patientService.fetchPatient(patientId)`) in a follow-up task — autofill values are editable by RND regardless.
- **Future:** AI recipe generation in Food Library (user gives ingredients → AI suggests recipe with macros). Not in this plan.
- **All AI calls:** `claude-haiku-4-5-20251001` (diagnosis review + monitoring decision only).

---

## Task 13: AI Recipe Generator (Food Library)

**Files:**
- Modify: `backend/app/Http/Controllers/RND/RecipeController.php` — add `aiGenerate()` method
- Modify: `backend/routes/api.php` — add POST route
- Modify: `backend/tests/Feature/RecipeControllerTest.php` — add AI generate tests
- Create: `frontend/app/api/rnd/recipes/ai-generate/route.ts` — proxy
- Modify: `frontend/app/(rnd)/food-library/recipes/new/page.tsx` — add "Generate with AI" panel
- Modify: `frontend/app/(rnd)/food-library/recipes/[id]/page.tsx` — same panel for edit

**Design:**
- Primary mode: RND selects ingredients from food library + types optional prompt → AI returns name + prep notes
- Macros always calculated deterministically from selected ingredients (existing `recalculateTotals()` logic — AI never touches numbers)
- AI model: `claude-haiku-4-5-20251001`
- Cost: ~$0.001 per generation (prompt + ingredients ~350 tokens in, name + instructions ~200 tokens out)
- All calls logged to `ai_usage_logs`

**Prompt sent to Haiku:**
```
System: You are a clinical dietitian's recipe assistant for a Philippine district hospital.
Generate a recipe name and preparation instructions for the given ingredients.
Return JSON: { "name": string, "prep_notes": string, "suggested_servings": number }
Keep prep_notes concise (3–6 steps). Focus on practical hospital kitchen preparation.

User: [optional RND prompt if provided]
Ingredients:
- Chicken breast, 200g
- Kangkong, 100g
- Garlic, 10g
...
```

- [ ] **Step 1: Write failing tests**

Append to `backend/tests/Feature/RecipeControllerTest.php`:

```php
public function test_ai_generate_returns_name_and_prep_notes(): void
{
    $rnd = User::factory()->create(['role' => 'rnd']);
    $food = FoodItem::factory()->create(['name' => 'Chicken breast', 'calories' => 165]);

    $this->mock(\App\Services\AIService::class, function ($mock) {
        $mock->shouldReceive('generateRecipe')
            ->once()
            ->andReturn([
                'name'               => 'Sinigang na Manok',
                'prep_notes'         => 'Boil chicken. Add vegetables. Season with sinigang mix.',
                'suggested_servings' => 4,
            ]);
    });

    $this->actingAs($rnd, 'sanctum')
        ->postJson('/api/rnd/recipes/ai-generate', [
            'prompt'      => 'Filipino soup',
            'ingredients' => [
                ['food_item_id' => $food->id, 'quantity' => 200, 'unit' => 'g'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Sinigang na Manok')
        ->assertJsonStructure(['data' => ['name', 'prep_notes', 'suggested_servings']]);
}

public function test_ai_generate_requires_at_least_one_ingredient(): void
{
    $rnd = User::factory()->create(['role' => 'rnd']);

    $this->actingAs($rnd, 'sanctum')
        ->postJson('/api/rnd/recipes/ai-generate', ['ingredients' => []])
        ->assertUnprocessable();
}
```

- [ ] **Step 2: Run tests — confirm fail**

```bash
php artisan test --filter=test_ai_generate
```

Expected: FAIL — route not found.

- [ ] **Step 3: Add AIService::generateRecipe() method**

In `backend/app/Services/AIService.php`, add:

```php
/**
 * Generate a recipe name and prep instructions from a list of ingredients.
 * Macros are NOT computed by AI — calculated deterministically from food_items.
 *
 * @param array $ingredients [['name' => string, 'quantity' => float, 'unit' => string], ...]
 * @param string|null $prompt Optional RND style prompt ("Filipino soup", "low sodium breakfast")
 * @return array{name: string, prep_notes: string, suggested_servings: int}
 */
public function generateRecipe(array $ingredients, ?string $prompt = null): array
{
    $ingredientLines = collect($ingredients)
        ->map(fn($i) => "- {$i['name']}, {$i['quantity']}{$i['unit']}")
        ->join("\n");

    $userMessage = ($prompt ? "Style/preference: {$prompt}\n\n" : '') .
        "Ingredients:\n{$ingredientLines}";

    $response = $this->client->messages()->create([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 400,
        'system'     => 'You are a clinical dietitian\'s recipe assistant for a Philippine district hospital. ' .
            'Generate a recipe name and preparation instructions from the given ingredients. ' .
            'Return only valid JSON: {"name": string, "prep_notes": string, "suggested_servings": number}. ' .
            'Keep prep_notes to 3–6 concise steps. Focus on practical hospital kitchen preparation.',
        'messages'   => [['role' => 'user', 'content' => $userMessage]],
    ]);

    $text   = $response->content[0]->text ?? '{}';
    $parsed = json_decode($text, true) ?? [];

    $this->logUsage($response, 'recipe-generate');

    return [
        'name'               => $parsed['name']               ?? 'Generated Recipe',
        'prep_notes'         => $parsed['prep_notes']          ?? '',
        'suggested_servings' => (int) ($parsed['suggested_servings'] ?? 1),
    ];
}
```

- [ ] **Step 4: Add aiGenerate() to RecipeController**

```php
/**
 * POST /api/rnd/recipes/ai-generate
 * AI generates name + prep notes. Macros NOT generated by AI.
 */
public function aiGenerate(Request $request): JsonResponse
{
    $validated = $request->validate([
        'prompt'                    => 'nullable|string|max:200',
        'ingredients'               => 'required|array|min:1',
        'ingredients.*.food_item_id'=> 'required|integer|exists:food_items,id',
        'ingredients.*.quantity'    => 'required|numeric|min:0.01',
        'ingredients.*.unit'        => 'required|string|max:50',
    ]);

    // Build ingredient list with names for the prompt
    $ingredients = collect($validated['ingredients'])->map(function ($ing) {
        $food = \App\Models\FoodItem::find($ing['food_item_id']);
        return [
            'name'     => $food->name,
            'quantity' => $ing['quantity'],
            'unit'     => $ing['unit'],
        ];
    })->toArray();

    $result = app(\App\Services\AIService::class)
        ->generateRecipe($ingredients, $validated['prompt'] ?? null);

    return response()->json(['data' => $result]);
}
```

- [ ] **Step 5: Add route**

In `backend/routes/api.php`, inside RND middleware group, after recipe routes:

```php
Route::post('recipes/ai-generate', [RecipeController::class, 'aiGenerate']);
```

> Place this BEFORE `Route::apiResource('recipes', ...)` so it's not swallowed by the `{recipe}` parameter.

- [ ] **Step 6: Run tests — confirm pass**

```bash
php artisan test --filter=test_ai_generate
php artisan test
```

Expected: 2 new tests pass, full suite green.

- [ ] **Step 7: Create Next.js proxy**

Create `frontend/app/api/rnd/recipes/ai-generate/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

export async function POST(req: NextRequest) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/recipes/ai-generate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: await req.text(),
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
```

- [ ] **Step 8: Add "Generate with AI" panel to recipe new/edit pages**

In both `frontend/app/(rnd)/food-library/recipes/new/page.tsx` and `recipes/[id]/page.tsx`, add an AI generation panel above the ingredient list. The panel:

1. Has an optional prompt textarea: `"Describe the recipe style (optional): Filipino dinner, low sodium..."`
2. Uses the already-selected ingredients from the ingredient list as input
3. Shows a "Generate with AI" button (disabled if no ingredients selected)
4. On success: auto-fills `name` state and `prepNotes` state in the recipe form
5. Shows a muted note: `"AI generates name and instructions only. Macros are calculated from your ingredients."`

```tsx
// Add to state:
const [aiPrompt, setAiPrompt]       = useState('');
const [aiGenerating, setAiGenerating] = useState(false);

// Add handler:
const handleAiGenerate = async () => {
  if (ingredients.length === 0) return;
  setAiGenerating(true);
  try {
    const res = await fetch('/api/rnd/recipes/ai-generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        prompt: aiPrompt || undefined,
        ingredients: ingredients
          .filter((i) => i.food_item_id)
          .map((i) => ({ food_item_id: i.food_item_id, quantity: i.quantity, unit: i.unit })),
      }),
    });
    if (!res.ok) throw new Error('AI generation failed.');
    const data = await res.json();
    if (data.data?.name)       setName(data.data.name);
    if (data.data?.prep_notes) setPrepNotes(data.data.prep_notes);
  } catch (e) {
    console.error(e);
  } finally {
    setAiGenerating(false);
  }
};

// Add UI panel (place above ingredient list):
<div className="bg-zinc-50 border border-zinc-200 rounded-2xl p-4 space-y-3">
  <div className="flex items-center gap-2">
    <span className="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Generate with AI</span>
    <span className="text-[9px] text-zinc-300">· Haiku fills name + instructions from your ingredients</span>
  </div>
  <textarea
    value={aiPrompt}
    onChange={(e) => setAiPrompt(e.target.value)}
    placeholder='Optional: "Filipino soup", "low sodium breakfast", "high protein snack"…'
    rows={2}
    className="w-full px-3 py-2 text-xs border border-zinc-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600"
  />
  <div className="flex items-center justify-between">
    <p className="text-[9px] text-zinc-400">Macros are always calculated from ingredients — not AI.</p>
    <button
      onClick={handleAiGenerate}
      disabled={aiGenerating || ingredients.filter((i) => i.food_item_id).length === 0}
      className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold bg-zinc-900 hover:bg-zinc-800 text-white rounded-lg transition-colors disabled:opacity-40 cursor-pointer">
      {aiGenerating
        ? <><Loader2 className="h-3 w-3 animate-spin" /> Generating…</>
        : <><Sparkles className="h-3 w-3" /> Generate</>}
    </button>
  </div>
</div>
```

Add `Sparkles` to Lucide imports.

- [ ] **Step 9: Verify in browser**

```bash
cd frontend && npm run dev
```

Navigate to Food Library → Recipes → New Recipe. Add 2–3 ingredients. Type a prompt. Click "Generate with AI". Verify name and prep notes auto-fill. Verify macros are untouched (calculated from ingredients as before).

- [ ] **Step 10: Commit**

```bash
git add backend/app/Services/AIService.php \
        backend/app/Http/Controllers/RND/RecipeController.php \
        backend/routes/api.php \
        backend/tests/Feature/RecipeControllerTest.php \
        frontend/app/api/rnd/recipes/ai-generate/route.ts \
        frontend/app/\(rnd\)/food-library/recipes/
git commit -m "feat: AI recipe generator — Haiku generates name + prep notes from selected ingredients and optional prompt"
```

---

---

## Task 14: Frontend — Auto-Generate Meal Plan Button

**Context:** `POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/generate` already exists and works.
`MealPlanController::generate()` is implemented. `MealPlanService` runs the full 7-step algorithm.
This task is frontend-only — wire the button in `MealPlanSection`.

**Files:**
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/generate/route.ts`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- Modify: `frontend/services/mealPlanService.ts` — add `generateMealPlan()`

- [ ] **Step 1: Create generate proxy route**

Create `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/generate/route.ts`:

```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string }> };

export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/ncp-records/${ncpRecordId}/meal-plans/generate`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: await req.text(),
  });
  const data = await res.json().catch(() => null);
  return NextResponse.json(data, { status: res.status });
}
```

- [ ] **Step 2: Add generateMealPlan() to mealPlanService.ts**

```ts
export async function generateMealPlan(
  ncpId: string,
  payload: { week_start_date: string; conditions?: string[]; allergens?: string[] }
): Promise<MealPlan | { insufficient_recipes: true; count: number }> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/generate`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) return data; // passes through {insufficient_recipes, count} or error
  return data.data ?? data;
}
```

- [ ] **Step 3: Add Generate button + logic to MealPlanSection**

Add state to `MealPlanSection`:
```tsx
const [generating, setGenerating] = useState(false);
const [generateError, setGenerateError] = useState<string | null>(null);
```

Add handler:
```tsx
const handleGenerate = async () => {
  setGenerating(true);
  setGenerateError(null);
  try {
    const d = new Date();
    const day = d.getDay();
    d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
    const weekStart = d.toISOString().split('T')[0];

    const result = await generateMealPlan(ncpId, { week_start_date: weekStart });

    if ('insufficient_recipes' in result) {
      setGenerateError(
        `Only ${result.count} recipe${result.count !== 1 ? 's' : ''} match this patient's restrictions. ` +
        `Add more goal-appropriate recipes to the food library, or build the meal plan manually.`
      );
      return;
    }
    // Success — reload plans
    await loadPlans();
  } finally {
    setGenerating(false);
  }
};
```

Add to the section header row (alongside "New Week" button):
```tsx
<button
  onClick={handleGenerate}
  disabled={generating}
  className="flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold border border-emerald-300 text-emerald-700 rounded-lg hover:bg-emerald-50 transition-colors cursor-pointer disabled:opacity-40">
  {generating
    ? <><Loader2 className="h-3 w-3 animate-spin" /> Generating…</>
    : <><Wand2 className="h-3 w-3" /> Auto-Generate</>}
</button>
```

Add error display below the header row:
```tsx
{generateError && (
  <div className="flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
    <AlertTriangle className="h-3.5 w-3.5 text-amber-600 flex-shrink-0 mt-0.5" />
    <p className="text-[10px] text-amber-800">{generateError}</p>
  </div>
)}
```

Add `Wand2, AlertTriangle` to Lucide imports.

- [ ] **Step 4: Verify in browser**

```bash
cd frontend && npm run dev
```

Navigate to Intervention page → Tab 1 → Meal Plan section. Click "Auto-Generate". Verify:
- If ≥ 15 goal-appropriate recipes exist: plan generates, days populate, day pills show kcal
- If insufficient recipes: amber warning message appears with count
- "New Week" manual button still works alongside it

- [ ] **Step 5: Commit**

```bash
git add frontend/app/api/rnd/ncp-records/\[ncpRecordId\]/meal-plans/generate/ \
        frontend/services/mealPlanService.ts \
        frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/MealPlanSection.tsx
git commit -m "feat: add Auto-Generate meal plan button — triggers MealPlanService algorithm, handles insufficient recipes"
```

---

## Task 15: Meal Plan Templates — Save and Load

**Context:** `meal_plan_templates` and `meal_plan_template_days` migrations already ran.
`MealPlanTemplate` and `MealPlanTemplateDay` models already exist.
Need: backend save/list/load-from-template endpoints + frontend save button + template picker.

**Files:**
- Modify: `backend/app/Http/Controllers/RND/MealPlanController.php` — add `saveTemplate()`, `templates()`, `fromTemplate()`
- Modify: `backend/routes/api.php` — add 3 routes
- Modify: `backend/tests/Feature/MealPlanControllerTest.php` — add template tests
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/[mealPlanId]/save-template/route.ts`
- Create: `frontend/app/api/rnd/meal-plan-templates/route.ts`
- Create: `frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/from-template/route.ts`
- Modify: `frontend/services/mealPlanService.ts` — add template functions
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx` — save button + template picker

- [ ] **Step 1: Write failing tests**

Append to `backend/tests/Feature/MealPlanControllerTest.php`:

```php
public function test_rnd_can_save_meal_plan_as_template(): void
{
    [$rnd, $ncp, $plan] = $this->setupPlanWithItems();

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/meal-plans/{$plan->id}/save-template", [
            'name'      => 'CKD Stage 4 — Week A',
            'goal_type' => 'renal_diet',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'CKD Stage 4 — Week A');

    $this->assertDatabaseHas('meal_plan_templates', ['name' => 'CKD Stage 4 — Week A']);
}

public function test_rnd_can_list_templates(): void
{
    $rnd = $this->rnd();
    \App\Models\MealPlanTemplate::forceCreate([
        'rnd_user_id' => $rnd->id, 'name' => 'Template A', 'goal_type' => 'renal_diet',
    ]);

    $this->actingAs($rnd, 'sanctum')
        ->getJson('/api/rnd/meal-plan-templates')
        ->assertOk()
        ->assertJsonCount(1, 'data');
}

public function test_rnd_can_create_plan_from_template(): void
{
    [$rnd, $ncp, $plan] = $this->setupPlanWithItems();
    $template = \App\Models\MealPlanTemplate::forceCreate([
        'rnd_user_id' => $rnd->id, 'name' => 'Template A',
    ]);

    $this->actingAs($rnd, 'sanctum')
        ->postJson("/api/rnd/ncp-records/{$ncp->id}/meal-plans/from-template", [
            'template_id'     => $template->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.generation_type', 'manual');
}
```

- [ ] **Step 2: Run tests — confirm fail**

```bash
php artisan test --filter=test_rnd_can_save_meal_plan_as_template
```

Expected: FAIL — routes not found.

- [ ] **Step 3: Add routes**

In `backend/routes/api.php`, after existing meal-plan routes:

```php
Route::post('ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template', [MealPlanController::class, 'saveTemplate']);
Route::get('meal-plan-templates', [MealPlanController::class, 'templates']);
Route::post('ncp-records/{ncpRecord}/meal-plans/from-template', [MealPlanController::class, 'fromTemplate']);
```

> Place `from-template` route BEFORE `generate` route so `from-template` isn't absorbed by a `{mealPlan}` param.

- [ ] **Step 4: Add methods to MealPlanController**

```php
/**
 * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/{mealPlan}/save-template
 */
public function saveTemplate(Request $request, NcpRecord $ncpRecord, MealPlan $mealPlan): JsonResponse
{
    $validated = $request->validate([
        'name'        => 'required|string|max:255',
        'description' => 'nullable|string',
        'goal_type'   => 'nullable|string|max:255',
    ]);

    $template = \App\Models\MealPlanTemplate::create([
        'rnd_user_id' => auth()->id(),
        'name'        => $validated['name'],
        'description' => $validated['description'] ?? null,
        'goal_type'   => $validated['goal_type'] ?? $ncpRecord->intervention?->goal_type,
    ]);

    // Copy each meal plan day + its items into template days
    foreach ($mealPlan->days as $day) {
        \App\Models\MealPlanTemplateDay::create([
            'template_id'  => $template->id,
            'day_of_week'  => $day->day_of_week,
            'meal_type'    => $day->meal_type,
            'food_item_id' => $day->items()->first()?->food_item_id,
            'recipe_id'    => $day->items()->first()?->recipe_id,
            'quantity'     => $day->items()->first()?->quantity ?? 1,
            'unit'         => $day->items()->first()?->unit ?? 'serving',
        ]);
    }

    return response()->json([
        'data' => ['id' => $template->id, 'name' => $template->name, 'goal_type' => $template->goal_type],
    ], 201);
}

/**
 * GET /api/rnd/meal-plan-templates
 */
public function templates(): JsonResponse
{
    $templates = \App\Models\MealPlanTemplate::where('rnd_user_id', auth()->id())
        ->orderByDesc('created_at')
        ->get(['id', 'name', 'description', 'goal_type', 'created_at']);

    return response()->json(['data' => $templates]);
}

/**
 * POST /api/rnd/ncp-records/{ncpRecord}/meal-plans/from-template
 * Creates a new blank meal plan pre-structured from a template's day/meal layout.
 */
public function fromTemplate(Request $request, NcpRecord $ncpRecord): JsonResponse
{
    $validated = $request->validate([
        'template_id'     => 'required|integer|exists:meal_plan_templates,id',
        'week_start_date' => 'required|date',
    ]);

    $template = \App\Models\MealPlanTemplate::with('days')->findOrFail($validated['template_id']);

    $plan = MealPlan::create([
        'intervention_id'  => $ncpRecord->intervention?->id,
        'patient_id'       => $ncpRecord->patient_id,
        'week_start_date'  => $validated['week_start_date'],
        'generation_type'  => 'manual',
        'status'           => 'draft',
    ]);

    foreach ($template->days as $tDay) {
        $day = \App\Models\MealPlanDay::create([
            'meal_plan_id' => $plan->id,
            'day_of_week'  => $tDay->day_of_week,
            'meal_type'    => $tDay->meal_type,
        ]);
        if ($tDay->food_item_id || $tDay->recipe_id) {
            \App\Models\MealPlanItem::create([
                'meal_plan_day_id' => $day->id,
                'food_item_id'     => $tDay->food_item_id,
                'recipe_id'        => $tDay->recipe_id,
                'quantity'         => $tDay->quantity,
                'unit'             => $tDay->unit,
                'nutrient_snapshot'=> $tDay->food_item_id
                    ? \App\Models\FoodItem::find($tDay->food_item_id)?->toNutrientSnapshot()
                    : null,
            ]);
        }
    }

    return response()->json(['data' => new MealPlanResource($plan->load('days.items'))], 201);
}
```

> Note: `FoodItem::toNutrientSnapshot()` is a convenience method to add. If it doesn't exist, inline the array: `['name' => $food->name, 'calories' => $food->calories, ...]`.

- [ ] **Step 5: Run tests — confirm pass**

```bash
php artisan test --filter=template
php artisan test
```

Expected: 3 new tests pass, full suite green.

- [ ] **Step 6: Create Next.js proxy routes**

`frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/[mealPlanId]/save-template/route.ts`:
```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';
const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string; mealPlanId: string }> };
export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId, mealPlanId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/ncp-records/${ncpRecordId}/meal-plans/${mealPlanId}/save-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: await req.text(),
  });
  return NextResponse.json(await res.json().catch(() => null), { status: res.status });
}
```

`frontend/app/api/rnd/meal-plan-templates/route.ts`:
```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';
const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
export async function GET(_req: NextRequest) {
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/meal-plan-templates`, {
    headers: { Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  return NextResponse.json(await res.json().catch(() => null), { status: res.status });
}
```

`frontend/app/api/rnd/ncp-records/[ncpRecordId]/meal-plans/from-template/route.ts`:
```ts
import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';
const API = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';
type Ctx = { params: Promise<{ ncpRecordId: string }> };
export async function POST(req: NextRequest, { params }: Ctx) {
  const { ncpRecordId } = await params;
  const store = await cookies();
  const token = store.get('nutriscope_token')?.value;
  const res = await fetch(`${API}/api/rnd/ncp-records/${ncpRecordId}/meal-plans/from-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: await req.text(),
  });
  return NextResponse.json(await res.json().catch(() => null), { status: res.status });
}
```

- [ ] **Step 7: Add template functions to mealPlanService.ts**

```ts
export interface MealPlanTemplate {
  id: number;
  name: string;
  description: string | null;
  goal_type: string | null;
  created_at: string;
}

export async function fetchMealPlanTemplates(): Promise<MealPlanTemplate[]> {
  const res = await fetch('/api/rnd/meal-plan-templates', { headers: { Accept: 'application/json' } });
  if (!res.ok) return [];
  return (await res.json()).data ?? [];
}

export async function saveMealPlanAsTemplate(
  ncpId: string,
  planId: number,
  payload: { name: string; description?: string; goal_type?: string }
): Promise<MealPlanTemplate> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/${planId}/save-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to save template.');
  return (await res.json()).data;
}

export async function createPlanFromTemplate(
  ncpId: string,
  payload: { template_id: number; week_start_date: string }
): Promise<MealPlan> {
  const res = await fetch(`/api/rnd/ncp-records/${ncpId}/meal-plans/from-template`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error('Failed to create plan from template.');
  return (await res.json()).data;
}
```

- [ ] **Step 8: Add Save as Template + template picker to MealPlanSection**

Add state:
```tsx
const [templates, setTemplates]             = useState<MealPlanTemplate[]>([]);
const [saveTemplateOpen, setSaveTemplateOpen] = useState(false);
const [templateName, setTemplateName]       = useState('');
const [savingTemplate, setSavingTemplate]   = useState(false);
const [fromTemplateOpen, setFromTemplateOpen] = useState(false);
```

Load templates on mount (after `loadPlans`):
```tsx
useEffect(() => {
  fetchMealPlanTemplates().then(setTemplates);
}, []);
```

Save template handler:
```tsx
const handleSaveTemplate = async () => {
  if (!activePlan || !templateName.trim()) return;
  setSavingTemplate(true);
  try {
    const saved = await saveMealPlanAsTemplate(ncpId, activePlan.id, {
      name: templateName.trim(),
    });
    setTemplates((prev) => [saved, ...prev]);
    setSaveTemplateOpen(false);
    setTemplateName('');
  } finally { setSavingTemplate(false); }
};
```

Load from template handler:
```tsx
const handleFromTemplate = async (templateId: number) => {
  setFromTemplateOpen(false);
  setCreatingPlan(true);
  try {
    const d = new Date();
    d.setDate(d.getDate() + (d.getDay() === 0 ? -6 : 1 - d.getDay()));
    const plan = await createPlanFromTemplate(ncpId, {
      template_id: templateId,
      week_start_date: d.toISOString().split('T')[0],
    });
    setPlans((p) => [plan, ...p]);
    setActivePlan(plan);
  } finally { setCreatingPlan(false); }
};
```

Add to the section header row alongside existing buttons:
```tsx
{/* Template picker button */}
{templates.length > 0 && (
  <button onClick={() => setFromTemplateOpen(true)}
    className="flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold border border-zinc-200 text-zinc-500 rounded-lg hover:border-emerald-400 hover:text-emerald-700 transition-colors cursor-pointer">
    <LayoutTemplate className="h-3 w-3" /> From Template
  </button>
)}

{/* Save as template — only when a plan exists */}
{activePlan && (
  <button onClick={() => setSaveTemplateOpen(true)}
    className="flex items-center gap-1 px-3 py-1.5 text-[10px] font-bold border border-zinc-200 text-zinc-500 rounded-lg hover:border-emerald-400 hover:text-emerald-700 transition-colors cursor-pointer">
    <BookmarkPlus className="h-3 w-3" /> Save as Template
  </button>
)}
```

Save template modal:
```tsx
{saveTemplateOpen && (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
      <h3 className="text-sm font-extrabold text-zinc-900">Save as Template</h3>
      <div className="space-y-1.5">
        <label className="block text-[9px] font-bold text-zinc-400 uppercase tracking-widest">Template Name</label>
        <input type="text" value={templateName} onChange={(e) => setTemplateName(e.target.value)}
          placeholder='e.g. "CKD Stage 4 — Week A"'
          className="w-full px-3.5 py-2.5 text-sm border border-zinc-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600" />
      </div>
      <div className="flex gap-2 justify-end">
        <button onClick={() => setSaveTemplateOpen(false)}
          className="px-4 py-2 text-xs font-bold text-zinc-500 hover:text-zinc-700 cursor-pointer">Cancel</button>
        <button onClick={handleSaveTemplate} disabled={savingTemplate || !templateName.trim()}
          className="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors disabled:opacity-40 cursor-pointer">
          {savingTemplate ? 'Saving…' : 'Save Template'}
        </button>
      </div>
    </div>
  </div>
)}
```

Template picker modal:
```tsx
{fromTemplateOpen && (
  <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6 space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-extrabold text-zinc-900">Load Template</h3>
        <button onClick={() => setFromTemplateOpen(false)} className="text-zinc-400 hover:text-zinc-700 cursor-pointer"><X className="h-4 w-4" /></button>
      </div>
      <div className="space-y-2 max-h-72 overflow-y-auto">
        {templates.map((t) => (
          <button key={t.id} onClick={() => handleFromTemplate(t.id)}
            className="w-full text-left p-3 border border-zinc-200 rounded-xl hover:border-emerald-400 hover:bg-emerald-50 transition-colors cursor-pointer">
            <p className="text-xs font-semibold text-zinc-800">{t.name}</p>
            {t.goal_type && <p className="text-[10px] text-zinc-400 capitalize">{t.goal_type.replace(/_/g, ' ')}</p>}
          </button>
        ))}
      </div>
    </div>
  </div>
)}
```

Add `LayoutTemplate` to Lucide imports.

- [ ] **Step 9: Verify in browser**

Navigate to Intervention → Meal Plan section. Verify:
- Active plan shows "Save as Template" button → modal → saved → appears in template list
- "From Template" button shows picker → selecting creates a new plan pre-populated from template
- Template plan loads correctly in the day/meal grid

- [ ] **Step 10: Commit**

```bash
git add backend/app/Http/Controllers/RND/MealPlanController.php \
        backend/routes/api.php \
        backend/tests/Feature/MealPlanControllerTest.php \
        frontend/app/api/rnd/meal-plan-templates/ \
        frontend/app/api/rnd/ncp-records/ \
        frontend/services/mealPlanService.ts \
        frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/_components/MealPlanSection.tsx
git commit -m "feat: meal plan templates — save current plan as template, load template as new plan"
```

---

---

## Task 16: Gap Fixes — MacroTrackerBar wiring, day flagging, food dislikes warning

Fixes 3 gaps identified against system requirements. All changes are in `MealPlanSection.tsx` and `page.tsx`.

**Gap 1 — MacroTrackerBar `current` always 0**
The bar lives in `page.tsx` but meal plan totals live inside `MealPlanSection`. Fix: move the bar inside `MealPlanSection` where `itemsByKey` and `dayTotals()` already exist. Remove it from `page.tsx`.

**Gap 2 — Day pill flagging not shown**
`MealPlanService` sets `meal_plan_days.flagged = true` when a day is >10% off targets. Day pills should show amber border when flagged.

**Gap 3 — Patient food dislikes warning missing**
System requirements: "Patient food dislikes shown as warning note in meal plan cells but NOT filtered out — RND decides." Need to fetch `assessment.food_dislikes` and display an amber chip inside any meal slot that contains a disliked item.

**Files:**
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`
- Modify: `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MacroTrackerBar.tsx`

- [ ] **Step 1: Update MacroTrackerBar to accept optional className**

In `MacroTrackerBar.tsx`, add `className?: string` to props and apply it to the wrapper div. This lets it be used both sticky (in page) and inline (inside MealPlanSection):

```tsx
interface Props { targets: MacroTarget[]; className?: string }

export default function MacroTrackerBar({ targets, className = '' }: Props) {
  if (targets.every((t) => t.target <= 0)) return null;
  return (
    <div className={`flex flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2.5 bg-emerald-50 border-b border-emerald-100 ${className}`}>
      {targets.map(({ label, current, target, unit }) => (
        <div key={label} className="flex items-baseline gap-1">
          <span className="text-[9px] font-bold text-emerald-600 uppercase tracking-wider">{label}</span>
          <span className={`text-sm font-extrabold font-mono ${statusColor(current, target)}`}>
            {Math.round(current)}
          </span>
          <span className="text-[9px] text-zinc-400">/ {Math.round(target)} {unit}</span>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Remove MacroTrackerBar from page.tsx**

In `page.tsx`, remove the entire `{tab === "nd" && <MacroTrackerBar ... />}` block and its import. The bar now lives inside MealPlanSection.

- [ ] **Step 3: Move MacroTrackerBar into MealPlanSection + wire real current values**

In `MealPlanSection.tsx`:

Add import:
```tsx
import MacroTrackerBar from "./MacroTrackerBar";
```

The component already accepts `prescriptionTargets`. Inside the component, after the `dayTotals` function, add:

```tsx
const selectedTotals = dayTotals(selectedDay);

const trackerTargets = [
  { label: 'Energy', current: selectedTotals.cal,  target: prescriptionTargets.energy,  unit: 'kcal' },
  { label: 'Protein', current: selectedTotals.prot, target: prescriptionTargets.protein, unit: 'g'    },
  { label: 'Carbs',   current: selectedTotals.carb, target: prescriptionTargets.carbs,   unit: 'g'    },
  { label: 'Fat',     current: selectedTotals.fat,  target: prescriptionTargets.fat,     unit: 'g'    },
];
```

In the JSX, place MacroTrackerBar just above the day selector pills (inside the `{activePlan && (...)}` block):

```tsx
<MacroTrackerBar
  targets={trackerTargets}
  className="sticky top-0 z-10 rounded-xl mb-2"
/>
```

Remove the old day totals bar (`bg-zinc-50 border border-zinc-200`) that showed current vs target below the day pills — MacroTrackerBar now replaces it. Keep only the day pills themselves.

- [ ] **Step 4: Add amber border to flagged day pills**

`MealPlanDay` already has `flagged: boolean`. Update the day pill button in MealPlanSection:

```tsx
// Find the first day entry for this day_of_week to check if any meal is flagged
const isDayFlagged = activePlan.days.some(
  (d) => d.day_of_week === day && d.flagged === true
);

// In the pill className, add flagged state:
className={`px-3 py-2 rounded-xl text-[10px] font-bold border transition-colors cursor-pointer text-center ${
  selectedDay === day
    ? 'bg-emerald-600 text-white border-emerald-600'
    : isDayFlagged
      ? 'bg-white text-amber-700 border-amber-300 hover:border-amber-400'
      : 'bg-white text-zinc-600 border-zinc-200 hover:border-emerald-300'
}`}
```

Add a small flag indicator inside flagged day pills (below the kcal):
```tsx
{isDayFlagged && selectedDay !== day && (
  <span className="block text-[8px] text-amber-500 font-bold">⚠ review</span>
)}
```

- [ ] **Step 5: Fetch food dislikes + pass to MealPlanSection**

In `page.tsx`, update `PatientMetrics` state loading. After `fetchAssessment`, also store food_dislikes:

Add state:
```tsx
const [foodDislikes, setFoodDislikes] = useState<string[]>([]);
```

In `loadMetrics`, after setting patientMetrics:
```tsx
if (assessment?.food_dislikes && Array.isArray(assessment.food_dislikes)) {
  setFoodDislikes(assessment.food_dislikes.map((d: string) => d.toLowerCase()));
}
```

Pass to MealPlanSection:
```tsx
<MealPlanSection
  ncpId={ncpId}
  prescriptionTargets={...}
  foodDislikes={foodDislikes}
/>
```

- [ ] **Step 6: Show food dislikes warning in meal slots**

Update `MealPlanSection` props interface:
```tsx
interface Props {
  ncpId: string;
  prescriptionTargets: { energy: number; protein: number; carbs: number; fat: number };
  foodDislikes?: string[];
}
```

Inside each meal slot, after rendering items, add a dislikes check. In the `items.map(...)` block, check if the item name matches any disliked food:

```tsx
{items.map((item) => {
  const s = item.nutrient_snapshot;
  const isDisliked = (foodDislikes ?? []).some(
    (d) => s?.name?.toLowerCase().includes(d)
  );
  // ... existing item JSX ...
  return (
    <div key={item.id} ...>
      {/* existing item row */}
      {isDisliked && (
        <p className="text-[9px] text-amber-600 font-bold flex items-center gap-1 px-1.5 pb-1">
          <AlertTriangle className="h-2.5 w-2.5" />
          Patient dislikes this food — RND review recommended
        </p>
      )}
    </div>
  );
})}
```

Ensure `AlertTriangle` is imported from lucide-react.

- [ ] **Step 7: Verify all 3 fixes in browser**

```bash
cd frontend && npm run dev
```

Check:
- MacroTrackerBar shows real kcal/protein/carb/fat from meal plan items for selected day (not 0)
- Color changes green/amber/red based on proximity to targets as you add/remove foods
- Day pills show amber "⚠ review" badge when MealPlanService flagged that day as >10% off
- Adding a food item whose name matches a disliked food shows the amber warning under that item

- [ ] **Step 8: Commit**

```bash
git add frontend/app/\(rnd\)/ncp/\[patientId\]/intervention/\[ncpId\]/
git commit -m "fix: wire MacroTrackerBar to real meal plan totals, add day flagging, add food dislikes warning"
```

---

## Execution Order (updated)

1. Task 1 — Backend recommendations endpoint (TDD)
2. Task 2 — shadcn components install
3. Task 3 — nutritionCalculations.ts utility
4. Task 4 — Intervention API proxy routes
5. Task 5 — interventionService.ts
6. Task 6 — GoalSelectorModal
7. Task 7 — MicronutrientToggle
8. Task 8 — NutritionPrescriptionForm
9. Task 9 — MacroTrackerBar
10. Task 10 — RecommendAvoidPanel
11. Task 11 — Tab content components (Education, Counseling, GoalPlanning, Encounter)
12. Task 12 — Page rebuild + MealPlanSection extract
13. Task 13 — AI Recipe Generator (Food Library)
14. Task 14 — Auto-Generate meal plan button
15. Task 15 — Meal plan templates (save/load)
16. Task 16 — Gap fixes (MacroTrackerBar wiring, day flagging, food dislikes warning)

Tasks 2–5 are independent — any order. Tasks 6–11 depend on Tasks 3 + 5. Task 12 depends on all previous. Tasks 13–15 are independent of each other but depend on Task 12. Task 16 depends on Tasks 12 + 14.

---

## Workflow Tokens
- Plan: `artifacts/superpowers/plan.md` (this file)
- Execution: `artifacts/superpowers/execution.md`
- Review: `artifacts/superpowers/review.md`
