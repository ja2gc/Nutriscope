# Superpowers Review - Frontend Workflows & System Requirements Alignment

**Milestone:** Complete Frontend UI/UX Premium Features & Clinical Workflows  
**Date:** 2026-06-03  
**Reviewer:** Antigravity AI Partner  

---

## Blockers

### B-1 · Entry Flow Broken for "Create Patient & Start Assessment"
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/patients/page.tsx#L159), [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/assessment/%5BncpId%5D/page.tsx#L74)
- **Problem:** Clicking "Create Patient & Start Assessment" routes the user to a placeholder view (`/ncp/select-patient/assessment/select-ncp`) showing *"Please pick a patient from the patient directory to commence screening"*. This completely blocks the intended clinical workflow.
- **Requirement:** RNDs must be able to go straight to the assessment page, upload a screening form/lab result, let OCR extract and auto-fill demographics, and only save/persist the patient profile and NCP record in the database at the end of the assessment session.

---

## Majors

### M-1 · Tab demographics fields are disabled/read-only
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/assessment/%5BncpId%5D/page.tsx#L535-L572)
- **Problem:** Demographic input fields (Patient Name, Age, Ward, Physician, Diagnosis) are marked `disabled` on Tab E. If OCR extracts them incorrectly, or when starting a temporary scanning session, the user cannot edit or correct them.
- **Requirement:** Demographics must be editable in the UI so the user can verify, correct, and save the patient profile fields alongside the clinical fields.

### M-2 · Missing automatic data flow across assessment tabs
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/assessment/%5BncpId%5D/page.tsx)
- **Problem:** Successfully extracted values from OCR remain local to Tab E and do not propagate to Tab B (Anthropometric Measurements) or Tab D (Biochemical Data), requiring double entry.
- **Requirement:** Extracted values (such as weight, height, and lab values) must automatically pre-populate the fields in other tabs (Tab B, Tab D), trigger derived calculations (like BMI, % IBW, risk score total), and update headers.

### M-3 · Non-Compliant NCP Diagnosis, Intervention & Monitoring UI/UX
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/diagnosis/%5BncpId%5D/page.tsx), [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/intervention/%5BncpId%5D/page.tsx), [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/monitoring/%5BncpId%5D/page.tsx)
- **Problem:** The downstream NCP pages do not support the strict layouts described in the system requirements:
  - **Diagnosis:** Needs a 3-domain filter (Intake NI, Clinical NC, Behavioral-Environmental NB) with absolutely no "Other/NO" domain. It must feature a structured PES builder (Problem -> Etiology -> Signs/Symptoms) and an AI review suggestion grid.
  - **Intervention:** The recommendation panel must be strictly algorithm-driven (using rules from the `clinical_rules` table), applying hard exclusions for patient allergies/religion and soft exclusions for dislikes. Auto-generation requires a 15-recipe gate and a Claude fallback. The encounter context should have no `encounter_location`.
  - **Monitoring:** Lacks clean tracking for weight progression, BMI trends, goal achievements, and inline biochemical comparisons.

### M-4 · Kitchen Procurement dashboard doesn't match inventory-linkage and alerts
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/food-service/procurement/page.tsx)
- **Problem:** The suggested shopping list shortfall calculation and the purchase order "Mark Received" triggers are not tightly integrated into the UI. Low-stock and expired inventory alerts are missing from the shopping list left panel.
- **Requirement:** The procurement page must feature a 3-tab layout containing:
  - **Tab 1:** A left panel showing inventory items with low stock or expiry warnings, and an "Add All Flagged" button to calculate shortfall based on active menu cycles + thresholds.
  - **Tab 2:** A PO tracker supporting status transitions (Draft -> Ordered -> Received) and receipt image upload. Receiving a PO must update physical stock level in the backend.

---

## Minors

### m-1 · Missing styling for confidence scoring indicators
- **Files:** [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/assessment/%5BncpId%5D/page.tsx#L588-L602)
- **Problem:** Field confidence outlines are hardcoded to simple checks or missing dynamic colors based on the actual OCR confidence score.
- **Requirement:** Border colors should reflect the actual parsing confidence: Green (confidence > 0.8), Yellow (0.5 to 0.8), and Red (< 0.5) to draw the RND's attention to fields that need validation.

---

## Summary & Next Actions

There are major workflow discrepancies between the current implementation and the clinical requirements under `docs/milestones/system-requirements`. The entry flow blocks RND intake, demographics are un-editable, tabs do not autofill from OCR outputs, and procurement is disconnected from inventory stock calculations.

### Action Plan
1. **Fix Entry Flow (B-1)**:
   - Modify the "Create Patient & Start Assessment" click handler in `ncp/patients/page.tsx`.
   - Implement a temporary/draft initialization that creates a placeholder patient and NCP record in the database, and redirects the user to the assessment page.
   - Make demographics editable in Tab E.
   - Enhance the saving routine to update the patient profile demographics in the database using the verified fields.
2. **Propagate OCR Data (M-2)**:
   - When OCR completes, copy extracted demographics, anthropometrics, and labs directly into the active state variables of other tabs.
   - Recalculate BMI, ideal body weight percentages, and risk scores dynamically in the frontend and save them.
3. **Restructure NCP & Food Service Tasks (M-3, M-4)**:
   - Divide the task checklist into clear, granular sub-tasks for all steps of NCP (Assessment, Diagnosis, Intervention, Monitoring) and Food Service (Inventory, Menu Cycle, Budget, Procurement).
