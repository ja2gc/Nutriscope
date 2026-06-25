# Clinical Care Workflow Deep Audit

Date: 2026-06-25  
Scope: Clinical Care / RND NCP lifecycle only. Food Service workflow is out of scope except where Clinical Care meal planning depends on food, recipe, or USDA data.  
Source of truth: reverse-engineered workflow in `docs/reviews/2026-06-25-clinical-care-ncp-workflow-audit.md`.

This document does not restate the workflow. It audits weaknesses in the implemented workflow, especially bypasses, incomplete ADIME records, report integrity, and data integrity.

## Evidence Map

Primary code paths reviewed:

| Area | Exact code paths |
|---|---|
| API routes | `backend/routes/api.php` RND NCP routes at lines 93-167; report routes at lines 52-60 and 313-318 |
| Patient/NCP | `backend/app/Http/Controllers/RND/PatientController.php::startNcpCycle`, `::destroy`; `backend/app/Http/Controllers/RND/NcpRecordController.php::destroy`; `backend/app/Models/NcpRecord.php`; `backend/database/migrations/2024_01_01_000003_create_ncp_records_table.php` |
| Assessment | `backend/app/Http/Controllers/RND/AssessmentController.php::{store,update,uploadAttachment,listAttachments}`; `backend/app/Http/Requests/RND/{StoreAssessmentRequest,UpdateAssessmentRequest}.php`; `backend/app/Models/Assessment.php`; `backend/database/migrations/2024_01_01_000004_create_assessments_table.php` |
| Diagnosis/PES | `backend/app/Http/Controllers/RND/DiagnosisController.php::{store,update,destroy}`; `backend/app/Http/Controllers/RND/AiDiagnosisController.php::{aiSuggest,aiApprove}`; `backend/app/Http/Requests/RND/{StoreDiagnosisRequest,UpdateDiagnosisRequest,AiSuggestDiagnosisRequest,AiApproveDiagnosisRequest}.php`; `backend/app/Models/Diagnosis.php`; `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` |
| Intervention/Rx | `backend/app/Http/Controllers/RND/InterventionController.php::{autofill,store,update,recommendations}`; `backend/app/Http/Requests/RND/{StoreInterventionRequest,UpdateInterventionRequest}.php`; `backend/app/Services/NutritionPrescriptionService.php`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`; intervention tab components |
| Meal plans/items | `backend/app/Http/Controllers/RND/MealPlanController.php`; `backend/app/Http/Controllers/RND/MealPlanItemController.php`; `backend/app/Services/MealPlanService.php`; `backend/app/Http/Requests/RND/{StoreMealPlanRequest,UpdateMealPlanRequest,GenerateMealPlanRequest,StoreMealPlanItemRequest}.php`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx` |
| Food/recipe/USDA | `backend/app/Http/Controllers/RND/{FoodItemController,RecipeController,UsdaController}.php`; `backend/app/Services/UsdaService.php`; `backend/app/Models/{FoodItem,Recipe,MealPlanItem}.php`; `backend/app/Http/Requests/{StoreFoodItemRequest,StoreRecipeRequest}.php` |
| Monitoring | `backend/app/Http/Controllers/RND/MonitoringController.php::{store,update,destroy,summary,aiReview,plan}`; `backend/app/Http/Requests/RND/{StoreMonitoringRequest,UpdateMonitoringRequest}.php`; `backend/app/Services/{MonitoringPlanService,MonitoringSummaryService}.php`; `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx` |
| Reports | `backend/app/Http/Controllers/ReportController.php::{instances,render,archive,store}`; `backend/app/Services/Reports/{ReportBrowser,ReportService}.php`; `backend/app/Services/Reports/Instances/{EntityInstanceSource,PeriodInstanceSource}.php`; `backend/app/Services/Reports/Generators/{NcpSummaryGenerator,PatientMenuPlanGenerator,DemographicCensusGenerator}.php`; Blade views under `backend/resources/views/reports/` |
| Frontend gates | `frontend/lib/ncpWorkflow.ts`; `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`; `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`; report UI `frontend/app/(rnd)/reports/page.tsx`, `frontend/components/reports/ReportsBrowser.tsx` |
| Policies/authorization | `backend/app/Policies` is absent; controllers generally rely on `auth:sanctum` + role middleware and route model binding rather than model policies or parent-child scoped binding |

## Enforcement Matrix

| Workflow Step | Frontend Enforcement | Backend Enforcement | Database Enforcement | Can Be Bypassed? |
|---|---|---|---|---|
| Patient selection | Patient profile pages route users through selected patient. No status gate for discharged/transferred patients. | `PatientController::startNcpCycle` accepts any bound `Patient`. | `patients.status` enum only: Active, Discharged, Transferred. | Yes. Direct API can create NCP for discharged/transferred patients. |
| NCP creation | `createNcpRecord` buttons in patient UI; no visible one-open-cycle guard. | Creates draft NCP unconditionally in `PatientController::startNcpCycle`. | No unique constraint for one draft/active NCP per patient. | Yes. Multiple draft/active cycles can be created. |
| Assessment | Assessment step always available in `frontend/lib/ncpWorkflow.ts`. | `AssessmentController::store` blocks duplicate via relationship lookup only; accepts almost all-null data. `uploadAttachment` can `firstOrCreate` an assessment. | No unique `assessments.ncp_record_id`; columns mostly nullable. | Yes. Empty or attachment-created assessment satisfies downstream gates. |
| Diagnosis | `getNcpStepState` requires any assessment object. Diagnosis page has UI completion checks. | `DiagnosisController::store` requires assessment existence, but not assessment completeness. AI approve same; AI suggest does not require assessment. | DB requires diagnosis fields but not clinical validity; no dependency constraint to assessment. | Yes. Blank assessment is enough; direct API can delete all diagnoses later. |
| PES statement | UI exposes editable `pesOverride`. | Backend ignores client PES on store and rebuilds from fields; update allows nullable fields that can corrupt/rebuild PES. | `diagnoses.pes_statement` non-null at creation, but no valid PES structure constraint. | Yes. Manual PES edit is not persisted; direct update can create invalid state or server error. |
| Intervention | Intervention page blocks if assessment/diagnosis missing; `ensureIntervention()` can create empty intervention. | `InterventionController::store` requires at least one diagnosis, not completeness; all intervention fields nullable; activates NCP if A+D+I exist. | No unique intervention per NCP; intervention fields nullable. | Yes. Empty intervention can activate NCP; after diagnosis deletion, intervention remains. |
| Nutrition prescription | Goal modal requires selected goal/stage in UI. | `autofill` requires goal_type and weight/height/DOB/sex; `store/update` do not require generated targets. | Target fields nullable. | Yes. Direct API can create intervention with no prescription targets. |
| Meal planning | UI is embedded in intervention page. | `MealPlanController` requires intervention existence; generator can default missing targets; item routes lack parent scoping. | Meal plan FK to intervention; item FK to day/food/recipe; no clinical target/allergen constraints. | Yes. Plan can be manual, off-target, wrong NCP, or based on another plan/day/item route ID. |
| Monitoring/Evaluation | Monitoring page blocks if A/D/I missing. | `MonitoringController::store` only requires intervention exists; monitoring fields nullable. | Monitoring FK to NCP only; fields nullable. | Yes. After empty intervention or deleted diagnoses, monitoring can be created. |
| Completion/discharge | No complete/discharge workflow found in NCP UI. | No NCP status transition controller except draft to active in `InterventionController::store`. | `ncp_records.status` enum only. No transition checks. | Yes. Status can become active without complete ADIME; completed/discharged are effectively unmanaged. |
| Attachments/documents | Assessment UI attaches documents to cycle. | `AssessmentController::uploadAttachment` creates blank assessment; `ScreeningDocumentController` has no ownership/scope check. | `screening_documents.assessment_id` nullable; no cascade; patient delete manually deletes. | Yes. Attachments can create clinical state and document routes can access by ID. |
| Reports | Report browser lists renderable instances by shallow data presence. | `ReportController::render/archive` only calls `hasData`; report generators tolerate missing clinical sections. | Reports store generated PDF path/snapshot, not completeness metadata. | Yes. Incomplete NCP and menu reports render/archive. |

## Actual Lifecycle State Review

Implemented state values:

- `backend/database/migrations/2024_01_01_000003_create_ncp_records_table.php`: `type` enum `new|followup|reassessment`; `status` enum `draft|active|completed|discharged`.
- `backend/app/Models/NcpRecord.php`: fillable includes `type`, `status`, `risk_score`.
- `backend/app/Http/Controllers/RND/PatientController.php::startNcpCycle`: always creates `type = new`, `status = draft`.
- `backend/app/Http/Controllers/RND/InterventionController.php::store`: updates `draft -> active` when `type === new`, assessment exists, diagnosis exists, and intervention was created.
- No controller/service was found that transitions `active -> completed`, `active -> discharged`, `completed -> reassessment`, `discharged -> reassessment`, or `archived`.
- No `archived` NCP status exists, although the requested clinical lifecycle includes Archived and report archiving exists separately.

Actual state diagram:

```mermaid
stateDiagram-v2
    [*] --> draft: PatientController::startNcpCycle
    draft --> active: InterventionController::store\nif assessment exists and diagnoses exist
    draft --> draft: Assessment / Diagnosis / Attachments / Deletes / Reports
    active --> active: Updates / Meal Plans / Monitoring / Reports
    completed: enum value exists\nno transition path found
    discharged: enum value exists\nno transition path found
    archived: not an NCP status
```

Lifecycle gaps:

| Lifecycle Concept | Actual Implementation | Audit Result |
|---|---|---|
| Draft | Created for every new NCP. | Exists but has no completeness rules. |
| Active | Set automatically when A+D+I records exist. | Invalid activation possible with blank assessment and empty intervention. |
| Completed | DB enum only. | Missing transition rules, required completion criteria, and UI action. |
| Discharged | DB enum only, separate from `patients.status = Discharged`. | Missing synchronization and transition rules. |
| Reassessment | NCP `type` enum exists but `startNcpCycle` always creates `new`. | Reassessment cannot be started as a first-class workflow. |
| Archived | No NCP status. | Missing state; deletion is used where archival should likely exist. |

## ADIME Compliance Review

| ADIME Requirement | Current Support | Missing Requirement / Bypass |
|---|---|---|
| Assessment must contain enough clinical data for diagnosis and prescription. | Assessment record can store dietary, anthropometric, biochemical, client history, allergies, restrictions, summary. | Store request makes all assessment clinical fields nullable. Attachment upload can create blank assessment. No completion flag or minimum clinical dataset. |
| Diagnosis must derive from assessment evidence. | Diagnosis store requires an assessment row. PES is generated from problem/etiology/signs. | Assessment existence is treated as assessment completion. AI suggest can run without assessment. Diagnosis is not tied to specific assessment evidence/version. |
| PES must be clinically reviewed and persisted. | Backend builds PES. UI has PES editor. | UI PES override is discarded by payload. No explicit PES review/approval status. Update request can null fields. |
| Intervention must follow diagnosis and include prescription/education/counseling. | Intervention store requires at least one diagnosis. NutritionPrescriptionService can autofill targets. | Intervention fields are nullable. Empty intervention activates NCP. No link from intervention goals to diagnosis/PES. |
| Meal plan must implement prescription and restrictions. | MealPlanService generates 7x5 plan and filters assessment allergies when available. | Conditions are unused; missing targets default to generic values; manual plans are not validated against prescription, allergies, restrictions, dislikes, medications, or disease rules. |
| Monitoring/Evaluation must evaluate intervention goals. | Monitoring stores weight, BMI, labs, intake, symptoms, goal achievement, summary. | Store request permits empty monitoring. Only intervention existence is enforced. No required comparison to targets. |
| Clinical report must distinguish complete from incomplete ADIME. | NCP Summary includes A/D/I/ME sections. | Report renders draft/incomplete NCPs with blanks or "No ..." text. No invalid/incomplete warning or hard stop. |

Conclusion: yes, a user can create a clinically incomplete record that appears operationally valid. The minimum backend path to an `active` NCP can be: create patient, create NCP, upload any attachment to create a blank assessment, create one diagnosis, create empty intervention. Monitoring and reports can then be added/rendered without a complete ADIME record.

## Detailed Findings

### WF-001 - Blank Assessment Satisfies Downstream Gates

| Field | Detail |
|---|---|
| Description | An assessment can be created with no clinically meaningful assessment data, then used to unlock Diagnosis, Intervention, activation, Monitoring, and reports. |
| Where it occurs | Assessment creation and all downstream gate checks. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/AssessmentController.php::store`; `backend/app/Http/Requests/RND/StoreAssessmentRequest.php`; `backend/database/migrations/2024_01_01_000004_create_assessments_table.php`; `backend/app/Http/Controllers/RND/DiagnosisController.php::store`; `frontend/lib/ncpWorkflow.ts::getNcpStepState`. |
| Reproduction steps | 1. Create patient. 2. Create NCP. 3. POST `/api/rnd/ncp-records/{id}/assessment` with `{}` or only trivial nullable fields. 4. Open/create diagnosis. 5. Proceed to intervention. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Incomplete assessment is treated as completed clinical evidence, undermining ADIME and all later documentation. |
| Recommended Fix | Define assessment completion criteria and use it for downstream gates and reporting eligibility. |

### WF-002 - Attachment Upload Creates a Blank Assessment

| Field | Detail |
|---|---|
| Description | Uploading a supporting document calls `firstOrCreate` on assessment, creating an assessment row without clinical assessment fields. This turns document upload into an Assessment bypass. |
| Where it occurs | NCP attachment/document workflow. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/AssessmentController.php::uploadAttachment`; `backend/routes/api.php` routes `POST ncp-records/{ncpRecord}/attachments`, `GET ncp-records/{ncpRecord}/attachments`; `backend/app/Models/ScreeningDocument.php`; `backend/database/migrations/2026_06_02_210746_create_screening_documents_table.php`. |
| Reproduction steps | 1. Create NCP. 2. Upload any valid PDF/JPEG/PNG to `/attachments`. 3. Fetch NCP. 4. Diagnosis step now sees an assessment relationship. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | A document record unintentionally becomes clinical completion evidence. |
| Recommended Fix | Decouple attachments from assessment creation; require an existing completed assessment or attach documents directly to NCP until assessment is explicitly saved. |

### WF-003 - Diagnosis Gate Checks Assessment Existence, Not Assessment Completeness

| Field | Detail |
|---|---|
| Description | Diagnosis creation is blocked only when no assessment row exists. It does not require dietary, anthropometric, biochemical, client history, restrictions, or RND summary. |
| Where it occurs | Diagnosis creation and AI diagnosis approval. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/DiagnosisController.php::store`; `backend/app/Http/Controllers/RND/AiDiagnosisController.php::aiApprove`; `backend/app/Http/Requests/RND/StoreDiagnosisRequest.php`; `backend/app/Models/Diagnosis.php`. |
| Reproduction steps | 1. Create blank assessment. 2. POST one diagnosis with problem/etiology/signs. 3. Diagnosis is accepted. |
| Clinical Severity | High |
| Data Integrity Severity | Medium |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | PES statements can be created without documented assessment evidence. |
| Recommended Fix | Gate Diagnosis on assessment completion and require diagnosis evidence references to assessment data. |

### WF-004 - AI Diagnosis Suggestion Runs Without Assessment

| Field | Detail |
|---|---|
| Description | AI suggestion does not require assessment existence, even though AI approval and manual diagnosis creation do. This allows draft clinical reasoning without the expected ADIME starting point. |
| Where it occurs | AI diagnosis suggestion. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/AiDiagnosisController.php::aiSuggest`; `backend/routes/api.php` `POST ncp-records/{ncpRecord}/diagnoses/ai-suggest`; `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` AI tab. |
| Reproduction steps | 1. Create patient and NCP. 2. Without assessment, POST AI suggest. 3. Suggestions are generated from patient medical diagnosis and sparse context. |
| Clinical Severity | Medium |
| Data Integrity Severity | Low |
| Reporting Severity | Medium |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | User can be guided toward diagnoses before assessment is documented. |
| Recommended Fix | Align AI suggest eligibility with manual diagnosis eligibility or clearly mark it as non-record draft unavailable for approval until assessment completion. |

### WF-005 - PES Override UI Is Not Persisted

| Field | Detail |
|---|---|
| Description | The Diagnosis page allows the RND to edit the PES statement, but `buildPayload` does not send `pes_statement`. Backend always rebuilds the PES from problem, etiology, and signs. |
| Where it occurs | PES statement workflow. |
| Exact code paths involved | `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` `pesOverride`, `buildPayload`; `backend/app/Http/Controllers/RND/DiagnosisController.php::store`; `backend/app/Models/Diagnosis.php::buildPes`; `backend/app/Http/Requests/RND/StoreDiagnosisRequest.php`. |
| Reproduction steps | 1. Open Diagnosis PES tab. 2. Edit PES wording. 3. Save. 4. Reload diagnosis list. 5. Saved PES is backend-generated, not the edited text. |
| Clinical Severity | Medium |
| Data Integrity Severity | Medium |
| Reporting Severity | Medium |
| UX Severity | High |
| Likelihood | High |
| Impact | RND believes clinically reviewed text was saved, but report uses a different PES. |
| Recommended Fix | Decide whether PES is generated-only or editable; if editable, persist and audit the reviewed PES separately from source fields. |

### WF-006 - Diagnosis Update Allows Incomplete or Error-Prone PES State

| Field | Detail |
|---|---|
| Description | Diagnosis update fields are nullable. Sending null or empty diagnosis components can either violate DB constraints, rebuild an invalid PES, or produce a type/server error depending on payload. |
| Where it occurs | Diagnosis edit API. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/DiagnosisController.php::update`; `backend/app/Http/Requests/RND/UpdateDiagnosisRequest.php`; `backend/app/Models/Diagnosis.php::buildPes`; `backend/database/migrations/2024_01_01_000006_create_diagnoses_table.php`. |
| Reproduction steps | 1. Create valid diagnosis. 2. PATCH `/diagnoses/{diagnosis}` with `{ "etiology": null }` or empty clinical fields. 3. Observe invalid rebuild, DB failure, or server error. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Existing PES record can become incomplete or unstable after edit. |
| Recommended Fix | Make update validation preserve required diagnosis invariants and rebuild PES only from validated complete values. |

### WF-007 - Intervention Can Be Empty and Still Activate the NCP

| Field | Detail |
|---|---|
| Description | `InterventionController::store` accepts an empty request because intervention fields are nullable. If assessment and diagnosis rows exist, the NCP moves from draft to active even without prescription, education, counseling, goals, barriers, strategies, session type, or follow-up date. |
| Where it occurs | Intervention creation and NCP activation. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/InterventionController.php::store`; `backend/app/Http/Requests/RND/StoreInterventionRequest.php`; `backend/database/migrations/2024_01_01_000007_create_interventions_table.php`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx::ensureIntervention`. |
| Reproduction steps | 1. Create blank assessment. 2. Create one diagnosis. 3. POST `/intervention` with `{}`. 4. Fetch NCP. 5. `status` is now `active`. |
| Clinical Severity | Critical |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | High |
| Likelihood | High |
| Impact | The system marks clinically incomplete ADIME documentation as active care. |
| Recommended Fix | Separate intervention draft creation from NCP activation and define required intervention/prescription fields before activation. |

### WF-008 - Intervention Gate Requires Any Diagnosis, Not Active/Complete Diagnosis

| Field | Detail |
|---|---|
| Description | Intervention creation checks that the NCP has at least one diagnosis row. It does not verify diagnosis completeness beyond database presence, nor whether the diagnosis still exists after activation. |
| Where it occurs | Intervention creation and post-activation edits/deletes. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/InterventionController.php::store`; `backend/app/Http/Controllers/RND/DiagnosisController.php::destroy`; `backend/app/Models/NcpRecord.php`; `backend/routes/api.php` diagnosis delete and intervention routes. |
| Reproduction steps | 1. Create assessment, diagnosis, intervention. 2. Delete all diagnoses. 3. NCP remains active and intervention remains accessible. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Active intervention can exist with no diagnosis/PES basis. |
| Recommended Fix | Re-evaluate NCP completeness on diagnosis deletion and prevent deleting the last required diagnosis without resolving dependent intervention state. |

### WF-009 - Nutrition Prescription Is Optional Outside Autofill

| Field | Detail |
|---|---|
| Description | `autofill` has useful prerequisites, but prescription target fields on intervention store/update are nullable. Direct API calls can create/update interventions without any nutrition prescription. |
| Where it occurs | Nutrition prescription generation and persistence. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/InterventionController.php::{autofill,store,update}`; `backend/app/Http/Requests/RND/{StoreInterventionRequest,UpdateInterventionRequest}.php`; `backend/app/Services/NutritionPrescriptionService.php`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/NutritionPrescriptionForm.tsx`. |
| Reproduction steps | 1. Complete A+D minimal path. 2. POST `/intervention` with `{}` or only notes. 3. Generate reports or meal plan. |
| Clinical Severity | Critical |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Care plan may lack core prescription targets while appearing active. |
| Recommended Fix | Make prescription completion a separate validated state required before meal planning/report completion. |

### WF-010 - Monitoring Requires Only Intervention Existence

| Field | Detail |
|---|---|
| Description | Backend monitoring creation checks only for an intervention. It does not require a completed assessment, diagnosis/PES, prescription, active status, meal plan, or prior follow-up schedule. Monitoring fields are nullable. |
| Where it occurs | Monitoring/Evaluation creation. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MonitoringController.php::store`; `backend/app/Http/Requests/RND/StoreMonitoringRequest.php`; `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`; `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/_components/LogVisitForm.tsx`. |
| Reproduction steps | 1. Create minimal empty intervention. 2. POST `/monitorings` with `{}`. 3. Monitoring entry is accepted. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | M/E records can exist without evaluable goals or measurements. |
| Recommended Fix | Require monitoring visit fields and verify a completed intervention/prescription exists. |

### WF-011 - NCP Status Has No Real State Machine

| Field | Detail |
|---|---|
| Description | Status values exist but only draft and active have an implemented path. Active is automatic and weak. Completed/discharged are not managed. Archived is absent. |
| Where it occurs | NCP lifecycle. |
| Exact code paths involved | `backend/database/migrations/2024_01_01_000003_create_ncp_records_table.php`; `backend/app/Models/NcpRecord.php`; `backend/app/Http/Controllers/RND/PatientController.php::startNcpCycle`; `backend/app/Http/Controllers/RND/InterventionController.php::store`. |
| Reproduction steps | 1. Create NCP. 2. Observe draft. 3. Create minimal intervention. 4. Observe active. 5. Search UI/API for complete/discharge/reassessment/archive transition; none found. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | Medium |
| UX Severity | High |
| Likelihood | High |
| Impact | Users cannot reliably determine whether a cycle is draft, active care, complete, discharged, or reassessment. |
| Recommended Fix | Define and enforce allowed transitions and completion criteria. |

### WF-012 - Multiple Open NCP Cycles per Patient Are Allowed

| Field | Detail |
|---|---|
| Description | Users can create unlimited draft/active NCP cycles for the same patient. The UI often treats latest record as current, while reports can select any NCP. |
| Where it occurs | Patient NCP creation and patient profile. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/PatientController.php::startNcpCycle`; `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx::handleStartNewCycle`; `backend/database/migrations/2024_01_01_000003_create_ncp_records_table.php`. |
| Reproduction steps | 1. Open active patient. 2. Click/start new cycle repeatedly or POST repeatedly. 3. Multiple drafts are created. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | Medium |
| UX Severity | High |
| Likelihood | High |
| Impact | Assessment, diagnosis, intervention, meal plan, and reports can be split across competing cycles. |
| Recommended Fix | Define one open NCP per patient or explicit reassessment/follow-up cycle rules. |

### WF-013 - Discharged/Transferred Patients Can Receive New NCP Cycles

| Field | Detail |
|---|---|
| Description | Patient status is stored, but NCP creation, assessment, intervention, meal plan, monitoring, and report generation do not gate on active patient status. |
| Where it occurs | Patient selection and lifecycle. |
| Exact code paths involved | `backend/app/Http/Requests/RND/UpdatePatientRequest.php`; `backend/app/Http/Controllers/RND/PatientController.php::startNcpCycle`; NCP child controllers; `backend/database/migrations/2024_01_01_000002_create_patients_table.php`. |
| Reproduction steps | 1. Set patient status to Discharged. 2. POST `/patients/{patient}/ncp-records`. 3. Continue ADIME steps. |
| Clinical Severity | High |
| Data Integrity Severity | Medium |
| Reporting Severity | Medium |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Records can be created after discharge without explicit readmission/reassessment semantics. |
| Recommended Fix | Define allowed post-discharge actions and require a readmission/reassessment workflow for new care. |

### WF-014 - Parent-Child Route Scoping Is Missing on Meal Plan Routes

| Field | Detail |
|---|---|
| Description | Routes include `ncpRecord`, `mealPlan`, `day`, and `item`, but controllers do not consistently verify that each child belongs to the parent NCP/meal plan/day. |
| Where it occurs | Meal plan show/update/delete, item list/create/update/delete, templates. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MealPlanController.php::{show,update,destroy,saveTemplate}`; `backend/app/Http/Controllers/RND/MealPlanItemController.php::{allItems,index,store,update,destroy}`; `backend/routes/api.php` meal plan routes lines 139-156. |
| Reproduction steps | 1. Create meal plan under NCP A. 2. Call `/ncp-records/{NCP_B}/meal-plans/{plan_A}` or nested item route with mismatched day/item IDs. 3. Operation succeeds or reads unrelated data. |
| Clinical Severity | High |
| Data Integrity Severity | Critical |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | One patient's plan/items can be modified through another patient's NCP route. |
| Recommended Fix | Enforce scoped route binding or explicit parent-child ownership checks for every nested route. |

### WF-015 - Meal Plan Templates Are Not Owner-Scoped on Show/Delete/Use

| Field | Detail |
|---|---|
| Description | Template listing is owner-scoped, but show, delete, and from-template use route/model IDs without owner checks. |
| Where it occurs | Meal plan template workflow. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MealPlanController.php::{templates,showTemplate,destroyTemplate,fromTemplate}`; `backend/database/migrations/2026_06_02_210749_create_meal_plan_templates_table.php`; `backend/database/migrations/2026_06_02_210750_create_meal_plan_template_days_table.php`. |
| Reproduction steps | 1. Create template as RND A. 2. Login as RND B. 3. Call show/delete/from-template with A's template ID. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | Medium |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Cross-user template leakage or deletion; wrong plan can be applied to patient. |
| Recommended Fix | Scope template read/delete/use to authenticated RND owner or explicitly shared templates. |

### WF-016 - Meal Plan Generation Ignores Clinical Conditions and Defaults Missing Prescription Targets

| Field | Detail |
|---|---|
| Description | MealPlanService accepts conditions but does not apply them. Missing intervention targets default to generic 2000 kcal, 70g protein, 250g carbs, 60g fat. |
| Where it occurs | Auto-generated meal plans. |
| Exact code paths involved | `backend/app/Services/MealPlanService.php::generate`; `backend/app/Http/Controllers/RND/MealPlanController.php::generate`; `backend/app/Http/Requests/RND/GenerateMealPlanRequest.php`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/MealPlanSection.tsx`. |
| Reproduction steps | 1. Create intervention without prescription targets. 2. Generate meal plan. 3. Plan is generated against default targets and not disease conditions. |
| Clinical Severity | Critical |
| Data Integrity Severity | Medium |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Generated menu can look valid while ignoring the clinical prescription or condition. |
| Recommended Fix | Require prescription targets and condition rules before generation; fail generation when required clinical inputs are absent. |

### WF-017 - Manual Meal Plans Are Not Validated Against Prescription, Allergies, or Restrictions

| Field | Detail |
|---|---|
| Description | Manual item creation validates food/recipe/USDA source and quantity/unit only. It does not enforce allergies, restrictions, dislikes, medication interactions, disease limits, or macro/micro target variance. |
| Where it occurs | Manual meal plan item creation/update. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MealPlanItemController.php::{store,update}`; `backend/app/Http/Requests/RND/StoreMealPlanItemRequest.php`; `backend/app/Models/FoodItem.php`; `backend/app/Models/Recipe.php`; `backend/app/Models/MealPlanItem.php`. |
| Reproduction steps | 1. Record peanut allergy in assessment. 2. Create manual meal plan item using food/recipe with peanut allergen. 3. Save succeeds. |
| Clinical Severity | Critical |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | High |
| Likelihood | Medium |
| Impact | Patient safety risk: restricted/allergenic food can be placed on a clinical patient menu. |
| Recommended Fix | Validate manual and edited items against patient clinical restrictions and prescription before save or finalization. |

### WF-018 - Client-Supplied Nutrient Snapshot Can Override Clinical Nutrition Data

| Field | Detail |
|---|---|
| Description | Meal plan item update accepts `nutrient_snapshot` from the client. This can make a meal item nutritionally inconsistent with its food/recipe/USDA source. |
| Where it occurs | Meal plan item edit. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MealPlanItemController.php::update`; `backend/app/Models/MealPlanItem.php`; `backend/database/migrations/2024_01_01_000011_create_meal_plans_table.php`. |
| Reproduction steps | 1. Create item for high-calorie food. 2. PATCH item with fake `nutrient_snapshot` showing low calories. 3. Reports/variance use persisted snapshot where applicable. |
| Clinical Severity | High |
| Data Integrity Severity | Critical |
| Reporting Severity | High |
| UX Severity | Low |
| Likelihood | Medium |
| Impact | Nutrient totals can be falsified or drift from source food. |
| Recommended Fix | Recalculate nutrient snapshots server-side from immutable source data; audit source and version. |

### WF-019 - USDA-Only Meal Items Can Disappear From Patient Menu Plan Report

| Field | Detail |
|---|---|
| Description | Patient Menu Plan report resolves display name from `foodItem` or `recipe`. Direct USDA `fdc_id` items with no saved food item/recipe have no name path and are skipped. |
| Where it occurs | Patient Menu Plan report. |
| Exact code paths involved | `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php::data`; `backend/app/Http/Controllers/RND/MealPlanItemController.php::buildSnapshot`; `backend/app/Services/UsdaService.php`; `backend/database/migrations/2026_06_05_000657_add_fdc_id_to_meal_plan_items.php`. |
| Reproduction steps | 1. Add a meal plan item using USDA `fdc_id` only. 2. Render patient menu plan. 3. USDA-only item is absent from grid if no linked name exists. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | Critical |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Printed menu can omit foods that are actually in the meal plan. |
| Recommended Fix | Persist display name/source metadata for USDA-only items and render it in reports. |

### WF-020 - Recipe Nutrient Totals Can Become Stale

| Field | Detail |
|---|---|
| Description | Recipe totals are recalculated on recipe store/update, but not automatically when underlying FoodItem nutrient values change. Existing recipes and meal plan snapshots can drift. |
| Where it occurs | Recipe nutrient calculations and meal planning. |
| Exact code paths involved | `backend/app/Models/Recipe.php::recalculateTotals`; `backend/app/Http/Controllers/RND/RecipeController.php::{store,update}`; `backend/app/Http/Controllers/RND/FoodItemController.php::update`; `backend/app/Console/Commands/RecalculateRecipeTotals.php`. |
| Reproduction steps | 1. Create food item and recipe using it. 2. Change food item's calories/protein. 3. Inspect recipe totals before running recalculation command. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Meal plan nutrition calculations can be based on outdated ingredient data. |
| Recommended Fix | Version or recalculate dependent recipe totals when food items change, and disclose snapshot basis. |

### WF-021 - Empty Recipes and Low-Nutrient Foods Can Enter Meal Planning

| Field | Detail |
|---|---|
| Description | Recipe creation requires only a name on POST; ingredients are optional. Food items require calories but macros/micros/allergens are optional. |
| Where it occurs | Food library and recipe library. |
| Exact code paths involved | `backend/app/Http/Requests/StoreRecipeRequest.php`; `backend/app/Http/Controllers/RND/RecipeController.php::store`; `backend/app/Http/Requests/StoreFoodItemRequest.php`; `backend/app/Http/Controllers/RND/FoodItemController.php::store`; `backend/app/Services/MealPlanService.php`. |
| Reproduction steps | 1. Create recipe with no ingredients. 2. Mark/use in meal planning. 3. Nutrient totals are zero or incomplete. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Generated/manual plans may include foods with incomplete nutrient and allergen data. |
| Recommended Fix | Define clinical-food completeness requirements before food/recipe can be used in Clinical Care meal plans. |

### WF-022 - Food/Recipe Deletion Has Historical and Runtime Risks

| Field | Detail |
|---|---|
| Description | Food and recipe destroy endpoints delete records directly. Meal plan item FKs may block deletion or, if changed later, break historical reporting. No archival/versioning is used for clinical food sources. |
| Where it occurs | Food database and recipe database deletion. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/FoodItemController.php::destroy`; `backend/app/Http/Controllers/RND/RecipeController.php::destroy`; `backend/database/migrations/2024_01_01_000011_create_meal_plans_table.php`; `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php`. |
| Reproduction steps | 1. Use food/recipe in meal plan. 2. Attempt delete food/recipe. 3. Either DB blocks unexpectedly or historical plan/report loses resolvable source if deletion semantics change. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | High |
| Likelihood | Medium |
| Impact | Clinical menus depend on mutable/deletable source records. |
| Recommended Fix | Use soft delete/archive and immutable clinical snapshots for food and recipe sources. |

### WF-023 - Reports Use Shallow `hasData` Checks

| Field | Detail |
|---|---|
| Description | Report rendering/archive verifies only that the selected entity exists in the report browser source. It does not verify clinical completeness. |
| Where it occurs | All clinical reports. |
| Exact code paths involved | `backend/app/Http/Controllers/ReportController.php::{render,archive}`; `backend/app/Services/Reports/ReportBrowser.php`; `backend/app/Services/Reports/Instances/EntityInstanceSource.php::hasData`; `backend/app/Services/Reports/Instances/PeriodInstanceSource.php::hasData`. |
| Reproduction steps | 1. Create draft NCP with no assessment or with blank assessment. 2. Render `ncp_summary` for that NCP. 3. PDF renders because NCP exists. |
| Clinical Severity | High |
| Data Integrity Severity | Medium |
| Reporting Severity | Critical |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Official-looking reports can be generated from incomplete clinical records. |
| Recommended Fix | Make report eligibility depend on report-specific minimum clinical data and surface missing data explicitly. |

### WF-024 - NCP Summary Silently Renders Missing ADIME Sections

| Field | Detail |
|---|---|
| Description | NCP Summary loads nullable assessment/intervention and maps empty diagnoses/monitorings. The view can show blank sections or "No diagnosis/monitoring" rather than treating incomplete ADIME as invalid. |
| Where it occurs | NCP Summary report. |
| Exact code paths involved | `backend/app/Services/Reports/Generators/NcpSummaryGenerator.php::data`; `backend/resources/views/reports/ncp-summary.blade.php`; `backend/app/Services/Reports/ReportBrowser.php` `ncp_summary` source. |
| Reproduction steps | 1. Create NCP only or A-only NCP. 2. Render/archive NCP Summary. 3. Report contains missing or placeholder clinical sections. |
| Clinical Severity | High |
| Data Integrity Severity | Medium |
| Reporting Severity | Critical |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Incomplete clinical documentation can be filed as a summary. |
| Recommended Fix | Add report completeness grading and block/archive only when required ADIME sections exist, or watermark as incomplete draft. |

### WF-025 - Patient Menu Plan Can Render Incomplete or Wrong Plan

| Field | Detail |
|---|---|
| Description | Patient Menu Plan source includes patients with any meal plan. Generator picks a provided meal_plan_id or latest plan for patient, without enforcing plan status, 7x5 completeness, prescription fit, or NCP ownership. |
| Where it occurs | Patient Menu Plan report. |
| Exact code paths involved | `backend/app/Services/Reports/ReportBrowser.php` `patient_menu_plan`; `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php::data`; `backend/app/Http/Controllers/RND/MealPlanController.php`. |
| Reproduction steps | 1. Create manual plan with one item. 2. Render patient menu plan. 3. Report renders mostly blank grid. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | Critical |
| UX Severity | High |
| Likelihood | High |
| Impact | Printed patient menu can omit meals or present an unapproved draft. |
| Recommended Fix | Require plan completeness/status and NCP scoping before report render/archive. |

### WF-026 - Demographic Census Risk Level Uses Screening Type, Not NCP Risk

| Field | Detail |
|---|---|
| Description | Demographic Census computes `risk_level` from `patients.screening_type` rather than `ncp_records.risk_score` or risk band. This conflates adult/pediatric screening type with risk. |
| Where it occurs | Demographic Census report. |
| Exact code paths involved | `backend/app/Services/Reports/Generators/DemographicCensusGenerator.php::data`; `backend/app/Services/RiskScoreCalculator.php`; `backend/app/Http/Controllers/RND/AssessmentController.php::store`; `backend/app/Models/NcpRecord.php`. |
| Reproduction steps | 1. Create assessment that calculates high risk score. 2. Render demographic census. 3. Risk grouping displays screening type values rather than calculated risk. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | Critical |
| UX Severity | Medium |
| Likelihood | High |
| Impact | Aggregate report misstates patient risk distribution. |
| Recommended Fix | Source census risk from latest completed NCP assessment risk band, not screening type. |

### WF-027 - On-Demand Clinical Report Rendering Is Not RND-Ownership Scoped

| Field | Detail |
|---|---|
| Description | Archived reports are owner-checked for show/download/view, but on-demand report render/archive is role-checked and data-source checked. It does not scope `ncp_summary` or `patient_menu_plan` to the current RND owner. |
| Where it occurs | Clinical report render/archive and instances. |
| Exact code paths involved | `backend/app/Http/Controllers/ReportController.php::{instances,render,archive,guardClinical}`; `backend/app/Services/Reports/ReportBrowser.php`; absence of `backend/app/Policies`; `backend/app/Http/Controllers/ReportController.php::authorizeOwner` only applies persisted `Report` rows. |
| Reproduction steps | 1. Login as RND B. 2. Guess/obtain NCP or patient ID owned by RND A. 3. Render `ncp_summary` or `patient_menu_plan`. |
| Clinical Severity | High |
| Data Integrity Severity | Medium |
| Reporting Severity | High |
| UX Severity | Low |
| Likelihood | Medium |
| Impact | PHI may be exposed across RND users where ownership boundaries are expected. |
| Recommended Fix | Define clinical record ownership/access policy and apply it to report instance browsing, rendering, and archiving. |

### WF-028 - Assessment/Intervention One-to-One Is Not Enforced by Database

| Field | Detail |
|---|---|
| Description | Models imply one assessment and one intervention per NCP, but database migrations do not add unique constraints on `assessments.ncp_record_id` or `interventions.ncp_record_id`. Controllers check duplicates at application level only. |
| Where it occurs | Assessment and intervention persistence. |
| Exact code paths involved | `backend/database/migrations/2024_01_01_000004_create_assessments_table.php`; `backend/database/migrations/2024_01_01_000007_create_interventions_table.php`; `backend/app/Models/NcpRecord.php`; `backend/app/Http/Controllers/RND/{AssessmentController,InterventionController}.php`. |
| Reproduction steps | 1. Race two POSTs to assessment or intervention. 2. Or insert directly/import data. 3. Multiple rows can exist without DB preventing it. |
| Clinical Severity | Medium |
| Data Integrity Severity | Critical |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | Low to Medium |
| Impact | Reports/services using `hasOne` can select arbitrary row and hide duplicates. |
| Recommended Fix | Add database uniqueness for true one-to-one clinical records and reconcile existing duplicates. |

### WF-029 - Biochemical One-to-One Is Not Enforced by Database

| Field | Detail |
|---|---|
| Description | Assessment has one biochemical data relationship, but DB does not enforce unique `biochemical_data.assessment_id`. |
| Where it occurs | Biochemical assessment data. |
| Exact code paths involved | `backend/database/migrations/2024_01_01_000005_create_biochemical_data_table.php`; `backend/app/Models/Assessment.php`; `backend/app/Models/BiochemicalData.php`; `backend/app/Http/Controllers/RND/AssessmentController.php::store`. |
| Reproduction steps | 1. Import/insert multiple biochemical rows for one assessment. 2. Report/generator loads only one `hasOne` row. |
| Clinical Severity | Medium |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Low |
| Likelihood | Low |
| Impact | Lab evidence may be duplicated or hidden. |
| Recommended Fix | Enforce one biochemical dataset per assessment or remodel as dated lab observations. |

### WF-030 - Clinical Document Routes Lack Ownership/Parent Scope Checks

| Field | Detail |
|---|---|
| Description | Screening document show/file/delete accept a bound document ID and do not check the current RND, patient, NCP, or assessment ownership/scope. |
| Where it occurs | Attachment document access. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/ScreeningDocumentController.php::{show,file,destroy}`; `backend/routes/api.php` `screening-documents/{screeningDocument}` routes; `backend/database/migrations/2026_06_02_210746_create_screening_documents_table.php`; absence of `backend/app/Policies`. |
| Reproduction steps | 1. Login as any RND. 2. Request `/api/rnd/screening-documents/{id}` or `/file` for another patient's document ID. 3. File response is returned if ID exists. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | Medium |
| UX Severity | Low |
| Likelihood | Medium |
| Impact | PHI document exposure and unauthorized deletion risk. |
| Recommended Fix | Apply document ownership/patient scope policy to show/file/delete. |

### WF-031 - Deletion Rules Permit Loss of Partial Clinical Records

| Field | Detail |
|---|---|
| Description | Patient and NCP deletion blocks only records that have assessment + diagnosis + intervention. Partial records can be deleted, including assessment-only or diagnosis-only clinical evidence. |
| Where it occurs | Patient/NCP deletion. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/PatientController.php::destroy`; `backend/app/Http/Controllers/RND/NcpRecordController.php::destroy`; cascade FKs in clinical migrations. |
| Reproduction steps | 1. Create patient/NCP/assessment. 2. Delete NCP or patient before diagnosis/intervention. 3. Partial clinical evidence is removed. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | Medium |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Early but clinically relevant assessment evidence can be lost instead of archived. |
| Recommended Fix | Use archival/cancellation states with audit reason instead of destructive deletion for any clinical record with child data. |

### WF-032 - No Model Policies or Ownership Checks for Most Clinical Resources

| Field | Detail |
|---|---|
| Description | RND endpoints rely on role middleware and route model binding. No `backend/app/Policies` directory was found, and controllers generally do not call `authorize`. |
| Where it occurs | Cross-patient/cross-RND clinical access. |
| Exact code paths involved | `backend/routes/api.php`; all RND controllers; absence of `backend/app/Policies`; `backend/app/Http/Middleware/RoleMiddleware.php`; `backend/app/Http/Middleware/AuditMiddleware.php`. |
| Reproduction steps | 1. Authenticate as any RND. 2. Request another RND's patient/NCP/meal plan/document by ID. 3. Role passes; resource loads unless a local manual scope check exists. |
| Clinical Severity | High |
| Data Integrity Severity | Critical |
| Reporting Severity | High |
| UX Severity | Low |
| Likelihood | Medium |
| Impact | Authorization is too coarse for PHI and clinical ownership. |
| Recommended Fix | Define clinical access policy and enforce it in controllers, reports, and nested route bindings. |

### WF-033 - Frontend Gates Are Advisory, Not Security or Integrity Controls

| Field | Detail |
|---|---|
| Description | `frontend/lib/ncpWorkflow.ts` and page-level workflow blocks improve navigation, but backend and DB do not mirror the same rules. Direct API calls bypass most sequence requirements. |
| Where it occurs | All clinical workflow steps. |
| Exact code paths involved | `frontend/lib/ncpWorkflow.ts`; `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx`; `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`; `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`; corresponding backend controllers. |
| Reproduction steps | 1. Use browser dev tools/curl/Postman against API routes. 2. Create minimal/empty records according to backend-only gates. |
| Clinical Severity | High |
| Data Integrity Severity | High |
| Reporting Severity | High |
| UX Severity | Medium |
| Likelihood | High |
| Impact | UI appears sequential but system of record allows non-sequential/incomplete ADIME. |
| Recommended Fix | Treat frontend gates as UX only; enforce workflow invariants in backend/domain layer and DB where applicable. |

### WF-034 - Monitoring/AI Review Can Operate on Weak or Empty Clinical Inputs

| Field | Detail |
|---|---|
| Description | Monitoring summary/AI review depends on monitoring plan and existing records, but monitoring entries can be empty and intervention targets can be absent. AI output may therefore evaluate insufficient data. |
| Where it occurs | Monitoring summary and AI review. |
| Exact code paths involved | `backend/app/Http/Controllers/RND/MonitoringController.php::{summary,aiReview}`; `backend/app/Services/{MonitoringPlanService,MonitoringSummaryService}.php`; `backend/app/Http/Requests/RND/StoreMonitoringRequest.php`. |
| Reproduction steps | 1. Create empty intervention. 2. Create empty monitoring. 3. Fetch summary or request AI review where plan allows. |
| Clinical Severity | Medium |
| Data Integrity Severity | Medium |
| Reporting Severity | Medium |
| UX Severity | Medium |
| Likelihood | Medium |
| Impact | Clinical trend/AI review can imply evaluation where no valid measures exist. |
| Recommended Fix | Require measurable monitoring payloads and suppress/reject AI review when required baseline/target data is missing. |

### WF-035 - NCP Summary Does Not Include Meal Plan and Patient Menu Plan Cannot Select a Specific Plan in UI

| Field | Detail |
|---|---|
| Description | The NCP Summary report does not include the patient's clinical meal plan. Meal planning is only available through the separate Patient Menu Plan report. In that report, the UI lets the RND select a patient, but not a specific meal plan/menu for that patient. The backend generator can accept `meal_plan_id`, but the report browser source emits only `patient_id`, so preview/download defaults to the latest meal plan by `week_start_date`. |
| Where it occurs | Clinical report generation and report browser UI. |
| Exact code paths involved | `backend/app/Services/Reports/Generators/NcpSummaryGenerator.php::data`; `backend/app/Services/Reports/Generators/PatientMenuPlanGenerator.php::data`; `backend/app/Services/Reports/ReportBrowser.php` `patient_menu_plan` and `ncp_summary` sources; `backend/app/Http/Controllers/ReportController.php::{instances,render,archive}`; `frontend/components/reports/ReportsBrowser.tsx::InstancesPanel`; `frontend/services/reportService.ts`. |
| Reproduction steps | 1. Create a patient with one NCP and two meal plans with different `week_start_date` values. 2. Render `ncp_summary`; no meal plan/menu appears. 3. Open Reports > Patient Menu Plan. 4. Select the patient. 5. There is no UI control to select the exact meal plan. 6. Preview/download renders the latest plan. |
| Clinical Severity | Medium |
| Data Integrity Severity | Medium |
| Reporting Severity | Critical |
| UX Severity | High |
| Likelihood | High |
| Impact | The RND can download the wrong patient menu when multiple clinical meal plans exist, and the NCP Summary does not present the full clinical care package if meal planning is expected as part of the NCP record. |
| Recommended Fix | Clarify report semantics: either include/link the selected meal plan in NCP Summary, or keep reports separate but expose specific `meal_plan_id` selection and scope it to the selected patient/NCP. |

## Report Completeness Matrix

| Report | Minimum Data Clinically Required | Actual Implementation Requires | Incomplete Report Can Generate? | Blank/Omitted Sections | Missing Data Surfaced? | Key Code Paths |
|---|---|---|---|---|---|---|
| NCP Summary | Patient demographics; completed assessment; at least one valid PES diagnosis; completed intervention/prescription; monitoring if cycle is beyond initial visit; status/context; if meal planning is considered part of the filed NCP package, the selected meal plan should be included or referenced. | Existing `ncp_records.id` in `ReportBrowser`/`EntityInstanceSource::hasData`. Generator tolerates nullable assessment/intervention and empty diagnoses/monitorings. It does not load or render meal plans. | Yes. | Yes: assessment/intervention blanks; "No diagnosis recorded"; "No monitoring entries yet"; no meal plan/menu section. | Mostly silent/placeholder, not blocking. | `ReportController::render/archive`; `ReportBrowser` `ncp_summary`; `NcpSummaryGenerator::data`; `reports/ncp-summary.blade.php`. |
| Patient Menu Plan | Patient; NCP; selected active/final meal plan; complete 7-day x 5-meal grid or approved coverage; each item with display name, quantity, source; prescription variance; allergy/restriction validation. | Patient has any `MealPlan` (`whereIn patient_id`). `ReportBrowser` emits `patient_id` only. `PatientMenuPlanGenerator` can accept `meal_plan_id`, but UI does not expose specific plan selection, so patient selection renders the latest plan. | Yes. | Yes: empty cells; USDA-only items can be omitted; wrong/latest plan can be shown when user intended a different plan. | Silent; no completeness/variance/error for ambiguous plan selection. | `ReportBrowser` `patient_menu_plan`; `PatientMenuPlanGenerator::data`; `ReportsBrowser.tsx::InstancesPanel`; `MealPlanController`; `MealPlanItemController`. |
| Demographic Census | Period; admitted patient cohort; accurate age/sex/ward/diagnosis; latest completed nutritional status and risk band. | Patients in admission date range. Nutritional status from latest assessment. Risk level from patient `screening_type`. | Yes. | Unspecified buckets for missing fields. | Silent aggregation under "Unspecified"; risk source is clinically wrong. | `ReportBrowser` `demographic_census`; `DemographicCensusGenerator::data`. |
| Archived clinical reports | Same as source report plus immutable snapshot and signatories. | Same shallow `hasData`; generated PDF stored with branding/signatory snapshot. | Yes. | Same as source report. | No completeness metadata. | `ReportController::archive`; `ReportService::generate`; report generators. |

## Data Integrity Review

Concrete risks:

| Risk | Example | Code Paths |
|---|---|---|
| Orphan-like clinical semantics despite FK integrity | Active intervention remains after all diagnoses are deleted. | `DiagnosisController::destroy`; `InterventionController::store`; `NcpRecord` status handling |
| Missing one-to-one uniqueness | Multiple assessments/interventions/biochemical rows can exist under race/import/direct DB. | Migrations `assessments`, `interventions`, `biochemical_data`; `NcpRecord` relationships |
| Nullable fields that are clinically required | Assessment, intervention, monitoring allow all important fields nullable. | `StoreAssessmentRequest`; `StoreInterventionRequest`; `StoreMonitoringRequest`; related migrations |
| Missing ownership checks | Any RND role can access model-bound patient/NCP/document/report instances by ID unless manually scoped. | RND controllers; `ScreeningDocumentController`; `ReportController::render/archive`; absence of policies |
| Missing parent-child scoping | Mismatched NCP/mealPlan/day/item route IDs can act on unrelated rows. | `MealPlanController`; `MealPlanItemController`; route model binding in `api.php` |
| Deletion risk | Patient/NCP partial records can be deleted; food/recipe deletion impacts historical plan sources. | `PatientController::destroy`; `NcpRecordController::destroy`; `FoodItemController::destroy`; `RecipeController::destroy` |
| Historical data risk | Recipe totals change when recalculated; food data updates can alter future plan calculations while old reports do not disclose source version. | `Recipe::recalculateTotals`; `FoodItemController::update`; `MealPlanItem.nutrient_snapshot` |
| Report data drift | On-demand reports stream current data; archived reports freeze PDF but not clinical completeness state. | `ReportController::render/archive`; `ReportService`; generators |

## Risk Matrix

| Rank | Finding ID | Severity | Primary Risk | Likelihood | Impact |
|---:|---|---|---|---|---|
| 1 | WF-007 | Critical | Empty intervention activates NCP | High | Active clinical care without intervention/prescription |
| 2 | WF-016 | Critical | Meal generation ignores conditions/defaults targets | High | Unsafe or clinically wrong meal plans |
| 3 | WF-017 | Critical | Manual meal plans ignore allergies/restrictions | Medium | Patient safety event |
| 4 | WF-023 | Critical | Reports use shallow data checks | High | Official incomplete reports |
| 5 | WF-014 | Critical | Missing nested meal plan scoping | Medium | Cross-patient meal plan corruption |
| 6 | WF-032 | Critical | Missing ownership policies | Medium | PHI/data access violations |
| 7 | WF-018 | Critical | Client nutrient snapshots accepted | Medium | Falsified nutrition data |
| 8 | WF-001 | High | Blank assessment unlocks workflow | High | ADIME evidence invalid |
| 9 | WF-002 | High | Attachment creates blank assessment | High | Document upload bypass |
| 10 | WF-012 | High | Multiple open NCP cycles | High | Split/inconsistent care record |
| 11 | WF-024 | High | NCP Summary renders incomplete sections | High | Misleading clinical documentation |
| 12 | WF-025 | High | Patient menu plan renders incomplete/wrong plan | High | Inaccurate patient menu |
| 13 | WF-035 | Critical reporting | NCP Summary omits meal plan and UI cannot select exact patient meal plan | High | Wrong or incomplete clinical report package |
| 14 | WF-026 | High | Census risk source wrong | High | Misleading aggregate reporting |
| 15 | WF-010 | High | Empty monitoring allowed | High | Invalid M/E |
| 16 | WF-030 | High | Document routes lack scope | Medium | PHI document exposure/deletion |
| 17 | WF-031 | High | Partial clinical records deletable | Medium | Loss of clinical evidence |
| 18 | WF-020 | High | Recipe totals stale | Medium | Inaccurate nutrition calculations |
| 19 | WF-008 | High | Last diagnosis can be deleted after activation | Medium | Intervention with no PES |
| 20 | WF-009 | High | Prescription optional | High | Care plan missing core prescription |
| 21 | WF-011 | High | Missing status machine | High | Lifecycle ambiguity |
| 22 | WF-027 | High | On-demand report not owner-scoped | Medium | PHI reporting exposure |
| 23 | WF-033 | High | Frontend-only gating | High | API bypass |
| 24 | WF-003 | High | Diagnosis only requires assessment row | High | PES unsupported by evidence |
| 25 | WF-013 | High | Discharged patient NCP creation | Medium | Post-discharge record confusion |
| 26 | WF-015 | High | Template owner bypass | Medium | Cross-user template risk |
| 27 | WF-019 | Critical reporting | USDA items omitted from report | Medium | Printed menu inaccurate |
| 28 | WF-028 | Critical data integrity | Missing one-to-one constraints | Low-Medium | Duplicate hidden records |
| 29 | WF-029 | High data integrity | Biochemical duplicate risk | Low | Lab data ambiguity |
| 30 | WF-021 | Medium | Empty recipe/incomplete food data | Medium | Incomplete calculations |
| 31 | WF-022 | High data integrity | Delete/historical source risk | Medium | Lost or blocked clinical source data |
| 32 | WF-004 | Medium | AI suggest before assessment | Medium | Premature clinical reasoning |
| 33 | WF-005 | Medium | PES override discarded | High | User/report mismatch |
| 34 | WF-006 | High | Diagnosis update instability | Medium | Invalid PES/edit failures |
| 35 | WF-034 | Medium | AI monitoring on weak data | Medium | Misleading monitoring review |

Severity grouping:

| Severity Group | Findings |
|---|---|
| Critical | WF-007, WF-009, WF-014, WF-016, WF-017, WF-018, WF-019, WF-023, WF-024, WF-025, WF-026, WF-028, WF-032, WF-035 |
| High | WF-001, WF-002, WF-003, WF-006, WF-008, WF-010, WF-011, WF-012, WF-013, WF-015, WF-020, WF-021, WF-022, WF-027, WF-029, WF-030, WF-031, WF-033 |
| Medium | WF-004, WF-005, WF-034 |
| Low | None found as standalone issues; low values appear only as per-dimension UX/data severities on higher-ranked findings. |

## Failure and Edge Case Coverage

| Scenario | Current Result | Main Findings | Code Paths |
|---|---|---|---|
| Missing Assessment | Diagnosis creation blocks only if no assessment row exists; reports can still render NCP Summary for NCP-only record. | WF-001, WF-023, WF-024 | `DiagnosisController::store`; `ReportController::render`; `NcpSummaryGenerator::data` |
| Blank Assessment | Accepted and unlocks diagnosis/intervention. | WF-001, WF-003 | `AssessmentController::store`; `StoreAssessmentRequest`; `DiagnosisController::store` |
| Attachment-only Assessment | Upload creates blank assessment and unlocks downstream flow. | WF-002 | `AssessmentController::uploadAttachment` |
| Missing Diagnosis | Intervention creation blocks if no diagnosis exists, but reports render and monitoring can continue if diagnosis is deleted after intervention. | WF-008, WF-023, WF-024 | `InterventionController::store`; `DiagnosisController::destroy`; `NcpSummaryGenerator::data` |
| Missing PES Statements | Store requires problem/etiology/signs and builds PES, but update can destabilize components; editable UI PES is not persisted. | WF-005, WF-006 | `DiagnosisController::{store,update}`; `Diagnosis::buildPes`; diagnosis page `buildPayload` |
| Missing Intervention | Monitoring blocks because backend requires intervention; NCP Summary can still render without intervention. | WF-023, WF-024 | `MonitoringController::store`; `NcpSummaryGenerator::data` |
| Empty Intervention | Accepted and can activate NCP. | WF-007, WF-009 | `InterventionController::store`; `StoreInterventionRequest` |
| Missing Monitoring/Evaluation | NCP Summary renders "No monitoring entries yet"; no completion rule requires M/E. | WF-010, WF-024 | `MonitoringController::store`; `NcpSummaryGenerator::data` |
| Archived Patients | No NCP archived state; discharged/transferred patients can still receive NCP cycles. | WF-011, WF-013 | `patients.status`; `ncp_records.status`; `PatientController::startNcpCycle` |
| Multiple Active NCP Cycles | Allowed; no DB unique or backend guard. | WF-012 | `PatientController::startNcpCycle`; `ncp_records` migration |
| Modified Meal Plans | Manual edits are not revalidated against clinical prescription/restrictions. | WF-017, WF-018 | `MealPlanItemController::{store,update}` |
| Modified Recipes | Recipe totals recalc only on recipe update; food changes can leave recipes stale. | WF-020 | `Recipe::recalculateTotals`; `RecipeController::update`; `FoodItemController::update` |
| Deleted Foods or Ingredients | Direct delete can fail on FK or threaten historical source resolution; no clinical archival. | WF-022 | `FoodItemController::destroy`; `RecipeController::destroy`; meal plan item FKs |
| Incomplete ADIME Documentation | Can become active and reportable with blank assessment, one diagnosis, empty intervention. | WF-001, WF-007, WF-023, WF-024 | `AssessmentController`; `DiagnosisController`; `InterventionController`; report generators |
| Report Generation from Incomplete Records | Allowed for NCP Summary and Patient Menu Plan because `hasData` is shallow. | WF-023, WF-024, WF-025 | `ReportController::render/archive`; `EntityInstanceSource::hasData`; report generators |
| Specific Patient Menu Selection | Patient Menu Plan UI lists patients, not each meal plan/menu. Multiple meal plans for one patient cannot be selected individually from the report browser, and NCP Summary omits meal plan entirely. | WF-025, WF-035 | `ReportBrowser` `patient_menu_plan`; `PatientMenuPlanGenerator::data`; `ReportsBrowser.tsx::InstancesPanel`; `NcpSummaryGenerator::data` |
| Cross-Patient Nested IDs | Meal plan/day/item IDs are not scoped to parent IDs. | WF-014 | `MealPlanController`; `MealPlanItemController` |
| Cross-RND PHI Access | Role-level RND access is broad; policies absent. | WF-027, WF-030, WF-032 | `ReportController`; `ScreeningDocumentController`; RND controllers |

## Top 10 Clinical Care Problems

1. Empty intervention can activate an NCP (`InterventionController::store`).
2. Assessment completion is represented by row existence, not clinical completeness.
3. Attachment upload can create an assessment and bypass the Assessment step.
4. Nutrition prescription targets are optional outside autofill.
5. Meal plan generation can use generic defaults and ignore clinical conditions.
6. Manual meal plan edits do not enforce allergies, restrictions, or prescription targets.
7. NCP Summary does not include the patient meal plan, while Patient Menu Plan cannot select a specific plan from the UI.
8. Monitoring entries can be empty and require only intervention existence.
9. PES manual edits in the UI are not persisted.
10. Multiple draft/active NCP cycles can coexist for one patient.

## Top 10 Quick Wins

1. Block empty assessment submissions at request level.
2. Stop `uploadAttachment` from creating assessment rows.
3. Require non-empty intervention fields before activating an NCP.
4. Require nutrition prescription targets before meal plan generation.
5. Add backend guard that monitoring requires a completed intervention/prescription.
6. Add report-level completeness warnings or hard stops for NCP Summary and Patient Menu Plan.
7. Add specific meal plan selection to Patient Menu Plan report browsing, or include/link the selected meal plan in NCP Summary.
8. Persist or remove editable PES override UI.
9. Add explicit parent-child checks in meal plan and item controllers.
10. Add owner checks to screening document routes.

## Top 10 Architectural Problems

1. Workflow state is scattered across controller existence checks instead of a domain state machine.
2. ADIME completion is not modeled as explicit per-step completion/validation state.
3. Clinical report eligibility is separated from clinical completeness.
4. Clinical report browsing is not modeled around the same NCP/meal-plan hierarchy used by care planning.
5. Role middleware substitutes for model-level ownership/PHI policy.
6. Route model binding is unscoped for nested resources.
7. Meal planning lacks a server-side clinical validation engine for restrictions and prescription fit.
8. Food/recipe nutrient data lacks versioning and immutable clinical snapshots.
9. NCP cycles lack uniqueness/open-cycle constraints and reassessment semantics.
10. UI workflow gates and backend invariants are inconsistent.

## Top 10 Questions Before Redesign

1. What exact minimum dataset makes an Assessment clinically complete for adult and pediatric patients?
2. Must every NCP have at least one PES diagnosis before any intervention can be saved, or only before activation?
3. Should PES statements be editable final text, generated-only text, or both source components plus reviewed final text?
4. What fields are mandatory for an Intervention to be clinically complete?
5. What rules define a valid Nutrition Prescription by goal type, disease stage, age, pregnancy/lactation, and screening type?
6. Can a patient have more than one open NCP, and how should follow-up vs reassessment differ from a new cycle?
7. What patient statuses should allow new clinical documentation after discharge/transfer?
8. Should meal plans be draft/active/approved/completed, and what completeness threshold is required for patient menu reports?
9. What ownership model applies: can any RND access all patients, or only assigned patients/NCPs?
10. Should reports be allowed as draft/incomplete with watermark, or blocked until clinical completeness is satisfied?
