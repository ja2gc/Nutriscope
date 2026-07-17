# NutriScope — Intervention Goals: Calculation Reference

> **This is the single source of truth for the prescription calculation engine.**
> All formula logic, thresholds, and nutrient targets live here.
> The PHP backend (`PrescriptionCalculator`) and TypeScript frontend engine both derive from this document.
> Assessment/diagnostic criteria (GLIM phenotypic, AWGS muscle mass, waist circumference, MUAC) are
> recorded during assessment but **do not feed the calculation engine** — they support the RND's
> clinical diagnosis only. Once `goal_type` and `disease_stage` are set, the engine only needs
> the inputs listed in §1.
>
> **Population:** Filipino adults and children. Asia-Pacific standards are the **default**.
> Western references are retained only for comparison.
>
> **Authority hierarchy:** This document → `prescription-targets.json` (machine-readable contract) →
> PHP backend (authoritative runtime) → TypeScript mirror.

**Last updated:** 2026-06-28
**References:** PDRI 2015 (FNRI-DOST, rev. Sept 2018) · WHO Asia-Pacific Perspective (2000) ·
KDOQI 2020 · ADA 2024/2026 · ESPEN 2019 · NICE CG32 · GLIM 2019/2025

---

## Table of Contents

1. [Calculation Engine Inputs](#1-calculation-engine-inputs)
2. [Step-by-Step Calculation Chain (Adult)](#2-step-by-step-calculation-chain-adult)
3. [Step-by-Step Calculation Chain (Pediatric)](#3-step-by-step-calculation-chain-pediatric)
4. [Baseline Nutrient Targets](#4-baseline-nutrient-targets)
5. [Renal Diet — CKD](#5-renal-diet--ckd)
6. [Diabetic Control](#6-diabetic-control)
7. [Cardiac Diet](#7-cardiac-diet)
8. [Weight Loss](#8-weight-loss)
9. [Weight Gain](#9-weight-gain)
10. [High Protein](#10-high-protein)
11. [Liver Disease](#11-liver-disease)
12. [Malnutrition](#12-malnutrition)
13. [Clinical Distinction: Malnutrition vs Weight Gain](#13-clinical-distinction-malnutrition-vs-weight-gain)
14. [Appendix — disease_stage Quick Reference](#14-appendix--disease_stage-quick-reference)
15. [Changelog](#15-changelog)

---

## 1. Calculation Engine Inputs

These are the **only fields the calculation engine reads**. Assessment fields not listed here
(MUAC, waist circumference, hip circumference, ASMI) are recorded in the
`assessments` table for clinical use but are **not passed to the prescription calculator**.
Calf circumference has been removed from the assessment entirely (it was an AWGS muscle-mass
proxy and fed nothing).

| Input | Source | Notes |
|---|---|---|
| `weight_kg` | Measured | Use dry weight if edema present — RND must enter dry weight manually |
| `height_cm` | Measured | — |
| `age_years` | Computed from `dob` at assessment date | — |
| `sex` | `patients.sex` | `male` \| `female` |
| `physical_activity_level` | Assessment | Maps to PAL factor (§2) |
| `stress_condition` | Assessment | Maps to stress factor (§2); `none` if absent |
| `goal_type` | RND selection | Determines which section applies |
| `disease_stage` | RND selection | Determines targets within goal |
| `edema_present` | Assessment boolean | If true: engine halts and requires RND to confirm dry weight before proceeding |
| `pregnancy_lactation_status` | Assessment | `none` \| `pregnant_t2` \| `pregnant_t3` \| `lactating` — triggers energy/protein add-ons |

**Derived within the engine (not stored as inputs):**

```
IBW          → Hamwi formula (§2)
AjBW         → only if %IBW > 120% (§2)
%IBW         → (weight_kg / IBW) × 100
working_weight → %IBW > 120 ? AjBW : weight_kg   [energy, fluid, BMR]
protein_weight → IBW (always, for all adult protein targets)
BMI          → weight_kg / (height_m)²
bmi_class_ap → Asia-Pacific table (§2)
BMR          → Mifflin-St Jeor using working_weight (§2)
TEE          → BMR × PAL × stress_factor (§2)
```

> **Edema rule:** If `edema_present = true` and the RND has not entered a dry weight, the engine must
> surface a warning and block prescription output. Fluid-bloated weight makes every kg-based target wrong.

---

## 2. Step-by-Step Calculation Chain (Adult)

### Step 1 — Ideal Body Weight (Hamwi Formula)

```
height_inches = height_cm / 2.54

Male:
  If height_inches >= 60:  IBW = 48.0 + 2.7 × (height_inches − 60)
  If height_inches < 60:   IBW = 48.0 − 2.7 × (60 − height_inches)

Female:
  If height_inches >= 60:  IBW = 45.5 + 2.2 × (height_inches − 60)
  If height_inches < 60:   IBW = 45.5 − 2.2 × (60 − height_inches)

Floor: IBW = max(IBW, 30)   [never less than 30 kg]
```

**Source:** Hamwi GJ (1964). Referenced in AND Nutrition Care Manual.

> **Known limitation:** Hamwi was derived from a US population using imperial units. It modestly
> over-estimates IBW in short-stature patients (common in Filipino adults). The 30 kg floor partially
> mitigates this. The RND may override the calculated IBW if clinically inappropriate — the override
> value is stored as `ibw_override_kg` and the engine uses it instead.

---

### Step 2 — Adjusted Body Weight (only if %IBW > 120%)

```
%IBW = (weight_kg / IBW) × 100

If %IBW > 120:
  AjBW = IBW + 0.25 × (weight_kg − IBW)
Else:
  AjBW = not used
```

> **0.25 factor:** AND nutrition-specific correction for metabolically active adipose tissue.
> Drug-dosing literature uses 0.4 — these are different contexts. Do not conflate.

---

### Step 3 — Working Weight (determines which weight feeds each formula)

```
working_weight = (%IBW > 120) ? AjBW : weight_kg
protein_weight = IBW   [always, for all adult protein g/kg targets]
```

This resolves the `calcWorkingWeight` ambiguity in the prior implementation:

| Formula component | Weight used |
|---|---|
| BMR (Mifflin) | `working_weight` |
| Energy kcal/kg (flat-rate goals) | `working_weight` |
| Fluid mL/kg | `working_weight` |
| Protein g/kg | `protein_weight` = IBW |

---

### Step 4 — BMI and Asia-Pacific Classification

```
BMI = weight_kg / (height_cm / 100)²
```

**Asia-Pacific BMI Classification (default for all Filipino patients):**

| BMI (kg/m²) | Asia-Pacific Class | WHO Western (reference only) |
|---|---|---|
| < 18.5 | Underweight | Underweight |
| 18.5 – 22.9 | Normal | Normal (to 24.9) |
| 23.0 – 24.9 | Overweight | Normal |
| 25.0 – 29.9 | Obese Class I | Overweight |
| ≥ 30.0 | Obese Class II | Obese Class I–III |

> **Action points** (within the AP system, not extra classification rows):
> - 23.0 = "increased risk" trigger point
> - 27.5 = "high risk" trigger point (within Obese I band)
>
> These are public-health action thresholds, not separate classification categories.

**Pediatric:** Does NOT use BMI cut-points. Uses WHO z-scores (§3).

**Source:** WHO Western Pacific Region / IASO / IOTF (2000). *The Asia-Pacific Perspective: Redefining
Obesity and its Treatment.* https://apps.who.int/iris/handle/10665/206936

---

### Step 5 — BMR (Mifflin-St Jeor Equation)

```
Male:   BMR = (10 × working_weight) + (6.25 × height_cm) − (5 × age_years) + 5
Female: BMR = (10 × working_weight) + (6.25 × height_cm) − (5 × age_years) − 161
```

Result in **kcal/day**.

> **Known limitation:** Mifflin-St Jeor was validated primarily in Western cohorts and may modestly
> over-predict BMR in some Asian adults (typically 3–8%). It remains the best general-purpose default
> short of indirect calorimetry, which is not available at Romana Pangan District Hospital.
> No correction factor is applied — the RND may adjust TEE clinically if needed.

**Source:** Mifflin MD et al. (1990). *Am J Clin Nutr.* 51(2):241–247. PMID: 2305711.

---

### Step 6 — Physical Activity Level (PAL) and Total Energy Expenditure

```
TEE = BMR × PAL
```

If an acute stress condition is present:
```
TEE = BMR × PAL × stress_factor
```

> Do not apply a stress factor unless the patient has an active acute condition. For goals that use
> a flat kcal/kg rate (CKD, High Protein, Liver Disease, Malnutrition `severe`), TEE is not used —
> the flat rate replaces it. The energy method is stated at the top of each goal section.

**PAL factors:**

| `physical_activity_level` | PAL | Typical clinical context |
|---|---|---|
| `bedbound` | 1.2 | ICU, immediate post-op, non-ambulatory |
| `light` | 1.375 | Ambulatory inpatient, limited mobility |
| `moderate` | 1.55 | Outpatient, light daily activity |
| `very_active` | 1.725 | Regular vigorous exercise |
| `extra_active` | 1.9 | Heavy physical labor |

> For most hospitalized patients: `bedbound` (1.2) or `light` (1.375).

**Stress factors:**

| `stress_condition` | Factor |
|---|---|
| `none` | 1.0 (omit from formula) |
| `minor_surgery` | 1.0 – 1.1 |
| `moderate_trauma_sepsis` | 1.2 – 1.4 |
| `major_burns` | 1.5 – 2.0 |

> **Double-count guard:** For `high_protein` and `malnutrition` goals that already use flat kcal/kg
> disease-specific rates, the stress factor is **not** applied on top. Those flat rates already
> embed severity. See §10.

**Source:** Roza AM, Shizgal HM (1984), as referenced in ASPEN Clinical Guidelines.

---

### Step 7 — Baseline Fluid

For goals without goal-specific fluid restriction:

```
Fluid = 30–35 mL/kg working_weight/day
     ≈ 1 mL/kcal/day  (independently consistent with PDRI water AI)
```

These are two equivalent estimation methods — not additive. Apply clinical judgment for patients
with fever, diarrhea, edema, cardiac or renal conditions, or ICU status.

**Source:** ASPEN/AND Clinical Nutrition Guidelines; NICE CG32.

---

### Step 8 — Pregnancy / Lactation Add-ons

Applied after goal-specific targets are computed:

| Status | Energy add-on | Protein add-on |
|---|---|---|
| `pregnant_t2` (2nd trimester) | +300 kcal/day | +27 g/day |
| `pregnant_t3` (3rd trimester) | +300 kcal/day | +27 g/day |
| `lactating` | +500 kcal/day | +27 g/day |

> **Source:** PDRI 2015 (FNRI-DOST). These add-ons apply over the goal-calculated baseline.
> CKD and cardiac disease-specific protein restrictions take precedence over the lactation add-on
> if they conflict — flag for RND review.

---

## 3. Step-by-Step Calculation Chain (Pediatric)

Pediatric calculations do **not** share formulas with adult calculations. The rows are never mixed.

### Step 1 — Weight Status Classification (WHO Z-Scores)

Pediatric patients do NOT use BMI cut-points.

| Z-score indicator | Assesses | Age range |
|---|---|---|
| WAZ (Weight-for-Age Z-score) | General nutritional status | 0–10 yrs |
| HAZ (Height-for-Age Z-score) | Stunting | 0–19 yrs |
| WHZ (Weight-for-Height Z-score) | Acute malnutrition | 0–5 yrs |
| BAZ (BMI-for-Age Z-score) | Overweight/obesity | 5–19 yrs |

| Z-score | Classification |
|---|---|
| > +2 | Overweight (BAZ) |
| +1 to +2 | At risk of overweight |
| −1 to +1 | Normal |
| −2 to −1 | At risk of underweight |
| < −2 | Underweight / Moderate malnutrition |
| < −3 | Severe malnutrition |

**Source:** WHO Child Growth Standards. https://www.who.int/tools/child-growth-standards/standards

---

### Step 2 — BMR (Schofield Equation)

W = weight in kg. Result in kcal/day.

**Males:**

| Age | Formula |
|---|---|
| 0–3 yrs | BMR = 59.512 × W − 30.4 |
| 3–10 yrs | BMR = 22.706 × W + 504.3 |
| 10–18 yrs | BMR = 17.686 × W + 658.2 |

**Females:**

| Age | Formula |
|---|---|
| 0–3 yrs | BMR = 58.317 × W − 31.1 |
| 3–10 yrs | BMR = 20.315 × W + 485.9 |
| 10–18 yrs | BMR = 13.384 × W + 692.8 |

**Source:** Schofield WN (1985). *Hum Nutr Clin Nutr.* 39 Suppl 1:5–41. PMID: 4044297.

---

### Step 3 — Pediatric TEE

```
TEE = BMR × PAL + Energy for Growth
```

**PAL (pediatric):**

| Level | PAL | Context |
|---|---|---|
| `bedbound` | 1.2 | ICU, post-op |
| `sedentary` | 1.4–1.5 | Inpatient, ambulatory |
| `light` | 1.6–1.7 | Outpatient |
| `active` | 1.8–1.9 | Normal daily activity |

**Energy for Growth (add to BMR × PAL):**

| Age | Additional kcal/day |
|---|---|
| 0–6 months | +70 |
| 6–12 months | +45 |
| 1–3 yrs | +20 |
| 4–18 yrs | +10–25 |

> During acute illness: growth allowance may be reduced or omitted. Restore during recovery.

**Source:** WHO/FAO/UNU (2004). *Human Energy Requirements.* https://www.fao.org/3/y5686e/y5686e.pdf

---

### Step 4 — Pediatric Fluid (Holliday-Segar)

```
First 10 kg:       100 mL/kg/day
Next 10 kg (10–20):  50 mL/kg/day
Each kg above 20:    20 mL/kg/day

Example: 25 kg child = (10×100) + (10×50) + (5×20) = 1600 mL/day
```

**Source:** Holliday MA, Segar WE (1957). *Pediatrics.* 19(5):823–832.

---

## 4. Baseline Nutrient Targets

These are the **healthy adult defaults** used when no goal-specific override applies.
All goal sections below override these values. The source is now PDRI 2015 (FNRI-DOST),
the legally mandated Philippine dietary standard (FDA Circular 2023-009).

### Adult Baseline (PDRI 2015)

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Per TEE (§2) | — |
| Protein | 0.8 g/kg IBW/day | Clinical floor; PDRI healthy-adult maintenance ≈ 1.0–1.2 g/kg but 0.8 is the disease-state floor used by all clinical goals |
| Carbohydrates | 55–75% total kcal | PDRI; reflects rice-based Filipino diet (IOM was 45–65%) |
| Fat | 15–30% total kcal | PDRI (IOM was 20–35%) |
| Fiber | 20–25 g/day | PDRI adult value (IOM was 25–38 g); therapeutic goals may target higher |
| Sodium | < 2000 mg/day | PDRI / WHO 2012 recommendation (IOM was < 2300 mg) |
| Free sugars | < 10% total kcal | PDRI / WHO 2015; add to all assessments |
| Fluid | 30–35 mL/kg/day | ≈ 1 mL/kcal/day (PDRI water AI confirms equivalence) |

**Source:** FNRI-DOST. *Philippine Dietary Reference Intakes (PDRI) 2015, rev. Sept 2018.*
https://www.fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf
FDA Circular 2023-009. https://www.fda.gov.ph/fda-circular-no-2023-009-adoption-of-2015-philippine-dietary-reference-intakes-pdri/

---

### Pediatric Baseline (PDRI 2015 — Protein RNI; IOM for macros)

**Protein RNI (PDRI 2015 — use g/day values; g/kg shown for reference):**

| Age group | Ref wt M/F (kg) | Protein RNI M/F (g/day) | Implied g/kg |
|---|---|---|---|
| 0–5 mo | 6.5 / 6.0 | 9 / 8 (AI) | ~1.4 |
| 6–11 mo | 9.0 / 8.0 | 17 / 15 | ~1.9 |
| 1–2 y | 12.0 / 11.5 | 18 / 17 | ~1.5 |
| 3–5 y | 17.5 / 17.0 | 22 / 21 | ~1.25 |
| 6–9 y | 23.0 / 22.5 | 30 / 29 | ~1.3 |
| 10–12 y | 33.0 / 36.0 | 43 / 46 | ~1.3 |
| 13–15 y | 48.5 / 46.0 | 62 / 57 | ~1.25 |
| 16–18 y | 59.0 / 51.5 | 72 / 61 | ~1.2 |

> **Why PDRI protein is higher than IOM g/kg:** PDRI builds in a protein-quality/digestibility
> correction for the typical Filipino rice-dominant diet. The clinical disease-state calculations
> below still use g/kg IBW because KDOQI/ESPEN/ADA protocols assume high-quality protein.

**Macronutrient distribution (PDRI 2015 AMDR):**

| Age | Protein % | Fat % | Carbohydrate % |
|---|---|---|---|
| Infants 0–5 mo | 5 | 40–60 | 35–55 |
| Infants 6–11 mo | 8–15 | 30–40 | 45–62 |
| Children 1–2 y | 6–15 | 25–35 | 50–69 |
| Children 3–18 y | 6–15 | 15–30 | 55–79 |

**Pediatric fiber:** "age + 5 g/day" heuristic (e.g., 8 yrs → 13 g). Aligns with PDRI pediatric ranges. ✅

---

## 5. Renal Diet — CKD

**`goal_type`:** `renal_diet`
**Energy method:** Flat rate — disease-specific kcal/kg **replaces** TEE.

### Adult

**Energy (all CKD stages):** 25–35 kcal/kg `working_weight`/day, individualized.
**System default:** 30 kcal/kg/day. RND adjusts per clinical assessment.

> Older adults (> 60 yrs): tend toward 25–30 kcal/kg/day. Younger/more active: 30–35 kcal/kg/day.
> Per KDOQI 2020 update — prior flat 30–35 kcal/kg was an acceptable simplification but
> individualization is now the standard.

**Protein uses `protein_weight` = IBW for all stages.**

| disease_stage | GFR (mL/min/1.73m²) | Protein (g/kg IBW/day) | Sodium | Potassium | Phosphorus | Fluid |
|---|---|---|---|---|---|---|
| `stage_1` | ≥ 90 | 0.8 | < 2000 mg | Unrestricted | Unrestricted | 30–35 mL/kg |
| `stage_2` | 60–89 | 0.8 | < 2000 mg | Unrestricted | Unrestricted | 30–35 mL/kg |
| `stage_3` | 30–59 | 0.6–0.8 | < 2000 mg | Monitor; restrict if K > 5.0 mmol/L | 800–1000 mg/day if elevated | 30–35 mL/kg |
| `stage_4` | 15–29 | 0.6 | < 2000 mg | < 2000 mg/day | 800–1000 mg/day | Unrestricted unless edema |
| `stage_5_predialysis` | < 15 | 0.6 | < 1500 mg | < 2000 mg/day | 800–1000 mg/day | Individualized (~1000–1500 mL) |
| `hemodialysis` | — | 1.2 | < 1500 mg | 2000–3000 mg/day | 800–1000 mg/day | **750 mL + prior-day urine output** |
| `peritoneal` | — | 1.2–1.5 | < 2000 mg | Generally unrestricted | 800–1000 mg/day | Individualized per dialysis Rx |

> **Sodium note (CKD stage 1–2):** KDOQI 2020 states < 2300 mg for early CKD. PDRI 2015 / WHO
> set the population sodium ceiling at < 2000 mg. NutriScope uses < 2000 mg across all CKD stages
> as a deliberate tightening for the Filipino context — stricter than KDOQI default but within
> the same direction of evidence and consistent with the PDRI baseline adopted system-wide.
> Stage 3–5 and dialysis sodium targets (< 2000, < 1500) are unchanged from KDOQI.

> **Fluid autofill:** `fluid_ml = 750` for `hemodialysis`. Peritoneal = RND manual entry.

> **Peritoneal energy:** Subtract 500–800 kcal/day from target to account for glucose absorbed
> from dialysate.

> **Water tracking:** USDA nutrient ID 1051 → `water_g` on `food_items`, compared against
> `fluid_ml` prescription target.

**Sources:**
- KDOQI Nutrition in CKD: 2020 Update. *Am J Kidney Dis.* 76(3 Suppl 1):S1–S107.
  https://www.ajkd.org/article/S0272-6386(20)30726-5/fulltext
- D'Alessandro C et al. *Nutrients* 13(10):3396, 2021.
  https://www.ncbi.nlm.nih.gov/pmc/articles/PMC8541480/

---

### Pediatric CKD

**Energy:** DRI × 1.0–1.1

| disease_stage | Protein | Electrolytes | Fluid |
|---|---|---|---|
| `stage_1` / `stage_2` / `stage_3` | DRI for age (PDRI g/day table in §4) | Monitor K and phosphorus | Holliday-Segar |
| `stage_4` / `stage_5_predialysis` | 0.8–1.0 g/kg/day | Restrict K if > 5.5 mmol/L; phosphorus 800 mg/day | Holliday-Segar; restrict if oliguric |
| `hemodialysis` | 1.4–1.8 g/kg/day | Per labs | Prescription-based |
| `peritoneal` | 1.5–2.0 g/kg/day | Per labs | Per dialysis Rx |

**Source:** KDIGO CKD in Children. https://kdigo.org/guidelines/ckd-in-children/

---

## 6. Diabetic Control

**`goal_type`:** `diabetic_control`
**Energy method:** TEE-based for all stages.
**`disease_stage` values:** `stage_1` | `stage_2` | `stage_3`

> **ADA 2024 position:** No single ideal macronutrient distribution exists for all people with
> diabetes. Targets below are clinically practical defaults. RND applies individualized judgment
> based on medication regimen, insulin type, activity, and glycemic response.

> **HbA1c:** Monitoring value only — does not change any macro formula.
> General ADA target: < 7.0% for most non-pregnant adults. Individualize for elderly/frail
> patients: < 7.5% (healthy older adults); < 8.0–8.5% (frailty, multiple comorbidities,
> high hypoglycemia risk).

### disease_stage selection

| disease_stage | Condition | When to use |
|---|---|---|
| `stage_1` | T1DM or T2DM, normal weight | Default — no excess weight, no CKD |
| `stage_2` | T2DM + overweight/obesity | BMI ≥ **23** (AP threshold) or %IBW > 110% — weight loss is a clinical priority |
| `stage_3` | T1DM or T2DM + CKD (any non-dialysis stage) | Protein restriction required; CKD takes precedence |

> **`stage_2` trigger changed to BMI ≥ 23** (AP overweight threshold). Prior value of ≥ 25 was
> the Western threshold and is incorrect for this population.

### Adult

| Nutrient | `stage_1` | `stage_2` | `stage_3` |
|---|---|---|---|
| Energy | TEE | TEE − 500 kcal/day; floor: F ≥ 1200 / M ≥ 1500 kcal | TEE (no deficit) |
| Carbohydrates | 45–60% total kcal; min 130 g/day | 45–55% of reduced total kcal | 45–60% total kcal |
| Carb distribution | 45–60 g/main meal; 15–30 g/snack (default planning target — individualize per regimen) | Same on reduced total | Same |
| Protein | 0.8–1.0 g/kg IBW | 0.8–1.0 g/kg IBW | Target ≈ 0.8 g/kg; avoid routinely exceeding 1.3 g/kg |
| Fat | < 30% total kcal; sat fat < 7% | < 30% total kcal | < 30% total kcal |
| Fiber | ≥ 25–30 g/day (therapeutic target) | ≥ 25–30 g/day | ≥ 25–30 g/day |
| Sodium | < 2000 mg/day | < 2000 mg/day | < 2000 mg/day |
| Fluid | 30–35 mL/kg (baseline) | 30–35 mL/kg (baseline) | Per CKD stage (§5) |

> **`stage_2` rationale:** 5% weight loss in T2DM produces clinically significant improvement
> in glycemic control, blood pressure, and lipids. The −500 kcal deficit targets ~0.5 kg/week.

> **`stage_3` protein:** Target 0.8 g/kg IBW/day. Avoiding routinely exceeding 1.3 g/kg is
> associated with reduced albuminuria and slower kidney function loss (ADA/KDIGO). This is a
> clinical target, not a strict cap — document it as guidance.

> **T1DM insulin note:** Carb distribution per meal affects insulin dosing. The 45–60 g/meal
> target is the prescription anchor. Carb-to-insulin ratio is set by the clinical team —
> NutriScope does not calculate insulin doses.

**Sources:**
- ADA Standards of Care 2024. *Diabetes Care* 47 (Suppl 1).
  https://diabetesjournals.org/care/issue/47/Supplement_1
- ADA Standards of Care 2026. *Diabetes Care* 49 (Suppl 1).
  https://diabetesjournals.org/care/article/49/Supplement_1/S6/163930/

---

### Pediatric Diabetic Control

**T1DM:**

| Nutrient | Target |
|---|---|
| Energy | DRI for age — do NOT restrict (growth must not be compromised) |
| Carbohydrates | 45–55% total kcal; consistent distribution per meal; carb counting preferred |
| Protein | DRI for age |
| Fat | < 30% total kcal; sat fat < 10% |
| Fiber | Age + 5 g/day |

**T2DM (typically adolescents, overweight/obese only):**

| Nutrient | Target |
|---|---|
| Energy | TEE − 500 kcal/day (only if overweight/obese; not for normal-weight adolescents) |
| Carbohydrates | 45–55% total kcal; reduce refined sugars and high-GI foods |
| Protein | DRI for age |
| Fat | < 30% total kcal |

**Source:** ADA Standards of Care — Pediatric Section 14. *Diabetes Care* 47 (Suppl 1).

---

## 7. Cardiac Diet

**`goal_type`:** `cardiac_diet`
**Energy method:** TEE-based.
**`disease_stage` values:** `mild` | `moderate` | `severe`

> **Staging note:** The mild/moderate/severe tiers are **NutriScope internal severity tiers**,
> not AHA standardized classifications. They are clinically reasonable defaults. RND applies
> judgment based on actual diagnosis, medication, and fluid status.

### Adult

**Energy:** TEE (weight maintenance). TEE − 500 kcal/day if overweight coexists (BMI ≥ 23, AP threshold).

| Nutrient | `mild` | `moderate` | `severe` |
|---|---|---|---|
| Sodium | < 2000 mg/day | < 2000 mg/day | < 1500 mg/day |
| Total fat | < 30% total kcal | < 28% total kcal | < 25% total kcal |
| Saturated fat | ≤ 7% total kcal | ≤ 6% total kcal | ≤ 6% total kcal |
| Trans fat | Minimize | Minimize | Minimize |
| Cholesterol | < 300 mg/day | < 200 mg/day | < 200 mg/day |
| Fiber | ≥ 25 g/day | ≥ 30 g/day | ≥ 30 g/day |
| Fluid (`fluid_ml`) | 30–35 mL/kg (baseline) | **≤ 2000 mL/day** | **1000–1500 mL/day** |

> **Fluid autofill:** `fluid_ml = 2000` for `moderate`; `fluid_ml = 1500` for `severe`.

> **Sodium:** Updated from < 2300 mg for `mild` — aligned to PDRI < 2000 mg as the base threshold.
> Severe already was < 1500 mg. ✅

**DASH targets (all severity levels, supplemental goals):**

| Nutrient | Daily target |
|---|---|
| Potassium | 4700 mg/day |
| Calcium | 1250 mg/day |
| Magnesium | 500 mg/day |

**Sources:**
- AHA Dietary Guidance 2021. *Circulation.* https://www.ahajournals.org/doi/10.1161/CIR.0000000000001031
- NHLBI DASH Eating Plan. https://www.nhlbi.nih.gov/education/dash-eating-plan

---

### Pediatric Cardiac Diet

**Energy:** DRI for age (do not restrict unless overweight).

| Age | Max Sodium/day |
|---|---|
| 1–3 yrs | < 1000 mg |
| 4–8 yrs | < 1200 mg |
| 9–13 yrs | < 1500 mg |
| 14–18 yrs | < 2000 mg |

| Nutrient | Target |
|---|---|
| Total fat | < 30% total kcal |
| Saturated fat | < 10% total kcal |
| Cholesterol | < 300 mg/day |
| Fiber | Age + 5 g/day |

**Source:** AHA Dietary Recommendations for Children and Adolescents.

---

## 8. Weight Loss

**`goal_type`:** `weight_loss`
**Energy method:** TEE-based (deficit applied to TEE).
**`disease_stage` values:** `overweight` | `class_1` | `class_2` | `class_3`

Stage maps to **Asia-Pacific BMI classification** (§2 Step 4).

### Adult

| disease_stage | AP BMI band | Energy target | Expected rate of loss |
|---|---|---|---|
| `overweight` | 23.0–24.9 | TEE − 250 to 500 kcal/day | 0.25–0.5 kg/week |
| `class_1` | 25.0–29.9 | TEE − 500 kcal/day | ~0.5 kg/week |
| `class_2` | 30.0–34.9 | TEE − 500 to 750 kcal/day | 0.5–0.75 kg/week |
| `class_3` | ≥ 35.0 | TEE − 750 to 1000 kcal/day (supervised) | 0.75–1.0 kg/week |

> **Caloric floors — never go below:**
> Female: ≥ 1200 kcal/day
> Male: ≥ 1500 kcal/day

| Nutrient | Target |
|---|---|
| Protein | 1.2–1.6 g/kg IBW/day (protein-sparing; preserves lean mass) |
| Carbohydrates | 45–55% of reduced total kcal; complex carbs preferred |
| Fat | 25–30% total kcal |
| Fiber | ≥ 25 g/day (satiety; PDRI floor) |
| Fluid | 30–35 mL/kg (baseline) |
| Sodium | < 2000 mg/day |

**Sources:**
- AND Evidence-Based Nutrition Practice Guideline: Adult Weight Management.
  https://www.andeal.org/topic.cfm?menu=5276
- NHLBI Clinical Guidelines on Overweight and Obesity.
  https://www.nhlbi.nih.gov/health/educational/lose_wt/

---

### Pediatric Weight Loss

Children should generally **not** be placed on a caloric deficit. Goal is weight **maintenance
while height increases** so BAZ normalizes over time.

| BAZ | disease_stage | Approach |
|---|---|---|
| +1 to +2 | `overweight` | No caloric restriction; improve food quality, increase fiber and activity |
| +2 to +3 | `class_1` | Maintain weight; DRI energy; reduce high-calorie/low-nutrient foods |
| > +3 | `class_2` / `class_3` | Modest energy reduction under specialist supervision only |

---

## 9. Weight Gain

**`goal_type`:** `weight_gain`
**Energy method:** TEE-based (`mild` / `moderate`); flat-rate target (`severe`).
**`disease_stage` values:** `mild` | `moderate` | `severe`

> **Distinction from `malnutrition`:** Weight gain is for patients needing a caloric surplus
> who do NOT meet GLIM criteria for a confirmed malnutrition diagnosis. See §13.

Stage maps to **%IBW** (adult) or **WAZ z-score** (pediatric).

### Adult

| disease_stage | %IBW | Energy target | Notes |
|---|---|---|---|
| `mild` | 85–90% | TEE + 300–500 kcal/day | Standard surplus |
| `moderate` | 70–84% | TEE + 500–750 kcal/day | Monitor tolerance |
| `severe` | < 70% | 30–35 kcal/kg `working_weight`/day | Use 32.5 kcal/kg system default; monitor clinical tolerance |

| Nutrient | Target |
|---|---|
| Protein | 1.2–2.0 g/kg IBW/day (higher end for severe) |
| Carbohydrates | 55–65% total kcal (primary energy driver) |
| Fat | 25–30% total kcal |
| Fluid | 30–35 mL/kg; monitor clinical tolerance |
| Sodium | < 2000 mg/day |

#### Severe-stage monitoring

Severe-stage prescriptions use the full 30–35 kcal/kg target. Potassium, phosphate, and magnesium
remain available as monitoring indicators when clinically appropriate, but they are not prescription
nutrients unless a numeric target is entered. No blank micronutrient target may be generated.

**Source:** NICE CG32. https://www.nice.org.uk/guidance/cg32

---

### Pediatric Weight Gain

| disease_stage | WAZ / BAZ | Energy target |
|---|---|---|
| `mild` | WAZ −1 to −2 | DRI × 1.1 |
| `moderate` | WAZ −2 to −3 | DRI × 1.2–1.3 |
| `severe` | WAZ < −3 | Specialist-directed full prescription |

| Nutrient | Target |
|---|---|
| Protein | 1.0–2.0 g/kg/day (increases with severity) |
| Fat | Age-appropriate; do not restrict |

> **Pediatric severe-stage note:** Monitor electrolytes as clinically indicated. For confirmed severe acute malnutrition (SAM) in
> children, involve a pediatric specialist — the WHO F-75/F-100 protocol (§12) applies to SAM
> specifically; this section applies to older children/adolescents needing general weight restoration.

**Source:** NICE CG32. https://www.nice.org.uk/guidance/cg32

---

## 10. High Protein

**`goal_type`:** `high_protein`
**Energy method:** Flat rate — disease-specific kcal/kg **replaces** TEE.
**`disease_stage` values:** `mild_stress` | `moderate_stress` | `severe_stress` | `burns`

Used for: post-surgery, trauma, sepsis, burns, pressure injuries, low albumin.

> **Do not apply TEE stress factor on top of the flat rate.** The flat kcal/kg already
> embeds stress severity. Applying an additional stress factor is double-counting.

### Adult

| disease_stage | Condition examples | Protein (g/kg IBW/day) | Energy (kcal/kg/day) |
|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild infection, low albumin | 1.0–1.2 | 25–30 |
| `moderate_stress` | Major surgery, trauma, sepsis, pressure injury | 1.2–1.5 | 25–30 |
| `severe_stress` | Critical illness, multi-organ failure | 1.5–2.0 | 25–30 |
| `burns` | Burns > 20% BSA | 1.5–2.0 | 30–35 |

**Additional micronutrient targets for specific conditions:**

| Condition | Target |
|---|---|
| Low albumin (< 3.5 g/dL) | Protein 1.5–2.0 g/kg IBW/day; monitor albumin trend alongside CRP — albumin is a negative acute-phase reactant and does not reliably reflect nutritional response in isolation |
| Pressure injuries | Zinc 25–40 mg/day; Vitamin C 500 mg/day; protein 1.25–1.5 g/kg IBW/day |
| Burns | Zinc 25–40 mg/day; Vitamin C 500–1000 mg/day; Vitamin A supplementation |

> **Albumin note:** Heavily influenced by inflammation, infection, liver function, hydration,
> and disease severity. Not a reliable standalone nutrition marker. Use as part of a broader
> clinical picture — normalization may reflect inflammation resolution, not nutritional recovery.

**Fluid:** 30–35 mL/kg baseline unless burns. Burns require individualized fluid resuscitation
(Parkland formula) — not calculated by NutriScope.

**Sources:**
- ASPEN/SCCM Guidelines for Nutrition Support in the Adult Critically Ill.
  https://www.nutritioncare.org/guidelines_and_clinical_resources/
- ESPEN Guidelines on Clinical Nutrition in Surgery (2017). *Clin Nutr.*

---

### Pediatric High Protein

| disease_stage | Condition examples | Protein (g/kg/day) | Energy |
|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild illness | DRI × 1.1–1.2 | DRI × 1.1 |
| `moderate_stress` | Major surgery, moderate trauma | 1.5 g/kg/day | DRI × 1.2–1.3 |
| `severe_stress` | Critical illness, sepsis | 2.0–3.0 g/kg/day | DRI × 1.3–1.5 |
| `burns` | Burns > 10% BSA | 2.0–3.0 g/kg/day | 1.5–2× DRI |

---

## 11. Liver Disease

**`goal_type`:** `liver_disease`
**Energy method:** Flat rate — disease-specific kcal/kg **replaces** TEE.
**`disease_stage` values:** `compensated` | `decompensated` | `encephalopathy_grade_1_2` | `encephalopathy_grade_3_4`

> **Critical:** Protein restriction in liver disease is **contraindicated** per ESPEN 2019 and
> EASL 2019. Even in hepatic encephalopathy, primary interventions are BCAA supplementation,
> vegetable/dairy protein sources, and lactulose/rifaximin — not protein restriction. Restricting
> protein worsens sarcopenia and outcomes. A temporary modest reduction (to 1.0 g/kg) is considered
> only in rare, protein-intolerant patients unresponsive to all other therapies — a historical approach
> no longer considered first-line.

### Adult

| disease_stage | Condition | Energy (kcal/kg/day) | Protein (g/kg IBW/day) | Sodium | Fluid |
|---|---|---|---|---|---|
| `compensated` | Cirrhosis, no ascites, no encephalopathy | 35–40 | 1.2–1.5 | < 2000 mg/day | 30–35 mL/kg |
| `decompensated` | Cirrhosis with ascites or fluid retention | 35–40 | 1.2–1.5 | < 2000 mg/day (strict) | Clinician-determined; restrict if edema |
| `encephalopathy_grade_1_2` | Mild-moderate encephalopathy | 35–40 | 1.2–1.5 | < 2000 mg/day | 30–35 mL/kg |
| `encephalopathy_grade_3_4` | Severe encephalopathy | 35–40 | 1.2–1.5 (target); temporary 1.0 only if protein-intolerant and unresponsive to all other therapies | < 2000 mg/day | Clinician-determined |

> **BCAA:** Target 0.25 g BCAA/kg IBW/day when encephalopathy is present.

> **Late-evening snack:** Recommended for all liver disease stages to reduce overnight fasting
> and prevent muscle catabolism.

> **Compensated sodium:** < 2000 mg/day applies even without ascites as prophylactic
> restriction to reduce fluid retention risk.

**Sources:**
- ESPEN Guidelines on Clinical Nutrition in Liver Disease (2019). *Clin Nutr.* 38(2):485–521.
  https://www.clinicalnutritionjournal.com/article/S0261-5614(19)30098-7/fulltext
- EASL Clinical Practice Guidelines on Nutrition in Chronic Liver Disease. *J Hepatol.* 2019.

---

### Pediatric Liver Disease

| disease_stage | Energy | Protein | Fat |
|---|---|---|---|
| `compensated` | 130–150% DRI for age | 1.5–3.0 g/kg/day | MCT oil preferred if steatorrhea |
| `decompensated` | 130–150% DRI | 2.0–3.0 g/kg/day | MCT oil; monitor fat tolerance |
| `encephalopathy_grade_1_2` | 130% DRI | 1.0–1.5 g/kg/day | Normal age-appropriate |
| `encephalopathy_grade_3_4` | 130% DRI | 1.0 g/kg/day (minimum — never below growth-protective floor) | Normal |

> **Fat-soluble vitamin protocol:** Vitamin A 5000–10,000 IU/day; Vitamin D 800–2000 IU/day;
> Vitamin E 25 IU/kg/day; Vitamin K 2.5–5 mg 2–3×/week.

---

## 12. Malnutrition

**`goal_type`:** `malnutrition`
**Energy method:** Flat rate for all stages.
**`disease_stage` values:** `moderate` | `severe`

> See §13 for the clinical distinction between `malnutrition` and `weight_gain`.

### Adult — Diagnosis (GLIM Criteria, 2019/2025)

Malnutrition diagnosis requires **≥ 1 phenotypic criterion AND ≥ 1 etiologic criterion.**
This is a clinical diagnosis made by the RND **before** selecting this goal type. The engine
does not run GLIM — it receives `goal_type = malnutrition` as an already-confirmed diagnosis.

> **GLIM criteria exist only in this section as diagnostic context. They are not calculation
> inputs and no field from the GLIM assessment changes any formula. The engine only reads
> `disease_stage`.**

**Phenotypic criteria (any 1):**
- Non-volitional weight loss:
  - Moderate: > 5% within 6 months OR > 10% beyond 6 months
  - Severe: > 10% within 6 months OR > 20% beyond 6 months
- Low BMI — **Asian-specific values** (GLIM Asian validation, *Clin Nutr* 2020):
  - Age < 70: moderate < 18.5 kg/m²; severe < 17.0 kg/m²
  - Age ≥ 70: moderate < 20.0 kg/m²; severe < 17.8 kg/m²
- Reduced muscle mass (assessed by BIA or anthropometric proxy — clinical documentation only;
  does not feed the prescription calculator)

**Etiologic criteria (any 1):**
- Reduced food intake/assimilation (< 50% estimated needs > 1 week, or any reduction > 2 weeks)
- Inflammation or disease burden (acute illness, chronic disease)

---

### Adult — Classification

| Indicator | `moderate` | `severe` |
|---|---|---|
| BMI (Asian) | 17.0–18.49 (< 70 yrs); 17.8–19.99 (≥ 70 yrs) | < 17.0 (< 70 yrs); < 17.8 (≥ 70 yrs) |
| %IBW | 70–84% | < 70% |
| System `risk_score` | 2–3 | > 3 |

> **MUAC removed from classification table.** Adult MUAC cut-offs are not well standardized
> and vary by region and frame size. MUAC is retained in the assessment schema as clinical
> documentation but is not used as a classification criterion in NutriScope. The BMI and %IBW
> criteria above are sufficient for `disease_stage` selection.

---

### Adult — Nutrition Targets

| disease_stage | Energy | Protein |
|---|---|---|
| `moderate` | 30–35 kcal/kg `working_weight`/day | 1.2–1.5 g/kg IBW/day |
| `severe` | 30–35 kcal/kg `working_weight`/day | 1.0 g/kg IBW/day system default; RND may individualize |

> **`severe` monitoring:** Serum phosphate, potassium, and magnesium remain available in the
> monitoring plan. They are not prescription targets unless a real numeric recommendation exists.

**Fluid:** 30–35 mL/kg `working_weight`/day; monitor for fluid overload.

**Sources:**
- GLIM Criteria 2019. *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(18)31525-7/fulltext
- GLIM 5-Year Update 2025. *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(25)00086-X/fulltext
- Asian GLIM low-BMI validation. *Clin Nutr.* 2020. PMID: 32739660.
- NICE CG32. https://www.nice.org.uk/guidance/cg32

---

### Pediatric — Malnutrition

**Classification (WHO z-scores):**

| Indicator | `moderate` | `severe` |
|---|---|---|
| WHZ (0–5 yrs) | −3 to −2 | < −3 |
| BAZ (5–19 yrs) | −3 to −2 | < −3 |
| MUAC (6–59 mo) | 115–125 mm | < 115 mm |
| HAZ (stunting) | −3 to −2 | < −3 |

> Pediatric MUAC retained for 6–59 months (universally accepted for this age band). Adult MUAC
> is removed from classification criteria only.

**Nutrition targets:**

**Moderate Acute Malnutrition (MAM):**

| Nutrient | Target |
|---|---|
| Energy | 100–135 kcal/kg/day |
| Protein | 1.0–4.0 g/kg/day (age-scaled) |

**Severe Acute Malnutrition (SAM):**

| Phase | Energy | Protein | Duration |
|---|---|---|---|
| Phase 1 — Stabilization | 80–100 kcal/kg/day | 1.0–1.5 g/kg/day | Until appetite returns (typically 2–7 days) |
| Phase 2 — Rehabilitation | 150–220 kcal/kg/day | 4.0–6.0 g/kg/day | Until WAZ −2 achieved |

WHO F-75 formula in Phase 1; F-100 or RUTF in Phase 2.

**Source:** WHO. *Management of Severe Acute Malnutrition in Infants and Children* (2013).

---

## 13. Clinical Distinction: Malnutrition vs Weight Gain

Both goal types may involve underweight patients and caloric surpluses. They are not redundant.

| Factor | `malnutrition` | `weight_gain` |
|---|---|---|
| Diagnosis | GLIM criteria confirmed by RND: ≥ 1 phenotypic + ≥ 1 etiologic | No diagnostic criteria — RND clinical judgment |
| Etiologic requirement | Must have reduced intake/assimilation OR inflammation/disease burden | Not required |
| Severe stage target | Flat 30–35 kcal/kg prescription | Flat 30–35 kcal/kg prescription |
| Micronutrients | Numeric prescription targets only; separate lab monitoring as indicated | Numeric prescription targets only; separate lab monitoring as indicated |
| Typical patients | Confirmed hospital malnutrition (NCP code NI-5.x or NC-3.x) | Post-illness recovery, general weight restoration without confirmed GLIM diagnosis |
| Monitoring | Daily labs for first 72 hours (severe) | Routine monitoring |

**Decision rule for the RND:**
- GLIM criteria met → `malnutrition`
- Underweight or needs weight gain but GLIM criteria not met → `weight_gain`
- Uncertain → RND confirms diagnosis before selecting the goal

---

## 14. Appendix — disease_stage Quick Reference

| goal_type | disease_stage values | Energy method | fluid_ml autofill |
|---|---|---|---|
| `renal_diet` | `stage_1` `stage_2` `stage_3` `stage_4` `stage_5_predialysis` `hemodialysis` `peritoneal` | Flat rate (25–35 kcal/kg; default 30) | 750 for `hemodialysis`; manual for `peritoneal` |
| `diabetic_control` | `stage_1` `stage_2` `stage_3` | TEE-based | 30–35 mL/kg (unrestricted) |
| `cardiac_diet` | `mild` `moderate` `severe` | TEE-based | 2000 for `moderate`; 1500 for `severe` |
| `weight_loss` | `overweight` `class_1` `class_2` `class_3` | TEE-based (deficit) | 30–35 mL/kg (unrestricted) |
| `weight_gain` | `mild` `moderate` `severe` | TEE (`mild`/`moderate`); flat rate (`severe`) | 30–35 mL/kg |
| `high_protein` | `mild_stress` `moderate_stress` `severe_stress` `burns` | Flat rate | 30–35 mL/kg; burns individualized |
| `liver_disease` | `compensated` `decompensated` `encephalopathy_grade_1_2` `encephalopathy_grade_3_4` | Flat rate | Baseline unless `decompensated` (clinician) |
| `malnutrition` | `moderate` `severe` | Flat rate | 30–35 mL/kg |
| `custom` | null | Manual RND entry | Manual RND entry |

> **Removed:** `fluid_restriction` as standalone goal type (removed 2026-06-05). Fluid restriction
> is a clinical modifier embedded within CKD and Cardiac goals.

---

## 15. Changelog

| Date | Change |
|---|---|
| 2026-07-17 | **Severe prescriptions changed to flat full targets.** Removed staged low-calorie output and phase metadata. Severe `malnutrition` and `weight_gain` use 32.5 kcal/kg by default; micronutrients appear only with numeric targets. |
| 2026-06-28 | **Calf circumference removed from assessment entirely** (column dropped, UI/request/resource fields removed). Was an AWGS muscle-mass proxy that fed no calculation. |
| 2026-06-28 | **Verification pass (51 values confirmed, 4 corrected).** CKD sodium, diabetic fiber, BMI risk labeling, and GLIM weight-loss criteria were corrected. |
| 2026-06-28 | **Consolidated into single source of truth.** Merged `intervention-goals.md` and `intervention-goals-asia-pacific-research.md`. Research doc retired. |
| 2026-06-28 | **PDRI 2015 adopted as baseline.** Carb 55–75% / fat 15–30% / fiber 20–25 g / sodium < 2000 mg / free sugars < 10% E. Replaces IOM DRI as the national reference (FDA Circular 2023-009). |
| 2026-06-28 | **AP BMI is the default** for all Filipino patients. Western cut-points retained as reference column only. |
| 2026-06-28 | **Weight-loss BMI bands updated to AP:** `overweight` 23–24.9 / `class_1` 25–29.9 / `class_2` 30–34.9 / `class_3` ≥ 35. |
| 2026-06-28 | **Diabetic `stage_2` trigger** changed from BMI ≥ 25 to BMI ≥ 23 (AP overweight threshold). |
| 2026-06-28 | **Cardiac mild sodium** tightened from < 2300 mg to < 2000 mg (PDRI / WHO alignment). |
| 2026-06-28 | **CKD stage 1–2 sodium** updated from < 2300 mg to < 2000 mg (PDRI alignment). |
| 2026-06-28 | **MUAC removed from adult malnutrition classification** (poorly standardized in Asian hospital context). Retained in pediatric 6–59 mo and in assessment schema for clinical documentation. |
| 2026-06-28 | **Muscle mass / AWGS / ASMI / calf circumference** removed from calculation logic entirely. These are GLIM diagnostic inputs recorded in the assessment schema; they do not feed the prescription calculator. |
| 2026-06-28 | **Waist circumference / hip / WHR** clarified: assessment/diagnostic fields only; not calculation inputs. |
| 2026-06-28 | **GLIM low-BMI Asian cut-points filled** (previously "adjust per regional thresholds" with no numbers): age < 70 moderate < 18.5 / severe < 17.0; age ≥ 70 moderate < 20.0 / severe < 17.8. |
| 2026-06-28 | **Edema flag added** to engine inputs: blocks prescription output until RND confirms dry weight. |
| 2026-06-28 | **Pregnancy/lactation add-ons** added as step 8 in the calculation chain (PDRI 2015). |
| 2026-06-28 | **Double-count guard documented:** high_protein and malnutrition flat-rate goals must not receive an additional TEE stress factor. |
| 2026-06-11 | Asia-Pacific BMI default (D1); weight-loss disease_stage re-cut to AP (D2); diabetic stage_2 trigger BMI ≥ 23 (D3) |
| 2026-06-11 | Weight-basis rule M2 pinned; machine-readable spec `prescription-targets.json` established |
| 2026-06-08 | Liver disease protein restriction labeled contraindicated; ESPEN/EASL alignment |
| 2026-06-08 | CKD energy individualized per KDOQI 2020 (25–35 kcal/kg, default 30) |
| 2026-06-08 | Section 10 added (Malnutrition vs Weight Gain clinical distinction) |
| 2026-06-06 | Removed `fluid_restriction` as standalone goal type |

---

*System requirements supersede any conflict with this document.*
*This document is the authoritative clinical reference. `prescription-targets.json` is the
machine-readable encoding. PHP backend is the authoritative runtime.*
