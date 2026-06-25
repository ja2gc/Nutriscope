# Clinical Care Current vs Proposed Gap Analysis

Date: 2026-06-25  
Source documents:

- `docs/reviews/2026-06-25-clinical-care-ncp-workflow-audit.md`
- `docs/reviews/2026-06-25-clinical-care-workflow-deep-audit.md`

Purpose: determine which Clinical Care NCP workflow gaps are worth addressing before defense and which should be deferred. The current implementation is the source of truth. The proposed behavior is the TO-BE workflow described in the workflow audit.

## Priority Definitions

| Priority | Meaning |
|---|---|
| Must Fix Before Defense | Affects clinical correctness, data integrity, report integrity, security/permission boundaries, ADIME compliance, or workflow completion. |
| Should Fix Before Defense | Improves usability, consistency, or workflow enforcement but has a defensible workaround. |
| Can Wait Until After Defense | Primarily architectural, advanced workflow management, nice-to-have, or future scalability. |

## 1. Status Lifecycle Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| SL-01 | No clinical state machine | NCP status is mostly `draft` or auto-promoted to `active`; `completed` and `discharged` exist but have no transition workflow. | Use explicit lifecycle states: draft assessment, assessment complete, diagnosis complete, intervention complete, active monitoring, completed, discharged, reassessment required. | Status is stored as a simple enum and updated only in `InterventionController::store`. | Critical: records can appear active without completed ADIME. | High: status does not reflect actual child record completeness. | High: reports can be generated from misleading statuses. | High: users cannot tell what is truly complete. | Medium: existing statuses need mapping/backfill. | Critical | Large |
| SL-02 | Active status is triggered by row existence | Creating any intervention after any assessment row and diagnosis row can activate the NCP. | Activate only after finalized Assessment, at least one finalized PES, and complete Intervention/Prescription. | Backend checks existence, not completeness. | Critical: false active care plans. | High: active records may lack prescription and care plan. | High: active NCP reports can be clinically incomplete. | Medium: UI suggests progress that backend has not validated. | Medium: existing active records need completeness recalculation. | Critical | Medium |
| SL-03 | No one-open-cycle rule | Patient can have multiple draft/active NCP cycles. | One open cycle per patient unless prior cycle is completed/discharged/reassessment-closed. | No backend guard or DB uniqueness. | High: care can split across cycles. | High: competing NCPs for same patient. | Medium: reports may select wrong cycle. | High: users may not know which cycle is official. | Medium: existing duplicate open cycles need reconciliation. | High | Medium |
| SL-04 | Patient discharge does not affect NCP lifecycle | Discharged/transferred patients can receive new NCP cycles and ongoing documentation. | New NCP creation blocked or routed through readmission/reassessment workflow. | Patient status is validated but not used in workflow rules. | High: post-discharge documentation can appear as active care. | Medium: chronology/status inconsistencies. | Medium: census and NCP reports may mix inactive care. | Medium: users can continue unintended workflow. | Small: mostly rule enforcement plus handling existing records. | High | Small |
| SL-05 | Reassessment and archived states are not first-class | NCP `type` has `reassessment`, but creation always uses `new`; no NCP archived state. | Explicit reassessment and archive/cancel paths with audit reason. | Enum values exist without workflow/service support. | Medium: reassessment history is ambiguous. | Medium: deletion substitutes for archival. | Medium: historical reporting unclear. | Medium: users cannot close/reopen cleanly. | Medium: new states/transition records may be needed. | Medium | Large |

## 2. Assessment Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| AS-01 | Assessment completion is only row existence | Empty assessment rows satisfy downstream diagnosis gate. | Assessment can be saved as draft but must pass minimum clinical validator before diagnosis. | Store request makes nearly all clinical fields nullable. | Critical: diagnosis can be unsupported by assessment evidence. | High: incomplete assessment is treated as complete. | High: NCP Summary can render blanks. | Medium: users are not told what is missing. | Medium: add completion fields and backfill state. | Critical | Medium |
| AS-02 | Attachment upload creates assessment row | Uploading a document calls `firstOrCreate` and can create a blank assessment. | Attachments link to NCP or draft assessment without counting as assessment completion. | Attachment workflow is coupled to the assessment relationship. | High: document upload bypasses Assessment. | High: blank assessment created unintentionally. | High: reports see an assessment relation with no clinical content. | Medium: user may not realize workflow advanced. | Medium: migrate/handle existing attachment-created assessments. | Critical | Medium |
| AS-03 | Assessment edits do not invalidate downstream work | Assessment can be changed after diagnosis/intervention without reopening or review. | Material assessment changes require downstream review or reopen affected steps. | No dependency/version tracking between A, D, and I. | High: diagnoses/interventions may no longer match assessment. | Medium: stale clinical reasoning remains active. | Medium: reports do not show mismatch. | Medium: users receive no revalidation prompt. | Medium: may need timestamps/review flags. | High | Medium |
| AS-04 | Assessment store/update validation differs | `physical_activity_level` is loose on store and stricter on update. | Same canonical validation on create and update. | Request rules drifted. | Low: inconsistent activity data affects prescription calculations. | Medium: invalid values can be stored. | Low: reports may display inconsistent values. | Medium: user sees save/update inconsistencies. | Small: clean invalid values if present. | Medium | Small |
| AS-05 | Assessment one-to-one not DB-enforced | Controller blocks duplicates, but DB has no unique `ncp_record_id`. | Enforce one assessment per NCP at database level. | Model relationship implies has-one, migration does not. | Medium: duplicate assessments can confuse care. | High: has-one may hide duplicates. | High: reports may select arbitrary assessment. | Low: rare unless race/import occurs. | Medium: dedupe existing duplicates before constraint. | High | Medium |

## 3. Diagnosis / PES Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| DP-01 | Diagnosis gated by assessment existence, not completion | Manual diagnosis and AI approve require only an assessment row. | Diagnosis unlocks only after completed assessment. | Backend checks `$ncpRecord->assessment()->exists()`. | Critical: PES may lack assessment basis. | Medium: diagnosis detached from evidence. | High: NCP Summary can show unsupported PES. | Medium: UI appears compliant while backend is weak. | Small/Medium: depends on AS-01 state model. | Critical | Medium |
| DP-02 | PES override is not persisted | UI allows editing PES text, but payload omits `pes_statement`; backend rebuilds from P/E/S. | Either remove manual PES edit or persist reviewed final PES with source components. | Frontend and backend disagree on PES authority. | Medium: clinician-entered wording is lost. | Medium: stored record differs from user intent. | High: report prints generated text, not reviewed text. | High: user trust issue. | Small: add/align field or remove UI. | High | Small |
| DP-03 | Diagnosis update can break PES invariants | Update request permits nullable fields; partial/null updates can corrupt or error. | Diagnosis update preserves required P/E/S invariants and rebuilds only from complete values. | Store and update validation are inconsistent. | High: existing PES can become invalid. | High: DB/type errors or invalid text. | High: reports may show invalid/missing PES. | Medium: edit workflow unstable. | Small: validate existing records if needed. | High | Small |
| DP-04 | Last diagnosis can be deleted after activation | Deleting all diagnoses does not downgrade active NCP or block monitoring/reporting. | Prevent deleting last finalized PES on active NCP unless downstream steps are reopened. | Delete endpoints do not recompute status/dependencies. | Critical: active intervention can have no diagnosis. | High: orphaned clinical rationale. | High: report can show active NCP with no PES. | Medium: user can accidentally break cycle. | Medium: existing active NCPs need validation. | Critical | Medium |
| DP-05 | AI diagnosis suggest can run before assessment | AI suggest can generate drafts without assessment; approval is gated. | AI suggestion aligns with assessment-complete gate or is clearly non-record draft. | AI suggest path has looser preconditions than manual/approve. | Medium: premature clinical reasoning. | Low: suggestions are not records until approved. | Low/Medium: can influence later reports indirectly. | Medium: user may assume AI output is clinically grounded. | None/Small. | Medium | Small |

## 4. Intervention Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| IV-01 | Empty intervention is valid | `POST /intervention {}` succeeds after any diagnosis and may activate NCP. | Intervention can be drafted, but completion requires goal/stage, prescription or exception, education/counseling, and follow-up plan. | Intervention request makes all clinical fields nullable. | Critical: active NCP can lack actual intervention. | High: intervention row misrepresents care. | High: NCP Summary can show blank intervention. | High: UI `ensureIntervention()` creates empty rows. | Medium: existing interventions need completeness status. | Critical | Medium |
| IV-02 | Nutrition prescription is optional | Autofill has prerequisites, but saved intervention does not require targets. | Prescription targets required before active NCP or meal plan finalization unless documented not applicable. | Prescription is stored on nullable intervention fields. | Critical: care plan lacks core nutrition prescription. | High: meal plans can default to generic targets. | High: reports miss or misstate prescription. | Medium: users may skip target creation. | Medium: backfill/mark incomplete. | Critical | Medium |
| IV-03 | Intervention not linked to specific PES | Intervention only belongs to NCP, not to selected/active PES diagnoses. | Intervention documents which PES/problem(s) it addresses. | Domain model lacks diagnosis-intervention linkage. | Medium/High: care goals may not address diagnosis. | Medium: traceability gap. | Medium: reports cannot show rationale mapping. | Medium: users manually infer relationship. | Medium: schema/link table may be needed. | High | Medium |
| IV-04 | Follow-up plan is optional | `next_followup_date`, session type, and monitoring plan are nullable. | Follow-up plan required for active intervention or documented exception. | Intervention model treats follow-up as optional note. | High: monitoring may not be scheduled. | Medium: M/E sequence lacks timing basis. | Medium: report cannot distinguish initial vs follow-up plan. | Medium: user gets weak workflow guidance. | Small/Medium. | High | Small |
| IV-05 | Intervention one-to-one not DB-enforced | Controller blocks duplicates, DB does not. | Unique intervention per NCP or explicit multi-intervention model. | Relationship is has-one but migration lacks unique constraint. | Medium: duplicate interventions can confuse prescription. | High: reports may show arbitrary intervention. | High: NCP Summary may be wrong. | Low/Medium. | Medium: dedupe before constraint. | High | Medium |

## 5. Meal Plan Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| MP-01 | Meal plans can be generated without prescription | Missing targets default to generic values. | Generation requires completed prescription or explicit exception. | MealPlanService has fallback targets. | Critical: generated plan may be clinically wrong. | Medium: plan looks valid despite weak source. | High: Patient Menu Plan can be wrong. | Medium: user may trust generated output. | Small/Medium: existing generated plans may need incomplete flag. | Critical | Medium |
| MP-02 | Auto-generation ignores clinical conditions | `conditions` input exists but is not applied. | Conditions/disease stage rules affect meal selection and validation. | Request shape anticipates rules but service does not use them. | High: renal/diabetes/etc. restrictions may not apply. | Medium: condition metadata is misleading. | High: reports imply plan is condition-aware. | Medium: user may assume filters worked. | Small if disabled; Large if full rule engine. | High | Medium |
| MP-03 | Manual items ignore allergies/restrictions/prescription | Manual meal plan items validate source and quantity only. | Manual and generated plans run same allergen, restriction, and target variance validation. | Clinical validation exists partially in generator, not item endpoints. | Critical: allergen/restriction safety risk. | High: invalid plan stored. | High: report prints unsafe plan. | High: no immediate warning/block. | Medium: existing plans need validation status. | Critical | Medium |
| MP-04 | Nested meal plan routes are not parent-scoped | MealPlan/Day/Item route params can be mismatched across NCPs/plans. | Scoped binding or explicit parent-child checks on every nested route. | Laravel route model binding is independent; controllers lack checks. | High: wrong patient plan can be modified. | Critical: cross-patient data corruption. | High: reports can use corrupted plans. | Medium: hard-to-debug user effects. | Small: no schema migration required. | Critical | Small |
| MP-05 | Meal plan templates lack complete ownership checks | Listing is owner-scoped, but show/delete/use are not consistently scoped. | Template show/delete/use scoped to owner or explicit sharing. | Controller methods use route model IDs without owner policy. | Medium: wrong templates can affect patient plans. | High: cross-user data leakage/deletion. | Medium: reports can reflect wrong template use. | Medium: confusing library behavior. | Small. | High | Small |
| MP-06 | USDA-only items can be omitted from report | Patient Menu Plan gets names from related food/recipe; direct USDA items may have no name. | Persist and report USDA display/source snapshot. | Report ignores item snapshot for display name. | Medium: patient menu missing actual foods. | High: stored plan and report differ. | Critical: downloaded menu incomplete. | Medium: user sees unexplained omissions. | Small/Medium: backfill snapshots where possible. | Critical | Small |
| MP-07 | Recipe and food data can drift | Recipe totals update on recipe save, not automatically when food values change; food/recipe names may change after plans. | Clinical meal plans use immutable snapshots/versioned food and recipe data. | Food/recipe catalog is mutable without versioning. | High: calculations may become stale or inconsistent. | High: historical plan basis unclear. | High: reports may reflect current names/totals inconsistently. | Medium: users cannot audit source. | Large if full versioning; Medium for snapshot-only. | High | Large |
| MP-08 | Patient Menu Plan cannot select exact plan in UI | Report browser lists patients, not each meal plan; backend can accept `meal_plan_id` but UI sends `patient_id`. | RND selects the exact meal plan/menu to preview/download, scoped to patient/NCP. | Report browser source is patient-based. | Medium: wrong menu may be downloaded. | Medium: report params are ambiguous. | Critical: latest plan may not be intended plan. | High: user lacks control. | Small/Medium: report source/API shape changes. | High | Medium |

## 6. Monitoring & Evaluation Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| ME-01 | Empty monitoring is valid | Once intervention exists, monitoring can be created with no meaningful data. | Monitoring requires visit date plus tracked indicator, goal evaluation, or structured exception. | Monitoring request makes all fields nullable. | High: M/E can be clinically empty. | High: invalid follow-up records stored. | High: NCP Summary can show weak/blank M/E. | Medium: users can save incomplete visits. | Medium: existing monitorings need completion flag. | High | Medium |
| ME-02 | Monitoring is not tied to follow-up encounter | Monitoring uses implicit created_at; no visit type, encounter number, or scheduled follow-up linkage. | Monitoring starts after active intervention and follow-up encounter. | No encounter model or required visit metadata. | High: ADIME M/E timing unclear. | Medium: follow-up chronology weak. | Medium: reports cannot classify initial vs follow-up. | Medium: user lacks workflow cues. | Medium/Large depending on encounter model. | High | Medium |
| ME-03 | Completion does not require Monitoring/Evaluation | NCP can be active with no monitoring; no implemented completed transition. | Full ADIME cycle completion requires at least one monitoring/evaluation entry or explicit initial-care-plan report type. | Lifecycle does not distinguish ADI initial plan from ADIME completed cycle. | Critical: incomplete ADIME may appear complete/active. | Medium: cycle state ambiguous. | High: reports can be incomplete. | Medium: no clear finish line. | Medium: status/report rules. | Critical | Medium |
| ME-04 | AI monitoring review can run on weak inputs | AI review/summary can be based on empty monitoring or missing prescription targets. | AI review blocked or labeled insufficient when baseline/targets/monitoring data are incomplete. | AI review depends on available data but not strict completeness rules. | Medium: misleading AI interpretation. | Medium: weak data becomes recommendation input. | Medium: report/summary may imply valid review. | Medium: user overtrust risk. | Small. | Medium | Small |

## 7. Reporting Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| RP-01 | Report eligibility uses shallow data checks | `hasData` checks only record existence or period data. | Reports validate report-specific clinical completeness before final render/archive. | ReportBrowser is generic and not clinical-validator aware. | Critical: official reports can be clinically incomplete. | Medium: report row/PDF has no validation state. | Critical: invalid reports can be filed. | Medium: users are not warned. | Medium: archived incomplete reports need classification. | Critical | Medium |
| RP-02 | NCP Summary renders incomplete ADIME | NCP Summary tolerates missing A/D/I/ME sections with blanks/placeholders. | Draft/incomplete NCP Summary is blocked or visibly watermarked; final summary requires required sections. | Generator is read-only and permissive. | High: incomplete care record appears official. | Medium: missing sections silently accepted. | Critical: filed clinical summary may be invalid. | Medium: no missing-data checklist. | Small/Medium: report templates and validation metadata. | Critical | Medium |
| RP-03 | NCP Summary omits patient meal plan | Meal plan is only in separate Patient Menu Plan report; NCP Summary does not include/link it. | If meal planning is part of NCP package, NCP Summary includes or references selected meal plan; otherwise report semantics are explicit. | NCP generator loads only NCP ADIME relations, not meal plans. | Medium: care package may feel incomplete. | Medium: NCP and menu plan can drift. | Critical: defense/demo may expect full NCP package. | High: users must know to download separate report. | Small/Medium. | High | Medium |
| RP-04 | Patient Menu Plan can render wrong/incomplete plan | UI selects patient; generator picks latest plan unless `meal_plan_id` provided; plan completeness not checked. | User selects exact final/active meal plan; report displays completeness/variance warnings. | Browser emits only `patient_id` and plan validation is absent. | High: wrong menu can be handed to patient. | High: ambiguous report source. | Critical: printed menu may be wrong. | High: missing selection control. | Medium. | Critical | Medium |
| RP-05 | Demographic Census uses wrong risk source | Risk level uses `patients.screening_type`, not NCP risk band. | Census risk uses latest completed assessment/NCP risk score band. | Report maps screening type as risk. | Medium: risk distribution clinically wrong. | High: aggregate data inaccurate. | Critical: census/reporting accuracy issue. | Medium: users may not notice. | Small/Medium: source logic and historical assumptions. | High | Small |
| RP-06 | Live vs archived completeness is unclear | Live reports use current data; archives freeze PDF but not validation status. | Reports store/render validation status and snapshot completeness summary. | Report snapshot stores branding/signatories/params, not clinical validation result. | Medium: filed report quality unclear. | Medium: historical completeness not auditable. | High: archived incomplete report may look final. | Medium: users lack confidence. | Medium. | High | Medium |

## 8. Permissions & Ownership Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PO-01 | No model-level clinical ownership policy | RND role generally grants broad access to model-bound resources by ID. | Explicit patient/NCP/document/report policies or assigned-patient access model. | `backend/app/Policies` is absent; controllers rely on role middleware. | High: PHI boundary unclear. | Critical: unauthorized modifications possible. | High: reports can expose other records. | Low: mostly invisible until breach. | Medium: may need assignment data. | Critical | Large |
| PO-02 | Clinical document routes lack scope checks | Screening document show/file/delete accept document ID without patient/NCP owner checks. | Document access scoped to owning patient/NCP and RND permissions. | Controller has no authorize/scope logic. | High: PHI document exposure. | High: unauthorized deletion possible. | Medium: attachments in reports may be affected. | Low. | Small/Medium. | Critical | Small |
| PO-03 | On-demand clinical report rendering not owner-scoped | Render/archive are role-checked, not owner-scoped to RND/patient/NCP. | Report instances/render/archive follow clinical access policy. | ReportController guards role but not clinical ownership. | High: PHI exposure. | Medium: report archive can be created for unauthorized record. | High: unauthorized report output. | Low. | Medium if ownership model absent. | Critical | Medium |
| PO-04 | Template ownership is incomplete | Meal plan template show/delete/use can bypass owner scope. | Template operations owner-scoped or share-scoped. | Methods do not consistently filter by `rnd_user_id`. | Medium. | High: cross-user template access. | Medium. | Medium. | Small. | High | Small |

## 9. Data Integrity Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| DI-01 | One-to-one relationships not DB-enforced | Assessment, intervention, and biochemical data are modeled as one-to-one but lack unique constraints. | Database enforces true one-to-one or model changes to one-to-many with dates. | Migration constraints are incomplete. | Medium: duplicate clinical sections possible. | Critical: hidden duplicate records. | High: reports may choose arbitrary row. | Low/Medium. | Medium: dedupe and add constraints. | High | Medium |
| DI-02 | Child records can become semantically orphaned | Intervention/monitoring can remain after diagnosis deletion; partial records can be deleted destructively. | Dependency-aware delete/reopen rules and archival for clinical records. | Delete endpoints do not recalculate lifecycle. | High: care plan rationale missing. | High: dependent records inconsistent. | High: reports show contradictions. | Medium. | Medium. | High | Medium |
| DI-03 | Client-supplied nutrient snapshots are accepted | Item update can accept `nutrient_snapshot` from client. | Server calculates/locks nutrient snapshot from trusted source. | Endpoint trusts client payload. | High: nutrition calculations can be falsified. | Critical: stored nutrition data unreliable. | High: reports can show wrong nutrition basis. | Low. | Small/Medium: recalc existing snapshots. | Critical | Small |
| DI-04 | Food/recipe deletion and mutation risk historical records | Food/recipe records are mutable/deletable; clinical plans rely on them. | Soft-delete/archive catalog items and use immutable snapshots in clinical plans/reports. | Catalog has no versioned clinical-source model. | Medium/High: historical plan basis can change. | High: FK failures or stale references. | High: reports can drift or fail. | High when delete fails unexpectedly. | Medium/Large. | High | Medium |
| DI-05 | Incomplete food/recipe data can enter meal planning | Recipes can be created without ingredients; foods may lack macros/micros/allergens. | Food/recipe must meet clinical-use completeness before available for Clinical Care plans. | Store requests allow sparse food/recipe data. | Medium/High: plans may lack nutrient/allergen data. | High: cannot validate plan fully. | High: reports can omit/understate nutrients. | Medium. | Medium: flag existing library records. | High | Medium |

## 10. ADIME Compliance Gaps

| Gap ID | Title | Current Behavior | Proposed Behavior | Why the Gap Exists | Clinical Impact | Data Integrity Impact | Reporting Impact | UX Impact | Migration Impact | Risk | Effort |
|---|---|---|---|---|---|---|---|---|---|---|---|
| AD-01 | ADIME uses row existence instead of completed steps | A/D/I/M steps are considered available once rows exist. | Each ADIME step has draft vs finalized/completed state with validators. | No completion model, only relationships. | Critical: incomplete records can appear valid. | High: step status inaccurate. | Critical: report integrity weak. | High: user cannot see missing requirements. | Large: add state fields and validators. | Critical | Large |
| AD-02 | Initial ADI plan and full ADIME cycle are not distinguished | Active NCP can exist without monitoring; reports do not distinguish initial plan from completed cycle. | Separate initial care plan report from full ADIME cycle report. | Lifecycle lacks monitoring/completion semantics. | High: "completed NCP cycle" is ambiguous. | Medium: cycle state incomplete. | High: report labels can mislead. | Medium. | Medium. | High | Medium |
| AD-03 | Downstream steps do not reopen when upstream changes | Assessment/diagnosis changes do not force review of intervention/monitoring. | Material upstream changes reopen or flag downstream steps. | No dependency graph or invalidation rules. | High: plan may not match updated evidence. | Medium: stale dependencies. | Medium/High: reports do not show stale chain. | Medium. | Medium/Large. | High | Large |
| AD-04 | Clinical recommendations/restrictions are advisory | Recommendations exist but manual planning and reports do not enforce or surface restrictions consistently. | Restrictions/recommendations are part of validation and report warnings. | Clinical rules are not integrated across manual save/report paths. | Critical for allergies/restrictions. | High: unsafe plans stored. | High: unsafe reports. | High: user must manually remember restrictions. | Medium. | Critical | Medium |

## Prioritization Matrix

| Priority | Gaps |
|---|---|
| Must Fix Before Defense | SL-01, SL-02, SL-03, SL-04, AS-01, AS-02, AS-03, AS-05, DP-01, DP-02, DP-03, DP-04, IV-01, IV-02, IV-04, IV-05, MP-01, MP-03, MP-04, MP-06, MP-08, ME-01, ME-03, RP-01, RP-02, RP-03, RP-04, RP-05, PO-02, PO-03, PO-04, DI-01, DI-02, DI-03, DI-04, DI-05, AD-01, AD-02, AD-04 |
| Should Fix Before Defense | SL-05, AS-04, DP-05, IV-03, MP-02, MP-05, ME-02, ME-04, RP-06 |
| Can Wait Until After Defense | MP-07, PO-01, AD-03 |

## Minimum Changes Required

Smallest set needed to make the NCP workflow clinically defensible and internally consistent:

1. Add backend clinical completeness gates for Assessment, Diagnosis/PES, Intervention/Prescription, and Monitoring.
2. Stop attachment upload from creating an assessment that satisfies downstream gates.
3. Prevent empty intervention from activating NCP; activate only after completed A/D/I.
4. Require nutrition prescription targets or documented exception before intervention completion and meal plan generation/finalization.
5. Block deleting the last finalized PES diagnosis from an active NCP unless the NCP is reopened.
6. Enforce one open NCP cycle per active patient and block new cycles for discharged/transferred patients unless explicitly reassessment/readmission.
7. Add parent-child scoping checks for meal plan, meal plan day, and meal plan item routes.
8. Add ownership/scope checks for screening documents, clinical report render/archive, and meal plan templates.
9. Add report completeness validation or draft watermark for NCP Summary and Patient Menu Plan.
10. Add specific Patient Menu Plan selection by meal plan, or make NCP Summary explicitly include/link the selected meal plan.
11. Fix USDA-only item display in Patient Menu Plan by using persisted item/source snapshot.
12. Fix Demographic Census risk source to use NCP risk score/band, not screening type.
13. Server-calculate nutrient snapshots and stop accepting client-supplied nutrient snapshots.
14. Add DB uniqueness for one-to-one clinical sections after deduping existing data.

## Recommended Changes

Changes that significantly improve workflow quality but are not strictly required for defense:

1. Add explicit lifecycle states beyond `draft` and `active`, including `assessment_complete`, `diagnosis_complete`, `intervention_complete`, `active_monitoring`, and `completed`.
2. Persist reviewed PES override text or remove the PES editor to avoid false persistence.
3. Add material-change warnings when assessment or diagnosis is edited after downstream steps exist.
4. Add follow-up metadata to monitoring: visit date, visit type, and encounter number.
5. Integrate condition/disease-stage rules into auto meal plan generation or clearly disable unsupported condition filters.
6. Add report validation summaries to archived report snapshots.
7. Add clinical-use completeness flags for food items and recipes.
8. Soft-delete/archive food and recipe records used in clinical plans.
9. Scope meal plan template operations and add optional shared-template semantics.
10. Add visible missing-data checklists in UI for each NCP step.

## Nice-to-Have Changes

Defer until after defense:

1. Full immutable versioning of food items, recipes, nutrient sources, and clinical rules.
2. Full assigned-patient ownership model if the current deployment assumes all RNDs may access all patients; still keep document/report scope checks for defense.
3. Advanced reassessment workflow with branching/carry-forward from prior NCP cycle.
4. Full encounter model with scheduling, missed-visit tracking, and longitudinal episode-of-care reporting.
5. Automated downstream invalidation graph for every upstream clinical edit.
6. Advanced AI review safety framework beyond basic input completeness checks.
7. Fine-grained report catalog modes for draft, final, teaching copy, and filed copy.
8. Clinical rule authoring UI for restrictions, micronutrient limits, and disease-stage logic.

## Suggested Implementation Order

1. **Stop false progression:** fix AS-01, AS-02, DP-01, IV-01, IV-02, ME-01.
2. **Protect active NCP integrity:** fix SL-02, DP-04, ME-03, AD-01, AD-02.
3. **Secure data boundaries:** fix MP-04, PO-02, PO-03, PO-04.
4. **Make reports defensible:** fix RP-01, RP-02, RP-03, RP-04, RP-05, MP-06.
5. **Stabilize NCP lifecycle:** fix SL-01, SL-03, SL-04, IV-05, AS-05, DI-01.
6. **Protect meal planning clinical safety:** fix MP-01, MP-03, DI-03, DI-05.
7. **Reduce historical/data drift risk:** fix DI-02, DI-04, then consider MP-07 after defense.
8. **Improve usability and consistency:** fix DP-02, DP-03, AS-04, DP-05, ME-04, RP-06.
9. **Defer advanced architecture:** PO-01, AD-03, full reassessment/encounter/versioning work.

## Defense Triage Summary

For defense, the safest story is not "the workflow is fully redesigned." The safest story is:

1. The current code follows the visible ADIME sequence but only weakly enforces it.
2. The minimum defensible version must prevent clinically empty A/D/I/M records from appearing complete.
3. Reports must not look final when the clinical record is incomplete or ambiguous.
4. Meal plans must not bypass patient restrictions or print the wrong/latest plan.
5. Permission and parent-child scoping must be tightened before relying on the module with real PHI.

