---
trigger: model_decision
description:  apply when working on backend, Laravel, API, migrations, models, services, jobs, or business logic
---

MODULES
RND

Dashboard: announcements, active NCPs, food service snapshot, reports, KPIs, budget chart
Recipes & Ingredients: recipe builder (multi-ingredient, auto-calculates nutrients+cost), foods library (USDA data, macros on card, click for micros). Cost in recipe builder only — never in NCP meal planning
NCP Patients: table (name/age/sex/physician/last assessment/status/risk/actions), OCR document upload on add, patient profile with NCP tabs + audit trail
NCP Assessment: Dietary(D), Anthropometric(A), Client History(C), Biochemical/Labs(B)
Albumin - g/dL
Hematocrit - %
BUN - mg/dL
Hemoglobin - g/dL
Calcium - mg/dL
LDL - mg/dL
Cholesterol - mg/dL
Phosphate - mg/dL
Creatinine - mg/dL
Potassium - mmol/L
Glucose - mg/dL
Sodium - mmol/L
HbA1C - %
Triglycerides - mg/dL
HDL - mg/dL
URR - %
Others - (free text)
BP - mmHg
Acid Base Gas (ABG) - various units (this form, will only take value of said data), Referral and nutritional screening form (will used ocr, autofill form), RND Summary. Biochemical: upload → OCR → auto-populate → manual override. Client history stores: allergies(json), medications(json), religious restrictions
NCP Diagnosis: tabs: Diagnosis Table→P→E→S→PES→AI Review. Domains: NI/NC/NB. PES: "[Problem] related to [Etiology] as evidenced by [S&S]". AI tab(Sonnet): draft PES, risk analysis, accept/reject/edit
NCP Intervention: tabs: Food/Nutrient Delivery, Education, Counseling, Goal Planning, Encounter Context. Nutrients customizable per goal. Disease stage UI stub only — logic TBD. Weekly meal plan, auto-generate via algorithm, real-time macro tracker
NCP Monitoring: versioned updates. Trend graphs. Goal achievement (algorithm). AI decision panel(Sonnet): Continue/Modify/Escalate/Discharge. AI risk + forecasting(Sonnet)
Food Service: Inventory(CRUD/stock/expiry/price/audit), Menu Cycle(weekly/cost per person/budget alerts/templates), Budget(planned vs actual/daily logs/export), Procurement(manual+suggested list/receipt upload/mark purchased→updates inventory)
Reports: NCP(ADIME)/inventory/menu cycle/budget/procurement. PDF via DomPDF. RND sees own, Admin sees all
Calendar: FullCalendar. Auto-events: follow-up dates, monitoring recheck, reassessment, menu activation, expiry(3d before), budget deadlines. System events: mark complete only. Manual: editable+deletable
Notifications: dashboard bell, page, module badges

FSS (mobile)

Dashboard: today's meals+readiness, inventory alerts, notifications, announcements
Inventory: view/update stock, color-coded(red=low/yellow=expiring/green=ok), flag low→notifies RND
Menu Cycle: weekly view, tap→ingredients+prep instructions, color alerts(red=missing/yellow=changed/green=ready)
Meal Prep Log: check off meals→updates status visible to RND+Admin
Notifications: RND/Admin alerts

Admin

Dashboard: system KPIs, charts(admissions/NCP completion/budget/inventory), activity feed
Users: create/edit/deactivate, assign roles, reset passwords
Reports: all roles, system analytics, export PDF
Announcements: create/pin/delete, visibility, read receipts
Audit Logs: full trail, filter, export
Settings: hospital info, budget thresholds, notification rules
Token Usage: daily/monthly chart from ai_usage_logs

NUTRIENT DISPLAY DEFAULTS PER GOAL
Renal Diet:       Energy, Protein, Sodium, Potassium, Phosphorus, Fluid
Diabetic Control: Energy, Carbohydrates, Sugar, Fiber
Cardiac Diet:     Energy, Fat, Saturated Fat, Sodium, Cholesterol
Weight Loss:      Energy, Protein, Carbohydrates, Fat
Weight Gain:      Energy, Protein, Carbohydrates, Fat
High Protein:     Energy, Protein
Custom Plan:      None pre-selected — RND picks everything manually
RND can customize via checklist of all macros+micros. Currently displayed nutrients pre-checked. Changes saved per intervention record.
MEAL PLAN ALGORITHM (NO AI)
1. Read from assessment: allergies(hard exclude), religious restrictions(hard exclude)
2. Read nutrition prescription from intervention
3. Query recipe library — filter out allergens/restricted ingredients, score by nutrient fit
4. Query food library for snacks — same filters
5. Build 7-day plan — assign best-fit recipes, adjust quantities mathematically, ensure variety
6. Validate each day — within 10% of targets = green, miss by >10% = flag for RND review
7. AI fallback (Sonnet) — ONLY if <5 recipes match. Label: "AI Suggested — Pending RND Review"
Patient food dislikes: NOT filtered. Displayed as warning note to RND only: "Patient dislikes: [list]"
Require min 15 recipes in library before auto-generation. Show prompt to RND if below threshold.
RECOMMEND/AVOID ALGORITHM (NO AI — PURE ALGORITHM)
Data pulled from:

assessments.allergies → hard filter
patients.religion + assessments.lifestyle → religious hard filter
assessments.medications → food-drug interaction check
diagnoses + interventions.goal_type + disease_stage → apply clinical_rules table
biochemical_data → lab value refinement (high potassium → stricter even without CKD)
food_items.allergens + micronutrients → match against filters

clinical_rules table drives all logic. Stage values TBD — seed after client confirms.
Output: RECOMMENDED / AVOID / MONITOR with reason per food item.
No AI used here.
Allergen tags on food_items: gluten, dairy, eggs, fish, shellfish, tree_nuts, peanuts, soy, sesame, seafood, chicken
AI USAGE (Sonnet 4.6 for everything)
AI used only where algorithm cannot substitute:

PES statement drafting
M&E decision panel (Continue/Modify/Escalate/Discharge)
Risk score analysis across NCP versions
Trend forecasting
Meal plan fallback (only when <5 recipes match)

NOT AI (pure algorithm/math):

Allergen filtering, religious restriction filtering, food-drug interactions
Disease-based recommend/avoid list, nutrient calculations
Goal achievement labeling, budget calculations, inventory alerts
Meal plan generation (primary), quantity adjustments

Token tracking — before every AI call:
php$todayTokens = AiUsageLog::whereDate('created_at', today())->sum('tokens_total');
if ($todayTokens >= 100000) throw new \Exception('Daily AI limit reached.');
// after call — log tokens_input, tokens_output, tokens_total, model, endpoint
Daily limit: 100,000 tokens. Set $10/month hard cap in Anthropic console.
INTEGRATIONS

PaddleOCR: POST to http://paddleocr:5000/ocr — always background Job — manual override always available
Anthropic: AIService.php only, model claude-sonnet-4-6, rate limit throttle:10,1, always background Job
USDA: FoodService.php only, cache in Redis 7 days, store in food_items after first fetch, meal plan uses local food_items only

SECURITY

All routes: auth:sanctum
Role guards: role:RND / role:FSS / role:Admin
Form Requests for all input validation
File uploads: PDF/JPG/PNG only, max 5MB
APP_DEBUG=false in production
Audit logging via spatie/laravel-activitylog on all sensitive models
Rate limiting: login(5/min), AI endpoints(10/min), OCR endpoints(10/min)