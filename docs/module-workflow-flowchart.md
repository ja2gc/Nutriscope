# NutriScope Module Workflow Flowchart

Verified against current RND web, FSS native mobile, and Laravel workflow boundaries on **2026-08-27**.

This is the demo guide and source-of-truth workflow map for the NutriScope modules that feed clinical care, food service planning, execution, monitoring, and report generation.

Primary actors:

- **RND** works in the web app and owns food data setup, recipes, menu cycles, patients, NCP/ADIME care plans, meal plans, monitoring, reports, budget, and insights.
- **FSS** works in the native mobile app and owns food-service execution: reviewing approved menus, receiving deliveries with evidence, recording actual served population, filing daily accomplishments, and opening their own semi-monthly reports.

The modules share data through food items, recipes, menu cycles, patient records, NCP records, prescriptions, meal plans, inventory, purchase orders, monitoring entries, and archived reports.

Demo logins: RND `rnd@nutriscope.local`, FSS `fss@nutriscope.local` (Maria Santos), both password `nutriscope2024!`. The mobile build points at `https://nutriscope.live`.


## 2. Food Data, Ingredients, Recipes, and Menu Cycles

Food data is the base used by both food service and clinical meal planning. RND can import ingredient records or manually create them, then use those records in recipes, menu cycles, shopping lists, and patient meal plans.

```mermaid
flowchart TD
    A["Open Food Service / Food Items"] --> B{"Create source"}
    B -->|"Import ingredient data"| C["Upload / import food or ingredient record"]
    B -->|"Manual entry"| D["Create item manually"]

    C --> E["Normalize item name, unit, category, nutrients"]
    D --> E
    E --> F["Set cost, supplier, allergens, restrictions, base unit"]
    F --> G{"Item kind"}
    G -->|"Ingredient"| H["Use inside recipes"]
    G -->|"Ready-to-serve food"| I["Use directly in menu cycle or meal plan"]
    G -->|"Supply"| J["Use in shopping list / procurement"]

    H --> K["Create recipe"]
    K --> K1["Add ingredient, quantity, unit, servings, prep notes"]
    K1 --> K2{"Units compatible?"}
    K2 -->|"No"| K3["Show validation error"]
    K2 -->|"Yes"| K4["Save recipe and recalculate cost/nutrients"]

    I --> L["Create menu cycle"]
    K4 --> L
    L --> L1["Set dates, days, meal slots, estimated population"]
    L1 --> L2["Slot recipes or ready-to-serve foods"]
    L2 --> L3["Compute quantity, cost/head, prep notes"]
    L3 --> L4["Activate cycle or keep draft"]

    L4 --> M["Generate suggested shopping list"]
    M --> N["Aggregate recipe ingredients and ready-to-serve items"]
    N --> O["Attach calculated need, purchase values, vendor, and estimated cost"]
    O --> P["Create and release purchase order / vendor groups"]
    P --> Q["FSS confirms actuals, uploads evidence, and marks vendors received"]
```

---

## 3. Clinical Care / NCP Workflow

Clinical Care follows the NCP/ADIME path: Assessment, Diagnosis, Intervention, Monitoring, Evaluation. The first visit creates the assessment, diagnoses, prescription, intervention, and meal plan. Monitoring starts only on the second visit and later because it requires follow-up data to compare against the baseline intervention.

```mermaid
flowchart TD
    A["Open Clinical Care / NCP Patients"] --> B{"Patient exists?"}
    B -->|"No"| C["Create patient"]
    B -->|"Yes"| D["Open patient profile"]

    C --> C1["Enter patient data: name, DOB, sex, admission date"]
    C1 --> C2["Optional: ward, physician, diagnosis, religion, contact, screening type"]
    C2 --> E["Start NCP cycle"]
    D --> E

    E --> F["Assessment tab"]
    F --> F1["Fill assessment: diet history, anthropometrics, labs, history, allergies, medications"]
    F1 --> F2["Upload supporting documents if needed"]
    F2 --> F3["Save assessment"]

    F3 --> G["Diagnosis tab"]
    G --> G1{"Diagnosis path"}
    G1 -->|"Manual PES"| G2["Choose domain NI / NC / NB"]
    G2 --> G3["Enter Problem, Etiology, Signs/Symptoms"]
    G1 -->|"AI-assisted"| G4["Request AI diagnosis suggestions from assessment context"]
    G4 --> G5["Review suggested domain, problem, etiology, signs"]
    G5 --> G6{"Approve suggestion?"}
    G6 -->|"No"| G
    G6 -->|"Yes"| G7["Convert suggestion into diagnosis"]
    G3 --> G8["Backend builds PES statement"]
    G7 --> G8
    G8 --> G9["Save one or more diagnoses"]

    G9 --> H["Intervention tab"]
    H --> H1["Select intervention goal and disease stage"]
    H1 --> H2["Autofill nutrition prescription from assessment data"]
    H2 --> H3{"Enough assessed data?"}
    H3 -->|"Yes"| H4["System computes energy, macro, fluid, micronutrient targets"]
    H3 -->|"No"| H5["RND enters targets manually or completes missing assessment data"]
    H4 --> H6["Add education, counseling, barriers, strategy, next follow-up"]
    H5 --> H6
    H6 --> H7["Save intervention"]

    H7 --> I["Meal Plan / Menu Cycle tab"]
    I --> I1{"Planning path"}
    I1 -->|"Manual"| I2["Create days and meal slots manually"]
    I1 -->|"Auto-generate"| I3["Generate menu cycle close to nutrition prescription"]
    I1 -->|"Template"| I4["Use saved template"]
    I2 --> I5["Add recipes, food items, or direct USDA items"]
    I3 --> I6["System scores foods/recipes against targets, allergens, restrictions"]
    I4 --> I5
    I6 --> I7["Review generated calories, macros, micronutrients, variance flags"]
    I5 --> I7
    I7 --> I8["Adjust portions/items until close to prescription"]
    I8 --> I9["Save meal plan"]

    I9 --> J{"Visit number"}
    J -->|"Initial visit"| K["Monitoring tab locked / show reason: follow-up visit required"]
    J -->|"Second visit and later"| L["Monitoring and Evaluation tab"]
    L --> L1["Enter follow-up weight, BMI, labs, intake notes, symptoms, goal achievement"]
    L1 --> L2["Compare progress against assessment baseline and intervention targets"]
    L2 --> L3["Optional AI review / monitoring summary"]
    L3 --> L4["Save monitoring entry"]

    H7 --> M["Reports tab"]
    I9 --> M
    L4 --> M
    M --> M1["Generate NCP Summary / Patient Menu Plan / Census"]
    M1 --> M2["Preview live report"]
    M2 --> M3["Archive final PDF snapshot when ready"]
```

### Clinical Care Tab Traversal

1. **Patients** - create or select a patient, then start or continue an NCP cycle.
2. **Assessment** - enter baseline patient and clinical data. This is the source used by diagnosis, AI suggestions, prescription autofill, meal planning, monitoring, and reports.
3. **Diagnosis / PES** - create one or more diagnoses manually or review AI-generated suggestions. AI is assistive only; RND reviews and saves the accepted diagnosis.
4. **Intervention / Prescription** - select the intervention goal and disease stage. The system can auto-prescribe energy, macros, fluid, and micronutrient targets when assessment data is complete enough.
5. **Meal Plan / Menu Cycle** - create a manual plan, use a template, or auto-generate a menu cycle that tries to match the nutrition prescription while avoiding known allergens and restrictions.
6. **Monitoring and Evaluation** - available on the second visit and later. It records follow-up findings and compares progress against the initial assessment and saved intervention.
7. **Reports** - generate live reports, review values, then archive final PDFs when the clinical record is ready.

---

## 4. Monitoring Gate

Monitoring is not part of the first-visit baseline. It should be opened only after an intervention exists and the patient has a second visit or later follow-up encounter.

```mermaid
flowchart TD
    A["RND opens Monitoring tab"] --> B{"NCP has saved intervention?"}
    B -->|"No"| C["Block: complete intervention first"]
    B -->|"Yes"| D{"Encounter / visit count"}
    D -->|"Visit 1"| E["Block: monitoring starts on second visit"]
    D -->|"Visit 2+"| F["Allow monitoring entry"]

    F --> G["Enter progress data"]
    G --> H["Compare against baseline assessment and prescription"]
    H --> I{"Progress result"}
    I -->|"Improving / stable"| J["Continue plan and schedule next monitoring"]
    I -->|"Not improving / new issue"| K["Revise intervention or reassess"]
    I -->|"Ready to close"| L["Generate final evaluation / report"]
```

---

## 5. FSS Mobile Navigation Map

FSS uses the native Android app. Daily destinations stay in bottom navigation; account utilities stay in the header and profile side menu.

```mermaid
flowchart TD
    SPL["App launch: animated NutriScope splash"] --> LOG{"Has saved token?"}
    LOG -->|"No"| LOGIN["Login screen"]
    LOG -->|"Yes"| TABS
    LOGIN --> TABS["Authenticated app shell"]

    TABS --> T1["Home"]
    TABS --> T2["Announcement: Announcements tab + SOP tab"]
    TABS --> T3["Menu"]
    TABS --> T4["Meal Prep"]
    TABS --> T5["Accomplish"]
    TABS --> T6["Purchase"]

    TABS --> HDR["Header on every screen"]
    HDR --> H1["Bell -> Notifications + unread badge"]
    HDR --> H2["Profile -> Account side menu"]
    H2 --> SIDE["Profile, Notifications, Help, Settings, Check updates, Sign out"]

    T5 --> L1["Daily Log: today or previous date"]
    T5 --> L2["My Reports: details + PDF"]
```

Page inventory: Home, Announcement, Menu, Meal Prep, Accomplish, Purchase, food profile, report detail, Notifications, Help, Settings, and Profile. Announcement contains separate Announcements and SOP tabs.

---

## 6. RND Food Service Planning Workflow

```mermaid
flowchart TD
    A["Maintain ingredient/supply reference catalog"] --> B["Create recipes with baseline servings and exact measurements"]
    B --> C["Create date-named menu cycle or load template copy"]
    C --> D["Assign recipes/items and activate"]
    D --> E{"Shopping-list path"}
    E -->|"Suggested food"| F["Select span + enter one estimated serving count"]
    E -->|"Manual food/event"| G["Name list + add ingredients"]
    E -->|"Manual supplies"| H["Name list + add supplies"]
    F --> I["Scale baseline recipes; omit Purchase-when-needed ingredients"]
    I --> J["Review calculated need and editable purchase values"]
    G --> J
    H --> J
    J --> K["Add manual rows or exclude generated rows"]
    K --> L{"Vendor, estimate/coverage, and budget ready?"}
    L -->|"No"| M["Show release blockers"]
    M --> J
    L -->|"Yes"| N["Create/release one vendor-grouped PO from included rows"]
    N --> O["Freeze plan and create PPA snapshot"]
```

---

## 7. FSS Mobile Execution Workflow

```mermaid
flowchart TD
    A["FSS opens mobile Home"] --> B["Review menu, meals, POs, announcements"]
    B --> C["Menu: view approved slots and profiles"]
    B --> D["Purchase: open PO and vendor"]
    D --> E["Review calculated values; confirm actual qty/price"]
    E --> F["Upload receipt and proof; optional OR"]
    F --> G["Explicitly mark vendor received"]
    B --> H["Meal Prep: review planned meals and record positive actual served population"]
    B --> I["Accomplish: record 2 counts, 5 duties, or off-duty"]
    I --> J["Own semi-monthly accomplishment report"]
    G --> K{"All vendor evidence complete?"}
    H --> L{"Suggested food span populations complete?"}
    K -->|"No"| D
    L -->|"No"| H
    K -->|"Yes"| M["Complete manual/supplies PO"]
    K -->|"Yes"| N["Suggested food waits for population"]
    L -->|"Yes"| O["Complete suggested food PO; ledger and reports"]
```

---

## 8. Two-Actor Interconnection

```mermaid
flowchart LR
    subgraph RND["RND - web planning and clinical lane"]
        R0["Login web"]
        R1["Food items / ingredients"]
        R2["Recipes"]
        R3["Menu cycles"]
        R4["Patients"]
        R5["Assessment"]
        R6["Diagnosis / PES + AI"]
        R7["Intervention / prescription"]
        R8["Meal plan / menu cycle"]
        R9["Monitoring"]
        R10["Reports / budget / insights"]
        R0 --> R1 --> R2 --> R3
        R0 --> R4 --> R5 --> R6 --> R7 --> R8 --> R9 --> R10
        R3 --> R8
    end

    subgraph FSS["FSS - mobile execution lane"]
        F0["Login mobile"]
        F1["Dashboard work queues"]
        F2["View active menu cycle"]
        F3["Daily accomplishment rows"]
        F4["Receive PO vendor groups"]
        F5["Meal Prep: record actual served population"]
        F0 --> F1
        F1 --> F2 & F3 & F4 & F5
    end

    R3 ==> X1["Shared activated menu cycle"]
    X1 ==> F2
    X1 ==> F5

    R3 ==> X2["Shopping list / PO"]
    X2 ==> F4
    F4 ==> X3["Confirmed actuals + receipt/proof + received state"]
    F5 ==> X4["Actual served population by service date"]
    F3 ==> X5["Daily duties/counts + semi-monthly report"]

    R8 ==> X6["Patient meal plan report data"]
    R9 ==> X7["Monitoring/evaluation report data"]
    X3 ==> R10
    X4 ==> R10
    X5 ==> R10
    X6 ==> R10
    X7 ==> R10
```

---

## 9. Reports and Data Flow

```mermaid
flowchart TD
    A["Operational source tables"] --> B{"Report / graph type?"}
    B -->|"NCP Summary"| C["Patient + assessment + diagnoses + intervention + monitoring"]
    B -->|"Patient Menu Plan"| D["Patient + intervention prescription + meal plan days/items"]
    B -->|"Demographic Census"| E["Patient admission period + latest NCP/assessment status"]
    B -->|"Budget summary / burn"| F["Fiscal-year budget + budget ledger"]
    B -->|"Per-head actual vs limit"| G["PO costs + served population + per-head/day limit"]
    B -->|"Menu / PPA / procurement"| H["Menu cycle + shopping list + PO + frozen PPA"]
    B -->|"FSS accomplishment"| I["One daily record per staff + refreshed semi-monthly snapshot"]

    C --> J["RND reports browser"]
    D --> J
    E --> J
    F --> J
    G --> J
    H --> J
    I --> J
    I --> K["FSS: own reports only"]

    J --> L{"Archive?"}
    K --> L
    L -->|"No"| M["Live report from current data"]
    L -->|"Yes"| N["Frozen PDF snapshot for reproducible download"]
```

---

## 10. Step-by-Step Demo Script

### Part A - RND prepares food data and food-service plan

1. Log in on web as `rnd@nutriscope.local`.
2. Open Food Service.
3. Import ingredients or manually create food items with units, nutrients, costs, suppliers, allergens, and restrictions.
4. Create recipes from ingredients and confirm recipe cost/nutrition recalculation.
5. Create or open a menu cycle; slot recipes and ready-to-serve foods.
6. Generate a shopping list from the active menu cycle.
7. Approve/convert the list to one purchase order with supplier vendor groups.

### Part B - RND runs Clinical Care / NCP

1. Open Clinical Care / NCP Patients.
2. Create a patient or open an existing patient profile.
3. Start an NCP cycle and fill the Assessment tab with patient data, diet history, anthropometrics, labs, allergies, medications, and clinical summary.
4. Go to Diagnosis / PES.
5. Create a diagnosis manually or request AI suggestions from the assessment context.
6. Review the AI suggestion, approve only the clinically correct one, and save the diagnosis.
7. Go to Intervention.
8. Select intervention goal and disease stage.
9. Use prescription autofill when assessment data is complete; otherwise complete missing data or enter targets manually.
10. Add education, counseling, barriers, strategy, and next follow-up.
11. Create a manual meal plan, use a template, or auto-generate a menu cycle close to the nutrition prescription.
12. Review calorie/macro/micronutrient variance and adjust foods or portions.
13. Save the intervention and meal plan.
14. On the initial visit, Monitoring stays unavailable because there is no follow-up comparison yet.
15. On the second visit or later, open Monitoring and Evaluation, enter follow-up findings, compare progress, optionally request AI review, and save.
16. Generate NCP Summary, Patient Menu Plan, or other clinical reports.
17. Preview the live PDF and archive the final report when ready.

### Part C - FSS executes food-service work on mobile

1. Launch mobile app and log in as `fss@nutriscope.local`.
2. Open Home for today's service list, meals-to-log KPI, POs awaiting receipt, active menu, and announcements.
3. Open Purchase, confirm actual values, optionally enter an OR number, upload receipt/proof, and explicitly mark each vendor received.
4. Open Meal Prep to review the selected day's menu and record actual served population.
5. Open Accomplish to record diet-list/accomplishment rows for today or a missed previous date.
6. Open Menu to review weekly menu slots and open each read-only food profile.
7. Open My Reports inside Accomplish to view and download personal semi-monthly reports.

### Part D - Reports close the loop

1. PO receipt and served-population data update actual budget/head and budget ledger.
2. One Daily Log per staff/date refreshes the matching semi-monthly FSS report.
3. Patient assessment, diagnosis, intervention, meal plan, and monitoring data generate clinical reports.
4. RND reviews live reports and archives final PDFs when the data is ready.






# Clinical Care/NCP Module Workflow

### Patient Selection

RND selects an active patient or creates one. If a patient is discharged/transferred, the system requires reactivation with reason before a new NCP can start. If an open NCP already exists, RND must continue it or close it before starting another.

### NCP Creation

System creates one draft NCP cycle. Status starts at `draft_assessment`. The cycle cannot become active until Assessment, Diagnosis, and Intervention are explicitly finalized.

### Assessment

RND may save drafts freely. Attachments are linked to the NCP but do not complete assessment. To finalize Assessment, required fields must pass a clinical validator. The validator records `assessment_completed_at` and `assessment_completed_by`.

### Diagnosis and PES

Diagnosis is unlocked only after assessment completion. RND enters structured P/E/S and may optionally override the rendered PES statement. At least one PES must be finalized. Deleting or changing a finalized diagnosis after intervention requires reopening downstream sections.

### Intervention and Prescription

Intervention is unlocked only after finalized PES. Goal/stage, prescription targets, education/counseling plan, and follow-up plan are required. Autofill is preferred when weight/height/DOB/sex exist; otherwise RND must enter targets manually or document why not applicable. Completing intervention promotes the NCP to `active`.

### Meal Planning

Meal plans are optional for non-oral/clinical-only cases but required when the intervention type includes oral diet planning. Manual and generated plans run the same validation: allergens hard-blocked, restrictions flagged, prescription variance calculated, and unresolved critical flags prevent active/final plan status.

### Monitoring and Evaluation

Monitoring starts after active intervention and a follow-up encounter. RND logs visit date, tracked indicators, intake/tolerance, symptoms, and goal evaluation. At least one meaningful indicator or exception reason is required. NCP moves to `active_monitoring`. Completion requires a final evaluation/discharge/reassessment decision.

### Clinical Reports

Reports are separated into:

- Draft preview: available anytime, watermarked incomplete.
- Final NCP Summary: available only when required sections are finalized.
- Initial Care Plan report: allowed after A/D/I, labeled "No monitoring visit yet."
- Full ADIME cycle report: requires at least one monitoring/evaluation entry.

## 11. TO-BE Workflow Diagram

```mermaid
flowchart TD
    A["Select patient"] --> B{"Patient active?"}
    B -- "No" --> C["Reactivate or stop"]
    B -- "Yes" --> D{"Open NCP exists?"}
    D -- "Yes" --> E["Continue open NCP"]
    D -- "No" --> F["Create draft NCP"]

    F --> G["Assessment draft"]
    E --> G
    G --> H["Upload attachments to NCP only"]
    G --> I{"Assessment complete validator passes?"}
    I -- "No" --> G
    I -- "Yes" --> J["Mark assessment_complete"]

    J --> K["Diagnosis/PES draft"]
    K --> L{"At least one finalized PES?"}
    L -- "No" --> K
    L -- "Yes" --> M["Mark diagnosis_complete"]

    M --> N["Intervention draft"]
    N --> O{"Prescription + education + follow-up valid?"}
    O -- "No" --> N
    O -- "Yes" --> P["Mark intervention_complete; NCP active"]

    P --> Q{"Meal plan required?"}
    Q -- "No" --> R["Document reason"]
    Q -- "Yes" --> S["Create/generate meal plan"]
    S --> T{"Allergen/restriction/variance checks pass?"}
    T -- "No" --> S
    T -- "Yes" --> U["Meal plan final/active"]

    P --> V["Follow-up encounter"]
    R --> V
    U --> V
    V --> W{"Monitoring entry complete?"}
    W -- "No" --> V
    W -- "Yes" --> X["Evaluation saved"]
    X --> Y{"Decision"}
    Y -- "Continue" --> V
    Y -- "Modify" --> N
    Y -- "Reassess" --> G
    Y -- "Discharge/Complete" --> Z["Close NCP"]

    P --> AA["Draft/Initial Care Plan report"]
    X --> AB["Full ADIME report"]
    Z --> AC["Final archived report"]
```
