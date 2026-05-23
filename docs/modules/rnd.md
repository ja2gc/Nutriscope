Website

Dashboard: announcements, active NCPs, food service snapshot, reports, KPIs, budget chart
Recipes & Ingredients: recipe builder (multi-ingredient, auto-calculates nutrients+cost), foods library (USDA data, macros on card, click for micros). Cost in recipe builder only — never in NCP meal planning
NCP Patients: table (name/age/sex/physician/last assessment/status/risk/actions), OCR document upload on add, patient profile with NCP tabs + audit trail
NCP Assessment: Dietary(D), Anthropometric(A), Client History(C), Biochemical/Labs(B), Referral, RND Summary. Biochemical: upload → OCR → auto-populate → manual override. Client history stores: allergies(json), medications(json), religious restrictions
NCP Diagnosis: tabs: Diagnosis Table→P→E→S→PES→AI Review. Domains: NI/NC/NB. PES: "[Problem] related to [Etiology] as evidenced by [S&S]". AI tab(Sonnet): draft PES, risk analysis, accept/reject/edit
NCP Intervention: tabs: Food/Nutrient Delivery, Education, Counseling, Goal Planning, Encounter Context. Nutrients customizable per goal. Disease stage UI stub only — logic TBD. Weekly meal plan, auto-generate via algorithm, real-time macro tracker
NCP Monitoring: versioned updates. Trend graphs. Goal achievement (algorithm). AI decision panel(Sonnet): Continue/Modify/Escalate/Discharge. AI risk + forecasting(Sonnet)
Food Service: Inventory(CRUD/stock/expiry/price/audit), Menu Cycle(weekly/cost per person/budget alerts/templates), Budget(planned vs actual/daily logs/export), Procurement(manual+suggested list/receipt upload/mark purchased→updates inventory)
Reports: NCP(ADIME)/inventory/menu cycle//ncp meal plan/budget/procurement. PDF via DomPDF. RND sees own, Admin sees all
Calendar: Library: FullCalendar (free open source) — install with:
pnpm add @fullcalendar/react @fullcalendar/core @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/interaction
FullCalendar renders events only — all event data lives in calendar_events MySQL table, fetched from Laravel,FullCalendar. Auto-events: follow-up dates, monitoring recheck, reassessment, menu activation, expiry(3d before), budget deadlines. System events: mark complete only. Manual: editable+deletable
Notifications: dashboard bell, page, module badges