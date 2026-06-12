# Intervention Goals — Asia-Pacific Localization Research

> **Status:** Research / working document (not yet system-of-record).
> **Purpose:** Brainstorm, fact-check, and propose an Asia-Pacific localization of
> [`intervention-goals.md`](intervention-goals.md). NutriScope is deployed in a
> Philippine hospital setting; the current reference doc leans on US/Western (AND, WHO global
> BMI, Hamwi, IOM/DRI) standards. This document records (a) what survives fact-checking,
> (b) what is wrong or imprecise, and (c) what should change to be appropriate for a
> Filipino / Asia-Pacific patient population.
> It feeds two downstream tasks: **revising the nutrient-prescription calculation** and
> **deciding what data we capture during assessment.**
>
> **Scope note:** This document does *not* change the production system. It is the evidence
> base for a later, deliberate revision of `intervention-goals.md` and the calculation engine.

**Date compiled:** 2026-06-11

---

## Table of Contents

1. [Why Asia-Pacific localization matters](#1-why-asia-pacific-localization-matters)
2. [The core change: BMI classification (WHO Asia-Pacific)](#2-the-core-change-bmi-classification-who-asia-pacific)
3. [Downstream effects of the BMI change](#3-downstream-effects-of-the-bmi-change)
4. [Anthropometry & body-weight basis (IBW, waist, MUAC, muscle mass)](#4-anthropometry--body-weight-basis)
5. [Energy & macros — what stays, what localizes (PDRI)](#5-energy--macros--what-stays-what-localizes-pdri)
6. [Disease-specific goals — fact-check & AP notes](#6-disease-specific-goals--fact-check--ap-notes)
7. [Fact-check ledger (existing doc: confirmed / correct / fix)](#7-fact-check-ledger)
8. [Assessment data — what to capture and why](#8-assessment-data--what-to-capture-and-why)
9. [Open questions for the RND / clinical owner](#9-open-questions-for-the-rnd--clinical-owner)
10. [Source list](#10-source-list)

---

## 1. Why Asia-Pacific localization matters

Asian populations develop obesity-related cardiometabolic disease (type 2 diabetes, hypertension,
CKD, cardiovascular disease) at **lower BMI and lower waist circumference** than European-ancestry
populations, because at the same BMI Asians carry a higher percentage of body fat and more visceral
fat. This is the central, well-established finding behind every Asia-Pacific cut-point.

Practical consequence for NutriScope: a Filipino patient flagged "Normal" by global WHO BMI
(18.5–24.9) may already be in the overweight/at-risk band that warrants a weight-management
intervention. The existing doc already acknowledges this as "a known limitation" (line 79) but then
keeps the Western table as the system default. **The recommendation of this research is to flip that
default**: classify on Asia-Pacific cut-points, and keep Western cut-points only as an optional
reference column.

> **Important nuance the current doc gets slightly wrong:** the existing caveat says the AP thresholds
> are "overweight at BMI ≥ 23, obese at BMI ≥ 27.5." The `27.5` figure is the **public-health "high
> risk" action trigger point**, not the boundary of the obese *class*. The actual WHO/IASO/IOTF
> Asia-Pacific *classification* table puts obesity at **≥ 25.0**, with 23–24.9 as overweight and
> ≥ 27.5 flagged as a higher-risk action point. Both `23` and `27.5` exist in the WHO consultation;
> they are different things. See §2.

---

## 2. The core change: BMI classification (WHO Asia-Pacific)

### Proposed Asia-Pacific BMI table (to replace the WHO Western table at lines 81–88)

| BMI Range (kg/m²) | Asia-Pacific Classification | WHO Western equivalent (reference only) |
|---|---|---|
| < 18.5 | Underweight | Underweight |
| 18.5 – 22.9 | Normal | Normal (extends to 24.9) |
| 23.0 – 24.9 | Overweight / "at increased risk" | Normal |
| 25.0 – 29.9 | Obese Class I | Overweight |
| ≥ 30.0 | Obese Class II | Obese Class I–III |

- **23.0** = lower action point ("increased risk"); **27.5** = higher action point ("high risk")
  within the obese-I band. These are *trigger points for public-health action*, not extra rows.
- Note the AP system collapses the three Western obese classes (I/II/III) into two (Class I, Class II).
  The Korean Society for the Study of Obesity 2022 and several national bodies further split Class II
  at ≥ 35, but the WHO WPRO baseline is the two-class system above.

**Fact-check status:** ✅ The AP cut-points (overweight ≥ 23, obese ≥ 25, action point 27.5) are
correct and well-sourced (WHO/IASO/IOTF 2000 *The Asia-Pacific Perspective*; Lancet 2004 WHO Expert
Consultation). The existing doc's *numbers* in the caveat are imprecise (it labels 27.5 as the obese
boundary); the *table* it ships (Western) is valid but not the right default for this population.

### Pediatric — no change needed

Pediatrics already correctly uses **WHO Child Growth Standards z-scores** (WAZ/HAZ/WHZ/BAZ), which are
international standards and **not** ethnicity-adjusted by design — WHO built them from a multi-country
sample that included Asian children and showed growth potential is similar across populations under
optimal conditions. ✅ Keep as-is. Do **not** apply BMI cut-point shifts to children.

---

## 3. Downstream effects of the BMI change

Changing the BMI classification is not cosmetic — several `disease_stage` mappings in the current doc
are anchored to Western BMI bands and must move with it.

### 3.1 Weight Loss staging (current doc §5, lines 551–556)

The `disease_stage` → BMI map currently uses Western bands. Proposed AP remap:

| disease_stage | Current (Western BMI) | Proposed (Asia-Pacific BMI) | Energy target (unchanged) |
|---|---|---|---|
| `overweight` | 25.0–29.9 | **23.0–24.9** | TEE − 250 to 500 kcal/day |
| `class_1` | 30.0–34.9 | **25.0–29.9** | TEE − 500 kcal/day |
| `class_2` | 35.0–39.9 | **≥ 30.0** (or 30–34.9 if a 3rd class kept) | TEE − 500 to 750 |
| `class_3` | ≥ 40.0 | ≥ 35.0 *(optional; AP often has no class 3)* | TEE − 750 to 1000 (supervised) |

> **Design decision needed:** AP classification natively has only two obese classes. We can either
> (a) collapse `weight_loss` to three stages (`overweight`, `class_1`, `class_2`), or
> (b) keep four stages and re-cut the boundaries (23–24.9 / 25–29.9 / 30–34.9 / ≥35). The caloric
> floors (Female ≥ 1200, Male ≥ 1500 kcal/day) are unaffected — these are physiological minimums, not
> population-specific. ✅ Floors confirmed against NHLBI/AND.

### 3.2 Diabetic Control `stage_2` trigger (current doc §3, line 416)

Currently: "T2DM + overweight/obesity (BMI ≥ 25 or %IBW > 110%)". Under AP cut-points the clinically
consistent trigger is **BMI ≥ 23** (the AP overweight threshold), since the whole point of `stage_2`
is "excess weight where weight loss is a clinical priority." Recommend changing the trigger to
**BMI ≥ 23**. The −500 kcal deficit and the "5% weight loss" rationale are unchanged and correct. ✅

### 3.3 Cardiac / overweight-coexisting deficits

Cardiac (§4) and other goals apply "TEE − 500 if overweight coexists." If "overweight" is detected via
BMI, that detection now fires at ≥ 23, not ≥ 25. No formula change — just the threshold that triggers it.

---

## 4. Anthropometry & body-weight basis

### 4.1 IBW formula — Hamwi is US-derived; flag, don't silently keep

The doc uses the **Hamwi** formula (§1, lines 100–102), which is a 1964 US clinical heuristic anchored
to imperial units (per inch over 5 feet). It systematically over-estimates IBW for short statures —
common in Filipino patients — and the doc's own "< 30 kg floor" is a patch over that.

**Options for AP context:**
- **Keep Hamwi** for continuity with AND-based Philippine hospital dietetics training (the doc's stated
  rationale), but document the short-stature bias explicitly.
- **Offer BMI-based IBW** as an alternative: `IBW = 22 × height_m²` (midpoint of AP normal range
  18.5–22.9), which is intrinsically population-scaled and avoids the imperial-unit baggage. The
  Broca index (height_cm − 100, −10% for women) is the simplest and is validated in Filipino adults,
  but is cruder than BMI-based.

**Recommendation:** keep Hamwi as default (training continuity) **but** add a BMI-method IBW
(target BMI 22) as a selectable basis, and surface the chosen method on the prescription so it is
auditable. The `%IBW` status table (lines 121–128) is a generic clinical heuristic and is fine to keep.

> ⚠️ **Internal consistency bug to fix during revision:** the BMI-status logic and the %IBW-status logic
> can disagree for the same patient (e.g. a short patient "Normal" by %IBW but "Overweight" by AP BMI).
> The doc should state which one drives `disease_stage` selection for each goal. Right now Weight Loss
> uses BMI, Weight Gain uses %IBW, Diabetic `stage_2` uses *either* ("≥25 **or** %IBW >110%"). Pick one
> primary axis per goal and document it.

### 4.2 Waist circumference — already captured, not yet used

The schema added `waist_cm` and `hip_cm` (migration `2026_06_07_123629`) but the reference doc never
defines thresholds. Add the **Asian-specific central-obesity cut-points** (IDF / WHO-WPRO):

| Measure | Men | Women | Source |
|---|---|---|---|
| Waist circumference (central obesity) | **≥ 90 cm** | **≥ 80 cm** | IDF Asian / WHO-WPRO |
| Waist-to-hip ratio (elevated risk) | > 0.90 | > 0.85 | WHO |

These matter because waist circumference catches the "metabolically obese, normal-BMI" Asian phenotype
that BMI alone misses — directly relevant to diabetic/cardiac goal selection. ✅ Cut-points confirmed.

### 4.3 MUAC cut-points (adult) — the doc's numbers need an AP sanity check

Current doc (§9, line 800) uses adult MUAC `190–210 mm` (moderate) / `< 185 mm` (severe). These are in
the right ballpark for the commonly used field threshold (< 230 mm = at risk, < 185–190 mm = severe in
emergency/African contexts) but **adult MUAC cut-offs are not well standardized and vary by region and
body frame.** For a hospital (not famine-relief) Asian context, MUAC is best used as a *muscle-mass
proxy within GLIM*, not a standalone diagnosis. Recommend: keep MUAC as supportive, lean on GLIM +
AWGS muscle-mass cut-points (below) as primary.

### 4.4 Reduced muscle mass (GLIM phenotypic criterion) — adopt AWGS, not Western

GLIM requires "reduced muscle mass" but lets each region set the cut-points. For Asia use the
**Asian Working Group for Sarcopenia (AWGS 2019)** values, *not* EWGSOP/US values:

| Measure | Men | Women |
|---|---|---|
| Appendicular skeletal muscle index (ASMI, BIA) | < 7.0 kg/m² | < 5.7 kg/m² |
| Calf circumference (screening proxy) | < 34 cm | < 33 cm |

Calf circumference is cheap, requires only a tape measure, and is validated as a muscle-mass proxy in
Thai/Vietnamese/Japanese older adults — a good candidate **new assessment field** (see §8).

### 4.5 Low-BMI phenotypic criterion for GLIM — use Asian cut-points

The current doc (§9, line 787) already nods to this ("for Asian populations, adjust per regional
thresholds") but gives no numbers. Adopt the validated Asian GLIM low-BMI cut-points:

| Severity | Age < 70 | Age ≥ 70 |
|---|---|---|
| Low BMI (supports malnutrition) | < 18.5 | < 20 |
| Severe low BMI | < 17.0 | < 17.8 |

✅ Asian-specific GLIM low-BMI cut-points confirmed (Clin Nutr 2020 validation). This is a concrete,
fillable gap in the current doc.

---

## 5. Energy & macros — what stays, what localizes (PDRI)

### What stays (international, not population-specific)

- **Mifflin-St Jeor BMR** — validated broadly; acceptable default. (A known limitation: most predictive
  equations including Mifflin were derived largely in Western cohorts and modestly over-predict in some
  Asian samples, but Mifflin remains the best general-purpose default and indirect calorimetry is the
  only true fix. No change recommended.) ✅
- **Schofield pediatric BMR** — international, weight-based. ✅
- **Activity & stress factors** — physiological, not ethnic. ✅
- **Disease-specific flat-rate kcal/kg** (CKD 25–35, liver 35–40, high-protein 25–35, refeeding ramp)
  — these come from KDOQI/ESPEN/NICE/ASPEN and are **weight-indexed**, so they self-scale to smaller
  Asian body sizes. No AP adjustment needed. ✅ (Confirmed KDOQI 2020 energy 25–35 kcal/kg and the
  diabetes-CKD protein 0.6–0.8 g/kg IBW figures.)

### What localizes to the Philippines: IOM DRI → PDRI 2015 (RECONCILED)

The doc cites **US IOM DRIs (2005)** for baseline macro distribution, fiber, and pediatric protein
(lines 211, 326). For a Philippine deployment the authoritative national standard is the
**Philippine Dietary Reference Intakes (PDRI 2015, FNRI-DOST, rev. Sept 2018)** — now the *legally
mandated* dietary standard (FDA Circular 2023-009). Below are the **actual PDRI numeric tables**
extracted from the FNRI Summary Tables, reconciled against the current doc. **Bold = differs materially
from the IOM value the doc currently uses.**

#### 5a. AMDR — Acceptable Macronutrient Distribution Range (% of total energy)

| Age group | Protein | Total Fat | Carbohydrate | Doc (IOM) currently says |
|---|---|---|---|---|
| Infants 0–5 mo | 5 | 40–60 | 35–55 | — |
| Infants 6–11 mo | 8–15 | 30–40 | 45–62 | — |
| Children 1–2 y | 6–15 | 25–35 | 50–69 | Fat 30–40% (0–3 y) |
| Children 3–18 y | 6–15 | **15–30** | **55–79** | Fat 25–35%; Carb 45–65% |
| Adults ≥ 19 y | 10–15 | **15–30** | **55–75** | Fat 20–35%; Carb 45–65% |

> **Material difference:** PDRI carbohydrate runs **higher** (55–75% vs IOM 45–65%) and fat runs
> **lower** (15–30% vs 20–35%). This reflects the rice-based Filipino diet. **Recommendation:** adopt
> PDRI ranges for the *baseline / healthy-eating* macro split (doc §1 line 206). **Do NOT** apply these
> to disease goals that explicitly override fat/carb (cardiac < 25–30% fat, diabetic 45–60% carb, weight
> goals) — those clinical caps win. PDRI defines the *normal* envelope, not the therapeutic prescription.

#### 5b. Protein RNI (g/day) and implied g/kg at PDRI reference weights

| Age group | Ref wt M / F (kg) | Protein RNI M / F (g/day) | Implied g/kg | Doc (IOM) currently says |
|---|---|---|---|---|
| Infants 0–5 mo | 6.5 / 6.0 | 9 / 8 (AI) | ~1.4 | 1.52 |
| Infants 6–11 mo | 9.0 / 8.0 | 17 / 15 | ~1.9 | 1.20 |
| Children 1–2 y | 12.0 / 11.5 | 18 / 17 | ~1.5 | 1.05 (1–3 y) |
| Children 3–5 y | 17.5 / 17.0 | 22 / 21 | ~1.25 | 0.95 (4–13 y) |
| Children 6–9 y | 23.0 / 22.5 | 30 / 29 | ~1.3 | 0.95 |
| Children 10–12 y | 33.0 / 36.0 | 43 / 46 | ~1.3 | 0.95 |
| Children 13–15 y | 48.5 / 46.0 | 62 / 57 | ~1.25 | 0.85 (14–18 y) |
| Children 16–18 y | 59.0 / 51.5 | 72 / 61 | ~1.2 | 0.85 |
| **Adults ≥ 19 y** | **60.5 / 52.5** | **71 / 62** | **~1.17 / 1.18** | **0.8 g/kg baseline** |
| Pregnant (2nd/3rd tri) | — | +27 | — | — |
| Lactating | — | +27 | — | — |

> **Important interpretation — do not naïvely swap the baseline:** PDRI's adult protein RNI (~1.1–1.2
> g/kg) is **higher** than the doc's 0.8 g/kg baseline because PDRI builds in a **protein-quality /
> digestibility correction** for the typical Filipino mixed diet (rice-dominant, lower-quality protein),
> *plus* the population safety margin (RNI = EAR + 2SD; adult EAR is M 57 / F 49 g). The doc's 0.8 g/kg
> is the **WHO/IOM physiological requirement for high-quality protein**.
> **Recommendation:** keep 0.8 g/kg IBW as the *clinical floor / disease-state baseline* (it is what
> KDOQI, ESPEN etc. assume), but document that **a healthy Filipino maintenance target is ~1.0–1.2
> g/kg** when planning normal (non-restricted) diets, and use the PDRI **g/day** figures as the
> reference for pediatric and healthy-adult planning. The disease-specific protein targets in §§2,7,8
> are unaffected.

#### 5c. Dietary fiber (g/day)

| Age group | PDRI fiber | Doc (IOM) currently says |
|---|---|---|
| Children 1–2 y | 6–7 | "age + 5" → 6–7 ✓ |
| Children 3–5 y | 8–10 | "age + 5" → 8–10 ✓ |
| Children 6–9 y | 11–14 | "age + 5" → 11–14 ✓ |
| Children 10–12 y | 15–17 | ~15–17 ✓ |
| Children 13–15 y | 18–20 | ~18–20 ✓ |
| Children 16–18 y | 20–23 | ~20–23 ✓ |
| **Adults ≥ 19 y** | **20–25** | **25–38 (W 25 / M 38)** |

> **Material difference:** PDRI adult fiber is **20–25 g/day**, notably **lower** than the doc's IOM
> 25–38 g. The pediatric "age + 5" heuristic the doc uses happens to land inside the PDRI ranges — keep
> it. **Recommendation:** change the adult baseline fiber target to **20–25 g/day (PDRI)**. Disease goals
> that call for higher fiber (diabetic/cardiac ≥ 25–30 g) remain valid as therapeutic targets.

#### 5d. Sodium, free sugars, potassium (PDRI "Additional Recommendations")

| Component | PDRI / WHO recommendation | Doc currently says |
|---|---|---|
| **Sodium** | **< 2 g/day (2000 mg) for adults** (WHO 2012 basis) | baseline/diabetic < 2300 mg |
| Free sugars | **< 10% of total energy** (adults & children) | *not specified* |
| Potassium | increase to **3510 mg/day** (adults; chronic-disease prevention) | DASH target 4700 mg (cardiac only) |

> **Material differences:** (1) PDRI's healthy-population sodium ceiling is **< 2000 mg**, *stricter*
> than the doc's general < 2300 mg — recommend aligning the baseline/diabetic `stage_1` sodium to
> **< 2000 mg** for PH context (the stricter cardiac/CKD targets already meet this). (2) PDRI adds a
> **free-sugars cap (< 10% E)** the doc lacks entirely — **recommend adding it as a baseline target and
> surfacing it prominently in the diabetic goal.** (3) The DASH 4700 mg potassium target (cardiac) is a
> therapeutic target above PDRI's general 3510 mg — both are fine in their contexts.

#### 5e. Energy (REI) sanity-check band & reference body weights

PDRI publishes REI by age/sex (single "moderately active" reference, not an activity matrix). Use as a
**plausibility band** for the Mifflin→TEE output of *healthy* adults — a TEE far outside these suggests
a data-entry error:

| Adult age | REI M (kcal) | REI F (kcal) | Water AI M / F (mL) |
|---|---|---|---|
| 19–29 | 2530 | 1930 | 2530 / 1930 |
| 30–59 | 2420 | 1870 | 2420 / 1870 |
| 60–69 | 2140 | 1610 | 2140 / 1610 |
| ≥ 70 | 1960 | 1540 | 1960 / 1540 |

- **Filipino reference body weights:** adult **M 60.5 kg / F 52.5 kg** — useful as a population anchor
  and confirmation that weight-indexed flat-rate kcal/kg formulas self-scale appropriately.
- **Water AI ≈ REI in kcal**, i.e. **≈ 1 mL/kcal** — this *independently validates* the doc's existing
  "1 mL/kcal" fluid rule (§1 line 189). ✅ No change needed there.
- Pregnant +300 kcal (2nd/3rd trimester only); Lactating +500 kcal.

#### 5f. What still stays international (re-confirmed)

- **Mifflin-St Jeor BMR** — still the best general-purpose default. (Modest over-prediction in some
  Asian cohorts is a known limitation; indirect calorimetry is the only true fix.) ✅
- **Schofield pediatric BMR** — international, weight-based. ✅ *(Note: for pediatric energy you can now
  cross-check the Schofield→TEE result against the PDRI REI-by-age column above.)*
- **Activity & stress factors** — physiological, not ethnic. ✅
- **Disease-specific flat-rate kcal/kg** (CKD 25–35, liver 35–40, high-protein 25–35, refeeding ramp)
  — KDOQI/ESPEN/NICE/ASPEN, weight-indexed → self-scale to smaller Asian body sizes. No change. ✅
  (Confirmed KDOQI 2020 energy 25–35 kcal/kg and diabetes-CKD protein 0.6–0.8 g/kg IBW.)

> **Summary of PDRI-driven edits to fold into `intervention-goals.md` §1:** (a) baseline macro split →
> carb 55–75% / fat 15–30% / protein 10–15%; (b) adult fiber → 20–25 g/day; (c) baseline sodium →
> < 2000 mg; (d) add free-sugars cap < 10% E; (e) relabel the pediatric protein table to PDRI g/day
> values (and note the ~1.0–1.2 g/kg healthy-adult maintenance figure); (f) keep 0.8 g/kg IBW as the
> clinical disease-state protein floor; (g) re-tag the reference from "IOM 2005" to "PDRI 2015 (FNRI)".

---

## 6. Disease-specific goals — fact-check & AP notes

| Goal | Fact-check verdict | Asia-Pacific note |
|---|---|---|
| **Renal / CKD** (§2) | ✅ KDOQI 2020 individualized 25–35 kcal/kg, protein 0.6–0.8 (0.55–0.60 non-diabetic; 0.6–0.8 with diabetes), HD 1.2, PD 1.2–1.5 — all **correct and current**. Doc's 2026-06-08 fix to individualized energy is right. | Weight-indexed → self-scales. Use **IBW** for protein math (doc already does). No AP-specific change. Worth noting Asian CKD literature increasingly favors plant-dominant low-protein diets — optional future enhancement, not a correction. |
| **Diabetic** (§3) | ✅ ADA 2024/2026 "no single ideal macro distribution," HbA1c individualization, stage_3 protein target language — all correct and nuanced. | Change `stage_2` BMI trigger 25 → **23** (§3.2). Consider citing **IDF Western Pacific** / Philippine UNITE for Diabetes CPG alongside ADA for regional legitimacy. |
| **Cardiac** (§4) | ✅ Doc honestly labels mild/moderate/severe as *internal* tiers, not AHA standard — good. DASH K/Ca/Mg targets correct. | Sodium targets fine. "Overweight coexists" deficit now triggers at BMI ≥ 23. |
| **Weight Loss** (§5) | ✅ Deficits and floors correct. | **Remap stages to AP BMI** (§3.1) — this is the single biggest AP edit. |
| **Weight Gain** (§6) | ✅ Refeeding ramp (5–10 → 30–35 kcal/kg, full needs day 4–7) matches NICE CG32. The 2026-06-08 correction (4–7 days, not 3-week) is right. | %IBW-based staging is body-relative → fine for AP. |
| **High Protein** (§7) | ✅ ASPEN/ESPEN protein and stress kcal/kg correct; albumin-as-negative-acute-phase-reactant caveat is excellent and current. | Weight-indexed → fine. |
| **Liver** (§8) | ✅ The "protein restriction is contraindicated" stance with BCAA/veg-dairy protein and 1.2–1.5 g/kg matches ESPEN 2019 / EASL — correct and modern. | 35–40 kcal/kg weight-indexed → fine. |
| **Malnutrition** (§9) | ✅ GLIM (≥1 phenotypic + ≥1 etiologic) correct; 2025 update cited. | **Fill the Asian cut-points**: low-BMI (§4.5) and AWGS muscle mass (§4.4). This is the goal that most needs AP numbers. |
| **Malnutrition vs Weight Gain** (§10) | ✅ Clinically sound distinction. | No AP change. |

**Net:** the existing clinical content is largely accurate and recently corrected — the document is in
good shape. The AP work is **not** about fixing bad medicine; it is about (1) the BMI classification and
its three downstream stage maps, (2) filling in the Asian cut-points the doc explicitly left as TODOs
(GLIM low-BMI, muscle mass, waist), and (3) swapping IOM→PDRI as the national reference.

---

## 7. Fact-check ledger

| Item in current doc | Verdict | Note |
|---|---|---|
| WHO Western BMI table (lines 81–88) | ✅ Numbers correct | But wrong *default* for this population — see §2 |
| AP caveat "obese at BMI ≥ 27.5" (line 79) | ⚠️ Imprecise | 27.5 is the high-risk *action point*; obese *class* starts at 25.0 |
| Hamwi IBW (lines 100–102) | ✅ Formula correct | US-derived; short-stature bias — see §4.1 |
| AjBW 0.25 factor; drug-dosing 0.4 caveat (line 113) | ✅ Correct | Good that it distinguishes the two |
| Mifflin-St Jeor (lines 138–139) | ✅ Correct | Modest over-prediction in Asians; acceptable default |
| Activity factors 1.2–1.9 (line 160) | ✅ Standard | — |
| Stress factors (line 172) | ✅ Standard (Roza/Shizgal lineage) | — |
| Fluid 30–35 mL/kg ≈ 1 mL/kcal (line 189) | ✅ Correct; "not additive" wording right | — |
| Schofield pediatric BMR (lines 256–270) | ✅ Correct | — |
| Holliday-Segar fluid (lines 332–340) | ✅ Correct; example math checks out | 25 kg → 1600 mL ✓ |
| CKD KDOQI 2020 energy/protein/electrolytes (§2 table) | ✅ Correct & current | Confirmed against KDOQI 2020 |
| Diabetic ADA stance + HbA1c tiers (§3) | ✅ Correct & current | Change stage_2 trigger to BMI ≥ 23 |
| Cardiac internal-tier disclaimer (line 480) | ✅ Honest & correct | — |
| Weight-loss deficits + floors (§5) | ✅ Correct | Remap stages to AP BMI |
| Refeeding ramp / NICE CG32 (§6, §9) | ✅ Correct | 4–7 days to full needs ✓ |
| Liver protein-restriction-contraindicated (§8) | ✅ Correct & modern | ESPEN 2019 / EASL ✓ |
| GLIM criteria (§9) | ✅ Correct | Asian cut-points missing — fill per §4.4/§4.5 |
| GLIM low-BMI "adjust per regional thresholds" (line 787) | ⚠️ Incomplete | Give the actual numbers: <18.5/<20; severe <17.0/<17.8 |
| MUAC adult 190–210 / <185 mm (§9) | ⚠️ Soft | Adult MUAC poorly standardized; use as GLIM support, not standalone |
| IOM DRI baseline macros/protein (lines 211, 326) | ⚠️ Swap source — now reconciled (§5) | PDRI 2015: carb 55–75% / fat 15–30%; adult fiber 20–25 g; sodium < 2000 mg; add free-sugars < 10% E; pediatric protein → PDRI g/day |
| Baseline protein 0.8 g/kg (line 205) | ✅ Keep as clinical floor | But note PDRI healthy-adult maintenance ≈ 1.0–1.2 g/kg (diet protein-quality correction) — §5b |
| Adult fiber 25–38 g (line 208) | ⚠️ Lower for PH | PDRI adult fiber **20–25 g/day** — §5c |
| 1 mL/kcal fluid rule (line 189) | ✅ Independently validated | PDRI water AI ≈ REI kcal ≈ 1 mL/kcal — §5e |
| Water USDA nutrient ID 1051 → water_g (line 375) | ✅ Implementation detail, correct | — |

---

## 8. Assessment data — what to capture and why

This is the second deliverable: which inputs the assessment must collect so the (revised) calculation
can run. Mapped against the current `assessments` schema.

### Already captured (schema confirms) — keep

- **Anthropometric:** `weight`, `height`, `bmi`, `usual_weight`, `weight_loss_percentage`,
  `weight_loss_period`, `body_composition`, `ibw_percentage`, `muac_mm`, `waist_cm`, `hip_cm`
- **Activity:** `physical_activity_level` (drives TEE; replaced free-text `lifestyle`) ✅ good move
- **Function/intake:** `functional_assessment`, `energy_intake_status`, `nutritional_status`,
  `dietary_intake_method` (24h recall / FFQ / 3-day / other), `present_diet`, `appetite_changes`
- **Clinical:** `medical_history`, `medications` (drug–nutrient), `allergies` (hard filter),
  `food_dislikes` (soft filter), `chewing_swallowing_difficulties`, `constipation`, `diarrhea_notes`,
  `food_intolerance`
- **Patient (from `patients`):** `dob` (→ age), `sex`, `religion` (dietary law filtering),
  `medical_diagnosis`
- **Biochemical:** separate `biochemical_data` table (HbA1c, albumin, electrolytes, GFR/creatinine) —
  these are monitoring/diagnosis inputs, not macro-formula inputs (doc is right that HbA1c/albumin don't
  change macros).

### Gaps to add (to support the AP revision)

| New field | Type | Drives | Rationale |
|---|---|---|---|
| `bmi_classification_system` | enum: `asia_pacific` \| `who_western` | Which cut-point table classifies BMI | Makes the AP-vs-Western choice explicit & auditable (§2) |
| `ibw_method` | enum: `hamwi` \| `bmi_22` \| `broca` | Which IBW basis was used | Resolves the IBW ambiguity & makes prescriptions reproducible (§4.1) |
| `calf_circumference_cm` | decimal | GLIM muscle-mass criterion (AWGS) | Cheap, tape-measure muscle proxy; needed to apply AWGS cut-points (§4.4) |
| `muscle_mass_asmi` | decimal, nullable | GLIM muscle-mass criterion (BIA) | If a BIA device is available; AWGS 7.0 / 5.7 kg/m² |
| `edema_present` | boolean | Whether to trust measured weight; fluid logic | Edema/ascites invalidates weight-based math; flagged in CKD/liver/cardiac |
| `pregnancy_lactation_status` | enum | Energy/protein add-ons; excludes some goals | Standard nutrition gate; currently absent |
| `glim_phenotypic` / `glim_etiologic` | json/checkbox set | Formal GLIM diagnosis for `malnutrition` goal | §10 says malnutrition *requires* GLIM — capture it structurally, not as free text |
| `waist_hip_ratio` | computed | Central-obesity risk | Derivable from existing waist/hip; just surface it |

### Derived/computed values the engine should produce (not stored raw)

- Age (from `dob` at assessment date), BMI (already stored but should be recomputed not trusted),
  AP BMI class, %IBW, IBW (by chosen method), AjBW (if %IBW > 120%), BMR, TEE, WHR, central-obesity flag,
  GLIM low-BMI severity band.

> **Principle:** store *measured* inputs and the *method choices*; compute everything derivable at
> prescription time so a change to the classification table re-flows automatically and historically
> stored prescriptions remain explainable.

---

## 9. Open questions for the RND / clinical owner

1. **Default classification:** Switch system default to **Asia-Pacific** BMI, with Western as an optional
   reference column? (This research recommends yes.)
2. **Obese-class count:** For `weight_loss`, collapse to AP's native two obese classes, or keep four
   stages with re-cut boundaries? (§3.1)
3. **IBW method:** Keep Hamwi as default for training continuity, or move to BMI-22 method? Offer both?
4. **PDRI adoption:** Should pediatric protein/fiber and AMDR be re-anchored to **PDRI 2015** now, or
   keep IOM until the PDRI tables are digitized into the system?
5. **MUAC vs calf circumference vs BIA:** Which muscle-mass proxy is realistically available at the
   point of care? That decides which GLIM muscle criterion we operationalize.
6. **Regional CPG citations:** Add Philippine/Asian guideline citations (FNRI PDRI, UNITE for Diabetes,
   PCP/PSN) alongside the US/EU ones for local credibility and audit?

---

## 10. Source list

**Asia-Pacific BMI & obesity**
- WHO Western Pacific Region / IASO / IOTF (2000). *The Asia-Pacific Perspective: Redefining Obesity and its Treatment.* — https://apps.who.int/iris/handle/10665/206936
- WHO Expert Consultation (2004). Appropriate body-mass index for Asian populations and its implications for policy and intervention strategies. *Lancet* 363:157–163. — https://www.sciencedirect.com/science/article/abs/pii/S0140673603152683
- Korean Society for the Study of Obesity (2022). Clinical Practice Guidelines for Obesity. — https://pmc.ncbi.nlm.nih.gov/articles/PMC10327686/
- Tham KW et al. (2023). Obesity in South and Southeast Asia — consensus on care and management. *Obesity Reviews.* — https://onlinelibrary.wiley.com/doi/10.1111/obr.13520

**Central obesity / waist circumference (Asian)**
- IDF Worldwide Definition of Metabolic Syndrome — Asian cut-points (men ≥ 90 cm, women ≥ 80 cm). — https://pmc.ncbi.nlm.nih.gov/articles/PMC7759813/

**Muscle mass / sarcopenia (Asian)**
- Asian Working Group for Sarcopenia (AWGS 2019): ASMI 7.0 (M) / 5.7 (F) kg/m². — https://www.ncbi.nlm.nih.gov/pmc/articles/PMC12397158/
- Calf circumference as muscle-mass proxy, Asian validation (Thai/Vietnamese older adults). — https://www.ncbi.nlm.nih.gov/pmc/articles/PMC10709895/ ; https://www.clinicalnutritionopenscience.com/article/S2667-2685(25)00127-5/fulltext

**GLIM Asian cut-points**
- Asian GLIM low-BMI validation (< 17.0 for < 70 y; < 17.8 for ≥ 70 y). *Clin Nutr* 2020. — https://pubmed.ncbi.nlm.nih.gov/32739660/
- GLIM Criteria (2019) and 5-Year Update (2025). *Clin Nutr.* — https://www.clinicalnutritionjournal.com/article/S0261-5614(18)31525-7/fulltext

**Philippine national standards**
- FNRI-DOST. Philippine Dietary Reference Intakes (PDRI) 2015. — https://www.fnri.dost.gov.ph/index.php/tools-and-standard/philippine-dietary-reference-intakes-pdri
- **PDRI 2015 Summary Tables (rev. Sept 2018)** — source of all numeric tables in §5. — https://www.fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf
- PDRI 2015 seminar deck (PDF). — https://fnri.dost.gov.ph/images/sources/SeminarSeries/41st/PHILIPPINE-DIETARY-REFERENCE-INTAKES-2015.pdf
- FDA Circular 2023-009 (adopts PDRI 2015 as dietary standard). — https://www.fda.gov.ph/fda-circular-no-2023-009-adoption-of-2015-philippine-dietary-reference-intakes-pdri/
- §5 underlying values: WHO Guideline on Sodium Intake (2012) < 2 g/day; WHO Guideline on Sugars Intake (2015) free sugars < 10% E; WHO Guideline on Potassium Intake (2012) 3510 mg/day — as cited in PDRI "Additional Recommendations."

**Disease-specific (confirmed current; from existing doc, re-verified)**
- KDOQI Nutrition in CKD 2020 Update. *Am J Kidney Dis* 76(3 S1):S1–S107. — https://www.ajkd.org/article/S0272-6386(20)30726-5/fulltext
- D'Alessandro C et al. Energy Requirement for Elderly CKD Patients. *Nutrients* 2021. — https://www.ncbi.nlm.nih.gov/pmc/articles/PMC8541480/
- ADA Standards of Care in Diabetes 2024 / 2026. *Diabetes Care.*
- NICE CG32 (refeeding). — https://www.nice.org.uk/guidance/cg32
- ESPEN Liver Disease 2019; EASL Nutrition in Chronic Liver Disease 2019.
- Broca index validation in obese Filipino adults (HERDIN). — https://www.herdin.ph/index.php?view=research&cid=83878

---

*Companion to [`intervention-goals.md`](intervention-goals.md). When the clinical owner signs off on the
decisions in §9, fold the approved changes into that document and bump its changelog.*

> **Implementation tracking:** the engineering work that consumes this research (calc accuracy,
> backend source of truth, micro/fluid UX, meal-plan tolerance, assessment data, monitoring, inventory)
> is planned in [`artifacts/superpowers/nutrition-engine-overhaul-plan.md`](../../artifacts/superpowers/nutrition-engine-overhaul-plan.md).
> The §9 open questions are the **Phase 0 sign-off gate** there — resolve them before Phase 1 coding.
