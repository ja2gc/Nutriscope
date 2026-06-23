# NutriScope — Demo & Workflow Guide

A hands-on walkthrough of the whole system: the three roles (**RND**, **FSS**, **Admin**),
exactly where to click on each surface, the two workflows (clinical NCP + food-service
operations), how the roles hand off to one another, and how reports work.

> Setup (install, Docker, env, ports) is in [README.md](README.md). This guide assumes the
> stack is running (`localhost:3000` web, Expo app for mobile) and the DB is seeded.
>
> **Accuracy note:** this guide was written against the actual code (sidebar, screens, API
> routes, controllers), not only the design docs in `docs/` — some of those docs predate the
> current build. Where this guide and `docs/` disagree, trust this guide.

---

## 1. The Three Roles

| Role | Stands for | Signs in on | Owns |
|------|-----------|-------------|------|
| **RND** | Registered Nutritionist-Dietitian | **Web** (`localhost:3000`) | Clinical care (NCP) **and** all food-service *planning* (menus, budgets, shopping lists, purchase orders) |
| **FSS** | Food Service Staff | **Mobile app** (Expo) | Kitchen *execution* — receiving stock, marking days served, diet-list headcounts, proof of purchase |
| **Admin** | System administrator (IT function) | **Web** (`localhost:3000`) | Users, audit log, AI-usage caps, report branding, oversight dashboards |

### Login is platform-gated

`AuthController::login` enforces *where* each role may sign in:

- **FSS → mobile only.** An FSS account rejected on the web with "Food Service staff must sign in through the mobile app."
- **RND / Admin → web only.** Rejected on the mobile app with "This app is for Food Service staff only."
- **Deactivated account** (`is_active=false`) → "Account is deactivated."
- **Wrong password** → "Invalid credentials."

So a full demo needs both surfaces open: the web console (RND + Admin) and the Expo app (FSS).

### Demo accounts — password `nutriscope2024!`

| Role | Email |
|------|-------|
| Admin | `admin@nutriscope.local` |
| RND | `rnd@nutriscope.local` |
| FSS | `fss@nutriscope.local` |

Reseed anytime: `cd backend && php artisan migrate:fresh --seed` (wipes everything).

---

## 2. How the Roles Connect

One Laravel API, two clients. **RND plans → FSS executes → derived numbers flow back to RND → Admin oversees.**
Two value chains share that API:

```
CLINICAL CHAIN  (RND only, on web)
  Patient → start NCP cycle → Assessment → Diagnosis → Intervention (meal plan) → Monitoring/Evaluation
       │
       └─► Reports: NCP Summary, Patient Menu Plan, Demographic Census

FOOD-SERVICE CHAIN  (RND plans on web  ──►  FSS executes on mobile)

  RND (web):  Foods/Recipes ─► Menu Cycle ─► activate ─► Budget cap
                                                │
                                                ▼
              Shopping List (auto, nets stock) ─► Purchase Orders (set "ordered")
                                                │   notifies FSS
                                                ▼
  FSS (mobile): sees PO ─► uploads receipt/proof photo
                                                │
                                                ▼
  RND (web):  marks PO "received"  ─►  stock auto-added to Inventory (ReceivingService)
                                                │
                                                ▼
  FSS (mobile): Prep ─► "Mark served" (deducts inventory) + Diet-list headcount per ward
                                                │
                                                ▼
  Derived: actual ₱-per-head (served cost ÷ served heads)
                                                │
                                                ▼
  RND (web):  Budget graph shows planned cap vs ACTUAL ₱/head
```

**The five cross-role handoffs to highlight in a demo:**

1. RND sets a PO to **`ordered`** → all FSS users get a notification ("upload proof of purchase").
2. FSS **uploads a receipt/proof** photo against that PO (FSS cannot edit the PO itself).
3. RND **marks the PO `received`** → `ReceivingService` adds the quantities to inventory in base units. *(Receiving is RND-only — the FSS app has no "mark received" button; its Procurement tab is read + attachments only.)*
4. FSS **marks the day served** and records **per-ward diet-list headcounts** → that is the actual served population.
5. The system derives **actual ₱/head** and shows it back to RND on the **Budget graph** and the printed **Budget report** — planned cap vs. real cost.

---

## 3. RND — Web Console

**Sidebar (left, dark):** Dashboard · Food Library · **Nutrition Care** (Patients, Assessment, Diagnosis, Intervention, Monitoring) · **Food Service** (Inventory, Menu Cycle, Budget, Procurement, Foods) · Reports Center · Announcements · Notifications · System Settings.

### 3a. Clinical workflow — the NCP cycle

The Nutrition Care Process is a gated sequence. **Each patient can have multiple NCP records (care cycles); each cycle moves A → D → I → M.**

1. **Nutrition Care → Patients** — search/create patients (name, ward, physician, status). Click a patient to open their profile.
2. **Start an NCP cycle** from the patient profile → creates a draft record. The sidebar's Assessment/Diagnosis/Intervention/Monitoring links now resolve to *this* cycle.
3. **Assessment (A)** — enter anthropometrics (height/weight), dietary data, client history (allergies/meds/religion), and biochemical/lab values. **Manual entry** (the old OCR auto-fill was removed). On save, the system computes **BMI**, **nutritional status**, and a deterministic **risk score** stored on the record. Supporting documents (lab sheets, referral forms) can be attached at the bottom of the page — storage only, no extraction; they appear at the end of the printed NCP report and on the patient profile's per-cycle attachments tab.
4. **Diagnosis (D)** — write **PES statements** ("[Problem] related to [Etiology] as evidenced by [Signs/Symptoms]"), domain NI/NC/NB. **AI assist** ("AI suggest") drafts 2–4 candidate PES with confidence + reasoning; the RND reviews and approves the ones to keep. AI never writes the record directly; every call is logged to `ai_usage_logs`.
5. **Intervention (I)** — set the nutrition prescription (goal type, disease stage, energy/protein/carb/fat/fluid targets). **Autofill Rx** computes spec-correct targets (Mifflin-St Jeor BMR, Hamwi IBW, pediatric formulas, fluid-by-weight, edema warning). **Recommend/avoid** maps the goal to clinical rules. Then build the **meal plan** (7-day × 5-meal) manually or **auto-generate** it — the generator samples recipes + ready-to-eat foods to hit the macro targets, filters allergens, and distributes by slot (B 25% / AM 10% / L 30% / PM 10% / D 25%) with a live macro tracker. When A + D + I all exist, the record auto-promotes to **active**.
6. **Monitoring & Evaluation (M)** — record follow-up visits (weight, BMI, labs, intake, symptoms, goal achievement), one row per visit. **Summary** returns a zero-AI rule-based delta of the last two visits; **AI review** narrates that into a 2–3 sentence interpretation + suggested action (Continue / Modify / Escalate / Discharge), rate-limited and cached.

> **Guardrail to demo:** try to delete a patient who has a fully worked-up cycle (A + D + I) → blocked with `422`. Patients with no completed cycle can be deleted.

### 3b. Food-service planning (same RND login, **Food Service** group)

1. **Foods** — the catalog: single ingredients, ready-to-eat items (e.g. a banana, Yakult), and recipes. Edit a catalog item (category, purchase price, purchase unit, units-per-purchase, base unit) — a live "₱ per base unit" readout updates as you type. Recipes show their auto-computed cost from ingredients.
2. **Menu Cycle** — the weekly grid (day × meal) of recipes / ready-to-eat items, each day carrying an estimated headcount. The planner shows live cost-per-head with a **red/amber/green chip** vs. the budget cap (green ≤ cap, amber within 10% over, red beyond).
3. **Activate** a cycle → freezes its cost as a snapshot (so old reports keep their cost even if prices change later). **Only one cycle is active at a time** — activating one retires the previous (it becomes `archived`). Re-activating the same cycle does **not** re-price the frozen snapshot.
4. **Budget** — set the per-head/day cap for the period. This is the planned ceiling.
5. **Procurement → Shopping List** — auto-generate from a menu cycle over a date range (or weekday span). It sums the *actual* planned weekdays in the span, rounds each item **up to whole purchase units**, and **nets out** what's already on hand or already on an open PO — so you never re-buy stock you have. Each line remembers its default vendor.
6. **Procurement → Purchase Orders** — split a shopping list into **one draft PO per vendor**. Set a PO to **`ordered`** to notify FSS, and later to **`received`** (which adds stock to inventory). Suppliers are RND-managed.

---

## 4. FSS — Mobile App (Expo)

**Bottom tabs:** Dashboard · Menu · Prep · Inventory · Procurement. (Notifications, Profile, Settings live in the header account menu.)
FSS is **read-only on planning, read-write on execution.**

1. **Dashboard** — today's service from the active cycle (per-meal, prepped/shortfall flags), tasks to action, no-stock count, and the FSS announcements feed (visibility FSS|All). All live from `GET /fss/dashboard/summary`.
2. **Menu** — read-only view of menu cycles (active first). Tap a day's meal to see the recipe **scaled to that day's headcount** with ingredients, cost, and prep notes. This screen also has **"Served population per day"** rows to **backfill** a headcount for a day you missed.
3. **Prep** — two sections:
   - **Meal Prep — Today's Service:** tap **"Mark served"** → `complete-day` deducts the planned ingredients from inventory at last cost. If stock is short, a **shortfall modal** appears ("insufficient stock") with "Proceed anyway" (records the shortfall and notifies RND) or "Cancel".
   - **Accomplishment / Diet List:** one entry per **ward** — ward name, **population (headcount)**, seven task checkboxes (helped food prep, stored supplies, collected diet list, apportioned food, cleaned utensils, assistant cook, maintained cleanliness), and an off-duty toggle. The day's ward populations **sum into the served population** that drives actual ₱/head. A running "Today's total headcount" shows the sum.
4. **Inventory** — current stock, binary state (in-stock green / out red). Receiving a PO updates this automatically; manual adjustments are normalized to base units.
5. **Procurement** — POs grouped by status (Ordered–Awaiting Receipt / Received / Draft / Cancelled). Expand a PO to see its items, then **Upload** a **receipt** or **proof** photo (Library or Camera), with an optional caption. FSS can delete its own attachments. **FSS cannot create, edit, or change the status of a PO** — the empty state literally reads "Purchase orders created by RND will appear here."

---

## 5. Admin — Web Console

**Sidebar:** Admin Dashboard · Manage Users · Reports · Audit Logs · Announcements · System Settings. (Notifications + Profile via the top-bar bell / account card.)
Admin = the IT-department function: **access control + oversight, no clinical data path.**

1. **Admin Dashboard** — live KPIs: users by role, patient count, **AI usage** (month-to-date calls/tokens + a 30-day token chart), audit-log volume (total + last 7 days), report count. All aggregated in SQL — count/rate level only, no patient detail.
2. **Manage Users** — create/edit/deactivate accounts, assign role (RND/FSS/Admin), reset passwords (rate-limited). `is_active=false` blocks the user at both login and middleware — this is the master switch that gates every other role.
3. **Audit Logs** — every mutating (non-GET) request across all roles is logged; clinical PHI **values** are redacted at write-time (field names kept, values replaced with "••• redacted"). Filterable + paginated, with expandable JSON properties.
4. **Announcements** — compose announcements with a category and **visibility** (FSS / Admin / All); Admin alone can **pin**. On save, a notification fans out to every active recipient matching the visibility (excluding the author). Uses the same shared composer component as RND.
5. **System Settings** — **report branding** (hospital name, address, accreditation, logos) used as the letterhead on every generated PDF; plus device-local appearance prefs and "mark all notifications read."
6. **Reports** — **Admin sees only three non-clinical types**: Demographic Census, Budget Report, Procurement Pack. This is **enforced server-side** (`ReportController::ADMIN_ALLOWED_TYPES`) — requesting NCP Summary or Patient Menu Plan returns `403`, by design (DPA / need-to-know).

---

## 6. Reports — browse, don't generate

The Reports Center (RND: **Reports Center**; Admin: **Reports**) is a **browser**, not a one-shot generator:

1. **Pick a report type** → see the real records/periods that actually exist.
2. **Click to view** → live in-app PDF preview.
3. **Download** the PDF, or **Archive** it (stores a permanent, frozen copy of the exact as-filed bytes — data + branding + signatories — for documents you formally submit).

Key behaviors:

- **Values auto-freeze at their date.** PO-derived reports (Cash Book, Procurement, Budget actuals) freeze at receipt; menu reports freeze at cycle activation. Re-opening an old report never re-prices it — only the **letterhead** reflects current branding (edited in Settings).
- **"Prepared by"** is always the logged-in user (their profile name); other signatory names come from the branding/template config.
- **RND report types:** Program Project Activity, Menu Calendar, Dietary Cash Book, Procurement Pack, Budget Report, Inventory Report (food service); **Demographic Census, Patient Menu Plan, NCP Summary** (clinical, RND-only). Archives are owner-scoped.
- **Admin report types:** Demographic Census, Budget Report, Procurement Pack only — no clinical reports, ever.

---

## 7. Suggested Demo Script (~12 min)

1. **Admin (web)** — log in at `localhost:3000`. Show the dashboard KPIs (users by role, AI-usage chart). Open Manage Users → point out the three roles and the active toggle.
2. **RND (web), clinical** — Nutrition Care → Patients → open a seeded patient. Walk the cycle: Assessment (show auto BMI + risk score) → Diagnosis (AI-suggest a PES, approve it) → Intervention (Autofill Rx, then auto-generate the meal plan, watch the macro tracker) → Monitoring (Summary, then AI review).
3. **RND (web), food service** — Food Service → Menu Cycle: show the cost-per-head chip vs. budget. Procurement → generate a shopping list for the current span (show stock netting), split into POs, set one to **`ordered`**.
4. **FSS (mobile)** — log in on the Expo app; the PO notification appears. Procurement tab → expand the ordered PO → **Upload** a proof photo. Then Prep tab → **Mark served**, and add a **diet-list** ward headcount.
5. **RND (web)** — Procurement → mark the PO **`received`** → switch to Inventory and show the stock jump. Then Food Service → Budget: the **actual ₱/head** is now populated from the served days (demo data lands it at **₱100–130/head**), shown against the planned cap.
6. **Admin (web)** — Audit Logs: show the new activity rows (clinical values redacted). Dashboard: AI-usage tick from the diagnosis suggestion.

---

## 8. Error Handling — what you'll see

The API fails specifically rather than 500-ing:

| Situation | Response |
|-----------|----------|
| Wrong password | `401 Invalid credentials.` |
| FSS on web / RND-Admin on mobile | `403` with the platform-specific message |
| Deactivated account | `403 Account is deactivated.` |
| FSS tries a planning write (PO/menu/budget/supplier) | `403` (role middleware) |
| Admin requests a clinical report | `403` (allowlist enforced server-side) |
| Mark-served with insufficient stock | `422 insufficient stock…` → shortfall modal ("Proceed anyway" / "Cancel") |
| Backfill served pop. for a day never served | `404 No completed service day for that date…` |
| Generate POs from an empty shopping list | `422 Shopping list has no items.` |
| Shopping-list span with end weekday before start | `422 End weekday must be on or after start weekday.` |
| Duplicate assessment on one NCP record | `409 Assessment already exists…` |
| Delete a fully worked-up patient | `422` clinical-records guard |
| Manual budget log on a day with received POs | `201` **with a warning** so cash isn't double-counted |
| Any validation failure | `422` with field-level messages |

Stock changes and notifications run inside DB transactions with `afterCommit`, so a
rolled-back PO never fires a phantom "awaiting receipt" alert, and a failed receive never
half-adds stock.

---

## 9. Resetting Between Demos

```bash
cd backend
php artisan migrate:fresh --seed   # wipes everything, reseeds demo data
```

Always run the **full** `db:seed` (not a single seeder) — the seed order matters
(users → catalog → recipes → food-service demo → patients), and the food-service demo
needs the RND user to exist first.
