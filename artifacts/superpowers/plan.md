# Implementation Plan - Nutriscope Clinical & Operations Workflows

This plan outlines the complete restructuring and implementation of high-fidelity clinical (NCP) workflows and operational (Food Service) portals in the Next.js frontend to align exactly with the specifications in [system-requirements](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/docs/milestones/system-requirements).

---

## 1. UI Navigation & Page Layout Structure (Visual Map)

The hierarchy below visualizes the application's pages, views, sub-tabs, modals, and sliding panels as a user traverses the interface (separate from the project's codebase file structure):

```text
Sidebar Navigation Menu (RND & FSS Views)
├── [Nutrition Care Process (NCP)]
│   ├── NCP Patients Directory
│   │   ├── Active Patients Table (Search, Filters, Risk Score badges)
│   │   └── Action: [Create Patient & Start Assessment] button
│   │       └── (Triggers quick temp patient creation + redirects to Assessment)
│   │
│   └── Patient Clinical Workspace (scoped to active Patient)
│       ├── Persistent Patient Header (Name, Ward, Diagnosis, Allergies Red Badges, Risk Status Badge, Lab Alerts)
│       │
│       ├── NCP Page 1: Assessment (6-Tab Sub-Layout)
│       │   ├── Tab A: Dietary History (Intake, Appetite, Restrictions, Supplements, Drug Interactions)
│       │   ├── Tab B: Anthropometrics (Weight, Height, Auto BMI, % IBW, Physical Assessment)
│       │   ├── Tab C: Client History (Medical, Social, Lifestyle, Allergies, Dislikes, Medications)
│       │   ├── Tab D: Biochemical / Labs
│       │   │   ├── Drag-and-drop Lab PDF/Image Upload Zone (OCR trigger)
│       │   │   ├── Lab input fields grid with confidence colored borders (Green, Yellow, Red)
│       │   │   └── Save & Sync Lab values
│       │   ├── Tab E: Referral / Screening Form
│       │   │   ├── Screening Form Selector (Adult B.07 vs Pediatric B.06 layout)
│       │   │   ├── Drag-and-drop Scan PDF/Image Upload Zone (OCR scanner beam animation)
│       │   │   ├── Demographic inputs (Editable on OCR complete: Name, Age, Ward, Physician, Diagnosis)
│       │   │   ├── Section A Checklist (15 adult or 18 pediatric conditions checkboxes)
│       │   │   ├── Section B Checklist (Intake & weight history)
│       │   │   └── Referral details (Diet prescription, referred by, date/time)
│       │   ├── Tab F: RND Summary (Clinical summary notes, reassessment needs)
│       │   │
│       │   └── Risk Score Section (Rendered below Tab E, above Tab F)
│       │       ├── 7 points scoring factors checklist
│       │       └── Action: [Verify, Save & Sync to RND] (Calculates score & saves Patient/Assessment)
│       │
│       ├── NCP Page 2: Diagnosis (6-Tab Sub-Layout)
│       │   ├── Tab 1: Diagnoses Table (Active diagnosis list, Edit/Delete, "AI Suggested" badges)
│       │   ├── Tab 2: Problem (P) Builder (Select Domain: NI, NC, NB -> Lists direction & macros/micros)
│       │   ├── Tab 3: Etiology (E) Builder (Pick etiology categories & add notes)
│       │   ├── Tab 4: Signs & Symptoms (S) Builder (Pick clinical symptoms & add notes)
│       │   ├── Tab 5: PES Statement (View auto-generated PES string, manual text overrides, Save)
│       │   └── Tab 6: AI Review (Claude Haiku suggestion list, Accept/Reject controls)
│       │
│       ├── NCP Page 3: Intervention (5-Tab Sub-Layout)
│       │   ├── Tab 1: Food / Nutrient Delivery
│       │   │   ├── Action: [Open Goal Selection Modal] (Diabetic, Renal, Cardiac, Custom targets)
│       │   │   ├── Nutrition Prescription targets & real-time current vs target macro charts
│       │   │   ├── Algorithm Recommendations (Include / Avoid lists with clinical reasons, hard allergen excludes)
│       │   │   ├── Weekly Meal Plan Grid (7 Days × 5 Meals)
│       │   │   └── Action: [Auto-Generate Plan] (15-recipe gate check, Claude fallback if < 5)
│       │   ├── Tab 2: Nutrition Education (Materials, handouts, patient instructions)
│       │   ├── Tab 3: Nutrition Counseling (Counseling goals, behavioral strategies, barriers)
│       │   ├── Tab 4: Goal Planning (Timeline outcomes linked to counselor goals)
│       │   └── Tab 5: Encounter Context (Session type, Next follow-up. NO encounter location)
│       │
│       └── NCP Page 4: Monitoring (Single page layout)
│           ├── Weight Progression Chart (Historical weights)
│           ├── BMI Trend Line Tracker
│           ├── Biochemical Lab Comparison Grid (Lab values across dates)
│           └── Goal Achievement checklist (Target outcomes status tracker)
│
└── [Food Service Operations]
    ├── Page 1: Inventory Management
    │   ├── Stock Dashboard Card metrics (Total items, Low stock alerts, Expiring warnings)
    │   ├── Stock Levels Directory Table (Name, category, qty, unit, expiry, threshold)
    │   └── Action: [Restock Item Modal] (Input restock qty, updates stock levels)
    │
    ├── Page 2: Menu Cycle (Weekly Kitchen Planner)
    │   ├── Roster list of cycle periods (Week start date, active status badges)
    │   ├── Action: [Create Menu Cycle] button (Triggers modal setup)
    │   ├── Main Scheduler Grid (7 Days × 5 Meals)
    │   ├── Real-time Cost Calculation Panel (Calculates cost/person, compares against 150 pesos budget)
    │   │   └── Colors: Green (<=150), Yellow (140-150), Red (>150)
    │   ├── Actions: [Save as Template] / [Load Template]
    │   └── Action: [Activate Cycle] (Double-confirmation dialog -> sets active status & activation date)
    │
    ├── Page 3: Budget Tracking
    │   ├── Spending Summary Cards (Planned budget [150 * patients * days] vs actual PO costs)
    │   ├── Monthly Spending Graph (Variance between planned and actual)
    │   └── Daily Log Variance breakdown grid
    │
    ├── Page 4: Procurement Portal (3-Tab Sub-Layout)
    │   ├── Tab 1: Suggested Shopping Lists
    │   │   ├── Left Alerts Panel (Low stock & expired inventory lists, [Add All Flagged] shortfall button)
    │   │   ├── Right List Builder (Add manual/search ingredients, calculate shortfall, estimated total cost)
    │   │   └── Actions: [Save Draft] / [Create Purchase Order]
    │   ├── Tab 2: Purchase Orders Registry
    │   │   ├── PO Table Registry (PO#, Supplier, Date, Total, Status: Draft/Ordered/Received)
    │   │   └── PO Detail Slide-out Drawer
    │   │       ├── Invoice/Receipt Dropzone (Upload vendor invoice image & preview)
    │   │       └── Action: [Mark Received] button (DB Transaction restocks physical inventory)
    │   └── Tab 3: Suppliers Directory
    │       └── Suppliers Table (Name, category, phone contact, payment terms)
```

---

## 2. NCP Step 1: Assessment Updates & Entry Flow

### Workflow Enhancements:
- **Quick Draft Patient Creation**:
  - In `ncp/patients/page.tsx`, the **Create Patient & Start Assessment** button will no longer redirect to `select-patient` placeholder routes.
  - Instead, it will call `createPatient` with default placeholder values (`name: "New Admission (Scanning)", dob: "1990-01-01", sex: "Male", admission_date: today, status: "Active"`), call `createNcpRecord`, and redirect the user directly to `/ncp/[tempPatientId]/assessment/[tempNcpId]`.
- **Editable Demographics on Tab E**:
  - Demographic fields on Tab E (Patient Name, Age, Ward, Physician, Medical Diagnosis) will be converted to editable input fields.
  - Demographics will be bound to state and updated by OCR text parsing.
  - Saving the form updates the Patient profile demographics on the backend.
- **Cross-Tab Data Propagation**:
  - Completing Tab E OCR automatically updates the state values for Tab B (Anthropometric weight/height) and Tab A.
  - Height and weight changes dynamically recalculate BMI and ideal body weight % in real-time.
- **Tab D Biochemical OCR Upload**:
  - Implement a drag-and-drop file upload on Tab D (Biochemical Data).
  - Triggers the OCR pipeline (`document_type = lab_result`) and populates standard lab input fields (Albumin, Hemoglobin, Glucose, Calcium, Sodium, Potassium, etc.).
  - Implements field confidence indicator colors (Green > 0.8, Yellow 0.5-0.8, Red < 0.5) around fields.
  - High-risk out-of-range lab results (e.g., Albumin < 3.0 g/dL) will appear as amber badges in the header.

---

## 3. NCP Step 2: Diagnosis Page Workflow

### Layout & PES Builder:
- **Strict 3-Domain filter**: Domain options are limited strictly to NI (Intake), NC (Clinical), and NB (Behavioral-Environmental) with no other domain options.
- **Tab 1: Diagnosis Table**:
  - Renders all diagnoses for the active NCP record.
  - Displays an "AI Suggested" badge if `ai_generated = true`.
- **PES Builder (Tabs 2-5)**:
  - **Tab 2 (Problem)**: Select Domain (NI, NC, NB). NI lists direction (Inadequate/Excessive) and macro/micro nutrient dropdowns. NC and NB display checkboxes for clinical/behavioral issues.
  - **Tab 3 (Etiology)**: Option checkboxes (e.g., poor appetite, chronic illness, lack of education).
  - **Tab 4 (Signs & Symptoms)**: Option checkboxes (e.g., % meal intake below target, abnormal labs).
  - **Tab 5 (PES Statement)**: Compiles `[Problem] related to [Etiology] as evidenced by [Signs and Symptoms]`, allowing manual text overrides before saving.
- **Tab 6: AI Review**:
  - Calls `AIService` using the `claude-haiku-4-5` model, sending patient context and returning draft suggestions. RND can accept, reject, or edit-and-accept these suggestions.

---

## 4. NCP Step 3: Intervention Page Workflow

### Prescription & Meal Planning:
- **Tab 1: Food / Nutrient Delivery**:
  - Goal selection modal (Renal Diet, Diabetic Control, Cardiac, Weight Loss/Gain, High Protein, Fluid, Custom) which auto-adjusts daily targets (kcal, protein, carbs, fat, fluid, micronutrient limits).
  - Shows real-time current vs target macro trackers (e.g., current kcal / target kcal), color-coded by fit (green if within 10%, red otherwise).
- **Algorithm-Driven Recommendations Panel**:
  - Powered by the backend `RecommendService` (reading `clinical_rules`).
  - **Hard Exclusions**: Never suggests foods containing allergens listed in `assessments.allergies` or religious exclusions (e.g., no pork for Muslim patients).
  - **Soft Exclusions**: Highlights food dislikes (`assessments.food_dislikes`) as warning notes in meal slots, but lets the RND override them.
  - Lists foods to **RECOMMEND** and foods to **AVOID** with corresponding clinical reasons.
- **Weekly Meal Plan Grid**:
  - 7 Days (Mon-Sun) × 5 Meals (Breakfast, AM Snack, Lunch, PM Snack, Dinner) grid.
  - Support adding food items or recipes.
- **Auto-Generation Algorithm**:
  - Requires minimum 15 recipes in the database. Warns user if below threshold.
  - Auto-selects best-scoring recipes based on nutrient fit.
  - Falls back to AI generation (Claude Sonnet 4.6) ONLY if less than 5 recipes pass allergy/religious filters.
- **Encounter Context (Tab 5)**:
  - Select session type (Initial or Follow-up) and next date.
  - Remove all occurrences of `encounter_location` in the UI and database model.

---

## 5. NCP Step 4: Monitoring Page Workflow

### Progression Tracking:
- Create a dedicated monitoring view showing:
  - Weight progression over time (tabular/graphical).
  - BMI trend lines.
  - Lab value comparison grids showing changes across dates.
  - Goal achievement checklist and clinical summaries.

---

## 6. Food Service Operational Pages

- **Inventory Page**:
  - Show stock levels, units, expiry dates, and minimum thresholds.
  - Quick summary cards for low stock and expiring items.
  - Quick action to restock quantities.
- **Menu Cycle Page (Weekly Planner)**:
  - 7-day × 5-meal grid representing the general hospital kitchen plan.
  - Auto-calculate daily cost per person.
  - Budget status indicator lights (Green <= 150 Php, Yellow 140-150, Red > 150 Php).
  - Template loading and saving workflows.
  - Double-confirmation activation flow. FSS role gets read-only view.
- **Budget Page**:
  - View planned budget (150 pesos × patient count × period days) vs actual procurement spending.
  - Monthly graph showing daily logs variance.
- **Procurement Page**:
  - **Tab 1: Suggested Shopping Lists**: Left inventory panel showing LOW/EXPIRED alerts, and "Add All Flagged" button. Right panel list builder compiles shopping list.
    - **Suggested Shopping List Logic**:
      1. Fetch the active menu_cycle for the upcoming week.
      2. Calculate total ingredient quantities needed for all scheduled meals.
      3. Compare the required quantities with current `inventory.quantity_in_stock`.
      4. For each ingredient where stock < needed + minimum_stock_threshold:
         suggest purchase quantity = `(needed + minimum_stock_threshold) - current_stock`.
      5. Only list items with a shortfall (do not include items with sufficient stock).
  - **Tab 2: Purchase Orders**: Table tracking Draft -> Ordered -> Received. Upload vendor invoice PDF/image dropzone. Transitioning to "Received" triggers inventory restocking update in database.
  - **Tab 3: Suppliers**: Category, contact detail, payment terms.

---

## Proposed File Changes

### NCP Demographics & Quick Start:
#### [MODIFY] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/patients/page.tsx)
- Re-bind "Create Patient & Start Assessment" button to create a placeholder patient record in the database, instantiate an NCP record, and navigate to the assessment page immediately.

#### [MODIFY] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/assessment/%5BncpId%5D/page.tsx)
- Change demographic input fields on Tab E to be editable.
- Populate state demographics from OCR extraction.
- Update `handleSave` to call `updatePatient` to save verified demographics.
- Implement Tab D (Biochemical) file upload, OCR, and confidence colored borders.
- Implement Risk Score point calculation checkbox grid.

### NCP Diagnosis:
#### [NEW] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/diagnosis/%5BncpId%5D/page.tsx)
- Create high-fidelity PES statement builder (Problem, Etiology, Signs/Symptoms tabs) with 3 domains (NI, NC, NB) and AI suggestions review.

### NCP Intervention:
#### [NEW] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/intervention/%5BncpId%5D/page.tsx)
- Create Goal selection, nutrient target calculator, algorithm recommendations panel (hard/soft exclusions), weekly meal plan grid, auto-generator trigger, and encounter context tab.

### NCP Monitoring:
#### [NEW] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/ncp/%5BpatientId%5D/monitoring/%5BncpId%5D/page.tsx)
- Implement weight progression, BMI trends, biochemical lab comparison tables, and goal status checklist.

### Food Service:
#### [NEW] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/food-service/inventory/page.tsx)
- Implement inventory stock tables, restock action modal, alert cards.

#### [NEW] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/food-service/budget/page.tsx)
- Implement planned vs actual summary, configurable cost-per-person, daily log variance grids.

#### [MODIFY] [page.tsx](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/frontend/app/%28rnd%29/food-service/procurement/page.tsx)
- Restructure Shopping Lists tab with left alert panel ("Add All Flagged"), PO status transitions (Draft -> Ordered -> Received), invoice preview dropzone, and Suppliers contact listing.

---

## Verification Plan

### Automated Verification:
- Run backend tests to verify model schema changes:
  ```powershell
  php artisan test
  ```
- Build frontend to ensure static types and compilation pass:
  ```powershell
  npm run build
  ```

### Manual Verification Grid:
1. Navigate to Patients page, click "Create Patient & Start Assessment". Verify immediate transition to Tab E Assessment without select-patient blocks.
2. Select Adult B.07, upload test scan file. Inspect OCR confidence colors, edit demographics inline, check risk points checklist, and hit save. Verify patient profile name is updated in the header.
3. Test Diagnosis PES builder: compile a PES statement and verify domains.
4. Test Intervention meal grid and recommendation filters.
5. Test Procurement Suggested Shopping List: click "Add All Flagged" and verify shortfall calculations.
