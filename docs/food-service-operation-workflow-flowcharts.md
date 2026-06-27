# Food Service Operation — Demo Flowcharts & Guide

This is the **demo guide and source-of-truth flowchart** for the food-service operation, covering both actors:

- **RND** (Research & Nutrition Dietitian) — works on the **web app**, owns *planning*: catalog, recipes, menu cycles, shopping lists, purchase orders, budget, insights, and all reports.
- **FSS** (Food Service Staff) — works on the **mobile app** (Expo/Android), owns *execution*: receiving deliveries, logging meal prep, recording diet-list headcounts, updating inventory, and filing the weekly accomplishment report.

The two actors **start independently**. RND begins from planning on the web; FSS begins from the today-work queue on mobile. They **only intersect through shared system artifacts**: the activated menu cycle, purchase orders & vendor groups, inventory, diet-list counts, meal-prep logs, the fiscal-year budget ledger, and the accomplishment report. Everything below reflects the **actual implemented system** (not an idealized design).

> Demo logins (seeded): RND `rnd@nutriscope.local`, FSS `fss@nutriscope.local` (Maria Santos), both password `nutriscope2024!`. The mobile build points at the production backend `https://nutriscope.live`.

---

## 1. FSS Mobile Navigation Map (read this first)

FSS mobile has **exactly four bottom tabs**. Everything else is reached from the **header** (top-right icons) or from **links inside the Prep tab** — not from the tab bar. This keeps the primary kitchen actions large and fat-finger-proof.

```mermaid
flowchart TD
    SPL["App launch: animated NutriScope splash"] --> LOG{"Has saved token?"}
    LOG -->|No| LOGIN["Login screen"]
    LOG -->|Yes| TABS
    LOGIN --> TABS["Authenticated app shell"]

    TABS --> T1["Tab 1: Dashboard"]
    TABS --> T2["Tab 2: Prep & Accomplishment"]
    TABS --> T3["Tab 3: Inventory"]
    TABS --> T4["Tab 4: Procurement"]

    TABS --> HDR["Header (every screen)"]
    HDR --> H1["Megaphone -> Announcements + SOP"]
    HDR --> H2["Bell -> Notifications (+ unread badge)"]
    HDR --> H3["Account -> Settings -> Profile"]

    T2 --> L1["Link: Menu cycles & served population"]
    T2 --> L2["Link: My accomplishment reports"]
    L1 --> MENU["Menu cycle screen (read-only foods + served-pop editor)"]
    L2 --> REP["Accomplishment reports viewer"]
```

**Page inventory (10 screens, 4 tabs):** Dashboard, Prep & Accomplishment, Inventory, Procurement *(tabs)*; Menu cycle, Accomplishment reports *(opened from Prep)*; Announcements/SOP, Notifications, Settings, Profile *(opened from header)*. No screen is a dead-end — every input has a submit path and every produced artifact (incl. the accomplishment report) is viewable in-app.

---

## 2. RND Web Workflow — Food Service Planning

```mermaid
flowchart TD
    A["RND logs in on web"] --> B["Open Food Service"]
    B --> C["Maintain suppliers"]
    B --> D["Create/update FS catalog items"]
    D --> D1{"Item kind?"}
    D1 -->|Ingredient| E["Use inside recipes"]
    D1 -->|Ready-to-serve| F["Slot directly in a menu cycle"]
    D1 -->|Supply| G["Use in manual shopping-list lines"]

    E --> H["Create food-service recipe"]
    H --> H1["Add ingredients, qty, unit, servings, prep notes"]
    H1 --> H2{"Units compatible with item base unit?"}
    H2 -->|No| H3["Reject save with validation error"]
    H2 -->|Yes| H4["Save recipe; recipe cost recalculated"]

    F --> I["Create/update menu cycle"]
    H4 --> I
    I --> I1["Set week start, cycle days, meal slots, estimate_population per day"]
    I1 --> I2{"Slot type?"}
    I2 -->|Recipe| I3["Recipe profile: scaled ingredients, cost, cost/head, prep notes"]
    I2 -->|Ready-to-serve| I4["Item profile: scaled qty, cost, cost/head, notes"]
    I3 --> I5["Save & compute cycle; activate freezes cost snapshot"]
    I4 --> I5
    I5 --> I6{"Fiscal-year budget exists?"}
    I6 -->|No| I7["Show computed food cost only"]
    I6 -->|Yes| I8["Compare computed cost/head to per-head/day limit"]

    I8 --> J["Generate suggested shopping list for a date range"]
    I7 --> J
    J --> J1["Resolve covering menu cycle for each date"]
    J1 --> J2{"Every date covered?"}
    J2 -->|No| J3["Save with uncovered dates + coverage warning"]
    J2 -->|Yes| J4["Save as fully covered"]
    J3 --> J5["Extract planned recipe ingredients + ready-to-serve items"]
    J4 --> J5
    J5 --> J6["Aggregate quantities across dates and populations"]
    J6 --> J7["Attach on-hand, pending PO, default supplier, cost estimates"]

    J7 --> K{"RND approves the list?"}
    K -->|No| K1["Stays draft for review/edit"]
    K -->|Yes| L["System creates ONE PO with vendor groups (one per supplier)"]
    L --> M["PPA planning snapshot auto-generates"]
    M --> N["PO enters open_execution -> handed to FSS for receiving"]
```

---

## 3. FSS Mobile Workflow — Execution (the demo path)

```mermaid
flowchart TD
    A["FSS opens app -> splash -> login"] --> B["Dashboard"]
    B --> B1["KPI: Meals to log today"]
    B --> B2["KPI: POs awaiting receipt (taps into Procurement)"]
    B --> B3["KPI: Items out of stock"]
    B --> B4["Today's service list"]
    B --> B5["Announcements feed"]

    %% Prep & Accomplishment tab
    B --> P["Tab: Prep & Accomplishment"]
    P --> P1["Meal Prep: today's service + Mark served"]
    P1 --> P1a{"Enough stock to serve?"}
    P1a -->|No| P1b["Shortfall modal -> proceed with allow_shortfall or cancel"]
    P1a -->|Yes| P1c["Day completed; ingredients deducted from inventory"]

    P --> P2["Accomplishment / Diet List form"]
    P2 --> P2a{"Off duty today?"}
    P2a -->|Yes| P2b["Save off-duty row -> report cell renders X"]
    P2a -->|No| P2c["Enter ward, headcount, task checkboxes -> save"]
    P2b --> P2d["Day total headcount = sum of ward rows"]
    P2c --> P2d
    P2d --> P2e{"All 7 days (Mon-Sun) logged for this staff?"}
    P2e -->|No| P2f["Report stays live/incomplete"]
    P2e -->|Yes| P2g["Auto-archive & FREEZE weekly accomplishment report"]

    P --> P3["Link -> Menu cycle screen"]
    P3 --> P3a["Read-only week: foods per meal slot"]
    P3a --> P3b{"Tap a slot"}
    P3b -->|Recipe| P3c["Recipe profile (scaled, cost/head, prep notes)"]
    P3b -->|Ready-to-serve| P3d["FS item profile via fs-items/{id}/profile"]
    P3a --> P3e["Actual served population editor — ANY day of the cycle"]

    P --> P4["Link -> My accomplishment reports"]
    P4 --> P4a["List own archived reports (named 'Maria Santos — ... week span')"]
    P4a --> P4b["Open -> native grid: tasks x days, X / tick / headcount, daily totals"]

    %% Procurement tab
    B --> Q["Tab: Procurement"]
    Q --> Q1["Purchase-order list (events)"]
    Q1 --> Q2["Open PO -> vendor-group rows"]
    Q2 --> Q3["Open vendor group -> detail"]
    Q3 --> Q3a["Enter OR number"]
    Q3 --> Q3b["Edit vendor line items (purchase qty/unit/price)"]
    Q3 --> Q3c["Upload receipt image"]
    Q3 --> Q3d["Upload proof-of-purchase photo"]
    Q3 --> Q3e["Mark vendor group received"]
    Q3a --> Q4{"All vendor groups received + served pop set?"}
    Q3c --> Q4
    Q3e --> Q4
    Q4 -->|No| Q5["PO stays open_execution"]
    Q4 -->|Yes| Q6["PO completes -> actual budget/head + ledger deduction"]

    %% Inventory tab
    B --> R["Tab: Inventory"]
    R --> R1["Search + filter (ingredient/supply/recipe)"]
    R1 --> R2["Tap item -> Add / Deduct stock modal"]
    R2 --> R3["No-stock items flagged red; feeds dashboard KPI"]

    %% Header
    B --> H["Header"]
    H --> H1["Announcements + SOP"]
    H --> H2["Notifications + unread badge"]
    H --> H3["Settings -> Profile / appearance / mark-all-read / logout"]
```

---

## 4. Two-Actor Interconnection (where RND & FSS meet)

```mermaid
flowchart LR
    subgraph RND["RND — web planning lane"]
        R0["Login (web)"]
        R1["FS catalog items"]
        R2["Recipes + prep notes"]
        R3["Menu cycle + estimate_population"]
        R4["Activate menu cycle"]
        R5["Date-driven shopping list"]
        R6["Convert list -> 1 PO + vendor groups"]
        R7["PPA planning snapshot"]
        R8["Budget / Insights / Reports"]
        R0 --> R1 --> R2 --> R3 --> R4 --> R5 --> R6 --> R7 --> R8
    end

    subgraph FSS["FSS — mobile execution lane"]
        F0["Login (mobile)"]
        F1["Dashboard work queues"]
        F2["View active menu cycle (read-only)"]
        F3["Diet-list counts + accomplishment tasks"]
        F4["Receipt/proof + OR on vendor groups"]
        F5["Complete meal-prep day"]
        F6["Set actual served population (any day)"]
        F7["Inventory updates"]
        F0 --> F1
        F1 --> F2 & F3 & F4 & F5 & F6 & F7
    end

    R4 ==> X1["Shared activated menu cycle"]
    X1 ==> F2
    X1 ==> F5

    R5 ==> X2["List uses exact menu dates + estimate_population"]
    X2 ==> R6

    R6 ==> X3["Open PO / vendor groups"]
    X3 ==> F4
    F4 ==> X4["Receipt/proof + OR + inventory stock-in"]

    F3 ==> X6["served_population from diet-list rows"]
    F6 ==> X6
    F5 ==> X7["Inventory stock-out + meal-prep logs"]
    X4 ==> X8["PO completion"]
    X6 ==> X8
    X8 ==> X9["Actual budget/head + budget-ledger deduction"]
    X9 ==> R8

    F3 ==> X10["FSS Accomplishment Report (own only)"]
    X10 ==> X11["Frozen snapshot when 7-day rule met"]
    X11 ==> R8
```

**Report visibility:** RND sees **all** FSS accomplishment reports (index/show/download); FSS sees **only their own**. Render/archive params are forced to the authenticated FSS user's ID.

---

## 5. Reports & Data Flow

```mermaid
flowchart TD
    A["Operational source tables"] --> B{"Report / graph type?"}
    B -->|Budget summary / burn| C["Fiscal-year budget + budget_ledger"]
    B -->|Per-head actual vs limit| D["PO costs + served_population + per-head/day limit"]
    B -->|Deduction timeline| E["budget_ledger entries + PO references"]
    B -->|Spend by supplier| F["Received PO vendor groups"]
    B -->|Menu / PPA / procurement| H["Menu cycle, shopping list, PO, frozen PPA"]
    B -->|FSS accomplishment report| G["diet_list_counts + per-staff task rows"]

    C --> I["RND web view"]
    D --> I
    E --> I
    F --> I
    H --> I
    G --> J["FSS: own reports only (mobile native grid)"]
    G --> I2["RND: all FSS accomplishment reports"]

    I --> K{"Archive?"}
    J --> K
    K -->|No| L["Live from current processed data"]
    K -->|Yes| M["Frozen snapshot stored; reproducible view/download"]
```

---

## 6. FSS Scope Guardrails (enforced server-side)

```mermaid
flowchart TD
    A["FSS mobile action"] --> B{"Action type?"}
    B -->|View menu cycle| C["Allowed: read-only active/saved menu"]
    B -->|Record diet list / accomplishment| D["Allowed: diet_list_counts + served basis"]
    B -->|Complete meal prep| E["Allowed: meal-prep log + inventory stock-out"]
    B -->|Set served population (any day)| E2["Allowed: upserts log for that cycle date"]
    B -->|Upload receipt/proof/OR| F["Allowed on existing vendor group"]
    B -->|Inventory add/deduct/restock| G["Allowed within inventory scope"]
    B -->|View own accomplishment report| R["Allowed: own reports only"]
    B -->|Create/edit menu, recipe, list, PO, supplier, budget, insights, PPA| H["BLOCKED 403 — RND-owned"]
    B -->|View another staff's report| H2["BLOCKED — owner-scoped"]
```

---

## 7. Step-by-Step Demo Script

**Part A — RND plans (web, `rnd@nutriscope.local`):**
1. Log in on web, open **Food Service**. The seed already contains 4 weekly cycles (3 past + current active) plus a draft next-week cycle.
2. Open the **active menu cycle**; click any slotted recipe/food to confirm scaled quantity, ingredients, total cost, cost/head, and prep notes.
3. **Generate a suggested shopping list** for a date range; observe coverage resolution and uncovered-date flagging.
4. **Approve/convert** the list → one PO with **vendor groups** (one per supplier) + an auto PPA planning snapshot. The PO enters `open_execution`.

**Part B — FSS executes (mobile, `fss@nutriscope.local` / Maria Santos):**
5. Launch the app → **animated NutriScope splash** → login. Land on **Dashboard** (today's service, meals-to-log, POs awaiting receipt, no-stock, announcements).
6. Tap the **POs awaiting receipt** KPI → jumps into the **Procurement** tab. Open a PO → a **vendor group** → enter the **OR number**, adjust line items, **upload a receipt image and a proof photo**, then **Mark received**.
7. Open **Prep & Accomplishment**:
   - **Mark served** for today (handles the shortfall modal if stock is short → deducts inventory).
   - Fill the **Accomplishment / Diet List** form: ward + headcount + task checkboxes, or flip **Off duty** to file an X day. The running total sums all ward rows for the day.
8. From Prep, tap **Menu cycles & served population** → read-only week; tap a recipe and a ready-to-serve food to confirm both open their profile; set **actual served population for any day** of the cycle.
9. From Prep, tap **My accomplishment reports** → open a seeded archived report ("Maria Santos — Accomplishment Report …week span"); confirm the native grid (tasks × days, ✓ / X / headcount, daily totals).
10. Open **Inventory**; add/deduct stock on an item; confirm no-stock items show red and the dashboard KPI reflects it.
11. Header: open **Announcements/SOP**, **Notifications** (unread badge), and **Settings → Profile**.

**Part C — the loop closes:**
12. Once **all vendor groups are received** and **served population exists** for the PO's dates, the PO **completes** → actual budget-per-head computes and a **budget-ledger deduction** posts.
13. Once **all 7 days (Mon–Sun)** have a diet-list row for the FSS staff, the **weekly accomplishment report auto-archives and freezes** — later diet-list edits never change the frozen snapshot.
14. RND opens **Budget / Insights / Reports** to see graphs and the FSS accomplishment reports (RND sees all; FSS sees only their own).

**Seeded state for the demo:** 3 frozen accomplishment reports for Maria Santos (one per completed past week, named by staff + week span, each with a generated PDF), 12 vendor groups across the four weeks, a fiscal-year budget with a 13-row ledger, completed meal-prep logs, and diet-list counts — so every report, KPI, and the actual budget-per-head render real figures on first launch.
