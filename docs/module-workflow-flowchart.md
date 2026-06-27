# Module food service operation Workflow Flowchart

This is the demo guide and source-of-truth workflow map for the NutriScope modules that feed clinical care, food service planning, execution, monitoring, and report generation.

Primary actors:

- **RND** works in the web app and owns food data setup, recipes, menu cycles, patients, NCP/ADIME care plans, meal plans, monitoring, reports, budget, and insights.
- **FSS** works in the mobile app and owns food-service execution: receiving deliveries, logging meal prep, recording diet-list headcounts, updating inventory, and filing weekly accomplishment reports.

The modules share data through food items, recipes, menu cycles, patient records, NCP records, prescriptions, meal plans, inventory, purchase orders, monitoring entries, and archived reports.

Demo logins: RND `rnd@nutriscope.local`, FSS `fss@nutriscope.local` (Maria Santos), both password `nutriscope2024!`. The mobile build points at `https://nutriscope.live`.

---

## 1. Module-Level Workflow

```mermaid
flowchart TD
    A["Start: RND signs in on web"] --> B{"Which module?"}

    B --> FS["Food Service / Food Data"]
    B --> CC["Clinical Care / NCP"]
    B --> RP["Reports"]

    FS --> FS1["Import ingredients or create food items manually"]
    FS1 --> FS2["Review nutrition values, units, allergens, cost, supplier"]
    FS2 --> FS3["Create recipes from ingredients or ready-to-serve items"]
    FS3 --> FS4["Create menu cycle or reusable meal pattern"]
    FS4 --> FS5["Generate shopping list / procurement / inventory flow"]

    CC --> P1["Manage patients"]
    P1 --> P2["Create patient or open existing patient profile"]
    P2 --> P3["Start NCP cycle / assessment"]
    P3 --> P4["Fill patient data and clinical assessment"]
    P4 --> P5["Create diagnosis / PES manually or with AI suggestion"]
    P5 --> P6["Save diagnosis"]
    P6 --> P7["Open intervention"]
    P7 --> P8["Select intervention goal / disease stage"]
    P8 --> P9["Auto-generate nutrition prescription from assessed data"]
    P9 --> P10["Create or generate patient menu cycle / meal plan"]
    P10 --> P11["Adjust plan toward nutrition prescription targets"]
    P11 --> P12["Save intervention and meal plan"]
    P12 --> P13{"Visit number"}
    P13 -->|"Initial visit"| P14["Monitoring locked / not yet applicable"]
    P13 -->|"Second visit and later"| P15["Open Monitoring and Evaluation"]
    P15 --> P16["Enter follow-up data, progress, labs, intake, symptoms"]
    P16 --> P17["Optional AI review / summary"]

    FS4 --> P10
    FS3 --> P10
    P12 --> RP
    P17 --> RP
    FS5 --> RP

    RP --> R1["Generate live reports"]
    R1 --> R2["Review completeness and values"]
    R2 --> R3["Archive / freeze final report when ready"]
```

---

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
    N --> O["Attach on-hand stock, pending PO, supplier, estimated cost"]
    O --> P["Approve list -> purchase order / vendor groups"]
    P --> Q["FSS receives deliveries and inventory updates"]
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

FSS mobile has exactly four bottom tabs. Everything else is reached from the header or from links inside the Prep tab.

```mermaid
flowchart TD
    SPL["App launch: animated NutriScope splash"] --> LOG{"Has saved token?"}
    LOG -->|"No"| LOGIN["Login screen"]
    LOG -->|"Yes"| TABS
    LOGIN --> TABS["Authenticated app shell"]

    TABS --> T1["Tab 1: Dashboard"]
    TABS --> T2["Tab 2: Prep & Accomplishment"]
    TABS --> T3["Tab 3: Inventory"]
    TABS --> T4["Tab 4: Procurement"]

    TABS --> HDR["Header on every screen"]
    HDR --> H1["Megaphone -> Announcements + SOP"]
    HDR --> H2["Bell -> Notifications + unread badge"]
    HDR --> H3["Account -> Settings -> Profile"]

    T2 --> L1["Link: Menu cycles & served population"]
    T2 --> L2["Link: My accomplishment reports"]
    L1 --> MENU["Menu cycle screen: read-only foods + served-pop editor"]
    L2 --> REP["Accomplishment reports viewer"]
```

Page inventory: Dashboard, Prep & Accomplishment, Inventory, Procurement, Menu cycle, Accomplishment reports, Announcements/SOP, Notifications, Settings, Profile.

---

## 6. RND Food Service Planning Workflow

```mermaid
flowchart TD
    A["RND logs in on web"] --> B["Open Food Service"]
    B --> C["Maintain suppliers"]
    B --> D["Create/update FS catalog items"]
    D --> D1{"Item kind?"}
    D1 -->|"Ingredient"| E["Use inside recipes"]
    D1 -->|"Ready-to-serve"| F["Slot directly in a menu cycle"]
    D1 -->|"Supply"| G["Use in manual shopping-list lines"]

    E --> H["Create food-service recipe"]
    H --> H1["Add ingredients, qty, unit, servings, prep notes"]
    H1 --> H2{"Units compatible with item base unit?"}
    H2 -->|"No"| H3["Reject save with validation error"]
    H2 -->|"Yes"| H4["Save recipe; recipe cost recalculated"]

    F --> I["Create/update menu cycle"]
    H4 --> I
    I --> I1["Set week start, cycle days, meal slots, estimated population per day"]
    I1 --> I2{"Slot type?"}
    I2 -->|"Recipe"| I3["Recipe profile: scaled ingredients, cost, cost/head, prep notes"]
    I2 -->|"Ready-to-serve"| I4["Item profile: scaled qty, cost, cost/head, notes"]
    I3 --> I5["Save and compute cycle; activate freezes cost snapshot"]
    I4 --> I5
    I5 --> I6{"Fiscal-year budget exists?"}
    I6 -->|"No"| I7["Show computed food cost only"]
    I6 -->|"Yes"| I8["Compare computed cost/head to per-head/day limit"]

    I8 --> J["Generate suggested shopping list for a date range"]
    I7 --> J
    J --> J1["Resolve covering menu cycle for each date"]
    J1 --> J2{"Every date covered?"}
    J2 -->|"No"| J3["Save with uncovered dates + coverage warning"]
    J2 -->|"Yes"| J4["Save as fully covered"]
    J3 --> J5["Extract planned recipe ingredients + ready-to-serve items"]
    J4 --> J5
    J5 --> J6["Aggregate quantities across dates and populations"]
    J6 --> J7["Attach on-hand, pending PO, default supplier, cost estimates"]

    J7 --> K{"RND approves the list?"}
    K -->|"No"| K1["Stays draft for review/edit"]
    K -->|"Yes"| L["System creates one PO with vendor groups by supplier"]
    L --> M["PPA planning snapshot auto-generates"]
    M --> N["PO enters open_execution -> handed to FSS for receiving"]
```

---

## 7. FSS Mobile Execution Workflow

```mermaid
flowchart TD
    A["FSS opens app -> splash -> login"] --> B["Dashboard"]
    B --> B1["KPI: Meals to log today"]
    B --> B2["KPI: POs awaiting receipt"]
    B --> B3["KPI: Items out of stock"]
    B --> B4["Today's service list"]
    B --> B5["Announcements feed"]

    B --> P["Tab: Prep & Accomplishment"]
    P --> P1["Meal Prep: today's service + Mark served"]
    P1 --> P1a{"Enough stock to serve?"}
    P1a -->|"No"| P1b["Shortfall modal -> proceed with allow_shortfall or cancel"]
    P1a -->|"Yes"| P1c["Day completed; ingredients deducted from inventory"]

    P --> P2["Accomplishment / Diet List form"]
    P2 --> P2a{"Off duty today?"}
    P2a -->|"Yes"| P2b["Save off-duty row -> report cell renders X"]
    P2a -->|"No"| P2c["Enter ward, headcount, task checkboxes -> save"]
    P2b --> P2d["Day total headcount = sum of ward rows"]
    P2c --> P2d
    P2d --> P2e{"All 7 days logged for this staff?"}
    P2e -->|"No"| P2f["Report stays live/incomplete"]
    P2e -->|"Yes"| P2g["Auto-archive and freeze weekly accomplishment report"]

    P --> P3["Link -> Menu cycle screen"]
    P3 --> P3a["Read-only week: foods per meal slot"]
    P3a --> P3b{"Tap a slot"}
    P3b -->|"Recipe"| P3c["Recipe profile: scaled, cost/head, prep notes"]
    P3b -->|"Ready-to-serve"| P3d["FS item profile"]
    P3a --> P3e["Actual served population editor"]

    P --> P4["Link -> My accomplishment reports"]
    P4 --> P4a["List own archived reports"]
    P4a --> P4b["Open native grid: tasks x days, X / check / headcount"]

    B --> Q["Tab: Procurement"]
    Q --> Q1["Purchase-order list"]
    Q1 --> Q2["Open PO -> vendor-group rows"]
    Q2 --> Q3["Open vendor group detail"]
    Q3 --> Q3a["Enter OR number"]
    Q3 --> Q3b["Edit vendor line items"]
    Q3 --> Q3c["Upload receipt image"]
    Q3 --> Q3d["Upload proof-of-purchase photo"]
    Q3 --> Q3e["Mark vendor group received"]
    Q3e --> Q4{"All vendor groups received + served pop set?"}
    Q4 -->|"No"| Q5["PO stays open_execution"]
    Q4 -->|"Yes"| Q6["PO completes -> actual budget/head + ledger deduction"]

    B --> R["Tab: Inventory"]
    R --> R1["Search + filter"]
    R1 --> R2["Tap item -> Add / Deduct stock modal"]
    R2 --> R3["No-stock items flagged red; dashboard KPI updates"]
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
        F3["Diet-list counts + accomplishment tasks"]
        F4["Receive PO vendor groups"]
        F5["Complete meal-prep day"]
        F6["Update inventory"]
        F0 --> F1
        F1 --> F2 & F3 & F4 & F5 & F6
    end

    R3 ==> X1["Shared activated menu cycle"]
    X1 ==> F2
    X1 ==> F5

    R3 ==> X2["Shopping list / PO"]
    X2 ==> F4
    F4 ==> X3["Receipt/proof + stock-in"]
    F5 ==> X4["Stock-out + meal-prep logs"]
    F3 ==> X5["Served population + accomplishment report"]

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
    B -->|"FSS accomplishment"| I["Diet-list counts + per-staff task rows"]

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
2. Open Dashboard for today's service list, meals-to-log KPI, POs awaiting receipt, no-stock items, and announcements.
3. Open Procurement, receive vendor groups, enter OR number, upload receipt/proof, and mark received.
4. Open Prep & Accomplishment, mark served, and record diet-list/accomplishment rows.
5. Open Menu cycles & served population to review active menu foods and set actual served population.
6. Open Inventory to add or deduct stock.
7. Open My accomplishment reports to review archived weekly reports.

### Part D - Reports close the loop

1. PO receipt and served-population data update actual budget/head and budget ledger.
2. Diet-list rows and accomplishment tasks generate FSS weekly reports.
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

