# Clinical Census, Prescription, PES Drafting, and Intervention Revision Design

**Date:** 2026-09-21

**Status:** Approved by the owner on 2026-09-21 after full session-coverage review.

## Purpose

This design finishes the approved post-deployment clinical changes without adding a new food-recall workflow or burdening the RND with duplicate data entry. It covers:

- structured weight-change duration;
- one cycle-owned primary diagnosis category for Demographic Census;
- pregnancy/lactation prescription modifiers and removal of the unused stress-factor input;
- fluid as guidance rather than a meal-plan scaling target;
- a narrow, source-gated, assessment-based PES drafting assistant;
- compact patient-facing portion details and the title `Nutrition Intervention Plan`;
- immutable intervention revisions created from Monitoring when treatment changes.

The Academy of Nutrition and Dietetics treats Assessment, Nutrition Diagnosis, Intervention, and Monitoring/Evaluation as distinct, interrelated NCP steps. NutriScope must preserve those boundaries: physician diagnosis, census category, PES nutrition diagnosis, and intervention goal are separate data with separate purposes.

## Product principles

- Keep the existing ADIME workflow. Add only three notable RND actions: choose one primary census category, enter weight-change duration as quantity plus unit, and optionally revise an intervention during Monitoring.
- Prefer no suggestion over an unsupported clinical suggestion.
- Never make the patient calculate maternal additions manually. The system calculates final targets.
- Do not add separate non-maternal "maintenance" additions beside goal-calculated prescriptions. Existing goal/stage output remains the final target so values are not double-counted.
- Historical clinical plans and prepared reports remain historical. Never silently replace them with current values.
- Keep patient-facing output concise. Calculation evidence and detailed source context belong in the RND interface, not as report clutter.
- Reuse current models, services, calculation authority, report preparation, audit redaction, and UI components. Do not build parallel engines.

## Scope boundaries

### Included

- Assessment, Diagnosis, Intervention, Monitoring, meal-plan scaling, Demographic Census, NCP Summary, Nutrition Intervention Plan PDF, demo seed data, Help/docs, and canonical RND storyboard changes required by this design.
- Forward-only migrations and explicit backfills needed to preserve existing data.
- Existing current-month and completed-month census behavior, with a deliberate basis-version rebuild for the new category semantics.

### Excluded

- New food-recall database, USDA-powered recall, PhilFCT, restaurant databases, OCR, or nutrient estimation from vague recall.
- Intervention-goal redesign beyond reconciling approved maternal/stress/fluid behavior with the existing authority.
- Patient-authored calculations.
- Preparation instructions or a new recipe-creation display field.
- A separate menu-plan revision workflow.
- Automatic diagnosis from physician text, intervention goal, or a general-purpose LLM.
- Copying or expanding proprietary NCPT catalog content without confirmed licensing.

## Session coverage ledger

This design is the implementation authority for the remaining work from the brainstorming session. The implementation handoff must also preserve these boundaries so old work is not repeated or silently reversed.

### Already completed and verified before this design

- Random, immutable, non-sequential `NS-XXXX-XXXX` patient codes replaced the proposed year-plus-sequence format. They appear as smaller muted text only beneath the patient name on the profile header, are accepted by the existing unified name/physician/hospital-number search field, and identify patients in privacy-limited audit views without exposing patient names.
- The login image panel contains only a larger centered brand lockup; its logo remains hover-rotatable and the background/card contrast was corrected.
- The redundant Patients NCP cycle-separation helper text was removed.
- Demographic Census starts at the earliest non-deleted ADIME cycle, includes the current month, and counts each separate cycle once even when several cycles belong to one person. The seeded April cycle is included.
- Relevant card Edit actions were aligned at the top-right beside their card headings.
- PDF preview parsing was cached/lazy enough to remove repeated report-render delay, and the patient-plan PDF pagination defect was corrected.
- The above baseline was committed, deployed, documented/storyboarded, and live-browser tested. The new implementation must regression-test it but must not rebuild or redesign it.

### Approved remaining work represented in this design

- Structured weight-change duration and removal of the duplicate/fixed three-month behavior.
- A separate required RND-selected primary diagnosis category for each Assessment/cycle, with the approved compact category set and `Other` details.
- Explicit seeded demo categories, census basis rebuild, live-report refresh, and frozen prepared-report history.
- Removal of stress-factor input/use; `BMR x PAL` TEE authority; maternal status as an Assessment modifier rather than an intervention goal.
- Automatic maternal energy/protein additions, existing calculation disclosure reuse, final patient targets, and fluid as guidance outside meal scaling.
- Source-gated, assessment-based, token-bounded PES drafts with deterministic eligibility, no endless regeneration, and manual RND control.
- Immutable intervention revision history created through an optional Monitoring action while ordinary monitoring remains simple.
- Patient-facing `Nutrition Intervention Plan`, compact scaled portion details from existing data, no new recipe-entry field, no preparation instructions, and no USDA note.
- Focused tests, real-PDF inspection, existing Help/flowchart/storyboard reconciliation, deployment, and full deployed browser/error QA.

### Explicitly rejected, superseded, or deferred

- No sequential or creation-year-derived patient identifier.
- No separate patient-ID search mode or patient-ID table column.
- No physician-diagnosis parsing, intervention-goal inference, or free-text buckets for census classification.
- Do not remove the diagnosis breakdown card; the temporary removal idea was superseded by the approved primary-category design.
- No separate maternal intervention goal and no patient-performed addition arithmetic.
- No new food-recall workflow, restaurant/Filipino food database, OCR intake reconstruction, or broad USDA expansion before the exhibition.
- USDA remains useful for existing single-food nutrient calculations and may support a future recall project, but that future project is not part of this change.
- No new recipe-display selection, vague household measures, preparation instructions, menu-plan revision entity, or duplicate calculation-transparency panel.
- No automatic clinical claim from one BMI, albumin result, abnormal lab, physician diagnosis, or general-purpose AI output.

## 1. Structured weight-change duration

### UX

Replace the free-text `Weight Loss/Gain Period` input with one compact row:

- positive numeric quantity;
- unit select: `weeks` or `months`.

Render it once in the canonical anthropometric section. Remove the duplicate Assessment-page input and every fixed `3 months` assumption or label. Display values naturally, such as `1 week` or `3 months`.

### Data

Add structured cycle-owned Assessment fields:

- `weight_change_period_value` — nullable positive small integer;
- `weight_change_period_unit` — nullable `weeks|months`.

Keep legacy `weight_loss_period` temporarily for backward-compatible reads and migration evidence, but stop writing it from the new UI. Backfill parseable values such as `3 weeks`, `1 month`, and `6 months`. Leave unparseable values untouched in the legacy column rather than guessing. All new calculations, AI evidence, reports, resources, and seed data use the structured pair through one formatting helper.

Quantity and unit are both present or both absent. Validation must reject half-filled and non-positive values.

## 2. Primary diagnosis category for Demographic Census

### Meaning and ownership

Add one required `Primary Diagnosis Category` selection to Assessment's Clinical / Referral and Screening area, next to but visually separate from physician diagnosis. Store it on the cycle-owned Assessment, not Patient.

The physician diagnosis remains unchanged free text. RND PES diagnoses remain separate. Intervention goal does not populate or infer the category.

The RND selects the condition principally driving nutrition care for that ADIME cycle. If physician text contains several diagnoses, use the one most responsible for the current nutrition intervention. A later cycle for the same patient can use a different category.

### Allowed categories

- `Cardiovascular`
- `Renal`
- `Diabetes`
- `Obesity`
- `Malnutrition`
- `Surgery / Trauma`
- `Liver`
- `Cancer`
- `Pregnancy / Lactation`
- `Other`

These are operational census buckets, not intervention-goal values. Labels stay short and ungrouped: no slash-pairs and no `and` labels.

- `Renal`, `Diabetes`, `Cardiovascular`, `Obesity`, `Malnutrition`, and `Liver` cover the common clinical reasons represented by existing goal families.
- `Surgery / Trauma` is one related tissue-injury/recovery bucket tied to the existing high-protein goal; burns may use this category when it is the principal care reason.
- `Pregnancy / Lactation` is one related maternal-state bucket while maternal status remains an Assessment modifier, not an intervention goal.
- `Cancer` uses the plain clinical label instead of `Oncology`, the name of the medical specialty. Cancer can drive different intervention goals while retaining one clear census category.
- Neurologic, respiratory, infectious, inflammatory, pressure-injury, uncommon endocrine/GI, and other uncommon conditions use `Other` unless another listed category is the main nutrition-care reason.
- `Malnutrition` is retained as the product label and `Obesity` is available as the contrasting excessive-weight category. Help text must explain selection by the principal nutrition-care reason rather than attempt to redefine WHO's broader public-health use of *malnutrition*.

When `Other` is selected, reveal one compact free-text field labeled `Specify category`. Require a short value server-side, but do not display `details required` wording. Reuse existing Select, TextInput, conditional-rendering, and FormRequest validation patterns; no new form-control system is needed. Census aggregation still groups it under `Other`; free text must not create fragmented report buckets.

### Validation and existing records

The selection is required for new Assessment saves and any later edit of an existing Assessment. Existing records with no category remain readable and appear as `Unclassified` until explicitly backfilled or reviewed. Do not infer real-patient categories from physician text or goal type.

### Demo data

Set explicit category values inside `PatientSeeder`:

- Maria's diabetic-control cycle: `Diabetes`;
- Roberto's current and past malnutrition cycles: `Malnutrition`.

For already deployed demo rows, use one narrowly targeted, idempotent backfill tied to known demo-cycle identity. Do not rerun `PatientSeeder` merely to add categories because it recreates the seeded clinical graph. Do not mutate unknown real records.

### Census and report identity

Rename the PDF breakdown heading to `By Primary Diagnosis Category`. Aggregate the Assessment category, never raw `patients.medical_diagnosis`.

Bump `DemographicCensusGenerator::BASIS_VERSION` once. Existing completed monthly census snapshots are rebuilt through the existing legacy-basis path; totals remain cycle counts and only the category grouping changes. Current month remains live.

Prepared/archived PDFs remain byte-for-byte frozen with their original data, branding, signatories, template, and appearance. They are never rewritten. A new live preview uses rebuilt census data and current branding. This distinction must be visible in tests and documentation.

## 3. Prescription inputs and maternal modifiers

### Stress factor and TEE

Remove the visible Assessment `Stress Factor` input and stop treating it as a required or editable prescription input. Keep the legacy column during this change to avoid a destructive migration, but do not send or use it in new workflows.

The authoritative shared TEE formula remains `BMR × PAL`. Goal-specific flat kcal/kg methods continue to replace TEE where the current goal authority says so. Do not apply a second stress multiplier on top of high-protein, malnutrition, or other disease-specific targets.

### Maternal status

Pregnancy/lactation remains an Assessment modifier, never an intervention goal. Use these explicit statuses:

- `None`
- `Pregnant — first trimester`
- `Pregnant — second trimester`
- `Pregnant — third trimester`
- `Lactating`

Legacy `pregnant` values become `pregnant_unspecified` during migration and require RND confirmation before maternal auto-fill. Never guess a trimester.

### Verified PDRI modifiers

Use FNRI-DOST *Philippine Dietary Reference Intakes 2015: Summary Tables*, revised September 2018:

- first-trimester pregnancy: `+0 kcal`; pregnancy protein guidance remains `+27 g/day`;
- second- and third-trimester pregnancy: `+300 kcal/day`, `+27 g protein/day`;
- lactation: `+500 kcal/day`, `+27 g protein/day`.

The PDRI summary also lists water additions. Water remains guidance-only in NutriScope: show applicable guidance to the RND, but do not use it to scale food. When a selected goal carries an explicit fluid restriction, preserve the goal/RND target and show the maternal water reference as context rather than silently overriding the restriction.

Every implemented clinical constant must include a concise source key in code comments and an exact document/version/page or section in the clinical logic documentation. A URL alone is not evidence. If exact source content cannot be verified, do not encode the rule.

### Calculation display

Reuse the existing collapsed `Show calculations` / `Hide calculations` panel. Do not add a second transparency UI.

For RND, show:

`goal-calculated baseline + maternal modifier = final prescription`

Show the modifier source, applicable trimester/status, and whether the RND modified the final target. The patient report shows final targets and one short applicable maternal note; it does not show arithmetic instructions or ask the patient to add values.

Goal selection retains progressive disclosure: show no stage list before a goal is selected, then show only the stages defined for that selected goal. Do not expose every goal's stage information at once.

## 4. Fluid boundary and meal-plan scaling

Fluid remains a prescribed daily guidance value and appears in Intervention UI and patient report. It must not be a meal-plan generation, candidate-selection, scaling, variance, or success target because beverage intake is not fully represented by food-water values.

Remove `water`/`fluid_ml` from:

- backend meal candidate and auto-scaling target arrays;
- scaling score and variance calculations;
- frontend target-match tracker used to judge whether a meal plan meets prescription.

Food and recipe water values may remain stored for nutrition metadata. They must not influence scaling. Display fluid separately as `Daily fluid guidance` with clear wording that it includes beverages and is not guaranteed by the listed foods.

For restricted-fluid goals, display the RND-prescribed limit prominently. Detailed accounting of water contained in every food is deferred; do not claim the generated plan satisfies that limit.

## 5. Source-gated PES drafting assistant

### Product boundary

Replace broad free-form diagnosis generation with an `Assessment-based PES drafts` assistant. It proposes drafts; it does not diagnose autonomously. Manual PES builder remains available for all supported terms.

The Academy describes nutrition diagnosis as professional critical reasoning using a problem the RND can address, an addressable root cause, and specific measurable assessment evidence. NutriScope therefore uses rule eligibility first and AI wording second.

### Initial eligible candidate families

Only direct, adequately structured Assessment evidence may enter the initial rule audit:

- unintended weight loss;
- overweight/obesity;
- swallowing/chewing difficulty;
- altered GI function;
- explicit food-medication interaction;
- predicted suboptimal intake.

Malnutrition and altered nutrition-related laboratory values remain manual unless implementation verifies and encodes multi-factor criteria from an approved source. A single BMI, albumin value, abnormal lab, medical diagnosis, or physician text is never sufficient by itself.

Nutrient-specific inadequate/excessive intake, knowledge deficit, food insecurity, harmful beliefs, non-adherence, self-monitoring deficit, and disordered eating remain manual in this release because current structured data cannot reliably prove them.

### Rule-card contract

Each enabled candidate has a small, versioned local rule card containing:

- stable source ID;
- diagnosis/problem identifier already licensed or approved for use;
- required evidence fields;
- corroborating evidence;
- disqualifiers and missing-data behavior;
- allowed evidence-to-PES mapping;
- verified source title, issuer, version/date, page/section, and URL;
- short paraphrase, not copied source pages.

No rule card is enabled until its exact source content is inspected and covered by tests. Do not scrape sources at runtime or send entire documents to the model. Do not copy proprietary eNCPT catalog content without confirmed permission.

### Data flow

1. Server builds a compact, de-identified evidence object from structured Assessment data, relevant abnormal labs, physician condition context, existing PES statements, and a length-bounded RND summary.
2. Deterministic eligibility code selects zero to three matching rule cards.
3. Only matched evidence and matched rule cards are sent to the configured AI provider.
4. AI may draft wording only for supplied eligible problems and may cite only supplied source IDs.
5. Server validates returned problem, evidence values, source IDs, duplicates/overlap, and PES shape. Unknown or invented content is rejected.
6. RND edits, accepts, or dismisses each draft. Acceptance uses the existing authorized save path and audit behavior.

Do not send name, patient code, hospital number, address, physician name, attachments, exact DOB, or unrelated free text. Real-patient external AI use requires the hospital's privacy/security approval and appropriate processor agreement; source citations alone do not establish HIPAA or Philippine Data Privacy Act compliance. The demo workflow uses fictional data.

Add a non-editable `patients.is_demo` boolean with a safe default of `false`; only the deterministic fictional `PatientSeeder` records receive `true`. Add an environment-backed real-patient AI feature flag whose default is `false`. External PES drafting is allowed when the patient is explicitly marked demo or when the hospital has deliberately enabled the real-patient flag after its privacy/security review. The flag and marker add no RND workflow. When external drafting is unavailable, show a short unavailable message and keep manual PES entry fully usable.

### Token and repeat-generation controls

- Hash normalized clinical evidence, rule-catalog version, and existing diagnoses into an Assessment fingerprint.
- Cache the validated result for an unchanged fingerprint instead of making another provider call.
- Persist dismissed candidate identity for that cycle/fingerprint.
- Assessment change creates a new fingerprint and enables refresh.
- Return at most three drafts.
- Zero eligible drafts is a successful result: `No sufficiently supported PES draft was found.`
- Remove numeric AI confidence from UI; it implies unsupported precision.
- Show concise `Evidence used` and `Source` sections on each draft.

## 6. Intervention revisions from Monitoring

### Clinical behavior

An ADIME cycle retains one current Intervention row for existing consumers plus immutable `InterventionRevision` snapshots for history.

Create revision 1 when the initial intervention is saved. Before Monitoring begins, explicit Intervention-page saves may update the initial plan and its revision-1 snapshot. After the first monitoring visit, treatment changes occur through Monitoring's optional `Revise intervention` flow rather than silently overwriting history.

### Minimal Monitoring workflow

The standard `Log Visit` form remains unchanged by default. Add one collapsed action: `Revise intervention`.

When selected:

- prefill the current prescription, education, counseling, barriers, strategies, session type, goal/stage, and follow-up data;
- require effective date and short clinical reason;
- show only the existing intervention sections needed for editing;
- save the monitoring visit, updated current Intervention, and immutable revision atomically;
- render a compact `Intervention revised` entry under that visit in the timeline.

Most visits require no extra action. Previous revisions remain read-only.

### Revision data

Add an audited, public-ID `intervention_revisions` table with:

- `intervention_id`;
- nullable originating `monitoring_id`;
- sequential version per intervention;
- `effective_at`;
- `reason`;
- actor identity;
- immutable JSON snapshot of every clinically relevant Intervention field;
- source marker such as `initial`, `monitoring_revision`, or `legacy_baseline`.

Centralize snapshot creation, current-row update, sequencing, and transaction behavior in one service. Do not duplicate revision logic in controllers.

Add nullable revision links to Monitoring and MealPlan. A newly generated meal plan links to the active revision. Patient-menu-plan generation reads prescription and intervention context from that revision snapshot, never the mutable current Intervention row.

For existing interventions, create one `legacy_baseline` revision from the current stored Intervention and link existing monitorings and meal plans to it. This preserves current known state without inventing unavailable historical revisions.

Multiple meal plans under one revision remain distinct meal plans and do not create another intervention revision. No menu-plan revision UI is added.

### Reports

- NCP Summary shows initial Intervention followed by dated revisions and the monitoring visit/reason that introduced each revision.
- Nutrition Intervention Plan renders the revision attached to the selected meal plan.
- Prepared/archived reports remain frozen and are not reinterpreted through a later revision.

## 7. Nutrition Intervention Plan and portion details

Rename patient-facing `Patient Menu Plan` wording to `Nutrition Intervention Plan` wherever it identifies the generated patient document. Preserve internal report type/route identifiers unless changing them is required for correctness; avoid a broad compatibility migration.

The PDF contains:

- patient/report identity already permitted by the current template;
- final nutrition prescription and applicable short modifier/guidance notes;
- existing menu schedule;
- current education, counseling, barriers, and strategies content intended for the patient;
- compact portion details.

Replace full recipe/preparation output with compact portion details generated from existing meal-plan item snapshots, recipe ingredients, stored quantities, and existing unit conversions. Do not add a recipe-creation field.

Each displayed dish/portion instance shows:

- dish or food name;
- ingredient/food name where applicable;
- stored household measure when a real conversion exists;
- gram or milliliter equivalent used by nutrient calculation.

Never invent vague measures such as `medium piece`. If no verified household conversion exists, show the precise stored gram/milliliter amount. USDA single foods use their saved meal-plan snapshot and selected quantity. Do not add a USDA source note to the patient PDF.

Keep output compact and deduplicate identical dish-plus-portion combinations where practical without hiding which menu slots use them. Remove preparation instructions and the old `Recipe Details` presentation. Pagination must prevent orphan headings, clipped tables, and repeated blank space.

## 8. Error handling and authorization

- Existing RND ownership/policy checks apply to Assessment, AI drafts, Intervention, Monitoring, meal plans, and reports.
- Public UUID route boundaries remain unchanged; never expose or guess internal IDs.
- Category and revision validation is server-side, not UI-only.
- Intervention revision + monitoring save is one transaction. Any failure rolls back both current-row and history changes.
- AI/provider failure leaves manual Diagnosis workflow fully usable.
- Malformed or unsupported AI output is rejected and never persisted.
- Audit events record field names and revision metadata while preserving existing clinical PHI redaction.
- Prepared report preview/download remains read-only and never creates a second identity or mutates an archived file.

## 9. Testing and verification

### Backend

- Structured weight period validation, formatting, legacy parsing, and unparseable fallback.
- Category allow-list, `Other` detail requirement, cycle ownership, authorization, and resources.
- Census aggregates categories, uses `Unclassified` for unresolved legacy records, counts every non-deleted cycle once, rebuilds old basis versions, and preserves archived report bytes.
- Seeder is deterministic and repeatable; demo cycles receive explicit categories with no duplicates or invented history.
- Maternal modifiers for first, second, and third trimester and lactation, including RND overrides and restricted-fluid behavior.
- Meal generation/scaling/variance ignores water while retaining energy, macros, and existing micronutrient behavior.
- Rule eligibility positive, negative, missing-data, duplicate, dismissal, cache/fingerprint, source-ID, malformed-output, authorization, and token-limit cases.
- Revision sequencing, immutability, transactions, legacy baseline backfill, active revision, monitoring linkage, meal-plan linkage, and historical report rendering.
- NCP Summary and Nutrition Intervention Plan report contracts.

### Frontend

- One structured weight-period control and no fixed three-month copy.
- Required category UX and conditional Other details.
- Maternal status controls and existing calculation disclosure baseline/modifier/final rows.
- No visible stress-factor field.
- Fluid displayed as guidance and absent from meal target-match UI.
- PES draft cache/refresh/dismiss/accept/edit/zero-result/source/evidence states.
- Monitoring remains simple until `Revise intervention` is chosen; revision history is readable and accessible.
- New document title and compact portion-details contract.
- Mobile 375/390 px and desktop 1440 px layouts have no horizontal overflow or inaccessible controls.

### PDF and browser acceptance

- Generate real Nutrition Intervention Plan and NCP Summary PDFs from representative seeded cycles.
- Inspect every page for identity, title, prescription, maternal note when applicable, portion accuracy, page count, clipping, overlap, orphan headings, and absence of preparation instructions/USDA note.
- Run affected tests first, then required backend suite, Pint, frontend tests, TypeScript, ESLint, and production build.
- Update existing Help/module docs, flowcharts, and `Storyboarding/RND NCP Video Storyboard.md`; do not create duplicate guidance.
- Commit only task files, push `main`, verify local/remote revision parity, deploy through the existing release workflow, confirm migration/release/health, then run deployed live-browser QA with fictional data.
- Live QA covers Assessment save, category persistence, census rebuild/current month, maternal calculation disclosure, PES draft behavior, fluid-free scaling, monitoring revision/history, both PDFs, responsive layouts, and browser errors. Inventory all observed errors before batch-fixing and rerun the full scoped sweep until clean.

## 10. Required source discipline

Authoritative starting references:

- Academy of Nutrition and Dietetics, *Nutrition Care Process Overview*: <https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview>
- Academy of Nutrition and Dietetics, *Nutrition Diagnosis*: <https://www.eatrightpro.org/practice/nutrition-care-process/ncp-overview/nutrition-diagnosis>
- Academy of Nutrition and Dietetics, *NCP Terminology*: <https://www.eatrightpro.org/practice/nutrition-care-process/ncp-terminology>
- FNRI-DOST, *Philippine Dietary Reference Intakes 2015: Summary Tables*, revised September 2018: <https://www.fnri.dost.gov.ph/images/images/news/PDRI-2018.pdf>
- WHO, *ICD-11 Reference Guide*: <https://icdcdn.who.int/icd11referenceguide/en/html/index.html>
- U.S. National Cancer Institute, *Definition of oncology*: <https://www.cancer.gov/publications/dictionaries/cancer-terms/def/oncology>
- Existing reviewed NutriScope clinical authority: `docs/logic/intervention-goals.md` and its cited primary guidelines.

Implementation must open and inspect the exact source content before encoding any rule. Record version/date/page or section in documentation and use concise source keys in code. Never infer a threshold from a title, search snippet, inaccessible link, or secondary summary.

## Acceptance criteria

- RND enters weight duration through quantity + weeks/months once; no fixed three-month assumption remains.
- Every newly saved Assessment has exactly one allowed primary census category; physician diagnosis, PES diagnosis, and intervention goal remain independent.
- Demographic Census groups cycles by primary category, preserves one-cycle-one-count totals, and never silently rewrites prepared archives.
- Maternal final targets are calculated automatically and transparently; patient never performs addition.
- Stress factor is absent from workflow; fluid is visible guidance and never a meal-plan scaling target.
- PES assistant returns only source-gated, evidence-supported drafts for unchanged data at most once, with no invented evidence or sources.
- RND can optionally revise Intervention while logging Monitoring; old revisions and associated meal-plan prescriptions remain immutable.
- Patient document is titled `Nutrition Intervention Plan`, contains compact precise portions, and contains no preparation instructions or USDA source note.
- Existing manual Diagnosis, normal Monitoring, meal generation, authorization, audit redaction, report identity, and frozen archive behavior remain available and correct.
