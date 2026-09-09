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
    L1 --> L2["Slot one or more ordered meal lines<br/>No bulk-rice menu line"]
    L2 --> L3["Compute quantity, cost/head, prep notes"]
    L3 --> L4["Activate cycle or keep draft"]

    L4 --> M["Generate suggested shopping list"]
    M --> N["Aggregate recipe ingredients and ready-to-serve items"]
    N --> N1["Review draft; add bulk Rice in kg when needed"]
    N1 --> O["Attach calculated need, purchase values, vendor, and estimated cost"]
    O --> P["Create and release purchase order / vendor groups"]
    P --> Q["FSS confirms actuals, uploads evidence, and marks vendors received"]
```

---

## 3. Clinical Care / NCP Workflow

Clinical care separates the ADIME record from the appointment that carries a visit. A scheduled appointment is patient-level until the RND explicitly starts it; start binds it to the patient's single current cycle. One visit may cover one or several ADIME steps.

```mermaid
flowchart TD
    A["Open Nutrition Care → Patients"] --> B{"Patient exists?"}
    B -->|"No"| C["Create patient and current NCP cycle"]
    B -->|"Yes"| D["Open patient profile"]
    C --> D
    D --> E{"Visit source"}
    E -->|"Scheduled"| F["Save date/time and written purpose"]
    E -->|"Walk-in"| G["Start visit now"]
    F --> H["Explicit Start Visit"]
    H --> I["Bind to current NCP cycle"]
    G --> I

    I --> J["Assessment"]
    J -->|"saved"| K["Diagnosis / PES"]
    K -->|"one or more saved"| L["Intervention and patient meal plans<br/>manual / exact template / prescription-generated"]
    L -->|"saved"| M["Monitoring and Evaluation available"]
    J --> N["Shared visit controls remain available"]
    K --> N
    L --> N
    M --> N
    N --> O{"Visit outcome"}
    O -->|"Normal"| P["Finish Visit"]
    O -->|"Interrupted"| Q["End Early with reason"]
    O -->|"No saved work"| R["Discard Mistaken Start"]

    D --> S["ADIME Records"]
    S --> T["Current Cycle"]
    S --> U["Past Records: 2 per page"]
    T --> V{"Cycle action"}
    V -->|"ADI complete"| W["Complete and Protect"]
    V -->|"Care stops"| X["Discontinue with reason"]
    V -->|"Open and unprotected"| Y["Delete"]
    W --> U
    X --> U

    L --> Z["Meal Plan 1, Meal Plan 2, …"]
    Z --> AA["Existing Patient Menu Plan PDF preview"]
    AA --> AB["View or download"]
```

### Clinical Care Tab Traversal

1. **Patients** - create/select a patient and review Overview, ADIME Records, Appointments, or Attachments.
2. **Appointments** - schedule with date/time and purpose, start a walk-in, or resolve a schedule as rescheduled, no-show, or cancelled.
3. **Assessment** - enter baseline data and save; the active visit records Assessment work when one exists.
4. **Diagnosis / PES** - requires Assessment and at least one saved diagnosis before Intervention unlocks.
5. **Intervention / Prescription** - review calculated targets, care actions, and numbered patient meal plans.
6. **Monitoring and Evaluation** - requires saved Assessment, Diagnosis, and Intervention; it records clinical follow-up, not appointment attendance.
7. **Reports** - open live report preview and download/archive through the existing report workflow.

---

## 4. Monitoring Gate

Monitoring is gated by saved clinical prerequisites, not by a predicted appointment purpose or a separate encounter count.

```mermaid
flowchart TD
    A["RND opens Monitoring"] --> B{"Assessment saved?"}
    B -->|"No"| C["Return to Assessment"]
    B -->|"Yes"| D{"Diagnosis saved?"}
    D -->|"No"| E["Return to Diagnosis"]
    D -->|"Yes"| F{"Intervention saved?"}
    F -->|"No"| G["Return to Intervention"]
    F -->|"Yes"| H["Allow Monitoring entry"]
    H --> I["Compare follow-up data with baseline and targets"]
    I --> J["Save clinical entry"]
    J --> K["Use Appointments/shared visit controls for next schedule"]
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
3. In Appointments, schedule a visit with date/time and purpose, or start a walk-in for the current cycle.
4. Explicitly start the scheduled visit. If navigation changes, use the persistent Resume banner.
5. Fill and save Assessment, then create/review and save a Diagnosis/PES.
6. In Intervention, review prescription targets and save education, counseling, goals, and any patient meal plan.
7. Continue into Monitoring when Assessment, Diagnosis, and Intervention exist; save follow-up clinical data when relevant.
8. Finish or end the visit from the shared controls on any ADIME step. Use Discard only for an empty mistaken start.
9. Resolve unattended schedules as No-show, Cancelled, or Rescheduled; confirm history and administering RND in Appointments.
10. In ADIME Records, complete/protect or discontinue the current cycle when clinically appropriate and review it in Past Records.
11. Select a numbered meal plan to open the existing Patient Menu Plan PDF preview, then view/download it.
12. Generate other clinical reports and archive a final report only when an as-filed copy is needed.

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

RND selects or creates a patient. The profile separates the current NCP cycle, paginated Past Records, appointments, and attachments. A new cycle cannot start while another current cycle is open.

### NCP Creation

The system creates one draft/current NCP cycle. Completing and protecting the cycle requires clinically complete Assessment, Diagnosis, and Intervention. Discontinuing requires a reason. Both terminal outcomes move the cycle to Past Records without changing older cycles.

### Assessment

RND saves the required Assessment fields. Attachments remain supporting files linked to the NCP and do not auto-fill or complete Assessment.

### Diagnosis and PES

Diagnosis is unlocked after Assessment is saved. RND enters structured P/E/S or reviews an assistive draft, then saves at least one diagnosis to unlock Intervention.

### Intervention and Prescription

Intervention is unlocked after Assessment and at least one Diagnosis. It contains goal/stage, backend-calculated prescription targets, food guidance, education, counseling, goals, and patient meal plans. Appointment purpose and next scheduling live in the shared visit workflow, not an Intervention-only encounter form.

### Meal Planning

Patient meal plans may be manual, generated, or template-based. Exact loaded templates can be quantity-scaled in the common editor without item substitution. Auto-generation already follows the saved prescription and can exclude snacks by redistributing targets across main meals, except when the selected liver-disease goal requires frequent intake. In ADIME history plans are numbered within the cycle and open the existing Patient Menu Plan PDF preview with prescription context and view/download actions.

### Monitoring and Evaluation

Monitoring unlocks after Assessment, Diagnosis, and Intervention exist. RND logs follow-up clinical data and compares it with baseline/targets. Appointment status, purpose, and next scheduling remain in Appointments/shared visit controls.

### Clinical Reports

The Reports page prepares current report data for preview. The same Patient Menu Plan report can be opened from a numbered meal plan in ADIME Records. View/download uses the existing PDF routes; archive freezes an as-filed copy.

## 11. Current NCP and Visit Workflow Diagram

```mermaid
flowchart TD
    A["Select patient"] --> B{"Current cycle exists?"}
    B -->|"No"| C["Start new cycle"]
    B -->|"Yes"| D["Continue current cycle"]
    C --> D
    D --> E{"Appointment source"}
    E -->|"Scheduled"| F["Save date/time + purpose"]
    E -->|"Walk-in"| G["Start immediately"]
    F --> H["Explicitly start and bind current cycle"]
    G --> I["One active visit for RND"]
    H --> I
    I --> J["Save one or multiple ADIME sections"]
    J --> K["Record sections worked/completed in visit"]
    K --> L{"Visit outcome"}
    L -->|"Normal"| M["Completed"]
    L -->|"Interrupted"| N["Ended early + reason"]
    L -->|"Empty mistake"| O["Discard"]
    D --> P{"Cycle outcome"}
    P -->|"ADI complete"| Q["Complete and Protect"]
    P -->|"Care stops"| R["Discontinue + reason"]
    Q --> S["Past Records"]
    R --> S
    J --> T["Live clinical report preview"]
    T --> U["View/download or archive"]
```
