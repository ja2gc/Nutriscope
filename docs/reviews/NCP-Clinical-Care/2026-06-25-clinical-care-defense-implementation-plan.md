# Clinical Care Defense-Focused Implementation Plan

Date: 2026-06-25  
Inputs:

- `docs/reviews/2026-06-25-clinical-care-workflow-deep-audit.md`
- `docs/reviews/2026-06-25-clinical-care-ncp-workflow-audit.md`
- `docs/reviews/2026-06-25-clinical-care-current-vs-proposed-gap-analysis.md`

Goal: define the smallest Clinical Care changes required to make the module clinically defensible, logically consistent, report-safe, and demo-safe for capstone defense.

This plan intentionally does not optimize for enterprise healthcare compliance, full long-term architecture, or perfect clinical workflow management.

## Decision Rule

| Classification | Meaning |
|---|---|
| Implement Before Defense | Needed for clinical correctness, report accuracy, workflow consistency, security boundaries, or demo credibility. |
| Implement If Time Allows | Useful and defensible, but not necessary if the demo story and minimum workflow are tightened. |
| Defer Until After Defense | Large architecture, advanced lifecycle management, future scalability, or deep compliance work. |

## Gap Classification

| Gap | Classification | Rationale |
|---|---|---|
| SL-01 No clinical state machine | Defer Until After Defense | Full state machine is too large; defense can be covered by targeted gates and report validation. |
| SL-02 Active status triggered by row existence | Implement Before Defense | False active NCP is one of the highest-risk demo and clinical issues. |
| SL-03 No one-open-cycle rule | Implement Before Defense | Multiple active/draft cycles confuse reports and patient workflow. |
| SL-04 Patient discharge does not affect NCP lifecycle | Implement Before Defense | Easy rule with high demo/clinical credibility value. |
| SL-05 Reassessment and archived states not first-class | Defer Until After Defense | Useful, but not needed for a clean initial NCP defense path. |
| AS-01 Assessment completion is only row existence | Implement Before Defense | Core ADIME correctness issue. |
| AS-02 Attachment upload creates assessment row | Implement Before Defense | Direct bypass of Assessment gate. |
| AS-03 Assessment edits do not invalidate downstream work | Implement If Time Allows | Important but can be handled manually for defense; full invalidation is bigger. |
| AS-04 Assessment store/update validation differs | Implement If Time Allows | Small consistency fix, not central to defense. |
| AS-05 Assessment one-to-one not DB-enforced | Implement If Time Allows | Controller already blocks normal duplicates; DB hardening can wait if time is tight. |
| DP-01 Diagnosis gated by assessment existence | Implement Before Defense | Core ADIME dependency. |
| DP-02 PES override not persisted | Implement Before Defense | Visible UX/report mismatch in the defense demo. |
| DP-03 Diagnosis update can break PES invariants | Implement Before Defense | Prevents invalid PES records and edit errors. |
| DP-04 Last diagnosis can be deleted after activation | Implement Before Defense | Active NCP without PES is not defensible. |
| DP-05 AI diagnosis suggest can run before assessment | Implement If Time Allows | Gate if AI is demoed; otherwise not essential. |
| IV-01 Empty intervention is valid | Implement Before Defense | Critical false-completion issue. |
| IV-02 Nutrition prescription is optional | Implement Before Defense | Prescription is central to clinical care and meal planning. |
| IV-03 Intervention not linked to specific PES | Defer Until After Defense | Better traceability, but not required for a defensible simple flow. |
| IV-04 Follow-up plan is optional | Implement Before Defense | Needed to justify Monitoring/Evaluation sequence. |
| IV-05 Intervention one-to-one not DB-enforced | Implement If Time Allows | Normal controller path already blocks duplicates; DB hardening can wait. |
| MP-01 Meal plans generated without prescription | Implement Before Defense | Prevents generic/non-clinical generated menus. |
| MP-02 Auto-generation ignores clinical conditions | Implement If Time Allows | Either implement a narrow warning or do not demo condition-aware generation. |
| MP-03 Manual items ignore restrictions/prescription | Implement Before Defense | Allergy/restriction mismatch is an obvious patient-safety issue. |
| MP-04 Nested meal plan routes not parent-scoped | Implement Before Defense | Small, high-value data integrity/security fix. |
| MP-05 Meal plan templates ownership incomplete | Implement If Time Allows | Important, but avoid template workflow in defense if not fixed. |
| MP-06 USDA-only items omitted from report | Implement Before Defense | Report accuracy issue that is easy to demonstrate. |
| MP-07 Recipe and food data can drift | Defer Until After Defense | Full versioning/snapshot strategy is too large. |
| MP-08 Patient Menu Plan cannot select exact plan | Implement Before Defense | User specifically needs exact menu selection and report download. |
| ME-01 Empty monitoring is valid | Implement Before Defense | Full ADIME cycle needs meaningful M/E. |
| ME-02 Monitoring not tied to follow-up encounter | Implement If Time Allows | Use visit date/summary as lightweight substitute before defense. |
| ME-03 Completion does not require Monitoring/Evaluation | Implement Before Defense | Reports/demo must distinguish initial ADI from full ADIME. |
| ME-04 AI monitoring review on weak inputs | Implement If Time Allows | Gate if AI review is demoed; otherwise avoid demoing it. |
| RP-01 Report eligibility uses shallow checks | Implement Before Defense | Final reports must not be generated from incomplete records. |
| RP-02 NCP Summary renders incomplete ADIME | Implement Before Defense | Major report integrity issue. |
| RP-03 NCP Summary omits patient meal plan | Implement Before Defense | Defense/demo quality issue; include or clearly link selected meal plan. |
| RP-04 Patient Menu Plan renders wrong/incomplete plan | Implement Before Defense | Printed patient menu must match selected plan. |
| RP-05 Demographic Census uses wrong risk source | Implement Before Defense | Report correctness issue with small expected fix. |
| RP-06 Live vs archived completeness unclear | Implement If Time Allows | Useful metadata, but not required if final report generation is gated. |
| PO-01 No model-level clinical ownership policy | Defer Until After Defense | Full policy model is too large; use targeted route/report scoping before defense. |
| PO-02 Clinical document routes lack scope checks | Implement Before Defense | PHI/document access risk and small targeted fix. |
| PO-03 On-demand clinical report rendering not owner-scoped | Implement Before Defense | PHI/report boundary risk. |
| PO-04 Template ownership incomplete | Implement If Time Allows | Avoid template demo if not fixed. |
| DI-01 One-to-one relationships not DB-enforced | Implement If Time Allows | Good hardening; not required if normal controller path is tested. |
| DI-02 Child records semantically orphaned | Implement Before Defense | Covered by blocking last PES delete and report/workflow gates. |
| DI-03 Client-supplied nutrient snapshots accepted | Implement Before Defense | Nutrition data integrity issue with high report impact. |
| DI-04 Food/recipe deletion and mutation historical risk | Implement If Time Allows | At least block referenced deletes if easy; full archival can wait. |
| DI-05 Incomplete food/recipe data can enter planning | Implement If Time Allows | Prefer warnings/eligibility flags; not required if curated demo data is used. |
| AD-01 ADIME uses row existence instead of completed steps | Implement Before Defense | Core clinical defensibility issue. |
| AD-02 Initial ADI vs full ADIME not distinguished | Implement Before Defense | Needed for report labels and defense explanation. |
| AD-03 Downstream steps do not reopen on upstream changes | Defer Until After Defense | Full invalidation graph is beyond defense scope. |
| AD-04 Recommendations/restrictions are advisory | Implement Before Defense | Must block or warn on obvious restriction/allergy conflicts. |

## Implement Before Defense Items

| Order | Item | Gaps Covered | Why It Must Be Fixed | User-Visible Impact | Risk If Left Unfixed | Complexity | Dependencies |
|---:|---|---|---|---|---|---|---|
| 1 | Add lightweight clinical completeness validators for Assessment, Diagnosis/PES, Intervention/Prescription, and Monitoring | AS-01, DP-01, IV-01, IV-02, IV-04, ME-01, AD-01 | Prevents empty rows from satisfying ADIME. | Users see clear missing-field errors before moving forward or filing reports. | Empty assessment/intervention/monitoring can still look complete. | Medium | Defines minimum required fields; frontend can mirror backend errors later. |
| 2 | Stop attachment upload from creating a valid assessment | AS-02 | Removes the easiest Assessment bypass. | Uploading documents no longer advances workflow accidentally. | Any attachment can unlock Diagnosis without assessment. | Small/Medium | Attachment listing must still work for NCP/assessment. |
| 3 | Change activation rule from row existence to completed A/D/I | SL-02, AD-01 | NCP should not become active until clinically meaningful ADI exists. | NCP status/progress becomes believable in demo. | Active status remains clinically false. | Medium | Item 1 validators. |
| 4 | Prevent multiple open cycles and discharged-patient new cycles | SL-03, SL-04 | Keeps patient selection and report selection coherent. | User gets clear message when a cycle already exists or patient is discharged/transferred. | Demo can show duplicate/invalid cycles. | Small | Needs definition of open statuses. |
| 5 | Fix PES persistence and update invariants | DP-02, DP-03 | Prevents report/user mismatch and invalid PES edits. | Edited PES is either saved correctly or removed as an editable field; edits validate cleanly. | RND believes a reviewed PES was saved when it was not. | Small | Diagnosis form and request/controller alignment. |
| 6 | Block deleting last PES from active/completed NCP | DP-04, DI-02 | Protects active care plan rationale. | Delete action shows clear error or requires reopening workflow. | Active NCP can have intervention/monitoring with no diagnosis. | Small/Medium | Active/completed rule from item 3. |
| 7 | Require prescription before meal plan generation/finalization | MP-01, IV-02 | Meal plans need clinical targets. | Generate/finalize buttons fail with actionable message until prescription is complete. | Generic targets can produce clinically wrong plan. | Small/Medium | Item 1 intervention validator. |
| 8 | Add manual meal-plan safety checks for allergies/restrictions and basic target variance | MP-03, AD-04 | Prevents obvious unsafe menus. | User sees hard blocks for allergens and warnings for target variance/restrictions. | Defense can expose unsafe patient menu generation. | Medium | Assessment allergies/restrictions and intervention targets must be available. |
| 9 | Scope nested meal-plan routes to NCP -> intervention -> meal plan -> day -> item | MP-04 | Prevents cross-patient/cross-plan data corruption. | No visible change except invalid mismatched URLs return 404/403. | Direct API can mutate another plan. | Small | None. |
| 10 | Stop accepting client-supplied nutrient snapshots; calculate server-side | DI-03 | Protects report/nutrition data integrity. | Nutrient totals become trustworthy; malicious/stale client data ignored. | Reports can be falsified by client payload. | Small/Medium | Existing food/recipe/USDA snapshot builder. |
| 11 | Fix Patient Menu Plan reporting: exact plan selection, completeness gate, USDA item display | MP-06, MP-08, RP-04 | Patient menu report must match the intended meal plan and show all items. | RND can choose a specific meal plan to preview/download; USDA foods appear. | Wrong/latest menu or missing USDA items can be printed. | Medium | ReportBrowser/source change; PatientMenuPlanGenerator update. |
| 12 | Make NCP Summary report defensible: block/watermark incomplete and include/link selected meal plan | RP-01, RP-02, RP-03, AD-02 | NCP report is likely a core defense artifact. | Report shows complete initial/ADIME status and selected menu reference when relevant. | Incomplete official-looking clinical report undermines defense. | Medium | Items 1, 3, 11. |
| 13 | Fix Demographic Census risk source | RP-05 | Current report uses screening type as risk level. | Census risk categories become clinically meaningful. | Aggregate report is factually wrong. | Small | Latest completed assessment/NCP risk source. |
| 14 | Scope clinical documents and on-demand clinical reports | PO-02, PO-03 | Prevents obvious PHI/report access issue. | Unauthorized direct URLs return 403/404. | Defense questions about access control expose weak boundaries. | Small/Medium | Decide current RND access rule: owner-only or all-RND with patient scope. |

## Implement If Time Allows

| Item | Gaps Covered | Why It Helps | Why It Is Not Required |
|---|---|---|---|
| Add consistent assessment enum validation | AS-04 | Removes create/update inconsistency. | Not central to demo if standard values are used. |
| Add duplicate DB constraints for assessment/intervention/biochem | AS-05, IV-05, DI-01 | Stronger data integrity. | Normal controller path already blocks duplicates; migration cleanup can be risky close to defense. |
| Gate AI diagnosis/monitoring on completeness | DP-05, ME-04 | Prevents weak AI output. | Can avoid AI features during defense or present as draft-only. |
| Add lightweight follow-up visit metadata | ME-02 | Makes monitoring more clinically structured. | Meaningful monitoring validator is enough for defense. |
| Add condition-aware generation warning or narrow filter | MP-02 | Avoids misleading condition input. | Can avoid claiming condition-aware auto-generation unless implemented. |
| Scope meal-plan templates | MP-05, PO-04 | Prevents cross-user template issues. | Avoid template workflow during defense if not fixed. |
| Add report validation snapshot metadata | RP-06 | Better audit trail. | If final render/archive is gated, this can wait. |
| Block deletes of referenced foods/recipes with friendly errors | DI-04 | Avoids FK/runtime failures. | Use curated demo data and avoid delete scenario if time is short. |
| Flag clinical-use readiness for foods/recipes | DI-05 | Helps plan validation. | Curated demo food library can avoid incomplete items. |

## Defer Until After Defense

| Item | Gaps Covered | Why Deferred |
|---|---|---|
| Full clinical state machine with detailed states | SL-01 | Too broad; targeted completion gates make the demo defensible. |
| Reassessment/archive/cancel workflow | SL-05 | Useful but not needed for a clean initial NCP cycle demo. |
| Link each intervention to specific PES records | IV-03 | Improves traceability, but defense can explain intervention is NCP-level. |
| Full immutable food/recipe versioning | MP-07 | Large architectural change. Snapshot/report fixes are enough before defense. |
| Full model policy/assigned-patient architecture | PO-01 | Large access-control redesign; targeted scoping is enough for capstone demo. |
| Downstream invalidation graph | AS-03, AD-03 | Important long term, but high complexity and not required if demo follows linear workflow. |
| Advanced encounter model and scheduling | ME-02 partial | Follow-up metadata is enough if time allows; full scheduling can wait. |

## Recommended Final Clinical Care Scope

Build only these before defense:

1. Backend clinical validators for minimum Assessment, Diagnosis/PES, Intervention/Prescription, and Monitoring completeness.
2. Attachment upload no longer creates or completes Assessment.
3. NCP activation requires completed Assessment + finalized PES + completed Intervention/Prescription.
4. One open NCP cycle per active patient; block new cycles for discharged/transferred patients.
5. PES editor/persistence fixed and diagnosis update validation hardened.
6. Last PES deletion blocked once NCP is active unless workflow is reopened.
7. Prescription required before meal plan generation/finalization.
8. Manual meal item allergen hard-blocks and basic restriction/target warnings.
9. Nested route scoping for meal plan/day/item.
10. Server-side nutrient snapshot calculation.
11. Patient Menu Plan exact meal-plan selection, completeness checks, and USDA display fix.
12. NCP Summary report completeness gate/watermark and selected meal plan inclusion/link.
13. Demographic Census risk source corrected.
14. Clinical document and clinical report direct access scoped.

That is the minimum defensible build. Avoid adding full reassessment, full state machine, full policies, recipe versioning, and advanced encounter management before defense.

## Changes Explicitly Deferred

Do not touch these before defense unless all minimum scope is finished:

1. Full NCP state machine with many new statuses: too large and migration-heavy.
2. Reassessment, archive/cancel, and readmission workflows: not needed for initial cycle demo.
3. Full assigned-patient authorization model: valuable but broad; targeted PHI route scoping is enough.
4. Full food/recipe/nutrient versioning: high effort; fix report snapshots and server-calculated item snapshots first.
5. Full downstream invalidation/reopen graph: complex; defense can use linear workflow and targeted deletion blocks.
6. Full encounter/scheduling module: use required monitoring visit fields instead.
7. Advanced condition-aware recipe scoring: avoid claiming it unless implemented; basic allergen/restriction safety is more important.
8. Template sharing/marketplace behavior: avoid template demo or fix only if time allows.
9. Advanced report archival metadata: useful, but report gating/watermarking matters more.
10. Food library clinical-readiness governance: use curated demo foods and add readiness later.

## Suggested Implementation Order

1. Clinical completeness validators: Assessment, PES, Intervention/Prescription, Monitoring.
2. Attachment bypass fix.
3. Activation/status guard using validators.
4. Patient/NCP guard: one open cycle and no new cycle for discharged/transferred patients.
5. PES persistence/update/delete hardening.
6. Meal plan generation/finalization guard requiring prescription.
7. Manual meal item allergy/restriction/variance warnings.
8. Nested route scoping for meal plan/day/item.
9. Server-side nutrient snapshot fix.
10. Patient Menu Plan exact meal-plan selection and USDA display.
11. NCP Summary completeness behavior and selected meal plan inclusion/link.
12. Demographic Census risk source.
13. Clinical document/report direct access scoping.
14. Optional time-allowed fixes: AI gates, template scoping, DB uniqueness, friendlier food/recipe delete handling.

## Defense Story After These Changes

The Clinical Care module will be defensible as:

1. A guided ADIME/NCP workflow with backend-enforced minimum clinical completion.
2. A module that distinguishes draft/incomplete records from final clinical reports.
3. A meal planning workflow that uses prescriptions and prevents obvious restriction/allergy conflicts.
4. A report workflow where the RND selects the exact patient/menu report to preview/download.
5. A demo-safe system where direct API bypasses no longer invalidate the visible workflow.

