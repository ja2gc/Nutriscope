# RND Role — Workflow (current state)

The RND (Registered Nutritionist-Dietitian) is the clinical + food-service planning role. RND routes live under `/api/rnd/*` (middleware `auth:sanctum, role:RND, audit`) and the food-service routes under `/api/fss/*` (shared `role:FSS,RND`). Frontend pages under `frontend/app/(rnd)/`.

> Scope note: this describes **how the system flows**. Known gaps/risks are tracked separately in [`docs/reviews/2026-06-14-system-review.md`](../reviews/2026-06-14-system-review.md) so this document stays stable across bug-fixes.

---

## 1. Dashboard
Landing page after login. Shows: active NCP count, upcoming follow-ups, food-service snapshot (budget-per-head), patient snapshot table, and the announcements feed. KPIs are read live.

## 2. Food Library & USDA (feeds the NCP)
- **Foods library** — food items with USDA-imported macros/micros. `GET /usda/search` (FDC API) → `POST /usda/import/{fdcId}` maps a USDA food into a local `FoodItem` (macros + key micros, deduped by `usda_fdc_id`).
- **Recipes** — clinical recipe builder (multi-ingredient; nutrients auto-calculated from ingredients; cost shown only here, never in NCP meal planning). Optional **AI recipe generator** (Haiku) writes the name + prep text only — **macros are always computed deterministically from ingredients, AI never touches numbers**.
- These food items + recipes are the candidate pool the NCP meal-plan generator draws from.

## 3. NCP (Nutrition Care Process) — the clinical lifecycle
A patient has one or more **NCP records** (care cycles). Start a cycle: `POST /patients/{patient}/ncp-records` → creates an `NcpRecord` (`status='draft'`). The cycle then moves A → D → I → M:

### 3.1 Screening + Assessment (A)
- `POST /ncp-records/{id}/assessment` captures anthropometrics (H/W/BMI), dietary, client history (allergies/medications/religion as JSON), and biochemical/labs.
- **OCR intake:** upload a screening form (`upload-screening`) or lab sheet (`upload-labs`) → background extraction job → review panel with confidence scores → on **approve** the mapped fields populate the Assessment and recompute BMI, nutritional status, and **risk score** (stored on the NCP record).
- Dietary/anthropometric/clinical data can also be entered manually.

### 3.2 Diagnosis (D)
- `POST /ncp-records/{id}/diagnoses` — PES statements ("[Problem] related to [Etiology] as evidenced by [Signs/Symptoms]"), domain ∈ NI / NC / NB.
- **AI assist:** `diagnoses/ai-suggest` (Haiku) drafts 2–4 candidate PES statements with confidence + reasoning; the RND reviews and `ai-approve` stores the chosen ones (`ai_generated=true`). Every AI call is logged to `ai_usage_logs`.

### 3.3 Intervention (I)
- `POST /ncp-records/{id}/intervention` — nutrition prescription: goal type, disease stage, energy/protein/carbs/fat/fluid targets, micronutrient limits, education + counseling notes.
- **Autofill Rx:** `intervention/autofill` runs `NutritionPrescriptionService` (pure compute — Mifflin-St Jeor BMR × activity factor, Hamwi IBW, pediatric Schofield/Holliday-Segar, fluid by weight, edema warning) to propose spec-correct targets.
- **Recommend / avoid:** `intervention/recommendations` maps the goal (renal, diabetic, cardiac, etc.) to `ClinicalRule` rows → recommend / avoid / limit food guidance.
- **Meal plan:** a weekly (7-day × 5-meal) plan, built **manually** or **auto-generated** (`meal-plans/generate`). The generator samples recipes + ready-to-eat food items to hit the intervention's macro/micro targets, filters out the patient's allergens, and distributes by slot (breakfast 25% / AM 10% / lunch 30% / PM 10% / dinner 25%). A real-time macro tracker shows the running totals vs target.
- When Assessment + Diagnosis + Intervention all exist, the NCP record auto-promotes to `status='active'`.

### 3.4 Monitoring & Evaluation (M) — follow-up visits
- **Intended flow:** monitoring is for **follow-up encounters (2nd visit onward)** — the first encounter establishes A/D/I; subsequent visits record progress.
- `POST /ncp-records/{id}/monitorings` records weight, BMI, labs, intake notes, symptoms, and goal achievement per visit (versioned — one row per visit).
- `monitorings/summary` returns a **rule-based delta** (compares the last two visits: weight change, lab flags, intake vs Rx, goal evaluation) at **zero AI cost**.
- `monitorings/ai-review` (Haiku) narrates that compact delta into a 2–3 sentence interpretation + suggested action (Continue / Modify / Escalate / Discharge). Rate-limited and cached per visit-pair; falls back to the rule-based summary if the API fails.

## 4. Food Service (RND planning side)
RND shares the food-service module with FSS (see [`fss.md`](fss.md)): inventory, suppliers, menu cycles, budgets, procurement, recipes, insights. RND typically owns **planning** (menu cycles, budgets), FSS owns **operations** (receiving, meal-prep logging).
- **Menu cycle costing freezes on activation** — when a cycle is activated its costed figures are snapshotted, so its reports keep that cost even if catalog prices change later (planner stays live).

## 5. Reports (browse-don't-generate)
The Reports Center is a **browser**, not a generator. Pick a report type → see the real records/periods that exist → **click to view it in-app** (live PDF preview) → **Download** or **Archive**.
- **Values auto-freeze at their date** — PO-derived reports (Cash Book, Procurement, Budget actuals) are frozen at receipt; menu reports freeze at cycle activation. Re-opening an old report never re-prices it. Only the **letterhead** reflects current branding.
- **Archive** is optional — it stores a permanent PDF of the exact as-filed copy (data + branding + signatories snapshot), for the documents you formally submit. Re-downloading an archive serves the frozen bytes.
- **Prepared-by** is always the logged-in user; other signatory names are blank placeholders filled per-hospital in **Template Edit** (which also edits the letterhead text + logos).
- RND-visible types: Program Project Activity, Menu Calendar, Dietary Cash Book, Procurement Pack, Budget Report, Inventory Report (food service); **Demographic Census, Patient Menu Plan, NCP Summary** (clinical — RND-only). Archives are owner-scoped (you see the ones you filed).

## 6. Calendar (planned) 
[2026-06-19] Pre-defense scope decision — calendar frontend hidden from RND nav (removed from sidebar). Backend (calendar_events table, controller, routes) preserved intact for post-defense wiring. Rationale: upcoming follow-up schedules are already surfaced on the RND dashboard, making the calendar view redundant for the current demo. Auto-event wiring (follow-up dates, monitoring rechecks, menu activation, stock expiry, budget deadlines) remains a post-defense task.

Backend (`calendar_events`) exists; the frontend is currently a scaffold. Intended flow: FullCalendar renders events sourced from the `calendar_events` table. **Auto-events** would be created by system events — follow-up dates, monitoring rechecks, reassessment, menu activation, stock expiry, budget deadlines. System events are mark-complete only; manual events are editable/deletable.

## 7. Notifications (planned)
[2026-06-19] For defense: two notification triggers only — (1) announcement posted, fanned out to users matching the announcement's existing visibility setting; (2) upcoming follow-up, fires 1 day before the most recent scheduled follow-up date. Follow-up notification requires Laravel scheduler running in the backend container — verify cron is set up in Docker. No schema changes needed.

Backend (`notifications`) + read/read-all endpoints exist; the frontend is a scaffold and **no event currently writes notifications**. Intended flow: a notification service creates entries on key events (announcements. upcoming follow-up 1 day before) surfaced via a dashboard bell + notifications page + per-module badges.

## 8. Settings (planned)
Basic settings stuff
[2026-06-19] Check existing scaffold before building. Build frontend only against what the backend already supports. Do not add settings with no backend support.

## 9. Profile (planned)
basic profile stuff
[2026-06-19] At minimum: User name (which should be the same variable for reports that are the ones that prepared it), email, password change. Check if profile photo upload is supported in the backend before adding it to the frontend.