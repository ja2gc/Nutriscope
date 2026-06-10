# Intervention Goals — Clinical Reference

> **Purpose:** Research and formula reference for all NutriScope intervention goal types.
> Covers both adult and pediatric populations. Formulas are separated by population — never shared across rows.
> `disease_stage` values defined here map directly to `interventions.disease_stage` in the database.
>
> **System rule:** system-requirements supersede any inconsistency in this document.

---

## Table of Contents

1. [Basic Macro Calculations (Foundation)](#1-basic-macro-calculations-foundation)
2. [Renal Diet — CKD](#2-renal-diet--ckd)
3. [Diabetic Control](#3-diabetic-control)
4. [Cardiac Diet](#4-cardiac-diet)
5. [Weight Loss](#5-weight-loss)
6. [Weight Gain](#6-weight-gain)
7. [High Protein](#7-high-protein)
8. [Liver Disease](#8-liver-disease)
9. [Malnutrition](#9-malnutrition)
10. [Malnutrition vs Weight Gain — Clinical Distinction](#10-malnutrition-vs-weight-gain--clinical-distinction)
11. [Appendix — disease_stage Quick Reference](#appendix--disease_stage-quick-reference)

> **Design decision (2026-06-06):** Fluid restriction is **not a standalone intervention goal**. It is a clinical modifier applied within CKD (`renal_diet`) and Cardiac (`cardiac_diet`) goals. See the `fluid_ml` column in Sections 2 and 4 for stage-specific fluid targets. The `fluid_restriction` goal_type has been removed from the system.

---

## 1. Basic Macro Calculations (Foundation)

All intervention goals reference these base calculations. Goal-specific sections override individual values as needed.

> **Energy method rule:** Goals marked **TEE-based** use the full formula chain below (BMR → TEE → goal modifier). Goals marked **Flat rate** use a disease-specific kcal/kg target that replaces TEE entirely. This distinction is stated explicitly at the top of each goal section.

---

### [ADULT]

#### Calculation Workflow

```
Step 1: Determine weight basis
  If %IBW ≤ 120%  → use Actual Body Weight (ABW)
  If %IBW > 120%  → use Adjusted Body Weight (AjBW)

Step 2: Calculate IBW (Hamwi formula)
  Male:   IBW = 48.0 kg + 2.7 kg × (height_inches − 60)
  Female: IBW = 45.5 kg + 2.2 kg × (height_inches − 60)
  Floor:  IBW cannot be < 30 kg
  For height < 5 feet: subtract per-inch value for each inch under 60

Step 3: Calculate AjBW (only if ABW > 120% IBW)
  AjBW = IBW + 0.25 × (ABW − IBW)
  Note: 0.25 is the AND nutrition-specific correction factor.
        Drug dosing uses 0.4 — do not conflate.

Step 4: Calculate %IBW
  %IBW = (ABW / IBW) × 100

Step 5: Calculate BMR (Mifflin-St Jeor)
  Male:   BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age) + 5
  Female: BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age) − 161
  Use ABW for %IBW ≤ 120%; use AjBW for %IBW > 120%

Step 6: Calculate TEE
  TEE = BMR × Activity Factor × Stress Factor (if applicable)

Step 7: Apply goal-specific modifier (see each section)
```

---

#### Body Mass Index (BMI)

```
BMI = weight_kg / height_m²
```

> **System decision (updated 2026-06-11):** NutriScope uses **WHO Asia-Pacific BMI cut-points as the
> system default** (decision D1) for its Filipino patient population, who develop cardiometabolic disease
> at lower BMI than European-ancestry populations. WHO Western cut-points are retained only as a labeled
> reference. See [`intervention-goals-asia-pacific-research.md`](intervention-goals-asia-pacific-research.md) §2.

| BMI Range | Asia-Pacific Classification (default) | WHO Western (reference only) |
|---|---|---|
| < 18.5 | Underweight | Underweight |
| 18.5 – 22.9 | Normal | Normal (to 24.9) |
| 23.0 – 24.9 | Overweight | Normal |
| 25.0 – 29.9 | Obese Class I | Overweight |
| ≥ 30.0 | Obese Class II | Obese I–III |

> 23.0 = "increased risk" action point; 27.5 = "high risk" action point (within Obese I). Weight-loss
> `disease_stage` maps to AP (D2): `overweight` 23–24.9 · `class_1` 25–29.9 · `class_2` 30–34.9 · `class_3` ≥35.

**Source:** WHO Western Pacific Region / IASO / IOTF (2000). *The Asia-Pacific Perspective: Redefining Obesity and its Treatment.* — https://apps.who.int/iris/handle/10665/206936
**Western reference:** WHO BMI Classification — https://www.who.int/news-room/fact-sheets/detail/obesity-and-overweight

---

#### Ideal Body Weight (IBW) — Hamwi Formula

> NutriScope uses the Hamwi formula for consistency with AND clinical nutrition guidelines.

```
Male:   IBW = 48.0 kg + 2.7 kg per inch over 5 feet
Female: IBW = 45.5 kg + 2.2 kg per inch over 5 feet
```

For patients shorter than 5 feet: subtract the per-inch value for each inch under 5 feet.
Minimum floor: IBW cannot be < 30 kg.

**Adjusted Body Weight (AjBW)** — used when actual weight > 120% IBW:

```
AjBW = IBW + 0.25 × (actual weight − IBW)
```

> The 0.25 factor is the AND nutrition-specific correction. Drug dosing literature uses 0.4 — these are different contexts and must not be mixed.

**%IBW:**

```
%IBW = (actual weight / IBW) × 100
```

> **Weight-basis rule (M2, decided 2026-06-11) — used by the engine:**
> - **Energy (flat kcal/kg) and fluid (mL/kg):** use **working weight** = `%IBW > 120 ? AjBW : actual`.
> - **BMR (Mifflin):** use **`%IBW > 120 ? AjBW : actual`** (actual body weight, adjusted only if obese).
> - **Protein (g/kg):** use **IBW** for all adult goals (doc tables specify g/kg IBW).
>
> This resolves the prior `calcWorkingWeight` ambiguity (which used IBW for the 90–120% band). The
> machine-readable encoding lives in [`prescription-targets.json`](prescription-targets.json) →
> `weight_basis`, which is the single source of truth for both the backend and frontend engines.

| %IBW | Nutritional Status |
|---|---|
| > 120% | Obese |
| 110 – 120% | Overweight |
| 90 – 110% | Normal |
| 85 – 90% | Mildly underweight |
| 70 – 84% | Moderately underweight |
| < 70% | Severely underweight |

**Source:** Hamwi GJ (1964). *Therapy: Changing dietary concepts.* In: Danowski TS (ed). Diabetes Mellitus: Diagnosis and Treatment. American Diabetes Association.
Referenced in: AND Nutrition Care Manual — https://www.andeal.org/

---

#### Basal Metabolic Rate (BMR) — Mifflin-St Jeor Equation

```
Male:   BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age_years) + 5
Female: BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age_years) − 161
```

Use **actual weight** for %IBW ≤ 120%. Use **AjBW** for %IBW > 120%.

**Source:** Mifflin MD et al. (1990). A new predictive equation for resting energy expenditure in healthy individuals. *Am J Clin Nutr.* 51(2):241–247. PMID: 2305711. https://pubmed.ncbi.nlm.nih.gov/2305711/

---

#### Total Energy Expenditure (TEE)

```
TEE = BMR × Activity Factor
```

For hospitalized patients with acute illness or injury, apply a stress factor on top:

```
TEE (stressed) = BMR × Activity Factor × Stress Factor
```

| Activity Level | Factor | Clinical Context |
|---|---|---|
| Sedentary | 1.2 | Bedbound, ICU |
| Light | 1.375 | Ambulatory inpatient |
| Moderate | 1.55 | Outpatient, light daily activity |
| Very Active | 1.725 | Regular vigorous exercise |
| Extra Active | 1.9 | Heavy physical labor + exercise |

> For most hospitalized patients, use 1.2 (bedbound) or 1.375 (ambulatory).

**Stress Factors** (multiply over BMR × activity factor when applicable):

| Condition | Stress Factor |
|---|---|
| Post-minor surgery | 1.0 – 1.1 |
| Moderate trauma / sepsis | 1.2 – 1.4 |
| Major burns | 1.5 – 2.0 |

> If no acute stress condition is present, stress factor = 1.0 (omit from formula).

**Source:** Harris JA, Benedict FG (1919) revised by Roza AM, Shizgal HM (1984). Referenced in ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

#### Baseline Fluid Requirements (Adult)

For goals without a specific fluid restriction, apply this baseline:

```
Fluid = 30–35 mL/kg body weight/day
or approximately 1 mL/kcal/day.
Use clinical judgment — these are two estimation methods, not a comparative rule.
```

> These are alternative approaches to estimating fluid needs, not additive. Either method may be used depending on clinical context. Apply clinical judgment for patients with fever, excess losses, cardiac or renal conditions, or ICU status.

**Source:** ASPEN/AND Clinical Nutrition Guidelines. Referenced in: NICE CG32 — https://www.nice.org.uk/guidance/cg32

---

#### Baseline Macronutrient Distribution (Adult)

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Per TEE or flat rate (see each section) | Goal-adjusted |
| Protein | 0.8 g/kg IBW/day | Baseline; overridden by goal |
| Carbohydrates | 45–65% total kcal | Prefer complex carbs |
| Fat | 20–35% total kcal | Limit saturated/trans |
| Fiber | 25–38 g/day | Women 25 g, Men 38 g |
| Fluid | 30–35 mL/kg/day or 1 mL/kcal (greater) | Overridden if restriction applies |

**Source:** IOM. Dietary Reference Intakes for Energy, Carbohydrate, Fiber, Fat, Fatty Acids, Cholesterol, Protein, and Amino Acids. 2005. https://www.ncbi.nlm.nih.gov/books/NBK56068/

---

### [PEDIATRIC]

#### Calculation Workflow

```
Step 1: Classify using WHO z-scores (not BMI cutoffs)
Step 2: Calculate BMR using Schofield equation (age-banded, weight-based)
Step 3: TEE = BMR × PAL + Energy for Growth
Step 4: Apply goal-specific modifier
```

---

#### Weight Status — WHO Z-Scores

Pediatric patients do NOT use BMI cutoffs. Use WHO growth standard z-scores.

| Z-Score Indicator | Assesses | Age Range |
|---|---|---|
| WAZ (Weight-for-Age Z-score) | General nutritional status | 0–10 yrs |
| HAZ (Height-for-Age Z-score) | Stunting | 0–19 yrs |
| WHZ (Weight-for-Height Z-score) | Acute malnutrition | 0–5 yrs |
| BAZ (BMI-for-Age Z-score) | Overweight/obesity | 5–19 yrs |

| Z-Score | Classification |
|---|---|
| > +2 | Overweight (BAZ) |
| +1 to +2 | Risk of overweight |
| −1 to +1 | Normal |
| −2 to −1 | At risk |
| < −2 | Underweight / Moderate malnutrition |
| < −3 | Severe malnutrition |

**Source:** WHO Child Growth Standards — https://www.who.int/tools/child-growth-standards/standards

---

#### Basal Metabolic Rate (BMR) — Schofield Equation

W = weight in kg. Result in kcal/day.

**Males:**

| Age Band | Formula |
|---|---|
| 0–3 yrs | BMR = 59.512 × W − 30.4 |
| 3–10 yrs | BMR = 22.706 × W + 504.3 |
| 10–18 yrs | BMR = 17.686 × W + 658.2 |

**Females:**

| Age Band | Formula |
|---|---|
| 0–3 yrs | BMR = 58.317 × W − 31.1 |
| 3–10 yrs | BMR = 20.315 × W + 485.9 |
| 10–18 yrs | BMR = 13.384 × W + 692.8 |

**Source:** Schofield WN (1985). Predicting basal metabolic rate, new standards and review of previous work. *Hum Nutr Clin Nutr.* 39 Suppl 1:5–41. PMID: 4044297. https://pubmed.ncbi.nlm.nih.gov/4044297/

---

#### Total Energy Expenditure (Pediatric)

```
TEE = BMR × PAL + Energy for Growth
```

**Physical Activity Level (PAL):**

| Level | PAL | Context |
|---|---|---|
| Bedbound/hospitalized | 1.2 | ICU, post-op |
| Sedentary | 1.4 – 1.5 | Inpatient, ambulatory |
| Light activity | 1.6 – 1.7 | Outpatient |
| Active | 1.8 – 1.9 | Normal daily activity |

**Energy Allowance for Growth (add to TEE):**

| Age | Additional kcal/day |
|---|---|
| 0–6 months | +70 |
| 6–12 months | +45 |
| 1–3 yrs | +20 |
| 4–18 yrs | +10–25 |

> Hospitalized patients: growth allowance may be reduced or omitted during acute illness; restore during recovery.

**Source:** WHO/FAO/UNU (2004). Human Energy Requirements. https://www.fao.org/3/y5686e/y5686e.pdf

---

#### Baseline Macronutrient Distribution (Pediatric)

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Per TEE + growth | Age-adjusted |
| Protein | Age-banded DRI (see below) | Never cut below DRI |
| Carbohydrates | 45–65% total kcal | Same % as adult |
| Fat | 30–40% (0–3 yrs); 25–35% (4–18 yrs) | Higher fat % for young children |
| Fiber | Age + 5 g/day (e.g., 8 yrs → 13 g/day) | Or adult DRI if lower |

**Protein DRI by Age (g/kg/day):**

| Age | g/kg/day |
|---|---|
| 0–6 months | 1.52 |
| 7–12 months | 1.20 |
| 1–3 yrs | 1.05 |
| 4–13 yrs | 0.95 |
| 14–18 yrs | 0.85 |

**Source:** IOM Dietary Reference Intakes for Macronutrients (2005) — https://www.ncbi.nlm.nih.gov/books/NBK56068/

---

#### Fluid Requirements — Holliday-Segar Method (Pediatric)

```
First 10 kg:          100 mL/kg/day
Next 10 kg (10–20):    50 mL/kg/day
Each kg above 20 kg:   20 mL/kg/day
```

**Example:** 25 kg child = (10 × 100) + (10 × 50) + (5 × 20) = **1600 mL/day**

**Source:** Holliday MA, Segar WE (1957). The maintenance need for water in parenteral fluid therapy. *Pediatrics.* 19(5):823–832. https://pubmed.ncbi.nlm.nih.gov/13429656/

---

## 2. Renal Diet — CKD

**goal_type:** `renal_diet`
**Energy method:** Flat rate — disease-specific kcal/kg replaces TEE.

Disease stage drives all nutrient targets. GFR staging follows KDOQI/KDIGO classification.

---

### [ADULT]

**Energy for all CKD stages:** 25–35 kcal/kg body weight/day, individualized based on age, sex, physical activity level, body composition, weight status goals, CKD stage, and concurrent illness or inflammation.

> **Age-specific guidance:** Older adults (> 60 years) tend toward the lower end of the range (25–30 kcal/kg/day) due to reduced physical activity and lower metabolic demand. Younger, more active patients may need the upper end (30–35 kcal/kg/day). System default: **30 kcal/kg/day** — RND adjusts based on clinical assessment.

> **Note:** The previous flat 30–35 kcal/kg target was a clinically acceptable simplification consistent with older teaching materials. KDOQI 2020 updated this to emphasize individualization, particularly for elderly patients at risk of overfeeding.

| Stage | disease_stage | GFR (mL/min/1.73m²) | Protein (g/kg IBW) | Sodium | Potassium | Phosphorus | Fluid (mL/day) |
|---|---|---|---|---|---|---|---|
| Stage 1 | `stage_1` | ≥ 90 | 0.8 | < 2300 mg | Unrestricted | Unrestricted | 30–35 mL/kg (baseline) |
| Stage 2 | `stage_2` | 60–89 | 0.8 | < 2300 mg | Unrestricted | Unrestricted | 30–35 mL/kg (baseline) |
| Stage 3 | `stage_3` | 30–59 | 0.6–0.8 | < 2000 mg | Monitor; restrict if K > 5.0 mmol/L | 800–1000 mg/day if elevated | 30–35 mL/kg (baseline) |
| Stage 4 | `stage_4` | 15–29 | 0.6 | < 2000 mg | < 2000 mg/day | 800–1000 mg/day | Unrestricted unless edema |
| Stage 5 pre-dialysis | `stage_5_predialysis` | < 15 | 0.6 | < 1500 mg | < 2000 mg/day | 800–1000 mg/day | Individualized (~1000–1500) |
| Hemodialysis | `hemodialysis` | — | 1.2 | < 1500 mg | 2000–3000 mg/day | 800–1000 mg/day | **750 mL + prior day urine output** |
| Peritoneal Dialysis | `peritoneal` | — | 1.2–1.5 | < 2000 mg | Generally unrestricted | 800–1000 mg/day | **Individualized per dialysis Rx** |

> **Fluid note:** Fluid restriction applies from hemodialysis and peritoneal stages. System autofills `fluid_ml = 750` for hemodialysis. Peritoneal is individualized — RND enters manually.

> **Peritoneal note:** Subtract ~500–800 kcal/day from energy target to account for glucose absorbed from dialysate.

**Water intake tracking:** USDA nutrient ID for water is **1051**. NutriScope extracts this as `water_g` on `food_items`, enabling estimated daily fluid intake from the meal plan to be compared against the `fluid_ml` prescription target.

**Sources:**
- KDOQI Clinical Practice Guideline for Nutrition in CKD: 2020 Update. Ikizler TA et al. *Am J Kidney Dis.* 76(3 Suppl 1):S1–S107, 2020. https://www.ajkd.org/article/S0272-6386(20)30726-5/fulltext
- D'Alessandro C et al. Energy Requirement for Elderly CKD Patients. *Nutrients* 13(10):3396, 2021. https://www.ncbi.nlm.nih.gov/pmc/articles/PMC8541480/
- KDIGO 2022 CKD Guideline — https://kdigo.org/guidelines/ckd-evaluation-and-management/

---

### [PEDIATRIC]

**Energy:** DRI for age × 1.0–1.1

| Stage | disease_stage | Protein | Electrolytes | Fluid |
|---|---|---|---|---|
| Stage 1–3 | `stage_1` / `stage_2` / `stage_3` | DRI for age | Monitor K and phosphorus | Per Holliday-Segar |
| Stage 4–5 | `stage_4` / `stage_5_predialysis` | 0.8–1.0 g/kg/day | Restrict K if > 5.5 mmol/L; phosphorus 800 mg/day | Per Holliday-Segar; restrict if oliguric |
| Hemodialysis | `hemodialysis` | 1.4–1.8 g/kg/day | Per labs | Prescription-based |
| Peritoneal | `peritoneal` | 1.5–2.0 g/kg/day | Per labs | Per dialysis prescription |

**Source:** KDIGO CKD in Children — https://kdigo.org/guidelines/ckd-in-children/

---

## 3. Diabetic Control

**goal_type:** `diabetic_control`
**Energy method:** TEE-based for all stages.
**disease_stage values:** `stage_1` | `stage_2` | `stage_3`

> **Note:** The ADA 2024 Standards state there is no single ideal macronutrient distribution for all people with diabetes. The targets below represent clinically practical defaults for a hospital nutrition system. The RND should apply individualized judgment, particularly for carbohydrate distribution based on medication regimen, insulin regimen, activity level, and glycemic response.
>
> **HbA1c monitoring note:** HbA1c is a laboratory monitoring value, not a calculation input. It is recorded in the assessment (biochemical data) and reviewed during monitoring and evaluation to assess whether the diabetic control intervention is achieving its goal. General ADA target: HbA1c < 7% for most non-pregnant adults. However, targets should be individualized — less stringent goals (< 7.5%, < 8.0%, or < 8.5%) are appropriate for elderly patients, those with frailty, multiple comorbidities, limited life expectancy, or high hypoglycemia risk. HbA1c does not change any macro formula.

---

### disease_stage values

| disease_stage | Condition | When to use |
|---|---|---|
| `stage_1` | T1DM or T2DM, normal weight | Default — no weight or CKD complication |
| `stage_2` | T2DM + overweight/obesity (BMI ≥ 25 or %IBW > 110%) | T2DM patient with excess weight where weight loss is a clinical priority |
| `stage_3` | T1DM or T2DM coexisting with CKD (any non-dialysis stage) | Protein must be restricted; CKD takes precedence on protein target |

---

### [ADULT]

| Nutrient | `stage_1` (T1DM / T2DM normal weight) | `stage_2` (T2DM + overweight) | `stage_3` (T1DM / T2DM + CKD) |
|---|---|---|---|
| Energy | TEE | TEE − 500 kcal/day; floor Female ≥ 1200, Male ≥ 1500 | TEE (no deficit) |
| Carbohydrates | 45–60% total kcal; min 130 g/day | 45–55% of reduced total kcal | 45–60% total kcal |
| Carb distribution | 45–60 g/main meal; 15–30 g/snack *(default planning target — individualize per medication regimen and glycemic response)* | Same distribution on reduced total | Same |
| Protein | 0.8–1.0 g/kg | 0.8–1.0 g/kg | Target ≈ 0.8 g/kg/day; avoid routinely exceeding 1.3 g/kg/day |
| Fat | < 30% total kcal; saturated < 7% | < 30% total kcal | < 30% total kcal |
| Fiber | ≥ 25–38 g/day | ≥ 25–38 g/day | ≥ 25–38 g/day |
| Sodium | < 2300 mg/day; < 1500 if HTN coexists | < 2300 mg/day | < 2000 mg/day |
| Fluid | 30–35 mL/kg (baseline) | 30–35 mL/kg (baseline) | Per CKD stage (Section 2) |

> **T1DM insulin note:** For T1DM patients on insulin, carbohydrate distribution per meal affects insulin dosing. The carb-per-meal targets above (45–60 g/main meal) serve as the prescription anchor. The RND and clinical team set the carb-to-insulin ratio separately — NutriScope does not calculate insulin doses.

> **`stage_3` note:** When diabetes and CKD coexist, target protein ≈ 0.8 g/kg/day. Avoid routinely exceeding 1.3 g/kg/day — higher intake is associated with increased albuminuria and accelerated kidney function loss per ADA/KDIGO guidelines. This is a clinical target, not a strict pharmacological cap.

> **`stage_2` note:** 5% weight loss in T2DM produces clinically significant improvement in glycemic control, blood pressure, and lipids. The −500 kcal deficit targets approximately 0.5 kg/week loss.

**Sources:**
- ADA Standards of Care in Diabetes 2024. *Diabetes Care* 47 (Suppl 1). https://diabetesjournals.org/care/issue/47/Supplement_1
- ADA Standards of Care in Diabetes 2026. *Diabetes Care* 49 (Suppl 1). https://diabetesjournals.org/care/article/49/Supplement_1/S6/163930/
- ADA Nutrition Therapy Consensus Report 2019. *Diabetes Care* 42(5):731. https://diabetesjournals.org/care/article/42/5/731/40480/
- ADA Standards of Care — CKD Section 11. *Diabetes Care* 2024. https://diabetesjournals.org/care/article/47/Supplement_1/S219/153938/
- ADA/AGS HbA1c individualization for older adults: American Geriatrics Society recommends HbA1c < 7.5% for healthy older adults; < 8.0%–8.5% for frail patients with comorbidities or high hypoglycemia risk. Referenced in: Glycemic Goals and Hypoglycemia. *Diabetes Care* 47 (Suppl 1) Section 6. https://diabetesjournals.org/care/article/47/Supplement_1/S111/153951/

---

### [PEDIATRIC]

**Type 1 Diabetes (T1DM):**

| Nutrient | Target | Notes |
|---|---|---|
| Energy | DRI for age — do NOT restrict | Growth must not be compromised |
| Carbohydrates | 45–55% total kcal; consistent distribution per meal | Carb counting preferred |
| Protein | DRI for age | Do not restrict |
| Fat | < 30% total kcal; saturated < 10% | |
| Fiber | Age + 5 g/day | |

**Type 2 Diabetes (T2DM) — typically adolescents:**

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Modest deficit (−500 kcal from TDEE) if overweight only | Only for overweight/obese adolescents |
| Carbohydrates | 45–55% total kcal | Reduce refined sugars and high-GI foods |
| Protein | DRI for age | |
| Fat | < 30% total kcal | |

**Source:** ADA Standards of Care — Pediatric Diabetes Management. *Diabetes Care* 47 (Suppl 1) Section 14. https://diabetesjournals.org/care/article/47/Supplement_1/S234/153955/

---

## 4. Cardiac Diet

**goal_type:** `cardiac_diet`
**Energy method:** TEE-based.
**disease_stage values:** `mild` | `moderate` | `severe`

> **Important:** The mild/moderate/severe staging used here are **NutriScope internal severity tiers**, not standardized clinical disease classifications from AHA guidelines. AHA does not define cardiac diet stages with these exact sodium thresholds. These tiers are reasonable clinical defaults for a hospital nutrition system and are consistent with the general direction of AHA sodium guidance, but the RND should apply clinical judgment based on the patient's actual diagnosis, medication regimen, and fluid status.

---

### [ADULT]

**Energy:** TEE (weight maintenance); TEE − 500 kcal if overweight coexists.

| Nutrient | Mild (`mild`) | Moderate (`moderate`) | Severe (`severe`) |
|---|---|---|---|
| Sodium | < 2300 mg/day | < 2000 mg/day | < 1500 mg/day |
| Total fat | < 30% total kcal | < 28% total kcal | < 25% total kcal |
| Saturated fat | ≤ 7% total kcal | ≤ 6% total kcal | ≤ 6% total kcal |
| Trans fat | Minimize | Minimize | Minimize |
| Cholesterol | < 300 mg/day | < 200 mg/day | < 200 mg/day |
| Fiber | ≥ 25–30 g/day | ≥ 30 g/day | ≥ 30 g/day |
| Fluid (`fluid_ml`) | 30–35 mL/kg (baseline) | **≤ 2000 mL/day** | **1000–1500 mL/day** |

> System autofills `fluid_ml = 2000` for moderate and `fluid_ml = 1500` for severe cardiac stages.

**DASH Diet Targets (all severity levels):**

| Nutrient | Daily Target |
|---|---|
| Potassium | 4700 mg/day |
| Calcium | 1250 mg/day |
| Magnesium | 500 mg/day |

**Sources:**
- AHA Dietary Guidance to Improve Cardiovascular Health. *Circulation* 2021. https://www.ahajournals.org/doi/10.1161/CIR.0000000000001031
- NHLBI DASH Eating Plan — https://www.nhlbi.nih.gov/education/dash-eating-plan

---

### [PEDIATRIC]

**Energy:** DRI for age (do not restrict unless overweight).

**Sodium targets by age (AHA/AAP):**

| Age | Max Sodium/day |
|---|---|
| 1–3 yrs | < 1000 mg |
| 4–8 yrs | < 1200 mg |
| 9–13 yrs | < 1500 mg |
| 14–18 yrs | < 2300 mg |

| Nutrient | Target |
|---|---|
| Total fat | < 30% total kcal |
| Saturated fat | < 10% total kcal |
| Cholesterol | < 300 mg/day |
| Fiber | Age + 5 g/day |
| Sodium | Age-specific (table above) |

**Source:** AHA Dietary Recommendations for Children and Adolescents — https://www.ahajournals.org/doi/10.1161/CIR.0000000000001031

---

## 5. Weight Loss

**goal_type:** `weight_loss`
**Energy method:** TEE-based (deficit applied to TEE).
**disease_stage values:** `overweight` | `class_1` | `class_2` | `class_3`

---

### [ADULT]

Stage maps to BMI classification.

| disease_stage | BMI | Energy Target | Expected Rate of Loss |
|---|---|---|---|
| `overweight` | 25.0–29.9 | TEE − 250 to 500 kcal/day | 0.25–0.5 kg/week |
| `class_1` | 30.0–34.9 | TEE − 500 kcal/day | ~0.5 kg/week |
| `class_2` | 35.0–39.9 | TEE − 500 to 750 kcal/day | 0.5–0.75 kg/week |
| `class_3` | ≥ 40.0 | TEE − 750 to 1000 kcal/day (supervised) | 0.75–1.0 kg/week |

**Caloric floors (never go below):**
- Female: ≥ 1200 kcal/day
- Male: ≥ 1500 kcal/day

| Nutrient | Target |
|---|---|
| Protein | 1.2–1.6 g/kg IBW/day (protein-sparing; preserves lean mass) |
| Carbohydrates | 45–55% of reduced total kcal; complex carbs preferred |
| Fat | 25–30% total kcal |
| Fiber | ≥ 25–38 g/day (satiety) |
| Fluid | 30–35 mL/kg (baseline) |

**Sources:**
- AND Evidence-Based Nutrition Practice Guideline: Adult Weight Management — https://www.andeal.org/topic.cfm?menu=5276
- NHLBI Clinical Guidelines on Overweight and Obesity — https://www.nhlbi.nih.gov/health/educational/lose_wt/

---

### [PEDIATRIC]

Children should generally NOT be placed on a caloric deficit. Goal is **weight maintenance while height increases** so BMI-for-age z-score normalizes over time.

| BAZ | disease_stage | Approach |
|---|---|---|
| +1 to +2 | `overweight` | No caloric restriction; improve food quality, increase fiber and activity |
| +2 to +3 | `class_1` | Maintain weight; DRI energy; reduce high-calorie/low-nutrient foods |
| > +3 | `class_2` / `class_3` | Modest energy reduction under specialist supervision only |

**Source:** ADA Standards for Pediatric T2DM and Obesity. https://diabetesjournals.org/care/article/47/Supplement_1/S234/153955/

---

## 6. Weight Gain

**goal_type:** `weight_gain`
**Energy method:** TEE-based (surplus applied to TEE) for `mild` and `moderate`. Flat rate protocol for `severe`.
**disease_stage values:** `mild` | `moderate` | `severe`

> **Important distinction from `malnutrition`:** Weight gain is used for patients who need a caloric surplus but do not meet GLIM criteria for malnutrition diagnosis. See Section 10 for the full clinical distinction.

Stage maps to %IBW (adult) or WAZ z-score (pediatric).

---

### [ADULT]

| disease_stage | %IBW | Energy Target | Notes |
|---|---|---|---|
| `mild` | 85–90% IBW | TEE + 300–500 kcal/day | Standard surplus |
| `moderate` | 70–84% IBW | TEE + 500–750 kcal/day | Monitor tolerance |
| `severe` | < 70% IBW | Refeeding protocol — start at 5–10 kcal/kg/day → target 30–35 kcal/kg/day, reach full needs by day 4–7 | Risk of refeeding syndrome; see protocol below |

> **`severe` ceiling:** Target energy is 30–35 kcal/kg/day. This is both the target and the ceiling during refeeding. Once full needs are reached (day 4–7), maintain at 30–35 kcal/kg/day — do not continue escalating beyond this.

| Nutrient | Target |
|---|---|
| Protein | 1.2–2.0 g/kg IBW/day (higher for severe) |
| Carbohydrates | 55–65% total kcal (primary energy driver) |
| Fat | 25–30% total kcal |
| Fluid | 30–35 mL/kg (baseline); monitor closely during refeeding |

#### Refeeding Syndrome Protocol (severe stage)

**Risk factors — any 1 triggers protocol:**
- BMI < 16
- Unintentional weight loss > 15% in 3–6 months
- Little or no nutritional intake > 10 days
- Low serum potassium, magnesium, or phosphate before refeeding

**Protocol:**

| Timeframe | Energy |
|---|---|
| Start | 5–10 kcal/kg/day (use 5 kcal/kg if BMI < 14 or negligible intake > 15 days) |
| Day 4–7 | Increase gradually to meet or exceed full needs (30–35 kcal/kg/day) |

- Monitor serum phosphate, potassium, magnesium **daily for first 72 hours**
- Supplement **thiamine 200–300 mg/day** before refeeding begins; continue for 10 days
- If phosphate falls below 0.5 mmol/L: stop feeding increase, replace electrolytes, reassess

> **Correction from previous version:** The refeeding timeline is 4–7 days to full needs, not a 3-week weekly progression. NICE CG32 is the authoritative source.

**Sources:**
- NICE Clinical Guideline CG32. Nutrition Support for Adults. https://www.nice.org.uk/guidance/cg32
- ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

### [PEDIATRIC]

| disease_stage | WAZ / BAZ | Energy Target |
|---|---|---|
| `mild` | WAZ −1 to −2 | DRI × 1.1 |
| `moderate` | WAZ −2 to −3 | DRI × 1.2–1.3 |
| `severe` | WAZ < −3 | Refeeding protocol (same phased approach as adult, scaled to body weight — see Section 6 protocol) |

> **Pediatric refeeding note:** The adult refeeding protocol applies in pediatrics with weight-based scaling. However, pediatric severe malnutrition (SAM) is a specialized condition — involve a pediatric specialist or pediatric dietitian where available. The WHO F-75/F-100 protocol (Section 9) applies to SAM specifically. The Section 6 refeeding protocol applies to weight_gain severe in older children and adolescents.

| Nutrient | Target |
|---|---|
| Energy | DRI × activity multiplier + growth allowance |
| Protein | 1.0–2.0 g/kg/day (increases with severity) |
| Fat | Age-appropriate (do not restrict) |

> Refeeding syndrome applies equally in pediatrics. Monitor electrolytes daily during first 72 hours regardless of age.

**Source:** NICE CG32 — https://www.nice.org.uk/guidance/cg32

---

## 7. High Protein

**goal_type:** `high_protein`
**Energy method:** Flat rate — disease-specific kcal/kg replaces TEE.
**disease_stage values:** `mild_stress` | `moderate_stress` | `severe_stress` | `burns`

Used for: post-surgery, trauma, sepsis, burns, pressure injuries, low albumin.

---

### [ADULT]

| disease_stage | Condition Examples | Protein (g/kg IBW/day) | Energy (kcal/kg/day) | Stress Factor |
|---|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild infection, low albumin | 1.0–1.2 | 25–30 | 1.0–1.1 |
| `moderate_stress` | Major surgery, trauma, sepsis, pressure injury | 1.2–1.5 | 25–30 | 1.2–1.4 |
| `severe_stress` | Critical illness, multi-organ failure | 1.5–2.0 | 25–30 | 1.4–1.6 |
| `burns` | Burns > 20% BSA | 1.5–2.0 | 30–35 | 1.5–2.0 |

> **Energy note:** For high protein goals, the flat kcal/kg rate already incorporates the stress factor implicitly. Do not apply an additional stress factor from Section 1 on top of the flat rate — that would double-count.

> **Micronutrient targets for specific conditions:**

| Condition | Additional Targets |
|---|---|
| Low albumin (< 3.5 g/dL) | Protein 1.5–2.0 g/kg/day; monitor albumin trend alongside CRP and inflammatory markers — albumin is a negative acute phase reactant and does not reliably reflect nutritional response in isolation |
| Pressure injuries | Zinc 25–40 mg/day; Vitamin C 500 mg/day; protein 1.25–1.5 g/kg/day |
| Burns | Zinc 25–40 mg/day; Vitamin C 500–1000 mg/day; Vitamin A supplementation |

> **Albumin note:** Serum albumin is heavily influenced by inflammation, infection, liver function, hydration status, and disease severity. It is not a reliable standalone nutrition outcome marker. ASPEN advises against using albumin in isolation to assess nutritional status. Monitor albumin trend as part of a broader clinical picture — normalization may reflect resolution of inflammation rather than nutritional recovery alone.

**Fluid:** 30–35 mL/kg baseline unless burns (burns require individualized fluid resuscitation per Parkland formula — not calculated by NutriScope).

**Sources:**
- ASPEN/SCCM Guidelines for Nutrition Support in the Adult Critically Ill Patient — https://www.nutritioncare.org/guidelines_and_clinical_resources/
- ESPEN Guidelines on Clinical Nutrition in Surgery (2017). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(17)30009-4/fulltext
- Mueller C, Compher C, Ellen DM. ASPEN Clinical Guidelines: Nutrition Screening, Assessment, and Intervention in Adults. *JPEN* 2011. (Advises against using albumin in isolation to assess nutrition status.) https://www.facs.org/media/paikclgt/lab_screeningserum_albumin_fact_sheet.pdf
- Harrington M et al. Admission serum albumin and nutritional therapy response. *eClinicalMedicine* 2022. https://www.thelancet.com/journals/eclinm/article/PIIS2589-5370(22)00031-1/fulltext

---

### [PEDIATRIC]

| disease_stage | Condition Examples | Protein (g/kg/day) | Energy |
|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild illness | DRI × 1.1–1.2 | DRI × 1.1 |
| `moderate_stress` | Major surgery, moderate trauma | 1.5 g/kg/day | DRI × 1.2–1.3 |
| `severe_stress` | Critical illness, sepsis | 2.0–3.0 g/kg/day | DRI × 1.3–1.5 |
| `burns` | Burns > 10% BSA | 2.0–3.0 g/kg/day | 1.5–2× DRI |

**Source:** ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

## 8. Liver Disease

**goal_type:** `liver_disease`
**Energy method:** Flat rate — disease-specific kcal/kg replaces TEE.
**disease_stage values:** `compensated` | `decompensated` | `encephalopathy_grade_1_2` | `encephalopathy_grade_3_4`

> **Important:** Protein restriction in liver disease is **contraindicated** per current ESPEN and EASL guidelines. Even in hepatic encephalopathy, the primary interventions are BCAA supplementation, vegetable and dairy-based proteins, and lactulose/rifaximin — not protein restriction. Routine protein restriction worsens sarcopenia and outcomes. A temporary, modest reduction may be considered only in rare protein-intolerant patients unresponsive to all other encephalopathy therapies — this is now considered a historical approach rarely used in modern practice.

---

### [ADULT]

| disease_stage | Condition | Energy (kcal/kg/day) | Protein (g/kg/day) | Sodium | Fluid | Notes |
|---|---|---|---|---|---|---|
| `compensated` | Cirrhosis, no ascites, no encephalopathy | 35–40 | 1.2–1.5 | < 2000 mg/day | 30–35 mL/kg baseline | Prefer small frequent meals; late-evening snack recommended |
| `decompensated` | Cirrhosis with ascites or fluid retention | 35–40 | 1.2–1.5 | < 2000 mg/day (strict) | Clinician-determined; restrict if edema | Sodium restriction critical |
| `encephalopathy_grade_1_2` | Mild–moderate encephalopathy | 35–40 | 1.2–1.5 | < 2000 mg/day | 30–35 mL/kg baseline | Maintain protein — do not restrict. Prefer vegetable and dairy protein sources. BCAA supplementation preferred. |
| `encephalopathy_grade_3_4` | Severe encephalopathy | 35–40 | 1.2–1.5 (target); temporary reduction to 1.0 only if protein-intolerant and unresponsive to all other therapies | < 2000 mg/day | Clinician-determined | Protein restriction is not first-line. Primary interventions: BCAA, vegetable/dairy protein, lactulose, rifaximin. Nasogastric or parenteral feeding if oral intake is unsafe. |

> **Sodium note for compensated stage:** < 2000 mg/day is the recommended sodium target for all cirrhosis stages regardless of ascites status, as prophylactic sodium restriction reduces risk of fluid retention progression.

> **BCAA:** Target 0.25 g BCAA/kg/day when encephalopathy is present.

> **Late-evening snack:** Recommended for all liver disease stages — reduces overnight fasting and prevents muscle catabolism.

**Sources:**
- ESPEN Clinical Nutrition Guidelines on Liver Disease (2019). Plauth M et al. *Clin Nutr.* 38(2):485–521. https://www.clinicalnutritionjournal.com/article/S0261-5614(19)30098-7/fulltext
- EASL Clinical Practice Guidelines on Nutrition in Chronic Liver Disease. *J Hepatol.* 2019. https://www.journal-of-hepatology.eu/article/S0168-8278(18)32145-7/fulltext
- Maharshi S et al. Protein restriction contraindicated in hepatic encephalopathy — current evidence supports 1.2–1.5 g/kg/day with vegetable protein preference. *J Gastroenterol Hepatol* 2021. https://www.ncbi.nlm.nih.gov/pmc/articles/PMC7911290/

---

### [PEDIATRIC]

| disease_stage | Energy | Protein | Fat | Notes |
|---|---|---|---|---|
| `compensated` | 130–150% of DRI for age | 1.5–3.0 g/kg/day | MCT oil preferred if steatorrhea | Fat-soluble vitamins A, D, E, K likely deficient — supplement |
| `decompensated` | 130–150% DRI | 2.0–3.0 g/kg/day | MCT oil; monitor fat tolerance | NG feeds may be needed |
| `encephalopathy_grade_1_2` | 130% DRI | 1.0–1.5 g/kg/day | Normal age-appropriate | Less protein restriction than adults; growth priority |
| `encephalopathy_grade_3_4` | 130% DRI | 1.0 g/kg/day (minimum) | Normal | Never restrict protein below growth-protective minimum |

> **Fat-soluble vitamin protocol:** Vitamin A 5000–10,000 IU/day; Vitamin D 800–2000 IU/day; Vitamin E 25 IU/kg/day; Vitamin K 2.5–5 mg 2–3×/week.

**Source:** ESPEN Guidelines on Pediatric Liver Disease — https://www.espen.org/guidelines-home

---

## 9. Malnutrition

**goal_type:** `malnutrition`
**Energy method:** Flat rate for `moderate`; Refeeding protocol for `severe`.
**disease_stage values:** `moderate` | `severe`

> **See Section 10** for the clinical distinction between `malnutrition` and `weight_gain`. These are not redundant — they differ in diagnostic criteria, protocol intensity, and clinical workflow.

---

### [ADULT]

#### Diagnosis — GLIM Criteria (2019, updated 2025)

Malnutrition diagnosis requires **≥ 1 phenotypic criterion AND ≥ 1 etiologic criterion.**

**Phenotypic criteria:**
- Non-volitional weight loss (> 5–10% in 6 months OR > 10–20% beyond 6 months for moderate; > 10–20% in 6 months OR > 20% beyond 6 months for severe)
- Low BMI (< 20 if age < 70; < 22 if age ≥ 70; for Asian populations, adjust per regional thresholds)
- Reduced muscle mass (assessed by BIA, CT, MRI, or anthropometric proxy)

**Etiologic criteria:**
- Reduced food intake or assimilation (< 50% of estimated energy needs > 1 week, or any reduction > 2 weeks)
- Inflammation or disease burden (acute illness, chronic disease)

#### Classification

| Indicator | `moderate` | `severe` |
|---|---|---|
| BMI | 16.0–18.49 | < 16.0 |
| %IBW | 70–84% | < 70% |
| MUAC | 190–210 mm | < 185 mm |
| System risk_score | 2–3 | > 3 |

#### Nutrition Targets

| disease_stage | Energy | Protein | Approach |
|---|---|---|---|
| `moderate` | 30–35 kcal/kg/day | 1.2–1.5 g/kg/day | Progressive feeding over 5–7 days; standard oral/enteral route |
| `severe` | Start 5–10 kcal/kg/day → target 30–35 kcal/kg/day, reach full needs by day 4–7 | Start 1.0 g/kg → target 1.5–2.0 g/kg | Refeeding protocol (see Section 6 protocol — identical steps apply) |

> **`severe` note:** Thiamine 200–300 mg/day must be given **before** refeeding begins and continued for 10 days. Daily electrolyte monitoring for first 72 hours.

**Fluid:** 30–35 mL/kg baseline; monitor closely during refeeding for fluid overload.

**Sources:**
- GLIM Criteria for the Diagnosis of Malnutrition (2019). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(18)31525-7/fulltext
- GLIM 5-Year Update (2025). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(25)00086-X/fulltext
- NICE CG32. Refeeding Syndrome — https://www.nice.org.uk/guidance/cg32
- WHO Malnutrition Fact Sheet — https://www.who.int/news-room/fact-sheets/detail/malnutrition

---

### [PEDIATRIC]

#### Classification — WHO z-scores

| Indicator | `moderate` | `severe` |
|---|---|---|
| WHZ (0–5 yrs) | −3 to −2 | < −3 |
| BAZ (5–19 yrs) | −3 to −2 | < −3 |
| MUAC (6–59 mo) | 115–125 mm | < 115 mm |
| HAZ (stunting) | −3 to −2 | < −3 |

#### Nutrition Targets

**Moderate Acute Malnutrition (MAM):**

| Nutrient | Target |
|---|---|
| Energy | 100–135 kcal/kg/day |
| Protein | 1.0–4.0 g/kg/day (age-scaled) |

**Severe Acute Malnutrition (SAM):**

| Phase | Energy | Protein | Duration |
|---|---|---|---|
| Phase 1 — Stabilization | 80–100 kcal/kg/day | 1.0–1.5 g/kg/day | Until appetite returns (typically 2–7 days) |
| Phase 2 — Rehabilitation | 150–220 kcal/kg/day | 4.0–6.0 g/kg/day | Until −2 WAZ achieved |

WHO F-75 formula in Phase 1; F-100 or RUTF in Phase 2.

**Source:** WHO. Management of Severe Acute Malnutrition in Infants and Children (2013) — https://www.who.int/publications/i/item/9789241506328

---

## 10. Malnutrition vs Weight Gain — Clinical Distinction

Both goal types can involve underweight patients and caloric surpluses. They are not redundant. The distinction is in diagnostic criteria and protocol intensity.

| Factor | `malnutrition` | `weight_gain` |
|---|---|---|
| Diagnosis | Requires GLIM criteria: ≥ 1 phenotypic + ≥ 1 etiologic criterion confirmed | No diagnostic criteria required — RND clinical judgment |
| Etiologic requirement | Must have reduced intake/assimilation OR inflammation/disease burden | Not required |
| Severe stage protocol | Mandatory refeeding protocol with daily electrolyte monitoring and thiamine | Refeeding protocol triggered only when %IBW < 70% |
| Thiamine supplementation | Mandatory before refeeding for severe | Only if refeeding protocol triggered |
| Typical patients | Confirmed hospital malnutrition (NCP diagnosis code NI-5.x or NC-3.x) | Post-illness recovery, athletes, patients needing general weight restoration without confirmed malnutrition diagnosis |
| Monitoring intensity | Daily labs for first 72 hours (severe) | Routine monitoring |

**Decision rule for the RND:**
- If GLIM criteria are met → use `malnutrition`
- If patient is underweight or needs weight gain but GLIM criteria are not met → use `weight_gain`
- If uncertain, `malnutrition` is the more conservative choice — its protocol is stricter and safer

---

## Appendix — disease_stage Quick Reference

| goal_type | disease_stage values | Energy method | fluid_ml autofill | Reference Section |
|---|---|---|---|---|
| `renal_diet` | `stage_1`, `stage_2`, `stage_3`, `stage_4`, `stage_5_predialysis`, `hemodialysis`, `peritoneal` | Flat rate (25–35 kcal/kg; default 30 kcal/kg, individualized by age) | 750 mL for `hemodialysis`; individualized for `peritoneal` | Section 2 |
| `diabetic_control` | `stage_1`, `stage_2`, `stage_3` | TEE-based | Not restricted (baseline 30–35 mL/kg) | Section 3 |
| `cardiac_diet` | `mild`, `moderate`, `severe` | TEE-based | 2000 mL for `moderate`; 1500 mL for `severe` | Section 4 |
| `weight_loss` | `overweight`, `class_1`, `class_2`, `class_3` | TEE-based (deficit) | Not restricted (baseline 30–35 mL/kg) | Section 5 |
| `weight_gain` | `mild`, `moderate`, `severe` | TEE-based (`mild`/`moderate`); Flat rate refeeding (`severe`) | Not restricted (baseline 30–35 mL/kg) | Section 6 |
| `high_protein` | `mild_stress`, `moderate_stress`, `severe_stress`, `burns` | Flat rate | Not restricted (baseline 30–35 mL/kg; burns individualized) | Section 7 |
| `liver_disease` | `compensated`, `decompensated`, `encephalopathy_grade_1_2`, `encephalopathy_grade_3_4` | Flat rate | Not restricted unless decompensated (clinician-determined) | Section 8 |
| `malnutrition` | `moderate`, `severe` | Flat rate (`moderate`); Refeeding protocol (`severe`) | Not restricted (baseline 30–35 mL/kg) | Section 9 |
| `custom` | `null` | Manual RND entry | Manual RND entry | Manual — no formula applied |

> **Removed goal:** `fluid_restriction` was a standalone goal type up to 2026-06-05. It was removed because fluid restriction is a clinical modifier embedded within CKD and Cardiac goals, not an independent nutritional intervention category.

---

## Changelog

| Date | Change |
|---|---|
| 2026-06-11 | **Asia-Pacific localization (D1):** BMI default switched to WHO Asia-Pacific cut-points (Western kept as reference); weight-loss `disease_stage` re-cut to AP (D2); diabetic `stage_2` trigger BMI ≥ 23 (D3) |
| 2026-06-11 | **Weight-basis rule pinned (M2):** energy/fluid use working weight (>120%→AjBW else actual); BMR same; protein uses IBW. Resolves `calcWorkingWeight` ambiguity |
| 2026-06-11 | **Machine-readable spec added:** `prescription-targets.json` is now the canonical engine contract (PHP authoritative, TS mirror); golden cases freeze expected outputs |
| 2026-06-11 | **PDRI baselines:** fiber 20–25 g, sodium < 2000 mg, free-sugars < 10% E, macro split carb 55–75% / fat 15–30% (research §5); pediatric goal-specific logic deferred (M4) |
| 2026-06-08 | Fixed liver disease encephalopathy protein — updated grade 1–2 and grade 3–4 to target 1.2–1.5 g/kg per ESPEN/EASL; protein restriction now labeled contraindicated; BCAA and vegetable/dairy protein as primary interventions |
| 2026-06-08 | Fixed albumin goal statement — removed "goal albumin ≥ 3.5 g/dL over 2–4 weeks"; replaced with monitoring language; added note that albumin is a negative acute phase reactant (ASPEN 2011) |
| 2026-06-08 | Fixed CKD energy — updated from flat 30–35 kcal/kg to individualized 25–35 kcal/kg per KDOQI 2020; added age-specific guidance; system default 30 kcal/kg |
| 2026-06-08 | Fixed diabetic carb distribution — labeled 45–60 g/meal as default planning target, not mandatory prescription |
| 2026-06-08 | Fixed HbA1c note — added individualization guidance for elderly/frail patients per ADA/AGS tiers |
| 2026-06-08 | Fixed diabetic stage_3 protein wording — changed from hard cap to target language per ADA/KDIGO |
| 2026-06-08 | Fixed cardiac staging label — added note that mild/moderate/severe are NutriScope internal severity tiers |
| 2026-06-08 | Fixed fluid baseline wording — removed "whichever is greater"; replaced with two estimation methods with clinical judgment |
| 2026-06-08 | Fixed pediatric weight gain refeeding — kept protocol, added specialist referral note |
| 2026-06-08 | Added Section 10 (Malnutrition vs Weight Gain clinical distinction) |
| 2026-06-08 | Renamed `diabetic_control` stages to neutral `stage_1`, `stage_2`, `stage_3` |
| 2026-06-08 | Added T1DM insulin carb distribution note (Section 3) |
| 2026-06-08 | Corrected refeeding timeline: 4–7 days to full needs, not 3-week weekly progression (Sections 6 and 9) |
| 2026-06-08 | Added energy method label (TEE-based vs Flat rate) to all goal sections |
| 2026-06-08 | Added Section 1 calculation workflow chain (Step 1–7) |
| 2026-06-08 | Added stress factor table and application rule to Section 1 |
| 2026-06-08 | Added baseline fluid requirement to Section 1 and all unrestricted goal sections |
| 2026-06-08 | Clarified AjBW trigger threshold: 120% IBW; clarified 0.25 correction factor vs 0.4 pharmacokinetic |
| 2026-06-08 | Added BMI system decision note (Western cutoffs with Filipino population caveat) |
| 2026-06-08 | Added weight gain energy ceiling note for severe stage |
| 2026-06-08 | Added micronutrient targets table for high protein conditions |
| 2026-06-08 | Updated GLIM reference to include 2025 update |
| 2026-06-06 | Removed `fluid_restriction` as standalone goal type |

---

*Last updated: 2026-06-08*
*System requirements supersede any conflict with this document.*