# NutriScope — Intervention Goals: Clinical and Calculation Reference

**Reviewed:** 21 October 2026.
**Follow-up source and implementation audit:** 25 October 2026, against source revision `79ee23b53a0ba047faa791cdb8e5b7fcb1211429`. This audit clarifies existing defaults and implementation limits; it does not introduce automatic clinical suitability selection.
**Status:** Version 2 implemented locally; release and live acceptance are separate checks. This reference separates verified source recommendations from local defaults. Numerical eligibility, overrides and follow-up still require the RND's assessment.

PHP `NutritionPrescriptionService::autofill` is the current calculation authority for previews and saves. The browser displays the server snapshot and never reconstructs a historical formula from current measurements. `prescription-targets.json` and `autofillLegacy` retain version-1 regression behavior only. Historical values, labels and generated report files are not bulk-rewritten. Use the [clinical maintenance map](../developers/ncp/README.md) for current owners and tests; historical planning remains in Git history.

## Clinical, reporting, and privacy boundary

Assessment, nutrition diagnosis, intervention and monitoring are separate Nutrition Care Process steps. A physician diagnosis, Nutrition care category, PES diagnosis and intervention goal are distinct records.

Current BMI classification uses [WHO 2007 sex-specific monthly bands](https://www.who.int/toolkits/growth-reference-data-for-5to19-years/indicators/bmi-for-age) at 61–228 completed months, then Asia-Pacific bands: Underweight <18.5, Normal 18.5–<23, Overweight 23–<25, Obese Class I 25–<30, and Obese Class II >=30 kg/m². Month 60 has BMI without a class; under-five BMI and pediatric adult-panel estimates are omitted. This bounded classification policy does not change the separate prescription engine's existing adult/pediatric boundary. `Assessment::classifyBmi` owns backend classification; the web mirrors it. Missing required age/sex, measurements, edema and pregnancy/lactation withhold classification. BMI/%IBW do not establish malnutrition or severity; >=35 is not an additional BMI class. Current reports separate BMI classification from screening risk; frozen census history remains unchanged. Adult source: [World Obesity Federation's Asia-Pacific table](https://www.worldobesity.org/about/about-obesity/obesity-classification), reproducing the WHO/IASO/IOTF 2000 report. Foundational reference dates are retained rather than described as new standards.

- Confirm the goal from the assessment. Never infer a diagnosis or Nutrition care category solely from the selected diet.
- Keep confirmed comorbidities and malnutrition diagnosis/severity in clinical context. A single primary goal does not exclude another condition.
- Pregnancy/lactation is a calculation context, not a goal. Oncology has no single automatic diet: General Diet, High Protein or Custom Plan may fit the assessed needs.
- A screening score alone cannot diagnose malnutrition or determine its severity. Albumin/prealbumin are not direct measures of protein stores or response to feeding.
- Use fictional examples in repository documentation and tests. Preserve role restrictions, data minimization and historical records. These controls alone do not certify privacy-law compliance.
- Census and PES presentation boundaries in §15 remain separate from the prescribing contract.

Sources: [Academy NCP model](https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-care-process-model), [ASPEN visceral-protein position paper](https://doi.org/10.1002/ncp.10588), [GLIM five-year update](https://pubmed.ncbi.nlm.nih.gov/40222089/). Privacy references remain [HHS minimum necessary](https://www.hhs.gov/hipaa/for-professionals/privacy/guidance/minimum-necessary-requirement/index.html), [HHS de-identification](https://www.hhs.gov/hipaa/for-professionals/special-topics/de-identification/index.html) and [Philippine Data Privacy Act](https://privacy.gov.ph/data-privacy-act/); this review does not independently audit legal compliance.

## 1. Approved goal options

Use these exact visible labels. Show **Type**, rather than suggesting every option is a disease severity stage.

| Goal | Type options | Proposed values |
|---|---|---|
| General Diet | None | `general_diet`; type null |
| Diabetic Control | Type 1 Diabetes; Type 2 Diabetes | `diabetic_control`; `type_1`, `type_2` |
| Renal Diet | CKD Stages 1–2; CKD Stages 3–5 Without Dialysis; Hemodialysis | `renal_diet`; `ckd_1_2`, `ckd_3_5_non_dialysis`, `hemodialysis` |
| Cardiac Diet | Hypertension; Dyslipidemia; Heart Failure | `cardiac_diet`; `hypertension`, `dyslipidemia`, `heart_failure` |
| High Protein | Postoperative Recovery; Wound Healing | `high_protein`; `postoperative_recovery`, `wound_healing` |
| Liver Disease Diet | Compensated Cirrhosis; Decompensated Cirrhosis | `liver_disease`; `compensated`, `decompensated` |
| Custom Plan | None | `custom`; type null |

This is **7 goals and 12 type choices**, replacing 9 goals and 30 stages. Reuse `disease_stage` for the selected type where compatible; do not create a parallel taxonomy.

- General Diet provides routine calculated estimates. Custom Plan supports clinician-entered targets and unsupported specialist contexts; both remain available.
- Weight Loss, Weight Gain and Malnutrition leave the new-goal menu. Energy direction is separate; malnutrition remains a diagnosis requiring appropriate protein, intake and monitoring care. A surplus alone is insufficient treatment.
- Cardiac conditions and recovery indications may coexist. The primary type guides notes; preserve all confirmed conditions. Never infer one condition from an old mild/moderate/severe label.
- Grouping CKD stages does not change the confirmed G stage, establish CKD from eGFR alone, or erase clinical stability. Keep the actual diagnosis in assessment.
- Cirrhosis has compensated and decompensated clinical stages. These are not types of every liver disease. Other liver conditions use individualized targets in Custom Plan.
- Peritoneal dialysis, burns, ICU protocols and encephalopathy grades are outside routine type options. Preserve them in historical records and clinical context.

Sources: [KDIGO 2024, CKD classification and §3.3.1][kdigo], [VA cirrhosis stages](https://www.hepatitis.va.gov/HEPATITIS/cirrhosis/background/stages.asp).

## 2. Energy adjustment and existing fluid field

### Energy

**Energy adjustment:** No Adjustment / Add Surplus / Add Deficit.
For surplus/deficit, require a positive whole-number **kcal/day** amount. No Adjustment stores zero. Keep the amount explicit; do not silently choose it from diabetes type, BMI, malnutrition grade or cirrhosis stage.

```text
calculated energy = baseline energy + applicable maternal addition + signed adjustment
signed adjustment = 0, +amount, or −amount
```

Apply the adjustment once from the original baseline on every recalculation. An adjustment to a clinical kcal/kg target is a change to that target, not proof of a surplus/deficit against measured expenditure. No automatic sex-specific calorie floor may silently change the displayed subtraction. Inappropriate or unusually low targets need RND review and a documented coherent prescription.

**Baseline and final target only:** The calculator has no initial-feeding, day-by-day, stepped, percentage-of-target or progression result. Gradual increases/decreases belong only in notes. They must not change energy, protein, carbohydrate, fat or fluid values. The explicitly selected fixed surplus/deficit is the only energy-direction adjustment; it is not a feeding ramp.

### Fluid

Reuse the existing `fluid_ml` amount and blank/maximum/minimum bound selector. Do not add a fluid-mode dropdown. A maximum denotes an explicitly entered prescribed limit; the underlying mode is metadata. Editing fluid never refreshes other targets from newer measurements.

- A confirmed limit takes precedence and persists through goal, type and energy changes. Do not clear it or replace it with an estimate.
- No universal 1,500/2,000-mL Heart Failure limit, 750-mL dialysis order, or ascites-triggered limit.
- Estimates are total daily needs, not automatically the amount to drink. Consider food, drinks, enteral/parenteral sources and the actual clinical order.
- Renal impairment, heart failure, fluid retention and abnormal losses require individualized fluid assessment. Do not present a routine estimate as an established safe order.
- If a legacy value has no recorded mode, label its meaning unrecorded; do not retrospectively classify it.
- No new standalone fluid-restriction goal is needed.

Sources: [NICE CG32 §1.4.2][nice], [AHA heart-failure lifestyle guidance][hf], [KDOQI 2020 statements 3.0–3.1][kdoqi].

## 3. Inputs, dates and body-weight basis

Record the observation date, age at that date, sex used by the equation, measured weight, height, activity estimate, maternal status and any clinically confirmed dry/reference weight. Preserve source dates when monitoring and assessment supply different fields.

- Use age in days/months for infant boundaries; do not cast a 9-month-old to age zero.
- Use measured weight in routine adult Mifflin estimation. Its original study included adults with obesity; it did not specify Hamwi adjusted weight above 120% IBW.
- Do not apply one global weight substitution to energy, protein and fluid. Each calculation records its own kg value and weight basis.
- With edema/ascites, require a clinically confirmed usable weight and named method. Do not calculate a dry weight from the goal label or silently use fluid-loaded scale weight.
- Underweight, obesity, dialysis, sarcopenia and fluid retention may require protocol-specific weight selection. The system can still multiply the confirmed values automatically.
- Do not label dry weight, ideal weight and adjusted weight as synonyms. ESPEN's liver commentary discusses ideal weight for predicted BMR with ascites; it does not validate the app's universal Hamwi substitution.
- Remove automatic adult Hamwi target weights in children. Pediatric growth targets require growth assessment, not a 30-kg floor.
- Save calculation inputs/version with the dated plan. Viewing a historical plan must not substitute today's age or latest weight.

Sources: [Mifflin original study][mifflin], [ESPEN liver guideline, energy-expenditure commentary][liver], [KDOQI 2020 body-weight assessment statement 1.1.6][kdoqi].

## 4. Adult resting energy and estimated maintenance

For an eligible routine adult, use the published simplified Mifflin–St Jeor equation:

```text
REE (kcal/day) = 10 × W(kg) + 6.25 × H(cm) − 5 × age(years) + C
C = +5 male; −161 female
estimated maintenance = REE × confirmed adult activity estimate
```

REE is predicted resting expenditure. Estimated maintenance is not a guarantee of stable body weight. Review intake and weight trend; prefer measured expenditure when clinically indicated/available.

The existing multipliers **1.2 / 1.375 / 1.55 / 1.725 / 1.9** may be retained as **local pragmatic adult activity estimates**, requiring RND confirmation. The cited Roza–Shizgal paper reevaluates Harris–Benedict; this review did not establish that it validates those exact multipliers. Do not attribute them to Mifflin, label them measured PAL, or apply them as a validated pediatric/hospital-stress protocol.

The adult workflow boundary remains 18 years, but the original Mifflin cohort was 19–78. At age 18 or outside the derivation population, disclose extrapolation and require clinical assessment. PDRI's own 16–18 reference band still includes age 18.

Sources: [Mifflin 1990][mifflin], [Roza–Shizgal 1984](https://pubmed.ncbi.nlm.nih.gov/6741850/), [NASEM 2023 energy guidance](https://www.nationalacademies.org/read/26818/chapter/9), [PDRI tables][pdri].

## 5. Adult goal calculations: sources versus proposed defaults

These defaults are **local product choices for eligible adults**, not universal treatment doses or a claim that a source mandates a midpoint. Confirm clinical weight and suitability before use; retain editable targets and explicit overrides. Acute instability, obesity requiring a different protocol, uncertain nutrition status, incompatible comorbid orders or unsupported populations require individualized prescribing.

| Selection | Intended automatic calculation | Source and boundary |
|---|---|---|
| General Diet | Mifflin-based estimated maintenance; routine protein prefill `1.0 × confirmed kg` | Local protein choice within ESPEN standard-diet 0.8–1.0 g/kg actual-weight guidance. Malnutrition, older age and illness may need more; do not mistake this for a PDRI individualized requirement |
| Diabetic Control, either type | Same energy method and routine protein prefill as General Diet when no conflicting needs | No hidden −500. ADA specifies individualized macros; renal disease and other conditions require their actual prescribed protein target |
| Cardiac Diet, all three types | Same routine energy method; protein follows assessed needs | No stage-specific fat percentage or automatic fluid restriction. The type guides food advice and clinical orders |
| Renal Diet: CKD Stages 1–2 | `30 × confirmed clinical kg` energy; routine protein prefill `1.0 × confirmed kg` only if clinically suitable | 30 is a local choice within KDOQI's stable-adult 25–35 range; early CKD does not itself mandate a low-protein diet |
| Renal Diet: CKD Stages 3–5 Without Dialysis | `30 × confirmed clinical kg` energy; `0.8 × confirmed protein kg` | Adopt KDIGO 2024 for the eligible stable-adult default. Do not automatically restrict protein in unstable, malnourished or pediatric patients |
| Renal Diet: Hemodialysis | `30 × confirmed clinical kg` energy; `1.2 × confirmed protein kg` | 1.2 is the local upper-end choice within KDOQI maintenance-dialysis 1.0–1.2, not a mandatory dose for all dialysis patients. Confirm treatment and weight basis |
| High Protein: either type | `30 × confirmed clinical kg` energy; `1.2 × confirmed protein kg` starting prefill | ESPEN hospital-diet guidance supports 30 kcal/kg actual weight and at least 1.2 g/kg in its population. This is not a burn/ICU dose or a complete pressure-injury protocol |
| Liver Disease Diet: either stage | `confirmed kcal/kg coefficient × confirmed clinical kg`; local starting coefficient **32.5**, subject to assessment | A proposed midpoint within ESPEN's 30–35 nutrition-support range, especially its malnutrition/muscle-depletion context; not a validated universal dose for every compensated or decompensated patient |
| Custom Plan | Clinician-entered energy/protein/macros; no inferred energy baseline | Show which values were entered and which were calculated. Do not manufacture a disease-specific formula |

**Cirrhosis protein:** ESPEN specifies 1.2 g/kg for non-malnourished compensated patients and 1.5 for malnutrition/sarcopenia. Apply assessed nutrition status independently of the stage. For decompensated cirrhosis without established nutrition status, request confirmation of the appropriate protein coefficient within clinical guidance; do not infer 1.5 from that label alone. Automatic liver protein requires the source-supported assessed context. Otherwise choose Custom Plan and enter the individual final targets in the existing fields. Neither stage automatically restricts protein for encephalopathy.

**Renal protocol difference:** KDOQI 2020 includes lower-protein supervised options for metabolically stable CKD; KDIGO 2024 recommends approximately 0.8 g/kg for adult G3–G5. This design adopts the latter default, not a blended stage ladder. KDIGO advises avoiding high protein above 1.3 g/kg in patients at risk of progression. A different renal prescription must record its rationale/protocol.

**Current implementation limits:** `NutritionPrescriptionService::autofill` selects renal protein from the chosen type; it does not determine metabolic stability or change renal protein from `nutritionStatus`. Liver protein uses the explicitly supplied `calculation_options.nutrition_status`, while the default liver energy coefficient remains 32.5 for both types. These are baseline prefills requiring assessed suitability, not automatic eligibility decisions. Clinical kcal/kg baselines are prescribed starting targets, not measurements of maintenance expenditure.

The calculation context currently loads measurements, edema/dry weight, activity, maternal status and food restrictions, plus explicitly supplied calculation options. It does not automatically derive clinical nutritional status from assessment or diagnoses. In particular, `AssessmentController::saveRiskScore` writes `Assessment.nutritional_status` from `RiskScoreCalculator`, including its manual-factor path. Despite the stored malnutrition labels, this field is a screening-derived classification, not independent confirmation of malnutrition or sarcopenia. Missing clinical findings do not establish stability or adequate nutrition. See [NCP data meaning](../developers/ncp/data.md) for field ownership.

**Wounds and recovery:** WHS pressure-ulcer guidance has 30–35 kcal/kg and 1.25–1.50 g/kg protein in the setting of inadequate intake and nutrition support. It does not establish the dose for every wound. Use wound assessment and a confirmed target. Do not add fixed high-dose zinc/vitamins without deficiency assessment or an order.

Sources: [ESPEN hospital nutrition, recommendation 15][hospital], [KDIGO §3.3.1][kdigo], [KDOQI 3.0.3–3.1.1][kdoqi], [ADA 2026 nutrition][ada], [ESPEN liver practical points 71–74][liver], [WHS 2023 update §§2.4–2.5][whs], [ESPEN surgery 2025][surgery].

## 6. Macros, micronutrients and rounding

Set protein from the assessed condition and selected weight basis. For routine non-infant adult calculations, a **25% energy fat prefill** is a local starting choice within PDRI's adult 15–30% range; it is not an ADA-mandated diabetes distribution. Carbohydrate is the residual after protein and fat. Display any departure from the selected reference distribution for RND review; do not force a disease-specific protein target to fit a general-population percentage.

**Implementation gap verified 25 October 2026:** the inspected calculator rejects incompatible energy/protein/fat arithmetic, but the calculation and display path does not implement the reference-distribution warning described above. That warning remains a requirement, not verified current behavior.

Use one declared rounding sequence in PHP, preview and trace:

```text
E = round_half_up(unrounded baseline + maternal kcal + signed kcal adjustment)
P = round_half_up(confirmed protein coefficient × protein weight + applicable maternal protein)
F = round_half_up(E × selected fat fraction / 9)
C = round_half_up((E − 4P − 9F) / 4)
```

Reject an infeasible negative carbohydrate residual; do not hide it with a zero clamp. Whole-gram macros may differ slightly from displayed energy; show rounding rather than promise exact equality. Keep unrounded intermediate values in the snapshot.

Do not create another nutrient prescription in food advice. Sodium, potassium, phosphorus, fiber and other displayed limits come from the confirmed prescription, with units and target/minimum/maximum meaning. WHO recommends less than 2 g sodium/day for adults at population level; individualized clinical orders and pediatric applicability still matter. Do not read PDRI sodium adequate intake as a therapeutic maximum or reuse automatic 1,500-mg/4,700-mg tiers.

Sources: [PDRI summary Table 1][pdri], [ADA 2026 nutrition][ada], [WHO sodium guidance](https://www.who.int/tools/elena/interventions/sodium-cvd-adults).

## 7. Maternal context

PDRI reference additions, when applicable, are: first trimester **0 kcal**, second/third **+300 kcal/day**, lactation **+500 kcal/day**; protein **+27 g/day** for pregnancy/lactation. Apply once and show separately. These additions do not by themselves validate stacking onto every renal, hepatic or other clinical endpoint; require assessment where prescriptions conflict.

PDRI water additions of +300 mL in pregnancy and +700 mL in lactation are guidance, not an instruction to override a prescribed fluid maximum. Do not silently add them to a restriction.

The legacy PHP and browser paths disagreed for adolescent pregnancy. Version 2 requires Custom Plan for adolescent pregnancy/lactation until an appropriate combined protocol is established; it does not stack the adult maternal additions onto pediatric EER.

Source: [PDRI summary Tables 1–2, revised September 2018][pdri]. Its reference-body-size daily protein values are population references, not a universal g/kg treatment prescription.

## 8. Pediatric calculations and limits

For patients younger than 18 at the selected measurement, the menu shows **General Diet**, **Diabetic Control → Type 1 Diabetes / Type 2 Diabetes**, and **Custom Plan**. An accessible tooltip explains the smaller menu and the individualized alternative. Age is automatic; there is no adult/pediatric toggle. A birthday or later assessment never changes a saved goal or calculation.

General Diet and both diabetes types use the **NASEM 2023 Table 5-15 EER** equations. These are general energy/growth estimates, not pediatric renal, cardiac, liver, critical-care or catch-up treatment doses. Custom Plan accepts an individual prescription without requiring a fabricated automatic estimate. Normal growth energy belongs in the baseline; refeeding or stepped progression does not.

Let A be age in years, H height in cm and W weight in kg. For ages <3:

| Sex | EER before rounding | Normal growth addition |
|---|---|---|
| Male | `−716.45 − A + 17.82H + 15.06W + growth` | 200 kcal <3 months; 50 at 3–<6 months; 20 thereafter |
| Female | `−69.15 + 80A + 2.65H + 54.15W + growth` | 180 kcal <3 months; 60 at 3–<6; 20 at 6–<12; 15 at 12–<36 months |

For ages 3–<18, EER is `constant + age coefficient × A + height coefficient × H + weight coefficient × W + growth`:

| Sex/category | Constant | Age | Height | Weight |
|---|---:|---:|---:|---:|
| Male, inactive | −447.51 | 3.68 | 13.01 | 13.15 |
| Male, low active | 19.12 | 3.68 | 8.62 | 20.28 |
| Male, active | −388.19 | 3.68 | 12.66 | 20.46 |
| Male, very active | −671.75 | 3.68 | 15.38 | 23.25 |
| Female, inactive | 55.59 | −22.25 | 8.43 | 17.07 |
| Female, low active | −297.54 | −22.25 | 12.77 | 14.73 |
| Female, active | −189.55 | −22.25 | 11.74 | 18.34 |
| Female, very active | −709.59 | −22.25 | 18.22 | 14.25 |

Growth additions: males 20 kcal at age 3, 15 at 4–8, 25 at 9–13, 20 at 14–17; females 15 at 3–8, 30 at 9–13, 20 at 14–17. Resolve infant boundaries from exact months at measurement. The source also includes age 18; this product's adult workflow starts at 18, with the Mifflin extrapolation caveat in §4.

Existing assessment activity labels map locally: sedentary → inactive; light → low active; moderate → active; very/extra active → very active. This is a practical **proxy requiring RND review**, not proof of the source's age-specific measured PAL band. Below age 3 no activity category is used. Do not multiply EER by the adult activity factor.

Pediatric macro choices are **local percentages within PDRI Table 1 AMDR**, not individualized protein RNI: protein 5%E before 6 months and 10%E thereafter; fat 50%E before 6 months, 35%E at 6–<12 months, 30%E at 1–<3 years and 25%E at 3–<18 years. Compute rounded protein/fat from final energy, then residual carbohydrate. Diabetes type changes guidance rather than energy or a mandatory macro ratio. Review growth, intake and clinical needs before prescribing; specialist disease doses are not inferred.

Sources: [NASEM 2023, Table 5-15](https://www.nationalacademies.org/read/26818/chapter/7), [Health Canada implementation](https://www.canada.ca/en/health-canada/services/food-nutrition/healthy-eating/dietary-reference-intakes/tables/equations-estimate-energy-requirement.html), [PDRI Table 1][pdri].

The following **rounded Schofield REE equations** are reproduced in the ESPGHAN/ESPEN/ESPR pediatric energy guideline, Table 1. W is kg; result is **resting kcal/day**.

| Age | Male | Female |
|---|---|---|
| Birth to <3 years | `59.5W − 30` | `58.3W − 31` |
| 3 to <10 years | `22.7W + 504` | `20.3W + 486` |
| 10 to <18 years | `17.7W + 658` | `13.4W + 692` |

These rounded published coefficients differ slightly from legacy high-precision variants. Select one cited table/version consistently before changing fixtures. A verified REE equation is not a verified total intake target. The source is a pediatric parenteral-nutrition guideline; critically ill children do not simply receive adult activity/stress additions.

- Freeze date-specific age and use correct reference bands; test infant 6/12-month and adolescent 17/18/19-year transitions.
- For the supported general EER path, calculate automatically and label its general-reference scope. Unsupported disease dosing and adolescent maternal care use Custom Plan. Schofield REE is reference-only and never substitutes for EER.
- Legacy protein bands 1.52/1.20/1.05/0.95/0.85 were IOM-style values; version 2 does not misattribute them to PDRI.
- PDRI infant fat ranges are 40–60% at 0–5 months and 30–40% at 6–11 months; universal 30% fat is inappropriate. Do not generate ordinary solid-food meals for young infants.
- Do not apply adult calorie deficits, CKD protein restrictions or Hamwi target weights automatically. KDIGO specifically advises against protein restriction in children with CKD.
- Use WHO growth assessment with age-appropriate definitions. For ages 5–19, BMI-for-age overweight is >+1 SD and obesity >+2 SD; height-for-age stunting is not an acute wasting diagnosis.
- WHO 2023 wasting/nutritional-oedema guidance supersedes the 2013 guideline for its under-5 scope. It does not supply a universal adolescent catch-up formula.

Implementation limit: the application does not yet compute WHO pediatric growth z-scores. Do not describe recorded raw BMI as an automatic BMI-for-age classification. Existing MUAC screening is restricted to known ages 6–59 months, using the standing 115/125-mm cutoffs and non-diagnostic screening labels. Older children, adults and unknown ages retain the measurement without applying these cutoffs. An above-cutoff MUAC does not exclude malnutrition. WHO's [September 2026 implementation guidance](https://www.who.int/publications/i/item/9789240122840) supports the 2023 guideline; the current screening bounds were checked against [WHO identification guidance](https://www.who.int/tools/elena/interventions/sam-identification) and the [2023 recommendation definitions](https://www.ncbi.nlm.nih.gov/books/NBK601655/).

Sources: [Pediatric energy guideline, Table 1][pediatric], [FAO infant energy](https://www.fao.org/4/y5686e/y5686e05.htm), [PDRI][pdri], [KDIGO pediatric practice point 3.3.1.4][kdigo], [WHO BMI-for-age](https://www.who.int/toolkits/growth-reference-data-for-5to19-years/indicators/bmi-for-age), [WHO 2023 wasting guidance](https://www.who.int/publications/i/item/9789240082830).

## 9. Fluid arithmetic

For eligible routine adults, **32.5 mL/kg/day** may remain an explicitly local midpoint within NICE's 30–35 total-fluid guidance. NICE §1.4.2 excludes severe illness/injury and refeeding risk from its routine recommendation. mL/kg and mL/kcal methods are not universally interchangeable.

The current pediatric Holliday–Segar daily maintenance arithmetic is:

```text
W ≤10 kg:       100W
10<W≤20 kg:     1,000 + 50(W−10)
W>20 kg:        1,500 + 20(W−20)       mL/day
```

This estimates maintenance in its clinical context; it is not a resuscitation, renal/cardiac restriction or infant feeding-volume prescription. Compare the chosen method with the actual clinical order and all fluid sources. A confirmed restriction remains a maximum and is shown separately from any reference estimate.

Sources: [NICE CG32][nice], [NICE pediatric IV-fluid recommendations, routine maintenance](https://www.nice.org.uk/guidance/ng29/chapter/recommendations).

## 10. Reviewed automatic notes

Each generated Education, Counseling goals and Strategies field contains **at most two concise sentences**. They are separate fields, not a combined paragraph. Barriers stays blank unless entered from the assessment. Never invent patient agreement, medication changes or a new nutrient order.

### Shared education

| Goal | Exact base text |
|---|---|
| general_diet | Choose varied foods in prescribed portions, including vegetables, fruit and protein; limit sweetened drinks and salty foods. |
| diabetic_control | Follow your meal portions for rice, bread, fruit and other carbohydrate foods; know what to eat or drink if your blood sugar drops. |
| renal_diet | Follow your kidney team's advice on protein and drinks; use less salt and avoid other foods only when your team advises it. |
| cardiac_diet | Choose vegetables, whole grains and fish; limit salty packaged foods, fatty meats and butter. |
| high_protein | Include protein foods you can safely eat at meals; eat enough to support healing, following your care plan. |
| liver_disease | Eat regular meals with enough protein and an evening snack if advised; avoid alcohol, raw or undercooked meat or seafood, and raw milk. |
| custom | Follow the food and drink plan made for you by your care team. |

Address the patient directly. Append one short sentence about relevant appetite/strength changes, growth/safe feeding, or pregnancy/breastfeeding. Include only confirmed contexts. Before six months, replace the base text with: “Follow your baby's breastmilk or infant-formula plan; do not start solid foods or change how you mix formula unless your care team advises it.”

### Counseling goals

- No Adjustment: “Follow the portions in your meal plan; discuss appetite or weight changes with your care team.”
- Add Surplus: “Eat the extra portions in your plan; tell your care team if you feel too full or your weight changes.”
- Add Deficit: “Follow the portions in your plan; choose water instead of sugary drinks and tell your care team if you feel very hungry.”

Do not retain an adjustment claim if a manually entered final energy target replaces the calculated target.

### Strategies by selected type

| Type | Exact base text |
|---|---|
| Type 1 Diabetes | Match meals and activity to the insulin plan agreed with your diabetes team; ask them before changing insulin doses. |
| Type 2 Diabetes | Follow your meal portions, stay active as advised and track blood sugar; ask your care team about a suitable weight goal. |
| CKD Stages 1–2 | Eat enough to stay well; early kidney disease alone does not mean you must avoid many foods. |
| CKD Stages 3–5 Without Dialysis | Follow the protein portions in your plan and attend kidney checkups and blood tests. |
| Hemodialysis | Spread protein foods across your meals and follow any salt or drink limits set by your dialysis team. |
| Hypertension | Use less salt, salty sauces and packaged foods to help manage blood pressure. |
| Dyslipidemia | Choose suitable high-fiber foods such as oats and beans; use oils such as olive or canola instead of some butter or fatty meat. |
| Heart Failure | Follow your advised salt and drink limits; tell your care team about worse swelling, shortness of breath or sudden weight change. |
| Postoperative Recovery | Eat meals and snacks you can manage; follow your surgical team's advice on when, what and how to eat. |
| Wound Healing | Eat enough food and include protein at meals; take nutrition supplements only if your care team recommends them. |
| Compensated Cirrhosis | Avoid long gaps between meals and tell your care team if you are eating less or losing strength. |
| Decompensated Cirrhosis | Keep eating the protein foods in your plan; ask your care team about salt and drink limits if you have swelling. |

Append at most one precaution sentence, only when relevant:
- Refeeding risk: “Your care team will guide gradual feeding increases and check your blood mineral levels.”
- Prescribed fluid limit: “Count drinks and water in foods toward your daily fluid limit.”
- Both: combine those clauses into one concise sentence.

There are no suggestion-inclusion checkboxes. Guidance from confirmed saved context is shown as selectable read-only text inside a neutral bordered collapsible section below its related field. Showing/hiding or copying never adds text to the plan; the RND must paste it into a field. Refeeding and gradual-change notes never change numeric targets. Source boundaries remain those in §§5–9 and the food sources in §11.

## 11. Food recommendation and avoidance content

Use the existing food panel with **Choose more often**, **Limit**, and a short **Avoid when indicated** line. These are adult examples, not a second prescription. Apply allergies, age/texture, current labs, comorbidities and actual orders before showing advice. When context is unavailable, use conditional wording rather than claiming individual safety.

| Selection | Choose more often | Limit / avoid when indicated |
|---|---|---|
| General Diet | Vegetables/fruit; varied protein foods; portioned grains | Sweetened drinks; salty processed foods |
| Type 1 Diabetes | Non-starchy vegetables; portioned grains/fruit; fish, eggs or tofu | Sweetened drinks and large carbohydrate portions; preserve the agreed low-glucose treatment |
| Type 2 Diabetes | Same shared diabetes pattern | Same shared diabetes advice; no compulsory deficit or fruit/rice ban |
| CKD Stages 1–2 | Varied foods fitting the prescription | Salty foods; mineral restrictions only when indicated |
| CKD Stages 3–5 Without Dialysis | Foods meeting the confirmed protein target; suitable plant foods | Salty foods and phosphate additives; potassium/phosphorus portions depend on labs/orders |
| Hemodialysis | Protein foods in prescribed portions; adequate energy | Salty foods; minerals and fluids as ordered, without carrying over a predialysis low-protein rule |
| Hypertension | Vegetables/fruit when suitable; whole grains; varied lean/plant proteins | Salty seasonings and processed foods; potassium-rich foods/salt substitutes require renal/medication review |
| Dyslipidemia | Fiber-rich foods; fish; unsaturated oils | Large portions high in saturated fat; avoid claiming omega-3 universally lowers LDL |
| Heart Failure | Balanced foods meeting the prescription; lower-sodium choices | Salty foods; fluid only to the actual prescribed limit |
| Postoperative Recovery | Protein foods across meals; adequate energy; tolerated textures | Unnecessary exclusions; surgical route/texture orders take precedence |
| Wound Healing | Protein foods; adequate energy; varied produce | Unnecessary exclusions; no routine high-dose vitamin/zinc advice |
| Compensated Cirrhosis | Regular meals; adequate protein; advised evening snack | Avoid alcohol, raw/undercooked animal foods and unpasteurized milk |
| Decompensated Cirrhosis | Same core cirrhosis pattern, adapted to intake/tolerance | Same food-safety exclusions; sodium/fluid limits only as ordered; no encephalopathy-driven protein ban |
| Custom Plan | Foods appropriate to the confirmed individual prescription | Actual allergies, safety exclusions and prescribed limits |

Keep each column to at most three short examples; use one concise conditional line. Match numeric limits to the saved prescription and distinguish a target from a maximum.

Content corrections required before release:

- No blanket CKD bans on bananas, dairy, beans, nuts or whole grains. Phosphate additives are more readily absorbed; remove the unsourced “100%” claim.
- Prefer lower-sodium preparation/labels. Canned foods and coconut milk are not categorical prohibitions.
- Ampalaya is a vegetable, not a proven diabetes treatment; no medicinal claims or supplement dosing.
- Whole nuts and thick spoonfuls of nut butter can be choking hazards for young children. Use safe textures and respect allergies. Thin plain lugaw is not automatically energy/protein dense; specify suitable fortification if advised.
- No “body can only use 25–40 g protein per meal,” albumin-restoration promise, guaranteed weekly weight change, or unmeasured rice-portion calorie-saving claim.
- Food advice must not invent zero-mg potassium/phosphorus limits from qualitative lab alerts. Unknown/stale labs do not justify a ban.

Sources: [ADA][ada], [NKF][kidney-foods], [AHA 2026][aha], [NIDDK][liver-foods], [CDC choking hazards](https://www.cdc.gov/infant-toddler-nutrition/foods-and-drinks/choking-hazards.html/), [NCCIH diabetes supplements](https://www.nccih.nih.gov/health/diabetes-and-dietary-supplements-what-you-need-to-know), [NIH omega-3](https://ods.od.nih.gov/factsheets/Omega3FattyAcids-HealthProfessional/).

## 12. Generation, editing and character limits

Retain **1,200 characters per field and 2,200 combined** across `education_notes`, `counseling_goals`, `barriers` and `strategies`. Count composed text, separators and interpolated values consistently. Frontend and backend both count Unicode code points, including in the existing field counters.

- Selecting a goal/type prepares a concise suggestion below each relevant Education, Behavioral Goals and Strategies field. Suggestions use the existing Shopping List collapsible/copy pattern and start collapsed. Copy writes only to the clipboard; users can also select text normally. Do not automatically populate or replace fields. Deleting/editing notes leaves the suggestion available for copying again; repeated copies never append or alter field contents.
- Preserve every existing field value. Show Copy success and a selectable-text fallback on clipboard failure. Pasted text follows existing per-field counters and combined save validation; copying does not consume any character budget.
- Leave barriers blank unless actually assessed. Templates must not claim a patient agreed to counseling or followed a plan.
- Keep generated education ≤300, counseling ≤120, strategies ≤300 and combined text ≤700, with at most two sentences per generated field. These are composition budgets, not new hard limits.
- Compose shared advice once, selected-type text once and only relevant conditional notes. All maximum combinations, including maternal/fluid/refeeding/pediatric contexts and the longest valid number, must fit or require RND review.
- Do not silently truncate safety instructions. If edited text leaves insufficient room, show a clear validation/suggestion state and let the RND resolve it before save.
- Notes, food panel, PDF and recommendation endpoint must share reviewed content. Reuse existing services/rules; remove competing hardcoded clinical text. No LLM is required.

Executable coverage in `InterventionGuidanceTest` checks every supported type with all conditional notes against the shorter composition budgets and the existing 1,200 characters per field / 2,200 combined hard limits. JavaScript and PHP both count Unicode code points. Infant education replaces ordinary solid-food advice before six months. Manual edits are preserved; new suggestions require an explicit replacement when text differs. Unchanged oversized legacy notes remain savable; new or edited guidance follows the current limits.

## 13. Show calculations and worked examples

Use saved inputs and the saved calculation version. For a legacy plan without a trace, say details were not recorded; do not reconstruct a precise historical formula from today's measurements.

Show two result groups: **Measurements** and **Final target**. The baseline group explains the formula, inputs, units, weight basis and activity estimate. The final group shows any applicable maternal addition, the selected fixed surplus/deficit and the final prescription. Show the saved age, sex, measurements, weight basis, activity factor/category, energy/protein factors, growth and maternal additions actually used. Keep references beside formulas and selected-goal guidance in Clinical notes. Hide calculation versions and conversion history. Display energy/fluid as whole numbers, measurements/age/grams with at most one decimal and coefficients/activity factors with up to three decimals; omit trailing zeros. Stored inputs retain full precision. If a target is manually overridden, show that fact alongside the calculated value. Notes contribute **zero** to arithmetic. Do not display starting-dose, feeding-stage, daily progression or stepped intermediate targets.

All examples below are fictional arithmetic checks, not recommended patient prescriptions.

| Example | Independently worked result |
|---|---|
| Male, 40 y, 70 kg, 170 cm, activity estimate 1.2; no maternal modifier | REE = 700 + 1,062.5 − 200 + 5 = **1,567.5**; estimated maintenance = **1,881 kcal/day** |
| Same inputs, Add Deficit 300 | **1,881 + 0 − 300 = 1,581 kcal/day**; protein 1.0 × 70 = 70 g; 25% fat rounds to 44 g; residual carbohydrate rounds to 226 g. Rounded macro energy is 1,580 kcal |
| Same inputs, Add Surplus 300 | **1,881 + 0 + 300 = 2,181 kcal/day** |
| Female, 40 y, 70 kg, 170 cm, activity estimate 1.2 | REE = 700 + 1,062.5 − 200 − 161 = **1,401.5**; unrounded estimated maintenance = 1,681.8, displayed as **1,682 kcal/day** |
| Stable CKD, confirmed 60-kg calculation bases | Energy 30 × 60 = **1,800**; G3–G5 protein 0.8 × 60 = **48 g**; hemodialysis 1.2 × 60 = **72 g** when each protocol applies |
| Cirrhosis, confirmed 60-kg bases and local 32.5 coefficient | Energy 32.5 × 60 = **1,950**. Protein 1.2 × 60 = **72 g** for the specified non-malnourished compensated context; 1.5 × 60 = **90 g** with confirmed malnutrition/sarcopenia, regardless of stage. Decompensation alone does not select 90 g |
| Pediatric male, 5 y, 20 kg | Published rounded Schofield REE 22.7 × 20 + 504 = **958 kcal/day**; this is not a total calorie-intake prescription |
| Pediatric female, 5 y, 20 kg | Published rounded Schofield REE 20.3 × 20 + 486 = **892 kcal/day**, with the same scope limitation |
| Pediatric daily maintenance arithmetic, 25 kg | 1,000 + 500 + 20 × 5 = **1,600 mL/day**, before clinical assessment/order |

If a manual final prescription differs, identify the override explicitly: e.g. **calculated 1,581; prescribed 1,650 kcal/day**. Do not generate an override from a refeeding note or use a starting-intake formula as the final-target formula.

## 14. Source currency, legacy boundary and implementation verification

Live QA identified old seeded text still containing obsolete numeric targets and feeding ramps after prescription conversion. The follow-up forward-only repair matches the exact six pre-review PatientSeeder template fingerprints before replacing a field with reviewed guidance. Modified/manual text and barriers are preserved; prescribed amounts and dates do not change. Verified encrypted originals precede mutation. Active linked reports refresh through normal preparation; archived report bytes remain frozen. This repair is separate from the routine suggestion/copy UI, which never writes to a field.

Clinical report formatting uses the existing Monitoring Plan micronutrient-unit definitions when a saved unit is absent or blank. Explicitly entered units and prescribed values are preserved. The same shared prescription partial serves the Intervention Plan and NCP Summary; normal preview preparation refreshes active report bytes.

Version 2 is implemented in the shared PHP calculation path; local and deployed verification are separate. See the [clinical maintenance map](../developers/ncp/README.md) for current owners and regression tests. The retired implementation plan remains in Git history.

- Keep original Mifflin attribution; correct its input use. Age alone does not make a historical equation obsolete.
- PDRI2015 summary revised2018 remains the verified published local reference used here. A workshop or current PDF copyright is not proof of new numeric tables.
- Use the actual **ADA2026**, **AHA2026 dietary guidance**, **ESPEN surgery2025**, and **WHO2023 wasting** documents for their populations. ESPEN liver2020 is a practical presentation of the2019 scientific guideline, not evidence all2019 recommendations became invalid.
- KDOQI2020 and KDIGO2024 have different scopes/recommendations; retain both transparently.
- Existing Hamwi bias claims, the30-kg floor rationale, exact activity-factor attribution, pediatric full-TEE chain and some old diagnostic thresholds were not established by the reviewed sources. Do not reproduce them as verified.
- Preserve version-1 numerical fixtures as legacy behavior. Add independently worked version-2 cases; passing PHP/TypeScript mirror tests proves agreement, not medical validity.
- Verify precise age/date, both sexes, weight basis, both diabetes types at opposite weight extremes, grouped CKD with actual diagnosis, dialysis fluid orders, both cirrhosis stages with/without muscle loss, coexisting conditions, maternal cases, notes/foods and manual overrides.
- Verify numerical invariance: the same confirmed inputs, coefficients, maternal context, fixed energy adjustment and fluid order must produce identical energy/protein/carbohydrate/fat/fluid values with or without refeeding or gradual-change notes. Test malnutrition context explicitly; no low starting-dose value may replace the final endpoint.
- The general pediatric EER path is automated; disease-specific treatment and specialist protocols remain clinician-entered. Passing arithmetic tests does not certify individual clinical suitability.
- Historical prescriptions and reports remain immutable. Current application behavior, release status and deployed clinical-rule rows require separate verification.

### Sources used for the revised contract

[mifflin]: https://pubmed.ncbi.nlm.nih.gov/2305711/?format=pubmed
[pdri]: https://www.fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf
[kdigo]: https://kdigo.org/wp-content/uploads/2024/03/KDIGO-2024-CKD-Guideline.pdf
[kdoqi]: https://www.andeal.org/topic.cfm?cat=5558&menu=5279
[ada]: https://doi.org/10.2337/dc26-S005
[aha]: https://professional.heart.org/en/guidelines-statements/2026-dietary-guidance-to-improve-cardiovascular-health-a-scientific-statementcir0000000000001435
[hf]: https://www.heart.org/en/health-topics/heart-failure/treatment-options-for-heart-failure/lifestyle-changes-for-heart-failure
[liver]: https://academy.espen.org/files/ESPEN-Guidelines/ESPEN_practical_guideline_Clinical_nutrition_in_liver_disease.pdf
[hospital]: https://www.espen.org/files/ESPEN-Guidelines/ESPEN_guideline_on_hospital_nutrition.pdf
[surgery]: https://www.espen.org/files/ESPEN-guideline-on-clinical-nutrition-in-surgery-Update-2025.pdf
[whs]: https://doi.org/10.1111/WRR.13130
[nice]: https://www.nice.org.uk/guidance/cg32/chapter/recommendations
[pediatric]: https://www.espen.org/files/ESPEN-Guidelines/Pediatrics/ESPGHAN_ESPEN_ESPR-guidelines-on-pediatric-parenteral-nutrition-Energy.pdf
[kidney-foods]: https://www.kidney.org/news-stories/6-kidney-disease-diet-myths
[liver-foods]: https://www.niddk.nih.gov/health-information/liver-disease/cirrhosis/eating-diet-nutrition

Source access: full official PDFs were checked for PDRI, KDIGO, ESPEN liver/hospital/pediatric/surgery; WHS publisher text and KDOQI publisher-indexed recommendation text were checked. Some direct publisher, FDA and legacy URLs were blocked/unavailable. Such access limits do not prove a source false and do not support unverified claims. The exact applicable recommendations, rather than generic source homepages, govern the statements above.

Follow-up access on 25 October 2026: KDIGO 2024, ESPEN hospital/liver/surgery PDFs, NASEM 2023 Table 5-15, the Academy's official 2020 CKD macronutrient recommendations and official NNC PDRI-based tables were accessible. The KDOQI journal URL returned 403; [the original publication record](https://pubmed.ncbi.nlm.nih.gov/32829751/) identifies DOI `10.1053/j.ajkd.2020.05.006`. The source link above now points to the Academy's accessible recommendation text. FNRI's original summary PDF returned 503. [NNC's official tables, p. 5](https://www.nnc.gov.ph/phocadownloadpap/FINAL_2017%20NM%20Talking%20Points.pdf) independently confirm the macro ranges and maternal energy additions; the maternal +27 g protein addition was not independently reverified in this follow-up. [NASEM 2023 Table 5-15, pp. 99–100](https://www.nationalacademies.org/read/26818/chapter/7) matches the implemented pediatric EER coefficients and growth additions. Prior source-access claims above describe the earlier review, not successful retrieval of every source on this date.

---

## 15. Meal-Plan Composition Research and Runtime Boundary

This section records the source review performed for the meal-plan generator and seeded clinical
templates on 2026-09-28. It controls composition and candidate selection only. The energy,
protein, carbohydrate, fat, fluid, and micronutrient prescription remains controlled by the
goal-specific sections above and the RND-confirmed patient prescription.

**Authority order for meal planning:** RND-confirmed individualized prescription → applicable
goal-specific clinical authority in §§5–12 → PDRI quantitative references → FNRI Food Exchange
Lists and Nutritional Guidelines for Filipino meal planning → Pinggang Pinoy as a visual
composition cross-check only. Pinggang Pinoy is not the nutrient-calculation engine and must not
override an individualized prescription.

### 15.1 Source record

| Source reviewed | Exact location reviewed | Finding used | Runtime/template decision |
|---|---|---|---|
| Academy of Nutrition and Dietetics, *Nutrition Care Process Model* and *What Is Medical Nutrition Therapy?* | NCP Model sections on the individual/RDN relationship and evidence-based practice; MNT sections “What Is Medical Nutrition Therapy?” and “What to Expect at Your Appointment,” reviewed March 3, 2026 | Nutrition intervention follows assessment and diagnosis; MNT is a personalized, culturally appropriate plan based on health, history, nutrition status, activity, lifestyle, and goals. | NutriScope never treats a generic plate as an individual prescription. The saved RND prescription, allergies, intervention goal/stage, maternal status, and current assessment remain authoritative; templates and auto-generation are reviewable starting points. |
| Academy of Nutrition and Dietetics, *Nutrition and Physical Activity Interventions for Adults in the General Population* | Current Academy position page, intervention-plan and individualized counseling/coaching discussion | General-population guidance supports evidence-based nutrition interventions while distinguishing population programming from individualized care. | Academy material is used for the care-process/personalization boundary, not for Philippines-specific numeric constants or a fixed U.S. meal pattern. |
| DOST-FNRI, *Philippine Dietary Reference Intakes 2015: Summary Tables*, revised September 2018 | Summary Tables pp. 1–8: energy, AMDR, protein, vitamins, minerals, electrolytes, water, fiber, free sugars, and maternal additions by age/sex/life stage | PDRI is the Philippine quantitative reference for healthy population groups. For adults, the summary gives age/sex reference energy and AMDR of 10–15% protein, 15–30% fat, and 55–75% carbohydrate; requirements differ by life stage rather than forming one universal “normal adult” value. | PDRI supplies baseline nutrient boundaries and maternal modifiers already documented in §§2 and 4. The generator does not hard-code one general calorie target: it scores foods against the saved patient prescription, which accounts for the patient and selected goal. |
| National Nutrition Council Governing Board Resolution No. 6, Series of 2012, and the *2012 Nutritional Guidelines for Filipinos* | Resolution adopting the guideline; ten public-health messages summarized by NNC | General guidance calls for food variety, fish/lean meat/poultry/egg/beans/nuts, more vegetables and fruits, calcium-rich foods, and limits on salty, fried, fatty, and sugar-rich foods. | The `custom` base varies culturally familiar foods and favors lean protein, vegetables, fruit, and less-refined carbohydrate choices. It does not turn public-health advice into unsupported disease-specific limits. |
| DOST-FNRI, *Pinggang Pinoy for Different Population Groups* | Web section describing the per-meal plate proportions | A general Filipino visual plate uses approximately one-half fruits/vegetables (`Glow`), one-sixth protein foods (`Grow`), and one-third rice, corn, bread, oatmeal, or root crops (`Go`). | Used only as a composition cross-check: `custom` offers a main dish, separately selectable carbohydrate, and vegetable side when the available foods and prescription permit. Quantities come from nutrient snapshots and the saved prescription, not fixed plate geometry. |
| DOST-FNRI, *Food Exchange Lists for Filipinos*, 4th edition (2019), as identified by the official MARFEL FAQ and HELP Online | MARFEL FAQ sections “What is the basis of the food measurements?” and “What is a Food Exchange Lists for Filipinos?”; HELP Online Food Exchange Lists tool description | The Food Exchange Lists are FNRI's portion/exchange tool for meal planning; MARFEL measurements use the 4th edition, 2019. | NutriScope continues to calculate from stored food/recipe nutrient snapshots and verified quantities. Food-exchange or household measures are shown only when an existing verified conversion is available; the system must not invent a measure or silently substitute an unavailable exchange dataset. |
| DOST-FNRI Policy Statement 13, *Pinggang Pinoy: A Healthy Food Plate for Filipino Adults* | PDF p. 2, scope and plate-composition paragraphs | Pinggang Pinoy is intended for healthy Filipinos and is explicitly not a therapeutic plan for conditions such as hypertension or diabetes. Its visual rule is one-half `Go` + `Grow` (with `Go` larger) and one-half `Glow` (with vegetables larger than fruit). | The general plate is not applied blindly to clinical goals. Renal, diabetes, cardiac, weight, high-protein, liver, and malnutrition templates retain the disease-specific limits and authorities documented in §§5–12. A carbohydrate may be reduced or omitted when it worsens fit to the prescribed target. |
| DOST-FNRI, *2019 4th Quarter FNRI Updates* | PDF pp. 5–6, maternal-needs discussion and pregnant/lactating Pinggang Pinoy examples | Pregnant/lactating examples use varied `Go` foods (rice, bread, noodles, root crops), `Grow` foods (fish, lean meat, egg, tofu), vegetables/fruits, and water; the publication also notes increased maternal nutrient needs. | Confirmed pregnancy/lactation changes both the final prescription through the PDRI modifiers in §2 and the generated food composition through the balanced-component path. Maternal templates include culturally familiar `Go`, `Grow`, `Glow`, fruit, and planned snack choices. Goal-specific restrictions still take precedence. |
| DOST-FNRI 43rd Seminar Series, *Development of 2017 Modified Cycle Menus for Pregnant and Lactating Women* | Abstract p. 1, methods and conclusion | FNRI developed and validated a four-week cycle menu for pregnant/lactating women using Pinggang Pinoy, sensory evaluation, and field pretesting. | Reusable pregnancy and lactation templates are appropriate, but remain starting patterns. Loading one under a non-`custom` clinical goal displays a review warning before scaling because a maternal example cannot replace a disease-specific prescription. |
| National Nutrition Council/Department of Health, *Healthy Eating Guide for Mommies* (March 15, 2022 infographic/article) | Article paragraphs on maternal additions, variety, food groups, fatty fish, milk/calcium foods, animal protein/iron, vegetables/fruits, leafy vegetables/folate, water, and medical-condition boundary | Healthy pregnant/lactating people should use varied carbohydrate foods, whole grains, fish/lean meat/egg/beans, calcium-rich foods, animal protein, vegetables, fruit, and leafy vegetables. The article explicitly directs those with diabetes, hypertension, allergies, or other conditions to individualized physician/RND advice. | The seed graph contains separate `Pregnant` and `Lactating` examples rather than one ambiguous maternal template. Both are food-pattern examples only and must be scaled/reviewed against the RND-confirmed trimester/status prescription and any clinical goal. |
| National Nutrition Council/Department of Health, *Healthy Pregnancy* and *Nutrition Tips for Breastfeeding Mothers* | Pregnancy daily-pattern paragraph; breastfeeding meal-plan checklist and additional-tips sections | Pregnancy guidance specifies daily variety across carbohydrate foods, protein foods, vegetables, fruit, and milk. The breastfeeding checklist includes vegetables, fruit, at least two milk servings, varied carbohydrate exchanges, and beans/lean meat/fish/poultry/tofu choices. | The pregnancy seed includes daily milk and fruit; the lactation seed includes two daily milk items and fruit. Both provide vegetables at lunch/dinner, varied Filipino protein dishes, and separate carbohydrate items. Quantities remain editable and prescription-scaled. |
| U.S. HHS/USDA, *Dietary Guidelines for Americans, 2025–2030*, 10th edition | Current-guidelines overview, January 2026 | The current U.S. guideline emphasizes nutrient-dense protein foods, vegetables, fruits, dairy, healthy fats, and whole grains while reducing highly processed foods, refined carbohydrates, added sugars, excess sodium, and unhealthy fats. | Used only as a current cross-check for the balanced-food direction. It adds no Filipino clinical constant and does not override PDRI, DOST-FNRI, or the disease-specific authorities in this document. |

**Reviewed sources:**

- DOST-FNRI, [Pinggang Pinoy for Different Population Groups](https://www.fnri.dost.gov.ph/index.php/programs-and-projects/news-and-announcement/183-fnri-dost-launches-pinggang-pinoy-for-different-population-groups)
- Academy of Nutrition and Dietetics, [Nutrition Care Process Model](https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-care-process-model)
- Academy of Nutrition and Dietetics, [What Is Medical Nutrition Therapy?](https://www.eatright.org/health/wellness/healthful-habits/what-is-medical-nutrition-therapy)
- Academy of Nutrition and Dietetics, [Nutrition and Physical Activity Interventions for Adults in the General Population](https://www.eatrightpro.org/practice/guidelines-and-positions/academy-positions/nutrition-and-physical-activity-interventions-for-adults-in-the-general-population)
- DOST-FNRI, [Philippine Dietary Reference Intakes 2015: Summary Tables, revised September 2018](https://fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf)
- National Nutrition Council, [Governing Board resolutions adopting the 2012 Nutritional Guidelines and 2015 PDRI](https://nnc.gov.ph/resources/policies-guidelines-and-publications/governing-board-resolutions/)
- National Nutrition Council, [2012 Nutritional Guidelines for Filipinos summary](https://nnc.gov.ph/luzon-region/revised-nutritional-guidelines-for-pinoys-presented-in-car/)
- DOST-FNRI, [MARFEL Food Exchange List FAQ](https://marfel.fnri.dost.gov.ph/marfel-faqs)
- DOST-FNRI, [HELP Online meal-planning tools](https://helponline.fnri.dost.gov.ph/)
- DOST-FNRI, [Policy Statement 13 — Pinggang Pinoy](https://www.fnri.dost.gov.ph/images/sources/PolicyStatement/PS13.pdf)
- DOST-FNRI, [2019 4th Quarter FNRI Updates](https://www.fnri.dost.gov.ph/images/sources/media/2019/4thQtr.pdf)
- DOST-FNRI, [Development of 2017 Modified Cycle Menus for Pregnant and Lactating Women](https://www.fnri.dost.gov.ph/images/sources/SeminarSeries/43rd/Develoment-of-2017-MGC.pdf)
- National Nutrition Council/Department of Health, [Healthy Eating Guide for Mommies](https://nnc.gov.ph/luzon-region/healthy-eating-guide-for-mommies/)
- National Nutrition Council/Department of Health, [Healthy Pregnancy](https://nnc.gov.ph/luzon-region/healthy-pregnancy/)
- National Nutrition Council/Department of Health, [Nutrition Tips for Breastfeeding Mothers](https://nnc.gov.ph/mindanao-region/nutrition-tips-for-breastfeeding-mothers/)
- U.S. HHS/USDA, [Current Dietary Guidelines, 2025–2030](https://odphp.health.gov/our-work/nutrition-physical-activity/dietary-guidelines/current-dietary-guidelines)

### 15.2 Generator and template contract

- Rice and other carbohydrate foods are direct meal-plan items, not wrapper recipes. Their quantity
  therefore scales independently from the main dish.
- A recipe may contain rice only when rice is structurally defining, such as arroz caldo,
  champorado, lugaw, or another true rice-based dish. A complete meal with intrinsic carbohydrate
  receives no automatic carbohydrate side.
- `Use rice as carb` limits automatic side-carbohydrate candidates to the pinned rice foods. When
  off, rice is excluded from that candidate pool and the generator chooses among suitable non-rice
  foods. Candidate selection remains allergy-filtered, prescription-scored, and varied within the
  best-fitting choices.
- The generator adds a side carbohydrate only when it improves prescription fit. This supports
  smaller or absent carbohydrate sides when required by the RND-confirmed goal and targets.
- The general `custom` pattern and confirmed maternal generation may add a vegetable side when the
  selected main dish does not already contain vegetables. Raw aromatics such as garlic are not
  standalone snacks; category fallback permits fruit only, while a vegetable must be explicitly
  marked ready-to-eat to enter the snack pool.
- Patient PDFs keep the existing meal schedule presentation, omit snack rows with no items, and
  show portion details in compact three-column rows. A repeated dish or food uses one detail card;
  distinct saved amounts remain separately labelled and linked from their menu cells. Carbohydrates
  remain ordinary meal items and are not given a new patient-facing `carb` label.
- Seeded templates are editable starting points, never automatic diagnoses or substitutes for RND
  review. The template compatibility response checks intervention goal, disease stage, and maternal
  status; scaling uses the final patient prescription. Each general, goal, pregnancy, and lactation
  pattern has a with-snacks and no-snacks variant. The no-snacks maternal variants fold the same
  required milk and fruit components into meals instead of discarding them. Liver disease remains
  the safety exception: both seeded variants preserve frequent intake. The maternal patterns are
  derived from the official NNC/DOH food-group and serving guidance above and are not represented
  as an exact government therapeutic diet.

### 15.3 Dated-plan and print boundary

- Intervention history is a newest-first, server-paginated collection of complete dated plans, not
  revisions of one mutable row. Prior plans are read-only and have no current/inactive status.
- A new plan prefills clinical fields from the newest saved plan. Authoritative calculation inputs
  come from the newest complete Monitoring snapshot, with the original Assessment as fallback.
- Monitoring stores calculation inputs and visit outcomes only. It never creates an Intervention
  Plan or menu. A treatment change is recorded as a separate plan from Intervention.
- Each saved plan may own zero or one menu. Creating a new plan does not copy the previous menu.
- Education, counseling goals, barriers, and strategies are limited to 1,200 characters each and
  2,200 characters combined. This is a print-legibility/product constraint, not a clinical target;
  no text is silently truncated in the report.
- Each saved plan with a menu has one dated Nutrition Intervention Plan report. NCP Summary uses
  only the newest plan. All report types use 8.5 × 13-inch long bond paper with their defined
  portrait or landscape orientation. The plan PDF prints the weekly menu before concise guidance,
  omits empty snack rows, and flows deduplicated portion cards in three columns.
- The Intervention meal-plan fluid calculation displays required fluid, approximate fluid from
  saved planned foods, and the nonnegative remaining amount for drinking. Food-water values remain
  informational and never enter recipe generation, scaling, variance, or target-match success.
- The food-service Menu Calendar PDF contains the shared hospital letterhead, saved weekly menu,
  and configured signatories only. It omits population and cost fields and removes an AM or PM
  snack row when that snack has no saved item anywhere in the selected cycle.

No new clinical source was introduced for this workflow boundary. Meal composition follows the confirmed saved prescription and the sources in §15.1. The revised version-2 calculation contract in §§1–14 is implemented; version-1 regression data and historical values remain separate.

### 15.4 Census and PES presentation boundary

- The cycle-owned Nutrition care category remains structured Assessment context and stays
  separate from physician diagnosis, PES diagnosis, and intervention goal. Current Demographic
  Census year/month screens count a new ADIME cycle in its start month after its first saved
  Assessment and a finished visit with Assessment work; assessed legacy cycles without visit
  records remain included. They render a compact age/sex
  matrix with sex as rows, every nutritional status including zero, **By Risk Level**, and sparse
  aggregate **By nutrition care category** totals; they omit ward. Completed month snapshots stay
  frozen, while the current month remains live. The selected view downloads through a dedicated
  server-rendered PDF without a browser print dialog, page URL footer, or filed report identity.
  Legacy Census PDFs require targeted cleanup.
- PES rule cards remain locally source-gated. The provider receives compact de-identified evidence
  and source-constrained candidates, but the review card displays only the Assessment evidence used.
  Source provenance remains in the server-side rule contract and code comments rather than adding
  report-like citation clutter to the clinical review UI.

No new clinical or nutrition source was introduced for these presentation decisions.

---

## 16. Changelog

Entries before 21 October 2026 describe historical decisions, not endorsement of superseded clinical claims.

| Date | Change |
|---|---|
| 2026-10-22 | **Approved refinement.** Energy direction and kcal amount are the only added routine prescription controls; existing final fields handle other overrides. Read-only suggestions sit in neutral bordered collapsible sections below their related fields; patient suggestions contain no calorie homework and never write to fields automatically. One-time conversion recalculates plans without snapshots from their recorded measurement source, preserving IDs/dates and all existing note fields. Missing evidence uses Custom Plan without inferred diagnoses/doses. Encrypted originals are verified on persistent private storage before mutation; existing menu quantities are rescaled. Active linked report caches expire for normal reprepare; archived bytes stay frozen. No reseeding or conversion/version UI. |
| 2026-10-21 | **Clinical/source audit and approved seven-goal design.** Replaced disputed calculation guidance with explicit source scope, proposed local defaults, independent energy/fluid controls, pediatric limitations, concise per-type notes and conditional food advice. Added saved calculation/provenance requirements. Version 2 implementation now uses the shared PHP calculator, persisted inputs and independent controls. Version-1 fixtures remain frozen; the later user-approved one-time conversion supersedes the initial no-conversion decision. |
| 2026-10-03 | **Census download, fluid display, and menu-calendar boundary reconciled.** Census adds all nutritional-status totals and a direct identity-free selected-view PDF; fluid displays required, food-provided, and remaining drinking amounts without affecting meal generation; Menu Calendar retains only the letterhead, saved weekly menu, applicable snack rows, and signatories. No new clinical or nutrition source was introduced. |
| 2026-10-02 | **Census and PES presentation override reconciled.** Census year/month summaries show age/sex, risk, and aggregate Nutrition care category totals. The category remains separate from confirmed medical diagnosis. PES cards show Assessment evidence but not source labels; server-side source gating remains authoritative. No new clinical or nutrition source was introduced. |
| 2026-09-29 | **Dated-plan workflow and print boundary reconciled.** Complete immutable Intervention Plans replace active revision consumers; Monitoring remains visit data only; each plan owns zero or one menu; reports use newest-plan or selected-plan semantics. Added the 1,200-per-field/2,200-combined guidance limit and long-bond menu-first print contract. No new clinical source was introduced. |
| 2026-09-28 | **Deployed QA follow-up reconciled.** Repeated foods now share one compact portion card with separately referenced saved amounts. This changes presentation only; no new nutrition calculation or external clinical source was introduced. Monitoring target-status, progressive help, and food-alert disclosure were aligned with the already approved clinical/runtime contract. |
| 2026-09-28 | **Meal-plan composition research and runtime boundary documented.** Added Academy, PDRI, FNRI Food Exchange List, Nutritional Guidelines, Pinggang Pinoy, maternal-menu, and current U.S. guideline review locations. The hierarchy makes the individualized prescription authoritative and limits Pinggang Pinoy to a visual cross-check; direct carbohydrates, rice preference, intrinsic-carbohydrate, balanced custom, maternal composition, snack safety, and goal-specific review rules are explicit. |
| 2026-09-21 | **Maternal and fluid runtime contract locked.** TEE remains BMR × PAL; trimester-specific energy and +27 g/day protein modifiers use PDRI 2015 Summary Tables 1–2 (rev. Sept 2018); fluid is guidance outside meal-plan scaling; goal stages remain progressively disclosed. |
| 2026-09-20 | **Clinical/reporting/privacy boundary documented.** Diagnosis categories remain separate from nutrition diagnoses and intervention goals; pregnancy/lactation remains a PDRI modifier; oncology requires individualized goal selection. Required calculation inputs and privacy limitations are explicit. Runtime pregnancy values and TEE inputs were reconciled with current code. |
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

*This reference records the revised clinical design and its limits. The shared PHP version-2 service is the current runtime authority; `prescription-targets.json` version 1 records legacy behavior only. The explicitly approved one-time conversion updates old plans from their recorded measurement source; ordinary edits and birthdays never recalculate historical plans.*
