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
10. [Appendix — disease_stage Quick Reference](#appendix--disease_stage-quick-reference)

> **Design decision (2026-06-06):** Fluid restriction is **not a standalone intervention goal**. It is a clinical modifier applied within CKD (`renal_diet`) and Cardiac (`cardiac_diet`) goals. See the `fluid_ml` column in Sections 2 and 4 for stage-specific fluid targets. The `fluid_restriction` goal_type has been removed from the system.

---

## 1. Basic Macro Calculations (Foundation)

All intervention goals reference these base calculations. Goal-specific files override individual values as needed.

---

### [ADULT]

#### Body Mass Index (BMI)

```
BMI = weight (kg) / height (m)²
```

| BMI Range | Classification |
|---|---|
| < 18.5 | Underweight |
| 18.5 – 24.9 | Normal |
| 25.0 – 29.9 | Overweight |
| 30.0 – 34.9 | Obese Class I |
| 35.0 – 39.9 | Obese Class II |
| ≥ 40.0 | Obese Class III |

**Source:** WHO BMI Classification — https://www.who.int/news-room/fact-sheets/detail/obesity-and-overweight

---

#### Ideal Body Weight (IBW) — Hamwi Formula

```
Male:   IBW = 48.0 kg + 2.7 kg per inch over 5 feet
Female: IBW = 45.5 kg + 2.2 kg per inch over 5 feet
```

For patients shorter than 5 feet: subtract the per-inch value for each inch under 5 feet.

**Adjusted Body Weight (AjBW)** — used when actual weight > 120% IBW:

```
AjBW = IBW + 0.25 × (actual weight − IBW)
```

**%IBW:**

```
%IBW = (actual weight / IBW) × 100
```

| %IBW | Nutritional Status |
|---|---|
| > 120% | Obese |
| 110–120% | Overweight |
| 90–110% | Normal |
| 85–90% | Mildly underweight |
| 70–84% | Moderately underweight |
| < 70% | Severely underweight |

**Source:** Hamwi GJ (1964). Referenced in: AND Nutrition Care Manual. Accessed via Academy of Nutrition and Dietetics — https://www.andeal.org/

---

#### Basal Metabolic Rate (BMR) — Mifflin-St Jeor Equation

```
Male:   BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age_years) + 5
Female: BMR = (10 × weight_kg) + (6.25 × height_cm) − (5 × age_years) − 161
```

Use **actual weight** for normal/underweight patients. Use **AjBW** for obese patients (>120% IBW).

**Source:** Mifflin MD et al. (1990). A new predictive equation for resting energy expenditure in healthy individuals. *Am J Clin Nutr.* 51(2):241–247. https://pubmed.ncbi.nlm.nih.gov/2305711/

---

#### Total Energy Expenditure (TEE)

```
TEE = BMR × Activity Factor
```

| Activity Level | Factor | Clinical Context |
|---|---|---|
| Sedentary | 1.2 | Bedbound, ICU |
| Light | 1.375 | Ambulatory inpatient |
| Moderate | 1.55 | Outpatient, light daily activity |
| Very Active | 1.725 | Regular vigorous exercise |
| Extra Active | 1.9 | Heavy physical labor + exercise |

For hospitalized patients, activity factor is typically **1.2** (bedbound) to **1.375** (ambulatory).

**Injury/Stress Factors** (multiply over TEE when applicable):

| Condition | Stress Factor |
|---|---|
| Post-minor surgery | 1.0 – 1.1 |
| Moderate trauma/sepsis | 1.2 – 1.4 |
| Major burns | 1.5 – 2.0 |

**Source:** Harris JA, Benedict FG (1919) revised by Roza AM, Shizgal HM (1984). Referenced in: ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

#### Baseline Macronutrient Distribution (Adult)

| Nutrient | Target | Notes |
|---|---|---|
| Energy | per TEE | Goal-adjusted |
| Protein | 0.8 g/kg IBW/day | Baseline; overridden by goal |
| Carbohydrates | 45–65% total kcal | Prefer complex carbs |
| Fat | 20–35% total kcal | Limit saturated/trans |
| Fiber | 25–38 g/day | Women 25 g, Men 38 g |
| Fluid | 30–35 mL/kg body weight/day OR 1 mL/kcal | Whichever is greater |

**Source:** Institute of Medicine (IOM). Dietary Reference Intakes for Energy, Carbohydrate, Fiber, Fat, Fatty Acids, Cholesterol, Protein, and Amino Acids. 2005. https://www.ncbi.nlm.nih.gov/books/NBK56068/

---

### [PEDIATRIC]

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

Weight-based Schofield equations (W = weight in kg, result in kcal/day):

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

**Source:** Schofield WN (1985). Predicting basal metabolic rate, new standards and review of previous work. *Hum Nutr Clin Nutr.* 39 Suppl 1:5–41. https://pubmed.ncbi.nlm.nih.gov/4044297/

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
| Energy | per TEE + growth | Age-adjusted |
| Protein | Age-banded DRI (see below) | Never cut below DRI |
| Carbohydrates | 45–65% total kcal | Same % as adult |
| Fat | 30–40% (0–3 yrs); 25–35% (4–18 yrs) | Higher fat % for young children |
| Fiber | Age + 5 g/day rule (e.g., 8 yrs → 13 g/day) | Or adult DRI if lower |

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
First 10 kg:         100 mL/kg/day
Next 10 kg (10–20):   50 mL/kg/day
Each kg above 20 kg:  20 mL/kg/day
```

**Example:** 25 kg child = (10 × 100) + (10 × 50) + (5 × 20) = **1600 mL/day**

**Source:** Holliday MA, Segar WE (1957). The maintenance need for water in parenteral fluid therapy. *Pediatrics.* 19(5):823–832. https://pubmed.ncbi.nlm.nih.gov/13429656/

---

## 2. Renal Diet — CKD

**goal_type:** `renal_diet`

Disease stage drives all nutrient targets. GFR staging follows KDOQI/KDIGO classification.

---

### [ADULT]

**Energy for all CKD stages:** 30–35 kcal/kg IBW/day

| Stage | disease_stage value | GFR (mL/min/1.73m²) | Protein (g/kg IBW) | Sodium | Potassium | Phosphorus | Fluid (mL/day) |
|---|---|---|---|---|---|---|---|
| Stage 1 | `stage_1` | ≥ 90 | 0.8 | < 2300 mg | Unrestricted | Unrestricted | Unrestricted |
| Stage 2 | `stage_2` | 60–89 | 0.8 | < 2300 mg | Unrestricted | Unrestricted | Unrestricted |
| Stage 3 | `stage_3` | 30–59 | 0.6–0.8 | < 2000 mg | Monitor; restrict if K > 5.0 mmol/L | 800–1000 mg/day if elevated | Unrestricted |
| Stage 4 | `stage_4` | 15–29 | 0.6 | < 2000 mg | < 2000 mg/day | 800–1000 mg/day | Unrestricted unless edema |
| Stage 5 pre-dialysis | `stage_5_predialysis` | < 15 | 0.6 | < 1500 mg | < 2000 mg/day | 800–1000 mg/day | Individualized (~1000–1500) |
| Hemodialysis | `hemodialysis` | — | 1.2 | < 1500 mg | 2000–3000 mg/day | 800–1000 mg/day | **750 mL + prior day urine output** |
| Peritoneal Dialysis | `peritoneal` | — | 1.2–1.5 | < 2000 mg | Generally unrestricted | 800–1000 mg/day | **Individualized per dialysis Rx** |

> **Fluid note for CKD:** Fluid restriction is required for hemodialysis and peritoneal dialysis stages. The system autofills `fluid_ml = 750` for hemodialysis (baseline conservative target; clinician adjusts based on urine output). Fluid counts include all beverages and high-water-content foods (soup, gelatin, ice cream, watermelon, grapes).

**Water intake tracking:** The USDA nutrient ID for water is **1051**. NutriScope extracts this as `water_g` on `food_items`. This allows the system to estimate daily water intake from the meal plan and compare against the `fluid_ml` prescription target. Food items with `water_g` populated will contribute to the fluid total shown in the micro display when `fluid_ml` is set.

> **Peritoneal note:** Subtract ~500–800 kcal/day from energy target to account for glucose absorbed from dialysate.

**Sources:**
- KDOQI Clinical Practice Guideline for Nutrition in CKD: 2020 Update. *Am J Kidney Dis.* https://www.ajkd.org/article/S0272-6386(20)30726-5/fulltext
- KDIGO 2022 Clinical Practice Guideline for Evaluation and Management of CKD — https://kdigo.org/guidelines/ckd-evaluation-and-management/

---

### [PEDIATRIC]

GFR staging uses eGFR corrected for body surface area (BSA).

**Energy:** DRI for age × 1.0–1.1 (CKD elevates metabolic needs; maintain adequate energy to support growth)

| Stage | disease_stage value | Protein | Electrolytes | Fluid |
|---|---|---|---|---|
| Stage 1–3 | `stage_1` / `stage_2` / `stage_3` | DRI for age | Monitor potassium, phosphorus | Per Holliday-Segar |
| Stage 4–5 | `stage_4` / `stage_5_predialysis` | 0.8–1.0 g/kg/day | Restrict K if > 5.5 mmol/L; phosphorus 800 mg/day | Per Holliday-Segar; restrict if oliguric |
| Hemodialysis | `hemodialysis` | 1.4–1.8 g/kg/day (higher losses via dialysis) | Per labs | Prescription-based |
| Peritoneal | `peritoneal` | 1.5–2.0 g/kg/day | Per labs | Per dialysis prescription |

> Higher protein targets in pediatric dialysis reflect protein losses through the dialysis membrane plus ongoing growth requirements.

**Source:** KDIGO Clinical Practice Guideline for CKD in Children — https://kdigo.org/guidelines/ckd-in-children/

---

## 3. Diabetic Control

**goal_type:** `diabetic_control`
**disease_stage:** `null` (no stage subdivision; type noted in clinical notes)

---

### [ADULT]

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Individualized to achieve/maintain healthy weight (TEE) | Deficit if overweight |
| Carbohydrates | 45–60% total kcal; minimum 130 g/day | Distribute across meals |
| Carb distribution | 45–60 g per main meal; 15–30 g per snack | 3 meals + 1–2 snacks/day |
| Glycemic Index | Prefer low-GI foods (GI < 55) | Limit refined sugars |
| Protein | 15–20% total kcal; 0.8–1.0 g/kg | Reduce if CKD coexists |
| Fat | < 30% total kcal | Saturated < 7%; minimize trans fat |
| Fiber | ≥ 25–38 g/day | Improves glycemic control |
| Sodium | < 2300 mg/day | < 1500 if HTN coexists |

**Source:** American Diabetes Association (ADA). Standards of Medical Care in Diabetes — 2024. *Diabetes Care* 47 (Suppl 1). https://diabetesjournals.org/care/issue/47/Supplement_1

---

### [PEDIATRIC]

**Type 1 Diabetes (T1DM):**

| Nutrient | Target | Notes |
|---|---|---|
| Energy | DRI for age (do NOT restrict calories) | Growth must not be compromised |
| Carbohydrates | 45–55% total kcal; carb counting preferred | Consistent distribution per meal |
| Carb-to-insulin ratio | ~1–2 g carb per 1 unit rapid-acting insulin | Varies by patient — set by clinical team |
| Protein | DRI for age (see Section 1) | Do not restrict |
| Fat | < 30% total kcal; saturated < 10% | |
| Fiber | Age + 5 g/day OR adult DRI, whichever is less | |

**Type 2 Diabetes (T2DM) — typically adolescents:**

| Nutrient | Target | Notes |
|---|---|---|
| Energy | Modest deficit (−500 kcal from TDEE) if overweight | Only for overweight/obese adolescents |
| Carbohydrates | 45–55% total kcal | Reduce refined sugars and high-GI foods |
| Protein | DRI for age | |
| Fat | < 30% total kcal | |

**Source:** ADA Standards of Care — Pediatric Diabetes Management. *Diabetes Care* 47 (Suppl 1) Section 14. https://diabetesjournals.org/care/article/47/Supplement_1/S234/153955/14-Children-and-Adolescents-Standards-of-Care-in

---

## 4. Cardiac Diet

**goal_type:** `cardiac_diet`

**disease_stage values:** `mild` | `moderate` | `severe`

---

### [ADULT]

**Energy:** TEE (weight maintenance); TEE − 500 kcal if overweight coexists.

| Nutrient | Mild (`mild`) | Moderate (`moderate`) | Severe (`severe`) |
|---|---|---|---|
| Sodium | < 2300 mg/day | < 2000 mg/day | < 1500 mg/day |
| Total fat | < 30% total kcal | < 28% total kcal | < 25% total kcal |
| Saturated fat | ≤ 7% total kcal | ≤ 6% total kcal | ≤ 6% total kcal |
| Trans fat | < 1% total kcal (minimize) | Minimize | Minimize |
| Cholesterol | < 300 mg/day | < 200 mg/day | < 200 mg/day |
| Fiber | ≥ 25–30 g/day | ≥ 30 g/day | ≥ 30 g/day |
| Fluid (`fluid_ml`) | Unrestricted | **≤ 2000 mL/day** | **1000–1500 mL/day** |

> **Fluid note for Cardiac:** Fluid restriction applies from moderate severity onward. Heart failure decompensation (severe stage) typically requires ≤ 1500 mL/day. The system autofills `fluid_ml = 2000` for moderate and `fluid_ml = 1500` for severe cardiac stages.

**DASH Diet Targets** (all severity levels):

| Nutrient | Daily Target |
|---|---|
| Potassium | 4700 mg/day |
| Calcium | 1250 mg/day |
| Magnesium | 500 mg/day |

**Sources:**
- American Heart Association (AHA). Dietary Guidance to Improve Cardiovascular Health. *Circulation* 2021. https://www.ahajournals.org/doi/10.1161/CIR.0000000000001031
- National Heart, Lung, and Blood Institute (NHLBI). DASH Eating Plan — https://www.nhlbi.nih.gov/education/dash-eating-plan

---

### [PEDIATRIC]

**Energy:** DRI for age (do not restrict unless overweight).

**Sodium targets by age (American Academy of Pediatrics / AHA):**

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
| Fiber | Age + 5 g/day rule |
| Sodium | Age-specific (table above) |

> Apply `mild` / `moderate` / `severe` stage restrictions by scaling sodium to age-appropriate floors, not adult absolutes.

**Source:** AHA Dietary Recommendations for Children and Adolescents — https://www.ahajournals.org/doi/10.1161/CIR.0000000000001031

---

## 5. Weight Loss

**goal_type:** `weight_loss`

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
| Protein | 1.2–1.6 g/kg IBW/day (protein-sparing — preserves lean mass) |
| Carbohydrates | 45–55% of reduced total kcal; complex carbs preferred |
| Fat | 25–30% total kcal |
| Fiber | ≥ 25–38 g/day (satiety) |

**Sources:**
- Academy of Nutrition and Dietetics (AND). Evidence-Based Nutrition Practice Guideline: Adult Weight Management — https://www.andeal.org/topic.cfm?menu=5276
- NHLBI. Clinical Guidelines on the Identification, Evaluation, and Treatment of Overweight and Obesity in Adults — https://www.nhlbi.nih.gov/health/educational/lose_wt/

---

### [PEDIATRIC]

Children should generally NOT be placed on a caloric deficit. Goal is **weight maintenance while height increases** so BMI-for-age z-score normalizes over time.

| BAZ | disease_stage | Approach |
|---|---|---|
| +1 to +2 (risk of overweight) | `overweight` | No caloric restriction; improve food quality, increase fiber and activity |
| +2 to +3 (overweight) | `class_1` | Maintain weight; DRI energy; reduce high-calorie/low-nutrient foods |
| > +3 (obese) | `class_2` / `class_3` | Modest energy reduction under specialist supervision only |

| Nutrient | Target |
|---|---|
| Energy | DRI for age (maintenance) or modest supervised deficit for BAZ > +3 |
| Protein | DRI for age — never restrict |
| Fat | Age-appropriate (25–35% for 4–18 yrs) |
| Fiber | Age + 5 g/day |

> Weight loss > 0.5 kg/month in children < 2 yrs is not recommended. For adolescents, maximum 0.5 kg/week under supervision.

**Source:** ADA Standards for Pediatric T2DM and Obesity. https://diabetesjournals.org/care/article/47/Supplement_1/S234/153955/14-Children-and-Adolescents-Standards-of-Care-in

---

## 6. Weight Gain

**goal_type:** `weight_gain`

**disease_stage values:** `mild` | `moderate` | `severe`

Stage maps to %IBW (adult) or WAZ z-score (pediatric).

---

### [ADULT]

| disease_stage | %IBW | Energy Target | Notes |
|---|---|---|---|
| `mild` | 85–90% IBW | TEE + 300–500 kcal/day | Standard surplus |
| `moderate` | 70–84% IBW | TEE + 500–750 kcal/day | Monitor tolerance |
| `severe` | < 70% IBW | Refeeding protocol — start at 50% TEE | Risk of refeeding syndrome |

| Nutrient | Target |
|---|---|
| Protein | 1.2–2.0 g/kg IBW/day (higher for severe) |
| Carbohydrates | 55–65% total kcal (primary energy driver) |
| Fat | 25–30% total kcal |

#### Refeeding Syndrome Protocol (severe stage)

**Risk factors — any 1 of the following triggers protocol:**
- BMI < 16
- Unintentional weight loss > 15% in 3–6 months
- Little or no nutritional intake > 10 days
- Low serum potassium, magnesium, or phosphate before refeeding

**Protocol:**

| Week | Energy |
|---|---|
| Week 1 | Start at 5–10 kcal/kg/day (≈ 50% of estimated needs) |
| Week 2 | Increase by 33% |
| Week 3 | Increase by 33%; reach full target |

- Monitor serum phosphate, potassium, magnesium **daily for first 72 hours**
- Supplement **thiamine 200–300 mg/day** before refeeding begins and continue for 7–10 days
- If phosphate falls below 0.5 mmol/L: stop feeding increase, replace electrolytes, reassess

**Sources:**
- NICE Clinical Guideline CG32. Nutrition Support for Adults (Refeeding Syndrome). https://www.nice.org.uk/guidance/cg32
- ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

### [PEDIATRIC]

| disease_stage | WAZ / BAZ | Energy Target |
|---|---|---|
| `mild` | WAZ −1 to −2 | DRI × 1.1 |
| `moderate` | WAZ −2 to −3 | DRI × 1.2–1.3 |
| `severe` | WAZ < −3 | Refeeding protocol (same as adult; scaled to body weight) |

| Nutrient | Target |
|---|---|
| Energy | DRI × activity multiplier + growth allowance |
| Protein | 1.0–2.0 g/kg/day (increases with severity) |
| Fat | Age-appropriate (do not restrict) |

> Refeeding syndrome applies equally in pediatrics. Use same protocol scaled to weight. Monitor electrolytes daily during first 72 hours regardless of age.

**Source:** NICE CG32 — https://www.nice.org.uk/guidance/cg32

---

## 7. High Protein

**goal_type:** `high_protein`

**disease_stage values:** `mild_stress` | `moderate_stress` | `severe_stress` | `burns`

Used for: post-surgery, trauma, sepsis, burns, pressure injuries, low albumin.

---

### [ADULT]

| disease_stage | Condition Examples | Protein (g/kg IBW/day) | Energy (kcal/kg/day) |
|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild infection, low albumin | 1.0–1.2 | 25–30 |
| `moderate_stress` | Major surgery, trauma, sepsis, pressure injury | 1.2–1.5 | 25–30 |
| `severe_stress` | Critical illness, multi-organ failure | 1.5–2.0 | 25–30 |
| `burns` | Burns > 20% BSA | 1.5–2.0 | 30–35 |

> **Low albumin (< 3.5 g/dL):** Target protein 1.5–2.0 g/kg/day. Goal: albumin ≥ 3.5 g/dL over 2–4 weeks.

> **Pressure injuries:** protein 1.25–1.5 g/kg/day; supplement zinc 25–40 mg/day and vitamin C 500 mg/day.

**Sources:**
- ASPEN/SCCM Guidelines for Nutrition Support Therapy in the Adult Critically Ill Patient — https://www.nutritioncare.org/guidelines_and_clinical_resources/
- ESPEN Guidelines on Clinical Nutrition in Surgery (2017). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(17)30009-4/fulltext

---

### [PEDIATRIC]

| disease_stage | Condition Examples | Protein (g/kg/day) | Energy |
|---|---|---|---|
| `mild_stress` | Post-minor surgery, mild illness | DRI × 1.1–1.2 | DRI × 1.1 |
| `moderate_stress` | Major surgery, moderate trauma | 1.5 g/kg/day | DRI × 1.2–1.3 |
| `severe_stress` | Critical illness, sepsis | 2.0–3.0 g/kg/day (infants may need upper range) | DRI × 1.3–1.5 |
| `burns` | Burns > 10% BSA | 2.0–3.0 g/kg/day | 1.5–2× DRI |

> Younger children (< 2 yrs) may require protein at the upper end of ranges; higher turnover rate at early developmental stages.

**Source:** ASPEN Clinical Guidelines — https://www.nutritioncare.org/guidelines_and_clinical_resources/

---

## 8. Liver Disease

**goal_type:** `liver_disease`

**disease_stage values:** `compensated` | `decompensated` | `encephalopathy_grade_1_2` | `encephalopathy_grade_3_4`

---

### [ADULT]

> **Important:** Protein restriction in liver disease is outdated practice. Current guidelines recommend maintaining or increasing protein to prevent sarcopenia, except during severe encephalopathy where temporary modest reduction may be needed.

| disease_stage | Condition | Energy (kcal/kg/day) | Protein (g/kg/day) | Sodium | Notes |
|---|---|---|---|---|---|
| `compensated` | Cirrhosis, no ascites, no encephalopathy | 35–40 | 1.2–1.5 | < 2000 mg if sodium retention | Prefer small frequent meals |
| `decompensated` | Cirrhosis with ascites or fluid retention | 35–40 | 1.2–1.5 | < 2000 mg (strict) | Sodium restriction critical |
| `encephalopathy_grade_1_2` | Mild–moderate encephalopathy | 35–40 | 0.8–1.0 | < 2000 mg | BCAA supplementation preferred; do not restrict protein long-term |
| `encephalopathy_grade_3_4` | Severe encephalopathy | 35–40 | 0.5–0.8 (temporary) | < 2000 mg | Rebuild protein as mental status improves; BCAA supplementation |

> **BCAA:** Branched-chain amino acid (leucine, isoleucine, valine) supplements preferred over standard protein when encephalopathy is present. Target 0.25 g BCAA/kg/day.

> **Late-evening snack:** recommended for all liver disease stages — reduces overnight fasting and prevents muscle catabolism.

**Sources:**
- ESPEN Clinical Nutrition Guidelines on Liver Disease (2019). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(19)30098-7/fulltext
- EASL Clinical Practice Guidelines on Nutrition in Chronic Liver Disease. *J Hepatol.* 2019. https://www.journal-of-hepatology.eu/article/S0168-8278(18)32145-7/fulltext

---

### [PEDIATRIC]

Pediatric liver disease (biliary atresia, PFIC, cholestatic disease) has significantly higher energy needs than adults due to malabsorption and growth demands.

| disease_stage | Energy | Protein | Fat | Notes |
|---|---|---|---|---|
| `compensated` | 130–150% of DRI for age | 1.5–3.0 g/kg/day | MCT oil preferred if steatorrhea | Fat-soluble vitamins A, D, E, K likely deficient — supplement |
| `decompensated` | 130–150% DRI | 2.0–3.0 g/kg/day | MCT oil; monitor fat tolerance | Nasogastric feeds may be needed for adequate intake |
| `encephalopathy_grade_1_2` | 130% DRI | 1.0–1.5 g/kg/day | Normal age-appropriate | Less protein restriction than adults; growth priority |
| `encephalopathy_grade_3_4` | 130% DRI | 1.0 g/kg/day (minimum) | Normal | Never restrict protein below growth-protective minimum |

> Fat-soluble vitamin supplementation protocol: Vitamin A 5000–10,000 IU/day; Vitamin D 800–2000 IU/day; Vitamin E 25 IU/kg/day; Vitamin K 2.5–5 mg 2–3×/week.

**Source:** ESPEN Guidelines on Pediatric Liver Disease. Referenced in ESPEN Guidelines — https://www.espen.org/guidelines-home

---

## 9. Malnutrition

**goal_type:** `malnutrition`

**disease_stage values:** `moderate` | `severe`

Maps to `assessments.nutritional_status` and `ncp_records.risk_score` in the system.

---

### [ADULT]

#### Classification

| Indicator | `moderate` | `severe` |
|---|---|---|
| BMI | 16.0–18.49 | < 16.0 |
| %IBW | 70–84% | < 70% |
| MUAC | 190–210 mm | < 185 mm |
| System risk_score | 2–3 | > 3 |

**GLIM Criteria (2019)** — diagnosis requires ≥ 1 phenotypic + ≥ 1 etiologic criterion:

*Phenotypic:* Weight loss, low BMI, reduced muscle mass
*Etiologic:* Reduced food intake/assimilation, disease burden/inflammation

#### Nutrition Targets

| disease_stage | Energy (kcal/kg/day) | Protein (g/kg/day) | Approach |
|---|---|---|---|
| `moderate` | 30–35 | 1.2–1.5 | Progressive feeding over 5–7 days; standard oral/enteral route |
| `severe` | Start 5–10 kcal/kg/day → target 30–35 | Start 1.0 → target 1.5–2.0 | Refeeding protocol (see Section 6) |

> For `severe`: thiamine 200–300 mg/day must be given **before** refeeding begins and continued for 7–10 days.

**Sources:**
- GLIM Criteria for the Diagnosis of Malnutrition (2019). *Clin Nutr.* https://www.clinicalnutritionjournal.com/article/S0261-5614(18)31525-7/fulltext
- WHO. Malnutrition Fact Sheet — https://www.who.int/news-room/fact-sheets/detail/malnutrition
- NICE CG32. Refeeding Syndrome — https://www.nice.org.uk/guidance/cg32

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
| Approach | Ready-to-Use Supplementary Food (RUSF) or enhanced home diet |

**Severe Acute Malnutrition (SAM):**

| Phase | Energy | Protein | Duration |
|---|---|---|---|
| Phase 1 — Stabilization | 80–100 kcal/kg/day | 1.0–1.5 g/kg/day | Until appetite returns (typically 2–7 days) |
| Phase 2 — Rehabilitation | 150–220 kcal/kg/day | 4.0–6.0 g/kg/day | Until −2 WAZ achieved |

WHO F-75 formula used in Phase 1 stabilization; F-100 or Ready-to-Use Therapeutic Food (RUTF) in Phase 2.

> Refeeding syndrome protocol applies in pediatric SAM — see Section 6. Monitor electrolytes daily during Phase 1.

**Source:** WHO. Management of Severe Acute Malnutrition in Infants and Children (2013) — https://www.who.int/publications/i/item/9789241506328

---

## Appendix — disease_stage Quick Reference

Full mapping of `goal_type` → `disease_stage` values → which section defines the targets.

| goal_type | disease_stage values | fluid_ml autofill | Reference Section |
|---|---|---|---|
| `renal_diet` | `stage_1`, `stage_2`, `stage_3`, `stage_4`, `stage_5_predialysis`, `hemodialysis`, `peritoneal` | 750 mL for `hemodialysis`; individualized for `peritoneal` | Section 2 |
| `diabetic_control` | `null` | Not restricted | Section 3 |
| `cardiac_diet` | `mild`, `moderate`, `severe` | 2000 mL for `moderate`; 1500 mL for `severe` | Section 4 |
| `weight_loss` | `overweight`, `class_1`, `class_2`, `class_3` | Not restricted | Section 5 |
| `weight_gain` | `mild`, `moderate`, `severe` | Not restricted | Section 6 |
| `high_protein` | `mild_stress`, `moderate_stress`, `severe_stress`, `burns` | Not restricted | Section 7 |
| `liver_disease` | `compensated`, `decompensated`, `encephalopathy_grade_1_2`, `encephalopathy_grade_3_4` | Not restricted | Section 8 |
| `malnutrition` | `moderate`, `severe` | Not restricted | Section 9 |
| `custom` | `null` | Manual RND entry | Manual RND entry — no formula applied |

> **Removed goal:** `fluid_restriction` was a standalone goal type up to 2026-06-05. It was removed because fluid restriction is a clinical modifier embedded within CKD and Cardiac goals, not an independent nutritional intervention category. Any existing records with `goal_type = 'fluid_restriction'` should be migrated to `renal_diet` or `cardiac_diet` as appropriate.

---

*Last updated: 2026-06-06*
*System requirements supersede any conflict with this document.*
