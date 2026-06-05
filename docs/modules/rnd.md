## RND Module (Website)

Dashboard: database-backed announcements, active NCPs, food service snapshot, reports, KPIs, budget chart
Recipes & Ingredients: recipe builder (multi-ingredient, auto-calculates nutrients+cost), foods library (USDA data, macros on card, click for micros). Cost in recipe builder only — never in NCP meal planning.
  - **AI Recipe Generator** (Food Library): RND selects ingredients + optional style prompt ("Filipino soup", "low sodium") → Haiku (`claude-haiku-4-5-20251001`) returns recipe name + prep instructions. Macros always calculated deterministically from ingredients — AI never touches numbers. ~$0.001/generation. Logged to `ai_usage_logs`.

### NCP Workflow

NCP Patients: table (name/age/sex/physician/last assessment/status/risk/actions), document upload on add, patient profile with NCP tabs + audit trail

NCP Assessment: Dietary(D), Anthropometric(A), Client History(C), Biochemical/Labs(B), Referral, RND Summary.
  - Screening forms: Upload adult or pediatric screening form → OCR extraction → auto-populate clinical conditions, intake/weight history, referral type → review panel with confidence scores → manual override
  - Biochemical: Upload lab results → OCR extraction → auto-populate biochemical_data fields (Albumin through URR) → review panel → manual override
  - Dietary, Anthropometric, and Clinical data entered manually (no OCR)
  - Deterministic risk scoring from screening checklist stored as `ncp_records.risk_score` in M4
  - Client history stores: allergies(json), medications(json), religious restrictions

NCP Diagnosis: tabs: Diagnosis Table→P→E→S→PES→AI Review. Domains: NI/NC/NB. PES: "[Problem] related to [Etiology] as evidenced by [S&S]". AI tab(Sonnet): draft PES, risk analysis, accept/reject/edit

NCP Intervention: tabs: Food/Nutrient Delivery, Education, Counseling, Goal Planning, Encounter Context. Nutrients customizable per goal. Disease stage UI stub only — logic TBD. Weekly meal plan, auto-generate via algorithm, real-time macro tracker

NCP Monitoring: versioned updates. Trend graphs. Goal achievement (algorithm). AI decision panel(Sonnet): Continue/Modify/Escalate/Discharge. System-calculated risk trend and forecasting support.

### Food Service

Inventory(CRUD/stock/expiry/price/audit), Menu Cycle(weekly/cost per person/budget alerts/templates), Budget(planned vs actual/daily logs/export), Procurement(manual+suggested list/receipt upload/mark purchased→updates inventory)

Procurement Documents:
  - Acceptance & Inspection Report: upload via OCR → auto-populate inspection_reports + line items. Also generatable as PDF from system data
  - Statement of Marketing Purchased: upload via OCR → auto-populate marketing_statements + line items. Also generatable as PDF from system data
  - Summary of Marketing Purchased: auto-generated from marketing_statement data

### Reports

Report types available to RND:
1. **ADIME Individual** — single patient NCP summary (open → search patient → generate/export)
2. **ADIME Aggregate** — aggregate patient analytics across date range
3. **NCP Census** — patient demographics, malnutrition breakdown, ADIME completion metrics (bi-annual reference format, arbitrary date range)
4. **Inventory** — stock levels, expiry tracking, usage rates
5. **Budget & Procurement** — planned vs actual, variance, procurement summaries, inspection records
6. **Menu Cycle** — weekly meal schedule with recipes, costs, nutritional breakdown (selectable by week/date range)
7. **Patient Menu Plan** — open report → search patient → generate/export individual meal plan
8. **Inspection Report** — generated from system procurement data
9. **Marketing Statement** — generated from system procurement data

All reports: PDF via DomPDF, background job generation, arbitrary date range selection. RND sees own reports, Admin sees all.

### Calendar
Library: FullCalendar (free open source) — install with:
pnpm add @fullcalendar/react @fullcalendar/core @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/interaction
FullCalendar renders events only — all event data lives in calendar_events MySQL table, fetched from Laravel. Auto-events: follow-up dates, monitoring recheck, reassessment, menu activation, expiry(3d before), budget deadlines. System events: mark complete only. Manual: editable+deletable

### Notifications
Dashboard bell, page, module badges
