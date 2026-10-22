# Intervention goals: fact-check and simplification plan

**Reviewed:** 21 October 2026, Asia/Manila.
**Status:** User-approved version 2 and subsequent UI refinements implemented locally. The user subsequently approved converting old goal/type selections and recalculating their prescribed amounts from their recorded measurements. Release and live acceptance are verified separately. The companion [clinical reference](docs/logic/intervention-goals.md) records the revised design, verified source ranges, local defaults and exact notes/food copy. `prescription-targets.json` remains version-1 legacy behavior. Older references `intervention-goals-0.md` and `intervention-goals-asia-pacific-research.md` are historical, not authorities for overriding this review.

**Requested outcome:** Keep common diet types; separate weight direction from diagnosis; correct source mismatches; support adults and children honestly; generate brief, editable guidance within existing limits; correct food recommendations; show energy adjustments in Show calculations. Gradual feeding changes, including refeeding, belong in notes and clinician review, not a new progression calculator.

## 1. Original intervention goals and stages

These were the frontend options and backend allowlist before version 2, retained here to explain the change. Together they contain **9 goals and 30 stage choices**.

| Original goal | Current stage labels and stored values | Actual adult energy calculation, before maternal additions |
|---|---|---|
| Renal Diet | Stage 1 `stage_1`; Stage 2 `stage_2`; Stage 3 `stage_3`; Stage 4 `stage_4`; Stage 5 pre-dialysis `stage_5_predialysis`; Hemodialysis `hemodialysis`; Peritoneal dialysis `peritoneal` | `30 × working weight` for every stage |
| Diabetic Control | Normal weight, T1/T2DM `stage_1`; T2DM + overweight `stage_2`; coexisting CKD `stage_3` | Stages 1/3: estimated TEE. Stage 2: `max(TEE − 500, sex-specific floor)` |
| Cardiac Diet | Mild `mild`; Moderate `moderate`; Severe `severe` | Estimated TEE for all three; stages change other targets |
| Weight Loss | Overweight, BMI 23–24.9 `overweight`; class I, BMI 25–29.9 `class_1`; class II, BMI 30–34.9 `class_2`; severe class II, BMI ≥35 `class_3` | `max(TEE − deficit, floor)`; respective deficits 375/500/625/875 kcal |
| Weight Gain | Mild, 85–90% IBW `mild`; Moderate, 70–84% IBW `moderate`; Severe, <70% IBW `severe` | Mild: TEE +400; moderate: TEE +625; severe: `32.5 × working weight` |
| High Protein | Mild stress `mild_stress`; Moderate stress `moderate_stress`; Severe stress `severe_stress`; Burns >20% BSA `burns` | `27.5 × working weight`; burns use 32.5 |
| Liver Disease | Compensated `compensated`; Decompensated `decompensated`; Encephalopathy grades I–II `encephalopathy_grade_1_2`; grades III–IV `encephalopathy_grade_3_4` | `37.5 × working weight` for all four |
| Malnutrition | Moderate, risk score 2–3 `moderate`; Severe, risk score >3 `severe` | `32.5 × working weight` for both; severe lowers protein to 1.0 g/kg IBW |
| Custom Plan | None | Estimated TEE; all targets editable |

Definitions in the original version-1 code:

- `TEE = Mifflin resting energy estimate × activity factor`.
- `working weight = adjusted weight` above 120% Hamwi IBW; otherwise measured/dry weight. Adjusted weight is `IBW + 0.25 × (actual − IBW)`.
- The same working-weight substitution is made inside Mifflin. All adult protein calculations use Hamwi IBW.
- **Every pediatric goal and stage previously returned the same Schofield × activity + growth baseline**, age-based protein, 30% fat, and Holliday–Segar fluid. Pediatric disease tables in the reference are not implemented.

The diabetes problem is a label/formula coupling, not an actual BMI permission check: `GoalSelectorModal` has no patient BMI input. A valid `stage_2` selection subtracts 500 kcal even for an underweight patient. Type 1/2, body size, kidney disease, and calorie direction are different facts and should not be represented as one numbered stage.

## 2. Approved goals and common types

The final menu retains **7 goals and 12 type choices**. Use the following visible options. “Type” replaces the misleading assumption that every option is a disease severity stage.

| Goal option | Type options |
|---|---|
| **General Diet** | None |
| **Diabetic Control** | Type 1 Diabetes; Type 2 Diabetes |
| **Renal Diet** | CKD Stages 1–2; CKD Stages 3–5 Without Dialysis; Hemodialysis |
| **Cardiac Diet** | Hypertension; Dyslipidemia; Heart Failure |
| **High Protein** | Postoperative Recovery; Wound Healing |
| **Liver Disease Diet** | Compensated Cirrhosis; Decompensated Cirrhosis |
| **Custom Plan** | None |

General Diet remains a routine calculated option; Custom Plan remains available for clinician-entered targets and specialist contexts. **Malnutrition leaves the goal menu**, while its diagnosis/severity and relevant monitoring remain in assessment and notes. A surplus may be appropriate but does not replace assessment of protein, intake, muscle loss or refeeding risk.

Cardiac conditions and recovery indications can coexist. The selected primary type guides notes; preserve other confirmed conditions and their orders. Grouped CKD types retain the actual confirmed G stage in assessment. Neither diabetes type is limited by weight category. The two cirrhosis stages remain selectable; encephalopathy grades and specialist conditions stay in clinical context or Custom Plan.

The exact stored values and clinical boundaries are in [the revised reference, §1](docs/logic/intervention-goals.md#1-approved-goal-options). Reuse the existing goal/type machinery rather than creating a second classification system.

### 2.1 Old-to-new mapping

This mapping applies to reviewed **new drafts**. Do not bulk-relabel or recalculate historical plans.

| Original selection | New draft selection | Required interpretation |
|---|---|---|
| Diabetes stages 1/2/3 | Diabetic Control → confirm Type 1 Diabetes or Type 2 Diabetes | Old stages cannot establish diabetes type. Preserve CKD separately. No automatic deficit |
| Renal stages 1/2 | Renal Diet → CKD Stages 1–2 | Preserve actual confirmed stage; no automatic broad mineral restriction |
| Renal stages 3/4/5 without dialysis | Renal Diet → CKD Stages 3–5 Without Dialysis | Preserve stage, stability and individualized protein assessment |
| Hemodialysis | Renal Diet → Hemodialysis | Confirm treatment, usable weight and actual fluid order |
| Peritoneal dialysis | Custom Plan, with treatment documented | Individualize dialysate-derived energy and protein; retain historical option labels |
| Cardiac mild/moderate/severe | Cardiac Diet → confirm Hypertension, Dyslipidemia or Heart Failure | Old severity cannot establish the condition. Preserve all sodium/fluid orders |
| Weight Loss, all stages | General Diet or appropriate disease goal + Add Deficit when clinically indicated | Preserve/review the old final target; do not apply a second deficit |
| Weight Gain, all stages | General Diet or appropriate disease goal + Add Surplus when clinically indicated | Severe used a kcal/kg endpoint; do not invent a previous surplus amount |
| High Protein, mild/moderate stress | High Protein → confirm Postoperative Recovery or Wound Healing | Do not infer the indication from stress severity |
| High Protein, severe stress/burns | High Protein only if a routine indication is confirmed; otherwise Custom Plan | No routine burn/ICU dose implied |
| Liver, compensated/decompensated | Liver Disease Diet → corresponding confirmed cirrhosis stage | Protein depends on nutritional status, not stage alone. Confirm weight with fluid retention |
| Liver, encephalopathy grades | Liver Disease Diet → confirm cirrhosis stage, or Custom Plan | Preserve encephalopathy clinically; remove grade-driven protein restriction |
| Malnutrition, both stages | General Diet or another clinically appropriate goal; separate energy adjustment if needed | Preserve diagnosis and severity; individualize protein and monitoring. Surplus alone is not complete care |
| Custom | Custom Plan | Preserve manual values; do not infer an existing general plan's intent |

Do not infer weight intention, diabetes type, nutritional status or prescribed restriction from an obsolete label. Require review when copying a legacy plan into a new draft.

### 2.2 Energy adjustment, independent of diabetes type or weight category

Add one dropdown and one conditional numeric field:

| Field | Values / behavior |
|---|---|
| Energy adjustment | No Adjustment; Add Surplus; Add Deficit |
| Adjustment amount | Positive whole-number kcal/day; required for surplus/deficit; hidden and stored as zero for no adjustment |

- Default to no adjustment. Do not choose loss/gain merely from BMI, diabetes type, or the goal label.
- Remove all old hidden stage-based surpluses and deficits when using the new calculation version. Apply the chosen adjustment **once**.
- Preserve the existing editable final energy target. A direct edit becomes a manual override; do not treat it as another baseline and apply the adjustment again.
- Use no percentage mode or extra severity dropdown. A 500-kcal deficit can be a clinician-selected value, not a universal default.
- An estimated-maintenance baseline plus an offset expresses intended energy balance. A disease-specific kcal/kg baseline is already a clinical target: adding 300 to it means **300 above that target**, not proof of a physiological surplus. Label this distinction in the calculation view.
- No adjustment means use the calculated target; it does not prove weight will remain stable. Review measured intake, weight trend, hydration, activity, and treatment response. Adult energy intake does not maintain a person's height. Children normally need energy for growth. [NASEM energy applications][s2]

**Examples of permitted combinations:** Type 1 + surplus for restoration; Type 1 + deficit when clinically appropriate; Type 2 + surplus for undernutrition; Type 2 + no adjustment; Type 2 + deficit when appropriate. Neither type determines the amount. Pediatric and maternal adjustments require their own reviewed clinical context, not adult weight-loss presets.

### 2.3 Fluid restriction remains available

**Do not remove fluid restriction.** The current catalog already has no standalone `fluid_restriction` goal; fluid targets are embedded in renal/cardiac calculations and the editable `fluid_ml` field. The proposal retains fluid management and makes its meaning explicit.

| Fluid control | Behavior |
|---|---|
| Estimated Fluid Needs | Show the applicable baseline estimate, clearly labeled as guidance rather than a prescribed restriction |
| Prescribed Fluid Limit | Enter the clinician-confirmed daily limit in mL/day; save and display it as a **maximum** |

Make this control available for every goal, including Renal Diet, Cardiac Diet, and Liver Disease. Store the mode and confirmed amount with the plan; preserve historical limits. A saved restriction overrides baseline guidance, and a goal or energy change must not clear it silently. With `nutrient_operators`, a restriction is `fluid_ml: max`, not a minimum intake target.

Do not autofill 750 mL as a completed dialysis order or automatically assign 1,500/2,000 mL from a vague cardiac tier. Use the actual clinical order. Heart failure may require individualized fluid restriction; not every person with heart failure needs the same limit. [AHA heart-failure guidance][s35]

Show “Fluid limit: ≤ prescribed amount mL/day” in the intervention and report, with concise instructions to count relevant drinks, soups and ice. A fluid limit is a numeric prescription, not merely a free-text note. Refeeding progression remains note-only as requested. Fluid guidance remains outside meal-generation success matching because foods alone do not establish total fluid intake.

## 3. Fact-check: calculations and source fidelity

**Verdict:** Some equations are arithmetically recognizable, but the complete system cannot currently be described as accurate for both adult and pediatric therapeutic prescriptions. Tests matching the repository's own constants establish consistency, not clinical validity.

The findings below refer to application code and the reference **before this document revision**. The revised clinical reference corrects the prose; those corrections do not imply the application has changed. Existing goals are not all maintenance-based: some already add/subtract calories, while others use clinical kcal/kg endpoints.

### 3.1 Confirmed calculation and implementation issues

| Finding | Evidence in current implementation | Correction proposed |
|---|---|---|
| Mifflin coefficients are correct; its weight input is modified | `NutritionPrescriptionService::bmr()` has the published simplified coefficients. `autofill()` first substitutes adjusted weight above 120% IBW | Use measured weight, or a clinically confirmed dry weight where appropriate, for routine adult Mifflin estimation. The original derivation included normal-weight and obese adults; it did not prescribe this Hamwi/adjusted-weight substitution. Label the output REE estimate, then estimated daily needs after activity. [Mifflin 1990][s1] |
| An extra energy reduction is hidden in the supposed maintenance baseline | Synthetic example, male, 100 kg, 170 cm, 40 years, factor 1.2: current custom target **1,941 kcal/day**; measured-weight Mifflin ×1.2 gives **2,241** | Remove the undocumented 300-kcal difference in this example before adding an explicit deficit. This demonstrates a source-input mismatch, not proof that 2,241 is the patient's true expenditure |
| Infant age bands are unreachable through the API as designed | Controller casts age to integer; context uses current `Carbon::age`. An infant of 9 months becomes age 0, so the <6-month protein/growth branch is used | Calculate age in days/months at the measurement date; use precise reference-specific bands and the same date in PHP and TypeScript |
| API and frontend use today's age with historical measurements | Reference says age at assessment; both calculation paths use current date | Freeze the calculation date and inputs for each prescription; recalculation is an explicit new action |
| Pediatric treatment goal is ignored | PHP returns `autofillPediatric($m)` before the goal switch; TypeScript explicitly defers pediatric goal-specific logic | Label the result a baseline estimate. Do not claim it implements pediatric renal, diabetes, liver, malnutrition, or burn protocols |
| Pediatric protein is not the claimed PDRI implementation | Runtime uses IOM-style 1.52/1.20/1.05/0.95/0.85 g/kg bands. PDRI has different age/sex reference intakes; e.g. its 6–11-month reference is 17/15 g/day | Choose and name the actual protocol; do not label one reference's numbers as another's. PDRI reference values use reference body size and are not a universal treatment dose for every sick child. [PDRI pp. 1–2][s3] |
| Infant macro distribution is inappropriate as a universal default | Pediatric fat is always 30% of energy; PDRI 0–5-month fat range is 40–60% | Use age-specific infant feeding guidance and specialist-entered needs. Do not generate an ordinary solid-food macro plan for young infants. [PDRI p. 1][s3] |
| The pediatric TEE chain is not established by the cited source | Schofield estimates resting expenditure. Current code adds the adult activity factors and fixed growth additions of 70/45/20/15 | A valid resting equation does not validate that entire chain. FAO gives age-specific expenditure/growth methods; its infant requirements are not this universal Schofield × adult factor rule. [FAO infant chapter][s4], [FAO children chapter][s5] |
| Hamwi becomes an automatic pediatric target weight | Backend `InterventionPlanService::create()` and frontend `defaultWeightTarget` call adult Hamwi without an age guard. At 115 cm its floor produces **30 kg**, even for a synthetic 20-kg child | Stop automatic adult ideal-weight targets in children. Use assessed growth trajectory; do not silently substitute a population percentile or an adult BMI |
| Maternal calculations diverge between runtimes | PHP returns early for children and skips the maternal modifier; TypeScript applies it after pediatric calculation | Add an adolescent-pregnancy parity case. Do not silently apply conflicting disease-specific protein targets and maternal additions |
| Saved values are not guaranteed to be backend-calculated | Autofill runs on the server, but save accepts editable numbers. Frontend may retain its preview after autofill fails | Preserve clinician overrides, but label their origin and store their calculation trace. Do not describe every saved number as server-generated |
| Custom fluid guidance disagrees across documentation and runtime | Parts of the reference say 30 mL/kg for custom; runtime uses 32.5. The reference also calls mL/kg and mL/kcal methods equivalent | State the actual method and its scope. They coincide only for matching energy-per-kg assumptions; they are not interchangeable for every prescription |

Direct PHP checks reproduced the adult example, identical pediatric outputs for weight-loss versus severe-malnutrition goals, and infant-age effects: with the same 8-kg male infant inputs, age 0 yields **605 kcal/12 g protein**, age 0.75 yields **580 kcal/10 g**. This last comparison isolates the age bug; neither number is independently validated as the infant's clinical requirement.

### 3.2 Adult goal and reference corrections

| Area | Fact-check | Proposed response |
|---|---|---|
| Renal protein | The current automatic ladder is not one universally accepted guideline. KDOQI 2020 and KDIGO differ; KDIGO 2024 suggests about 0.8 g/kg/day for adults with G3–G5 and rejects low-protein diets in metabolic instability. [KDIGO §3.3.1][s6], [joint ADA/KDIGO report][s7] | Use a named renal protocol and confirmed treatment context; do not describe 0.6 for all advanced CKD as mandatory. Diabetes, frailty, malnutrition, dialysis, and stability matter |
| Dialysis protein | Current PD default 1.35 g/kg is above the KDOQI maintenance-dialysis 1.0–1.2 range cited in the joint report. Individual losses may justify a different prescription. [Joint report][s7] | Keep treatment-specific clinical review; do not turn one midpoint into a universal dose |
| Renal fluid and dialysis energy | Hemodialysis outputs 750 mL and merely notes adding urine output; PD outputs 1,000 mL despite documentation saying manual. PD energy subtraction is a note, not an implemented calculation | Do not label either base as a completed fluid order. Require the RND's actual fluid target when restriction is prescribed. PD non-food energy must come from the treatment, not a fixed 500–800-kcal guess |
| Diabetes | No universal ideal macro split or mandatory weight category exists for a diabetes nutrition plan. [ADA 2026 §5][s8] | Type 1/2 only; keep energy independent. Coexisting CKD uses the renal assessment and actual protein target, not diabetes “stage 3” |
| Weight loss | ADA 2026 supports individualized 500–750-kcal deficits in its adult weight-management context. It does not validate this app's 375/500/625/875 mapping to Asian BMI classes. Common calorie ranges are not universal physiological minimums. [ADA 2026 §8][s9] | Remove the severity ladder and claims of guaranteed weekly weight loss. Review low targets; do not silently clamp them upward and misreport the requested deficit |
| Weight gain | NICE CG32 concerns adult nutrition support; it does not establish the app's %IBW staging or its pediatric 1.1/1.2–1.3 multipliers | Remove %IBW-based surplus stages. Use clinician-selected adjustment or a reviewed clinical endpoint. [NICE CG32][s10] |
| Cardiac | Current reference says subtract 500 if overweight, but runtime never does. Its mild/moderate/severe tiers are internal; they are not standard AHA stages and do not justify automatic 2,000/1,500-mL restrictions | Remove the tiers and hidden-loss claim. Retain actual clinician sodium/fluid orders. Avoid excessive sodium; fluid limits require individual assessment. [AHA HF guidance][s11] |
| Liver | Routine cirrhosis energy of 37.5 kcal/kg for everyone exceeds the common ESPEN 30–35 range for the cited nutrition context. Source guidance varies by liver condition. [ESPEN liver, recommendations 54/57/58][s12] | Use Liver Disease Diet with Compensated Cirrhosis / Decompensated Cirrhosis. Both can calculate from confirmed coefficients and weights. The source's protein distinction follows malnutrition/sarcopenia, not decompensation alone; other liver conditions need individualized targets |
| Hepatic encephalopathy | Core code retains protein, but its exception note, food panel, and education template suggest restriction in severe encephalopathy | Remove a routine restriction exception. ESPEN advises against protein restriction in cirrhosis with encephalopathy. Do not generate treatment or BCAA doses from an encephalopathy label. [ESPEN liver][s12] |
| Recovery/high protein | Stress stages and fixed kcal/kg values do not implement all current ICU, burn, wound, and surgery guidance. Early ICU feeding is phase-dependent, and ESPEN prefers measured expenditure when possible. [ESPEN ICU 2023][s13] | Keep routine recovery; remove ICU/burn dose claims from automatic presets. Use current surgery guidance rather than the 2017 source alone. [ESPEN surgery 2025][s14] |
| Albumin | Low albumin alone does not establish protein depletion or an indication for a high-protein dose; albumin/prealbumin reflect illness and inflammation | Remove “low albumin” as a stand-alone goal indication and the education claim that these values reflect protein status. [ASPEN position paper][s15] |
| Malnutrition diagnosis | GLIM assessment and a screening risk score are different. BMI/%IBW or a score alone cannot establish the diagnosis/severity | Keep diagnosis clinical, including malnutrition at higher BMI. Remove risk-score-based stage labels. GLIM 2025 updates diagnosis, not the app's calorie coefficients. [GLIM update][s16] |
| Universal weight bases | No reviewed source validates “Hamwi IBW for all adult protein, adjusted weight for all energy/fluid” across every condition | Specify weight basis per calculation, with named protocol and clinical rationale; do not swap one universal IBW formula for another |

### 3.3 Pediatric corrections and limits

1. **Baseline arithmetic is not pediatric therapeutic validation.** Schofield's resting coefficients are recognizable in ESPGHAN/ESPEN's table, but its clinical context and total-energy method matter. In critically ill children that guidance does not support simply adding activity/stress factors. It is a parenteral-nutrition guideline, not a universal oral-meal prescription. [Pediatric energy guideline, Table 1 and critical-illness section][s17]
2. **CKD:** The existing pediatric reference's low-protein rows need correction. KDIGO specifically advises against protein restriction in children because of growth impairment. [KDIGO pediatric practice point 3.3.1.4][s6]
3. **Growth classification:** The reference conflates under-5 thresholds with those for ages 5–19. WHO BMI-for-age defines overweight above +1 SD and obesity above +2 SD for the latter group. HAZ identifies stunting; it does not by itself classify acute wasting. [WHO BMI-for-age][s18], [WHO wasting guidance][s19]
4. **Diabetes:** Do not import an automatic adult 500-kcal deficit into a pediatric Type 2 plan. Pediatric care calls for individualized, developmentally appropriate nutrition and weight management. [ADA children/adolescents 2026][s20]
5. **Fluid:** Holliday–Segar arithmetic is a maintenance estimate, not a universal renal/cardiac fluid order or resuscitation protocol. Keep separate from food-water estimates and from an ordered restriction.
6. **Near-term scope:** Permit clinician-entered pediatric prescriptions and goal-specific notes, while identifying automated values as baseline estimates. Publish pediatric therapeutic autofill only after a separately validated age-, route-, growth-, and condition-specific protocol. A routine goal menu need not expose specialist feeding formulas.
7. Keep the current adult workflow boundary of 18 during this change, but apply each reference's own age bands. In particular, PDRI still uses its 16–18-year row at age 18. Test 17/18/19 explicitly; do not claim the Mifflin derivation validates every age or clinical population.

### 3.4 Refeeding and gradual changes: notes only

Per the requested scope, **do not introduce feeding phases, automatic titration, daily ramps, or refeeding multipliers**. Retain one eventual energy target. Generate the short strategy note in the clinical reference §10 when assessed refeeding risk applies, including under General Diet or any disease goal. Do not equate an energy surplus with refeeding risk; risk also exists without an underweight or severe-malnutrition label. Missing risk information requires assessment rather than an invented diagnosis.

There is a real source mismatch to document: the current severe target is the full endpoint, while NICE has different introduction advice for high-risk refeeding. A note does not make full-target feeding immediately safe. Normal electrolytes or a non-severe stage do not by themselves exclude risk. [NICE CG32 §§1.4.5–1.4.8][s10]

The saved final target and Show calculations must say **eventual target** when this note applies. Initial intake and advancement remain clinician-directed and separate from this endpoint calculation. No automatic starting-dose override, percentage reduction or staged result is introduced. Do not embed medication, thiamine, electrolyte doses, or a generated day-by-day schedule in patient counseling.

## 4. Source currency and attribution ledger

Age alone does not invalidate a source. Separate a historical equation, a current guideline, a newer update, and a local product default.

| Existing source | Review result as of 21 October 2026 | Action |
|---|---|---|
| Mifflin 1990 | Original coefficients verified in abstract; it predicts resting expenditure. The app's adjusted-weight substitution is not that published method | Keep equation attribution, correct inputs/labels; do not invent a Filipino percentage correction |
| Hamwi 1964 / generic AND manual attribution | An old heuristic; exact full source and the claimed short-stature bias/floor rationale were not established in this review | Stop presenting the 30-kg floor or universal protein basis as source-validated. Remove pediatric use |
| Roza/Shizgal 1984 | Paper concerns Harris–Benedict reevaluation, not demonstrated validation of the app's exact five activity factors across Filipino inpatients/children. [Publication record][s21] | Remove unsupported attribution. Describe retained adult activity multipliers as pragmatic estimates pending protocol validation, not validated pediatric factors |
| Asia-Pacific BMI 2000 / WHO consultation 2004 | Regional classifications and WHO public-health action points are distinct. There is no single BMI definition that automatically prescribes a deficit for every Asian patient. [WHO consultation][s33] | Name the adopted local classification. Do not claim BMI 23 automatically proves weight loss is appropriate, or that one regional scheme replaces all WHO international categories. Keep BMI separate from diabetes type and energy adjustment |
| PDRI 2015, summary revised 2018 | Official summary remains available; no replacement set of numeric tables was verified. A newer workshop/project does not prove a new published standard | Keep age/sex references and maternal reference additions. Do not call 0.8 g/kg Hamwi a PDRI healthy-adult prescription. [Official tables][s3] |
| FDA Circular 2023-009 | The cited URL was inaccessible; official FDA labeling material refers to the circular. Its use in food labeling does not establish this calculator as a legally mandated hospital formula. [FDA labeling annex][s22] | Remove that broad legal claim; preserve clinical versus population-reference distinction |
| KDOQI 2020 | Remains relevant, but should be read alongside newer KDIGO and treatment context; not simply “replaced by KDIGO” | Cite exact recommendations and disclose differences rather than blending them into one ladder. Publisher full text was blocked; joint ADA/KDIGO report was available. [KDOQI publication][s23], [joint report][s7] |
| ADA 2024 plus a generic 2026 link | Annual guidance has a 2026 edition | Cite the actual 2026 nutrition, weight-management, and pediatric sections used; remove old blanket A1c 8.0–8.5 and fixed macro claims unless independently rechecked |
| AHA Dietary Guidance 2021 | **Same organization has 2026 Dietary Guidance** | Update dietary-pattern source. It still does not validate the app's cardiac severity ladder. Use separate HF guidance for fluid claims. [AHA 2026][s24] |
| ESPEN surgery 2017 | **Same organization has a 2025 update** | Replace the old primary surgery reference with the 2025 guidance for the relevant population. [Surgery update][s14] |
| ESPEN ICU 2019 / generic ASPEN page | ESPEN has a 2023 practical, partially revised ICU guideline; a generic resource index is insufficient evidence for a dose | Use the named current document, while keeping ICU outside routine recovery autofill. [ICU update][s13] |
| ESPEN/EASL liver 2019 | ESPEN has a 2020 practical guideline based on the 2019 scientific guideline; this is not proof the original became invalid | Reconcile energy ranges and protein guidance with the applicable condition; delete contradictory patient-facing statements. [Practical guideline][s12] |
| NICE CG32 | Older guidance remains published; it covers adults, not the claimed pediatric catch-up stages | Keep applicable adult guidance and refeeding note, correct the scope |
| WHO SAM 2013 | **WHO 2023 wasting/nutritional-oedema guideline explicitly supersedes it** for its under-5 scope | Replace source; remove “until WAZ −2” as a universal recovery endpoint and avoid applying infant/under-5 protocols to all adolescents. [WHO 2023][s19] |
| GLIM 2019/2025 | 2025 update exists; diagnosis does not supply the calorie/protein ladder | Keep diagnosis separate from prescribing and screening. The separate Asian validation citation must be checked in full before retaining the exact severe Asian BMI cutoffs; an abstract/database title alone is insufficient |
| Generic references for wound vitamins/pediatric liver supplements | The existing high-dose blanket tables are not established by the specific sources reviewed | Remove automatic supplement advice. Use assessed deficiency and an ordered protocol; do not prescribe vitamins from a goal selection. [WHS wound guideline 2.5][s25] |

**Review limits:** Some publisher/FDA/legacy URLs were unavailable; unavailable means unverified, not false. The 2026 AHA update, 2025 ESPEN surgery update, 2023 WHO replacement, original Mifflin abstract, PDRI tables, accessible ESPEN PDFs, and KDIGO PDF were checked. This review does not certify the entire historical reference, privacy/legal content, or every external citation.

## 5. Food recommendations and education audit

### 5.1 Original data flow

- `RecommendAvoidPanel.tsx` displays a hardcoded goal-only list. It receives **only `goalType`**, with no stage, age, allergies, actual nutrient prescription, or lab context.
- There is a separate backend path: `ClinicalRulesSeeder` → `ClinicalRule` → `RecommendService` → recommendations endpoint. The visible panel does not consume `fetchRecommendations`.
- `educationTemplates.ts` contains a third version of the advice. Templates are inserted only when education is empty; changing a goal can leave advice from the old goal.
- Backend recommendation lab refinements sometimes use `threshold: 0` for qualitative review advice. A displayed “0 mg” restriction would be false. Use no numeric threshold when there is no numeric order.
- The endpoint maps food rules from one goal, so it must not imply it has considered all comorbidities. It also reads original assessment labs; it does not establish that those are the latest clinical labs.

### 5.2 Findings and replacements

| Current wording or behavior | Problem | Replacement |
|---|---|---|
| Renal: avoid bananas, dairy, whole grains, high-potassium/high-phosphorus foods for the goal generally | CKD does not require every patient to restrict all these foods. It can conflict with low potassium, dialysis needs, adequate intake, and plant-food benefits | Make portions and restrictions depend on actual labs, prescribed limits, and renal treatment. Prioritize sodium/additives review; do not create a universal banana/whole-grain ban. [NKF kidney-diet myths][s26], [NKF potassium][s27] |
| Renal education: phosphate additives “absorb 100%” | False precision | Say “phosphate additives are absorbed more readily”; do not add an exact percentage without the applicable source |
| Diabetes: avoid white bread/high-GI carbohydrates; fixed 3 meals and 1–2 snacks | A preferred higher-fiber pattern is not a universal prohibition or mandatory eating schedule | Use portioned carbohydrate choices, preferred higher-fiber foods, and meal timing aligned with treatment. Preserve appropriate hypoglycemia treatment. [ADA nutrition][s8], [ADA low-glucose guidance][s28] |
| Ampalaya highlighted as a diabetes-specific preferred food | Acceptable vegetable example, but selection must not imply a proven glucose-lowering treatment | List it as one non-starchy vegetable; do not claim a medicinal effect or recommend extracts. [NCCIH diabetes supplements][s29] |
| Cardiac: promote potassium-rich foods without context | Can conflict with hyperkalemia/CKD/medications | Give potassium advice only when appropriate for the patient's actual prescription and clinical review |
| Cardiac: avoid all canned goods, coconut milk, or saturated-fat foods | Preparation, sodium content, amount, and overall pattern matter. Low-sodium canned foods can fit | “Limit salty canned foods and large portions of foods high in saturated fat.” Do not ban an entire affordable food format. [AHA dietary guidance][s24] |
| Fish examples include sardines without preparation qualifiers | Salted/canned versions may be high in sodium | Prefer fresh or lower-sodium preparation; use labels and portions |
| Liver: “Never restrict protein long-term except during active severe encephalopathy” and similar education heading | Contradicts the source's advice against routine protein restriction | Remove the exception; keep adequate protein and clinical management. Continue alcohol/raw-shellfish safety guidance for cirrhosis. [ESPEN liver][s12], [NIDDK cirrhosis][s30] |
| High protein: body can “only use ~25–40 g at a time” | Turns a muscle-synthesis discussion into an unsupported hard cap on total protein use | “Spread protein across meals as tolerated”; no fixed absorption/use ceiling |
| High protein: albumin/prealbumin “reflect protein status over weeks” | Misleading nutrition monitoring claim | Review intake, weight/muscle/function, healing, and clinical status; do not use these proteins as direct intake-response markers. [ASPEN][s15] |
| Seeded malnutrition rule: restore albumin; fixed 35 kcal/kg/1.5 g/kg as recommendations | Conflicts with editable prescription and with the source caveat above | Display the saved individualized target and assess recovery; do not create a second numeric prescription in food advice |
| Seeded omega-3 rule: lowers triglycerides **and LDL** | Overgeneralized LDL claim | Say fish can support a heart-healthy eating pattern; do not promise LDL lowering from omega-3. Do not turn fish advice into supplement prescribing. [NIH ODS][s31] |
| Seeded cardiac rules: automatic 1,500 mg sodium, 4,700 mg potassium, 200 mg cholesterol | Not individualized; can disagree with both the calculator and the saved orders | Numeric nutrient limits must come from the confirmed prescription. General food guidance must not quietly impose new targets |
| Weight-loss education: cut rice by a quarter to remove 150–200 kcal/day | No starting portion is specified; calorie saving is not calculable | Use measured portions and verified recipe/food data; remove the fixed saving claim |
| Gain/recovery examples: whole peanuts; full-fat milk; lugaw called energy-dense implicitly | Age, allergy, texture, preparation and fortification matter | Filter allergens; use safe textures for children; describe fortification where intended. Plain thin lugaw is not guaranteed energy/protein dense. [CDC choking hazards][s32] |
| Malnutrition education: labs every 1–2 weeks | Cannot be universal during early refeeding or acute illness | “Follow the clinical team's monitoring schedule”; keep urgent symptom advice concise |

These findings concern content in repository code and seed data. The deployed database contents were not queried; do not claim the corrected seed file proves production rows are already corrected.

### 5.3 Revised food panel

The exact per-selection examples are maintained once in [the clinical reference, §11](docs/logic/intervention-goals.md#11-food-recommendation-and-avoidance-content). They cover all seven goals and all twelve type selections, including General Diet and both cirrhosis stages.

Use **Choose more often**, **Limit**, and a short **Avoid when indicated** line. Keep at most three examples per column. Adapt to allergies, age/texture, current clinical context and actual orders. Unknown or stale labs do not justify a ban. A lower-sodium canned food can fit; a banana, dairy, whole grain, bean or nut is not universally contraindicated in CKD.

Food guidance must not create another numerical prescription. Display only actual confirmed targets, with units and target/minimum/maximum meaning. The reviewed copy removes severe-encephalopathy protein restriction, medicinal ampalaya claims, albumin-restoration promises, fixed protein-use ceilings, unsupported rice calorie savings and routine high-dose wound supplements.

## 6. Generated notes: concise, editable, within limits

The canonical copy is in [clinical reference §10](docs/logic/intervention-goals.md#10-reviewed-automatic-notes): seven shared education templates, twelve type-specific strategy additions and conditional energy, malnutrition, refeeding, pediatric, maternal and fluid notes. Keep this as one content contract for notes, food advice and reports.

- Insert shared education and selected-type guidance automatically into the existing free-text fields. No new note editor, progression calculator or LLM is needed.
- On selection changes, replace generated text only when it remains unedited. Preserve RND edits and offer the new suggestion clearly; never silently duplicate or overwrite.
- Keep barriers blank unless actually assessed. Do not fabricate agreement, symptoms, intake or diagnoses.
- Retain **1,200 characters per field / 2,200 combined** across education, counseling goals, barriers and strategies. Aim for generated education ≤450, counseling ≤200, strategies ≤850 and combined ≤1,200. These are smaller template budgets, not new hard limits.
- Validate all composed combinations, separators, interpolated numbers and Unicode characters. Current JavaScript UTF-16 and backend character counts differ for emoji; use one consistent code-point rule.
- Preserve safety text and manual additions. Where text does not fit, require the RND to resolve the composition; no silent truncation.
- Refeeding and gradual increases/decreases affect **notes only**. State that the final figure is an eventual target when relevant. No starting-dose, feeding-phase, stepped or percentage-of-target calculation may replace it. Initial intake and advancement remain clinician-directed; no automatic ramps or medication/electrolyte doses.

Character checks and arithmetic examples are recorded after verification in §9. Source links accompany the canonical copy; the content still needs patient-specific RND review.

## 7. Calculation design and Show calculations

### 7.1 One calculation order

1. Resolve measurement date, age, sex, measured/dry weight, height, activity and maternal status. Preserve which measurement each value came from; do not combine stale inputs without showing their source.
2. Compute **one** baseline using the applicable method. Display its name, inputs, units, weight basis and reference. Baseline means estimated maintenance for the routine Mifflin path, or an individualized clinical target for a kcal/kg path.
3. Apply a supported maternal addition once, with a review note when another condition creates a conflict. Its reference addition is not proof that stacking on every disease prescription is appropriate.
4. Apply the explicit signed energy adjustment once. No diabetes/BMI/gain-stage offsets remain in this version.
5. Set protein from the applicable protocol/confirmed target, then derive carbohydrate and fat from final energy. Never just add calories while leaving macros tied to the old target.
6. Validate feasible macro energy. A zero-carb clamp must not conceal protein/fat calories greater than the total target. Warn about incompatible distributions and require a coherent RND target.
7. Apply manual final targets only as explicit overrides; show the calculated result alongside them. Refeeding/progression notes do not enter this equation.

### 7.2 Routine adult methods and exact defaults

The detailed contract is [clinical reference §§3–9](docs/logic/intervention-goals.md#3-inputs-dates-and-body-weight-basis). These are **proposed local presets with named source boundaries**, not claims that guidelines mandate each midpoint.

| Goal/type | Proposed local calculation | Essential boundary |
|---|---|---|
| General Diet; either diabetes type; cardiac types | Measured-weight Mifflin REE × RND-confirmed adult activity estimate | No hidden BMI/type adjustment. Routine protein1.0 g/kg is a local standard-diet prefill only when suitable; malnutrition/illness may require more |
| Renal: CKD Stages 1–2 |30 kcal/kg; routine protein1.0 g/kg when suitable | Keep actual diagnosis and individualized assessment; early CKD alone does not mandate protein restriction |
| Renal: CKD Stages 3–5 Without Dialysis |30 kcal/kg; protein0.8 g/kg | Adopt KDIGO2024 for eligible stable adults; do not impose on children, unstable or malnourished patients |
| Hemodialysis |30 kcal/kg; protein1.2 g/kg | Local choices within KDOQI25–35 kcal/kg and1.0–1.2 protein ranges; confirm clinical weight and actual fluid order |
| High Protein: either recovery type |30 kcal/kg; protein1.2 g/kg as a starting prefill | Based on ESPEN hospital nutrition recommendation15; not a universal wound/burn/ICU protocol |
| Liver Disease Diet: either stage |Confirmed coefficient × confirmed clinical kg; proposed local starting energy coefficient32.5 | Midpoint within the cited30–35 nutrition-support range, not a universal dose.1.2 protein for the specified non-malnourished compensated context;1.5 for malnutrition/sarcopenia regardless of stage. Otherwise confirm coefficient; decompensation alone does not select1.5 |
| Custom Plan |Clinician-entered targets, optional explicitly requested estimate |No invented specialist formula |

**Both cirrhosis stages support automatic multiplication.** What cannot be inferred from the stage is the patient's nutritional status, suitable weight or a fluid restriction. The original implementation computed both with the same 37.5-kcal/kg and 1.35-g/kg-IBW coefficients; that does not prove these coefficients represent each patient's needs. Version 2 uses the reviewed coefficients and confirmed weight basis described above.

For routine adults,25% energy fat is a local prefill within PDRI's15–30% range. Set protein first, then fat and residual carbohydrate from **final** energy; preserve clinical overrides. Record source-specific weight bases rather than universal Hamwi/adjusted-weight substitution.

Maternal reference additions apply once when appropriate. Pediatric General Diet and diabetes use NASEM 2023 Table 5-15 general EER with normal growth, exact infant months and age/sex/activity coefficients. Macro percentage choices follow PDRI age-specific AMDR. The assessment activity-label mapping is a local proxy requiring review. Schofield is reference-only; adult treatment presets are hidden. Adolescent maternal and specialist prescriptions use Custom Plan. Full equations and scope are in [reference §8](docs/logic/intervention-goals.md#8-pediatric-calculations-and-limits).

### 7.3 Required calculation display

The view must explain the number actually saved, not rerun today's formulas using today's age.

Show **Baseline calculation** and **Final target** as the two result groups. Explain the baseline formula and fixed adjustments within them; do not create initial-feeding, daily-progression or intermediate stage results.

```text
Method: Mifflin–St Jeor, adult estimated maintenance
Weight basis: confirmed measured/dry weight
REE = 10 × weight(kg) + 6.25 × height(cm) − 5 × age(years) + sex constant
Estimated maintenance = REE × activity estimate
Baseline: 2,000 kcal/day
Maternal addition: 0 kcal/day
Selected adjustment: deficit 500 kcal/day
Calculated target: 2,000 + 0 − 500 = 1,500 kcal/day
Final target: 1,500 kcal/day
Source measurement date and calculation version: recorded with this plan
```

The 2,000-kcal baseline above is an illustrative trace value, not the result for a specified patient. A surplus example must similarly show `2,000 + 300 = 2,300`. For a kcal/kg path, show `coefficient × selected weight`, label it **clinical target**, then show the adjustment. If manually changed to 1,600, show both calculated 1,500 and manual final 1,600. If a lower-target warning is acknowledged, record it; do not silently clamp to a floor and display a subtraction that no longer matches the result.

Show protein basis, carbohydrate/fat recalculation, rounding and any actual fluid order alongside the energy steps. A refeeding-related plan says “eventual target”; its note is visible but contributes **zero** to the arithmetic.

## 8. Implementation plan

> The approved design is implemented in version 2. The steps below describe the implementation contract. Local defaults remain distinguishable from source recommendations; clinical suitability still requires the RND's assessment.

**Architecture:** Use the PHP calculator as the sole current calculation authority; the browser renders the saved calculation snapshot. Reuse existing intervention fields, clinical-rule storage, guidance fields, and trace UI. Add explicit energy adjustment/provenance; remove duplicate stage logic and conflicting guidance. No new prescription framework, AI generator, or external dependency is required. The calculator exposes baseline and final target only; progression instructions remain free text.

### Task 1 — Record the approved clinical contract and version it

**Files:** `docs/logic/intervention-goals.md`, `docs/logic/prescription-targets.json`; mark the older `intervention-goals-0.md` and localization research as historical where their claims conflict.

- [x] Record the seven approved goal names and twelve exact type choices in the revised reference, including General Diet and both cirrhosis stages. Remove Malnutrition as a separate goal while preserving diagnosis/severity clinically. Record source ranges, proposed local defaults, weight/date rules, notes, food guidance and pediatric limits. Preserve actual CKD stage and coexisting conditions.
- [x] Correct the clinical reference's confirmed source mismatches and unsupported attributions. Runtime corrections are implemented in version 2.
- [x] Create calculation version 2. Keep version-1 vectors as legacy regression data; add independently worked version-2 cases rather than mechanically regenerating expected values from the implementation.
- [x] Before activation, review the explicit local defaults and eligibility: pragmatic adult activity estimate, standard-diet protein prefill, KDIGO stable-adult renal default, confirmed cirrhosis coefficients/weight basis, and supported pediatric total-intake protocol. Do not label these choices as universal PDRI or disease-specific source mandates.

**Deliverable:** The revised source-linked clinical reference and an explicitly versioned implementation contract. The current version-1 JSON must remain identified as legacy until new independently verified cases are added.

### Task 2 — Correct context and calculation provenance

**Modify:** `backend/app/Services/InterventionCalculationContextService.php`, `backend/app/Http/Controllers/RND/InterventionController.php`, `backend/app/Services/NutritionPrescriptionService.php`, `frontend/lib/nutritionCalculations.ts`, intervention `page.tsx`.

**Tests:** `backend/tests/Unit/InterventionCalculationContextServiceTest.php`, `backend/tests/Unit/NutritionPrescriptionServiceTest.php`, `frontend/lib/nutritionCalculations.test.ts`.

- [x] Add failing cases for age at observation date, infants either side of 6/12 months, adult transition, dry weight, no pediatric Hamwi target, and adolescent maternal parity.
- [x] Correct date/age precision and Mifflin weight use. Separate the weight basis for protein/fluid from the energy formula; no global substitution rule.
- [x] Use automatic NASEM general EER for the supported pediatric menu, with reference-only Schofield. Unsupported disease and adolescent maternal prescriptions use Custom Plan.
- [x] Add source/measurement date and calculation version to the result and saved snapshot; do not recalculate historical plans on read.
- [x] Run the focused context/calculator tests and compare independent hand-worked cases.

### Task 3 — Add energy adjustment and simplify goal types end to end

**Modify:** `backend/config/clinical.php`, `backend/app/Support/InterventionGoalCatalog.php`, `backend/app/Http/Requests/RND/StoreInterventionRequest.php`, `backend/app/Services/InterventionPlanService.php`, `backend/app/Models/Intervention.php`, intervention resources, calculator/controller, `frontend/services/interventionService.ts`, `frontend/lib/interventionGoalState.ts`, intervention `page.tsx`, `_components/goals.ts`, `GoalSelectorModal.tsx`, `NutritionPrescriptionForm.tsx`.

**Create:** One additive migration for `energy_adjustment_mode` (`none|surplus|deficit`), `energy_adjustment_kcal` (nonnegative whole kcal), `fluid_mode` (`estimated|restricted`, nullable for unclassified legacy plans), and `calculation_snapshot` (JSON, nullable for legacy/manual plans). Store the fluid amount and final nutrient targets in existing columns. The snapshot includes method/version, inputs/date/weight bases, baseline, maternal addition, signed adjustment, calculated result, fluid mode and manual overrides. Do not add feeding-phase or ramp fields.

**Tests:** Existing goal catalog/state tests, `backend/tests/Feature/NcpInterventionTest.php`, `InterventionPlanServiceTest.php`, frontend service and intervention UI tests.

- [x] Add failing tests for Type 1/2 at underweight/normal/higher-weight inputs, with independent none/surplus/deficit selections; the selected type never adds an offset.
- [x] Add API tests: 2,000 +300 =2,300; 2,000 −500 =1,500; repeated recalculation does not compound; none clears stale magnitude; invalid signed/NaN/nonfinite/nonpositive final values are rejected.
- [x] Test General Diet, all twelve type choices, preservation of actual CKD stage, coexisting cardiac/recovery conditions and clinical malnutrition severity. Test both cirrhosis stages at different nutritional states: malnutrition can change protein independently of stage. No type invents a fluid limit. Historical peritoneal, malnutrition, gain/loss and encephalopathy-grade plans remain readable.
- [x] Add fluid tests: an actual prescribed restriction remains a maximum across goal/energy changes; it overrides estimated guidance; zero/negative/missing restriction amounts are rejected; an unclassified historical fluid value is not silently relabeled. Show the confirmed amount and mode in reports and calculation details.
- [x] Remove old hidden goal-stage energy adjustments for version 2. Display the adjustment input only when needed; make manual overrides explicit.
- [x] Update new-plan allowlists and frontend catalog together. Retain a legacy display map and preserve historical plans without recalculation. A legacy plan copied into a new draft needs review of diabetes type and numeric targets.
- [x] Persist/audit adjustment and snapshot fields using existing clinical-value redaction. Validate preview origin and server recalculation; failed autofill must not silently label a client preview authoritative.
- [x] Rerun focused API, state and UI tests.

### Task 4 — Unify food advice and automatic notes

**Modify:** `backend/app/Support/InterventionGuidance.php`, `backend/app/Services/RecommendService.php`, `backend/database/seeders/ClinicalRulesSeeder.php`, recommendation/autofill controller responses, `frontend/services/interventionService.ts`, `frontend/lib/educationTemplates.ts`, `frontend/lib/interventionGuidance.ts`, intervention `page.tsx`, `RecommendAvoidPanel.tsx`, `EducationTab.tsx`, `CounselingTab.tsx`.

**Tests:** `backend/tests/Feature/RecommendServiceTest.php`, existing guidance limit/UI tests; create `backend/tests/Unit/InterventionGuidanceTest.php` and `_components/RecommendAvoidPanel.test.tsx` for generated-copy and panel behavior.

- [x] Define concise reviewed note templates once on the server; return food guidance and note suggestions in one shared contract. Delete the hardcoded competing panel text and replace long frontend templates rather than maintaining three clinical versions.
- [x] Reuse clinical rules where appropriate; numeric limits always come from the actual prescription. Qualitative lab advice has a nullable/absent threshold, never zero mg.
- [x] Correct the seeded reasons listed in §5. Update known seeded identities idempotently; do not delete custom hospital rules indiscriminately. Verify deployed seed changes separately.
- [x] Use current, explicit clinical context. Unknown/stale labs do not authorize a potassium ban. A heart-healthy preset must not suggest extra potassium against a confirmed renal limit. Preserve allergy exclusions and age/texture suitability.
- [x] Automatically insert notes into empty/unedited fields; preserve manual edits; handle goal changes and pending suggestions within both character budgets. Keep patient barriers blank.
- [x] Add tests for all seven shared templates and all twelve type additions, plus pediatric/refeeding/malnutrition/maternal/fluid combinations, longest interpolated values, shared budget, emoji counting, edit preservation and no silent safety-text truncation.
- [x] With identical confirmed inputs and fixed adjustments, add/remove refeeding and gradual-increase/decrease notes and assert identical energy, protein, carbohydrate, fat and fluid. Cover malnutrition context explicitly: a 2,000-kcal baseline plus a selected 300-kcal surplus stays 2,300 with or without the note. No starting-dose or staged low result may replace the final endpoint. Verify no severe-encephalopathy restriction text, false albumin marker claim, blanket CKD food bans or contradictory zero-mineral limit remains.

### Task 5 — Extend Show calculations and downstream outputs

**Modify:** `frontend/lib/prescriptionCalculationTrace.ts`, `_components/PrescriptionCalculationPanel.tsx`, intervention resources and reports, `backend/resources/views/reports/partials/nutrient-prescription.blade.php`, affected audit serializers, `backend/database/seeders/InterventionMealPlanTemplateSeeder.php`, template goal filtering, and any AI prompt or report that consumes goal/stage labels.

**Tests:** `frontend/lib/prescriptionCalculationTrace.test.ts`, calculation panel tests, report/plan tests, affected meal-template tests.

- [x] Verify rendered none/surplus/deficit traces, maternal addition once, clinical-kcal/kg baseline labeling, eventual-target note, and manual override.
- [x] Read saved trace inputs for historical plans; legacy plans say calculation details were not recorded rather than reconstructing an exact trace from current observations.
- [x] Update visible labels, template filtering and exports for the seven goals and new types. Do not lose existing meal-plan links, counseling text, or allergies.
- [x] Meal generation uses the final confirmed energy/macros once. It does not reapply a deficit, a maternal addition, or food-advice thresholds.
- [x] Trigger existing meal-plan rescaling when confirmed nutrient targets change; preserve historical reports and view-only protections.

### Task 6 — Validate clinical examples and release narrowly

- [ ] Review synthetic cases with the clinical owner: both diabetes types at opposite weight extremes; stable CKD with/without diabetes; dialysis with an actual fluid order; recovery with CKD; malnutrition at normal/high BMI; cirrhosis with encephalopathy; infants and adolescent pregnancy.
- [ ] Confirm source, input units, age, body-weight basis, arithmetic, rounding, macronutrient feasibility, and exact source applicability for each case. Passing mirror tests alone is insufficient.
- [ ] Run the targeted checks below. Broaden tests only for additional affected code or unresolved failures.
- [ ] Deploy schema, backend catalog/calculator, reviewed guidance data, and frontend together. Confirm actual production rule contents and supported client versions; do not assume seeds ran.
- [ ] Keep pediatric therapeutic automation and specialist protocols outside release until individually validated. No historical prescriptions are silently rewritten.

Suggested focused commands after implementation (adjust test files to the final change):

```powershell
# backend directory; enable zip for this command if the local CLI still needs it
php -d extension=zip vendor/phpunit/phpunit/phpunit --filter='NutritionPrescriptionServiceTest|InterventionCalculationContextServiceTest|NcpInterventionTest|InterventionPlanServiceTest|RecommendServiceTest'

# frontend directory: legacy calculation suite uses node:test, not Vitest
node --test lib/nutritionCalculations.test.ts
npm test -- lib/interventionGoalState.test.ts lib/prescriptionCalculationTrace.test.ts services/interventionService.test.ts
```

## 9. Verification performed for this audit

The table below records **historical audit results before implementation**. Current version-2 checks are recorded in §11; these are separate from production data inspection and live acceptance.

**Documentation verification, 21 October 2026:** independently recomputed 18 worked arithmetic values covering male/female adult REE, fixed surplus/deficit, macro rounding, renal/dialysis protein, cirrhosis and male/female pediatric REE/maintenance-fluid arithmetic. Verified seven shared education templates and twelve type additions. The longest strategy composition is 805 code points. After the final brevity revision, executable checks keep every generated field to at most two sentences: education ≤300, counseling ≤120, strategies ≤300 and combined guidance ≤700. The earlier 805-character strategy was an overly broad stacked combination, not the final template. These checks verify arithmetic and length, not individual clinical suitability. Baseline/final-only and note-invariance requirements are implementation acceptance criteria.

| Check | Observed result |
|---|---|
| Backend `NutritionPrescriptionServiceTest` | **94 passed, 776 assertions**, using `php -d extension=zip vendor/phpunit/phpunit/phpunit --filter=NutritionPrescriptionServiceTest` |
| Backend calculator + calculation-context tests | **95/96 passed**; context test `test_latest_monitoring_wins_with_per_field_assessment_fallback_without_mutation` expects monitoring food dislike `ampalaya`, receives assessment `okra`. This separate context/test discrepancy remains open |
| Node calculation suite | **32 passed** using `node --test lib/nutritionCalculations.test.ts` |
| Vitest calculation-trace and goal-state suites | **12 passed** across two files. The legacy calculation test was excluded by Vitest config and was run separately with Node |
| Direct PHP synthetic arithmetic checks | Reproduced 1,941 versus 2,241 adult baseline; infant-age branch difference; equal pediatric outputs for opposite goals; 30-kg pediatric Hamwi floor |
| Initial local Artisan attempt | Blocked by missing CLI `ZipArchive`; running PHPUnit with `-d extension=zip` worked without editing system configuration |

Existing tests deliberately encode some disputed behavior, including adjusted Mifflin weight and full severe-malnutrition targets without refeeding metadata. Their success must not be presented as proof that the source supports those choices. The requested note-only refeeding design preserves one numeric endpoint while correcting its meaning and patient-facing advice.

## 10. Source links

Links support the specific claims above. Guidelines and original research take precedence over generic calculators or secondary summaries. Publication date and population matter; a newer document from another organization is not automatically a replacement.

[s1]: https://pubmed.ncbi.nlm.nih.gov/2305711/?format=pubmed
[s2]: https://www.nationalacademies.org/read/26818/chapter/9
[s3]: https://www.fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf
[s4]: https://www.fao.org/4/y5686e/y5686e05.htm
[s5]: https://www.fao.org/4/y5686e/y5686e06.htm
[s6]: https://kdigo.org/wp-content/uploads/2024/03/KDIGO-2024-CKD-Guideline.pdf
[s7]: https://pmc.ncbi.nlm.nih.gov/articles/PMC9870667/
[s8]: https://doi.org/10.2337/dc26-S005
[s9]: https://diabetesjournals.org/care/article/49/Supplement_1/S166/163915/8-Obesity-and-Weight-Management-for-the-Prevention
[s10]: https://www.nice.org.uk/guidance/cg32/chapter/Recommendations
[s11]: https://www.ahajournals.org/doi/10.1161/CIR.0000000000001063
[s12]: https://www.espen.org/files/ESPEN-Guidelines/ESPEN_practical_guideline_Clinical_nutrition_in_liver_disease.pdf
[s13]: https://www.espen.org/files/ESPEN-Guidelines/ESPEN_practical_and_partially_revised_guideline_Clinical_nutrition_in_the_intensive_care_unit.pdf
[s14]: https://www.espen.org/files/ESPEN-guideline-on-clinical-nutrition-in-surgery-Update-2025.pdf
[s15]: https://aspenjournals.onlinelibrary.wiley.com/doi/abs/10.1002/ncp.10588
[s16]: https://pubmed.ncbi.nlm.nih.gov/40222089/
[s17]: https://www.espen.org/files/ESPEN-Guidelines/Pediatrics/ESPGHAN_ESPEN_ESPR-guidelines-on-pediatric-parenteral-nutrition-Energy.pdf
[s18]: https://www.who.int/toolkits/growth-reference-data-for-5to19-years/indicators/bmi-for-age
[s19]: https://www.who.int/publications/i/item/9789240082830
[s20]: https://diabetesjournals.org/docm-care/article/doi/10.2337/doc26-a014/164625/Section-14-Children-and-Adolescents
[s21]: https://pubmed.ncbi.nlm.nih.gov/6741850/
[s22]: https://www.fda.gov.ph/wp-content/uploads/2025/01/ANNEX-K.pdf
[s23]: https://www.sciencedirect.com/science/article/abs/pii/S0272638620307265
[s24]: https://professional.heart.org/en/guidelines-statements/2026-dietary-guidance-to-improve-cardiovascular-health-a-scientific-statementcir0000000000001435
[s25]: https://doi.org/10.1111/WRR.13130
[s26]: https://www.kidney.org/news-stories/6-kidney-disease-diet-myths?page=1
[s27]: https://www.kidney.org/kidney-topics/potassium-your-ckd-diet
[s28]: https://diabetes.org/living-with-diabetes/hypoglycemia-low-blood-glucose/symptoms-treatment
[s29]: https://www.nccih.nih.gov/health/diabetes-and-dietary-supplements-what-you-need-to-know
[s30]: https://www.niddk.nih.gov/health-information/liver-disease/cirrhosis/eating-diet-nutrition
[s31]: https://ods.od.nih.gov/factsheets/Omega3FattyAcids-HealthProfessional/
[s32]: https://www.cdc.gov/infant-toddler-nutrition/foods-and-drinks/choking-hazards.html/
[s33]: https://www.sciencedirect.com/science/article/pii/S0140673603152683
[s34]: https://www.niddk.nih.gov/health-information/liver-disease/cirrhosis/definition-facts
[s35]: https://www.heart.org/en/health-topics/heart-failure/treatment-options-for-heart-failure/lifestyle-changes-for-heart-failure

## 11. Implementation and compatibility record

**22 October refinement:** Only energy direction and a fixed kcal/day amount are added to the routine prescription form. Existing final nutrient/fluid fields and bounds remain editable; no coefficient or calculation-weight override controls are exposed. Liver nutritional status remains an assessed goal-specific input, separate from optional notes. If the source does not establish a suitable automatic liver protein target, use Custom Plan for the individual prescription. Education and Strategies contain their respective suggestion-only checkboxes. Each relevant field has a collapsed suggestion and Copy action using the Shopping List pattern; users may also select text normally. Goal selection, showing/hiding and copying never write to fields. Patient-facing suggestions give actions, not calorie arithmetic. Text fields match the Assessment white background, border and focus styling. Show calculations uses Measurements and Final target, compact precision and actual saved inputs; references stay outside Clinical notes, which contains goal guidance. Versions/conversion history are not shown.

**Approved one-time adaptation:** The forward-only migration converts plans without snapshots and recalculates using each plan's recorded measurement source, preserving identity and dates. It verifies encrypted originals in persistent private storage before mutation, rescales existing menu quantities through the normal service, and expires active linked report caches for normal reprepare. All existing note fields are preserved. Missing diagnosis/dosing evidence uses Custom Plan without guessing. Archived report bytes remain frozen under the report lifecycle. This supersedes the initial no-conversion decision below; deployment never runs PatientSeeder.

- Current creation, explicit recalculation and preview use the same PHP version-2 path. A direct target edit is an identified manual override. The browser does not run a second current formula.
- Automatic menu selection uses age at the weight observation, including exact months. Children see General Diet, diabetes types and Custom Plan, with a tooltip. Saved inputs, goal and version remain unchanged by birthdays/new measurements.
- Notes-only changes, including refeeding, never recalculate values from newer monitoring. Existing goal labels are retained for history and unchanged legacy edits; new drafts require a supported selection.
- The additive migration creates nullable metadata fields only. It does not relabel goals, infer diabetes type, backfill formula claims, rewrite targets, rebuild menus, delete reports or run PatientSeeder.
- New demo seeding uses Type 2 Diabetes and General Diet with malnutrition context. Demo final targets and concise guidance are coherent; no low refeeding starting dose becomes the endpoint. Deliberate patient reseeding remains a demo reset operation and is never part of deployment.
- Existing meal-template identities and serving snapshots remain usable. New General Diet templates reuse the balanced pattern; retired rehabilitation/weight patterns are general-purpose on deliberate reseeding, without deleting historical library records.
- New report renders use saved numeric targets and recorded provenance. The calculation line appears only when a real snapshot exists. Legacy reports do not acquire a guessed baseline or maternal modifier. Fluid maxima stay maxima, with an explicit warning if planned food fluid exceeds the order.
- Filed/archived report bytes follow the existing freeze/storage lifecycle. No patient repair or report-regeneration script is needed for this additive migration. Production data and access are separate verification claims; do not claim a local test queried deployed patient records.
- Live browser QA remains deferred as requested. Production migration, running revision, rule contents and patient/report behavior still require deployed verification.

### Local verification record — 22 October 2026

- Backend focused follow-up: 120 tests passed, 2,413 assertions, covering calculations, guidance, persistence, monitoring fallback, reports, menu generation, repeatable seeders and route/audit contracts. A subsequent final regression run passed 18 tests with 456 assertions, including measurement-age transition and confirmed weight-basis validation.
- The initial full backend run executed 1,900 tests: 1,891 passed, six failed and three errored. Task-related failures were corrected and covered by follow-up runs; subprocess ZIP errors were resolved by the test-only PHP configuration. One unrelated existing failure remains in `PersonNameStaleConsumerTest`, concerning direct name access in `Diagnosis/PesEvidenceBuilder.php`. The full suite was not rerun after those fixes; it must not be described as fully green.
- Frontend full run: 216 files and 788 tests passed. TypeScript, ESLint and the production build passed. Scoped PHP formatting and whitespace checks passed.
- A fictional report generated through the real report service was inspected visually: one page, no clipping or overlap, recorded baseline 1,881 minus 300 equals final 1,581 kcal/day, and a preserved 1,700-mL fluid maximum.
- Generated guidance remains at most two sentences per field. The maximum tested all-context composition is 268 education, 79 counseling and 273 strategy code points (620 combined), below the stricter composition budgets and field limits.
- These results establish local behavior only. Git delivery and deployed/live acceptance are separate checks; no deployed patient data was inspected or repaired.
