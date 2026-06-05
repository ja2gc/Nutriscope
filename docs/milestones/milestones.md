# NutriScope Milestones

## Workflow Rules
- **Mark done only after testing and user approval.**
- **Backend**: Run `php artisan test` — all tests must pass before marking done.
- **Frontend**: Confirm renders correctly in browser via `/verify` or `/run`.
- Use `/plan` before implementing anything non-trivial. Wait for approval.
- Use `/code-review` after completing a feature block.

---

## Completed ✅

### Planning & Architecture
- [x] Models, migrations, seeders, and system architecture defined
- [x] Reference document analysis (NCP forms, screening forms, reports)
- [x] All documentation aligned with system direction

### Laravel Foundation
- [x] Laravel setup, Docker (MySQL + Redis), Sanctum, API routes scaffold
- [x] Role middleware (RND / FSS / Admin) + audit middleware
- [x] Auth endpoints (login, logout, me) with tests

### Patient Management
- [x] Patient CRUD API + NCP record initialization
- [x] Risk score output, patient test coverage
- [x] Announcements backend (model, API, tests)

### Database Scaffold
- [x] All supporting table migrations (20+ tables across NCP, inventory, budgets, reports, etc.)
- [x] Extraction template seeder (adult, pediatric, lab)
- [x] Report template seeder (9 types)

### OCR & Extraction Pipeline
- [x] OCR service (HTTP client + mock fallback)
- [x] Extraction engine (template-based regex parser + confidence scoring)
- [x] Document processing job + event
- [ ] PHPUnit: OCR and extraction tests

### NCP Assessment
- [x] Assessment CRUD API + biochemical data upsert
- [x] Risk score calculator (7 factors → score + nutritional status)
- [x] Screening document upload and approval endpoints
- [x] Assessment test coverage

### Diagnosis & AI Review
- [x] Diagnosis CRUD API + PES statement builder
- [x] AI suggestion service (Claude Haiku) + approve/reject endpoints

### Intervention & Meal Planning
- [x] Intervention CRUD API
- [x] Meal plan generation service (7-step algorithm: allergen filter, nutrient scoring, 10% tolerance)
- [x] RecommendService (clinical rules-driven recommend/avoid engine)
- [ ] PHPUnit: MealPlanService and intervention tests

### Food Library & Recipes
- [x] Food item CRUD with full micronutrient tracking (19+ nutrients)
- [x] USDA food search, import, auto-category mapping, and Big 9 allergen detection
- [x] Filipino food library seeder (35 USDA-sourced ingredients)
- [x] Recipe CRUD with auto-calculated macros, micros, and cost
- [x] USDA preview endpoint (nutrients without importing)

### Clinical Rules Engine
- [x] RecommendService: allergen, religion, medication, and biochemical rules
- [x] Intervention within-target tolerance check (10%)
- [x] FoodItem allergen scope methods
- [ ] ClinicalRulesSeeder: full 9-condition, 30+ rule coverage
- [ ] MealPlanService AI fallback (Claude Sonnet, < 5 recipes gate)

### Monitoring
- [x] Monitoring CRUD API + resource
- [ ] Trend calculation and goal achievement algorithm
- [ ] AI decision panel (Continue / Modify / Escalate / Discharge)

### Food Service Operations
- [x] Inventory, menu cycle, budget CRUD
- [x] Shopping list generation (menu vs stock shortfall)
- [x] Purchase order workflow (Draft → Ordered → Received, auto-restock)
- [x] Supplier management
- [ ] PHPUnit: food service feature tests

### Admin Module
- [x] User management CRUD + role assignment + soft delete
- [x] Audit log controller with filtering
- [x] Admin announcement controller
- [x] Admin test coverage

### Auth & Shell (Frontend)
- [x] Login page, AuthContext, middleware, logout route
- [x] Sidebar (collapsible, role-aware, dropdown persistence), global shell

### RND Dashboard (Frontend)
- [x] KPI cards, patient snapshot widget
- [x] Announcements feed with composer (create/edit, image upload)

### Patient List & Profile (Frontend)
- [x] Patient list (search, filter, pagination), create patient CTA
- [x] Patient profile (demographics, risk badge, allergy badges, NCP tabs)

### NCP Assessment UI (Frontend)
- [x] Persistent patient header (allergies, risk badges)
- [x] Assessment tabs: Dietary History, Anthropometrics (auto-BMI/IBW), Client History, Biochemical/Labs grid, RND Summary
- [ ] Lab OCR upload + extraction review panel
- [ ] Screening form renderer (adult B.07 / pediatric B.06) + OCR pre-fill

### Diagnosis UI (Frontend)
- [x] Diagnosis table, Problem/Etiology/Signs builder tabs
- [x] PES auto-build, AI review tab (Accept / Reject / Edit)

### Intervention & Meal Plan Builder (Frontend)
- [x] Full meal plan builder (Library / USDA / Recipe food picker, 7 days × 5 meals)
- [ ] Nutrition prescription form (editable macros/micros) + macro tracker
- [ ] Recommend/Avoid panel display
- [ ] Education, Counseling, Goal Planning forms
- [ ] Encounter context (session type, next follow-up date)

### Food Library & Recipes UI (Frontend)
- [x] Food library page (USDA search, macro cards, micronutrient popup, import)
- [x] Recipe builder (multi-ingredient, auto-macro/cost/micros)
- [x] Recipe list with category filter and macro summary

---

## In Progress / Up Next

### Report Generation
- [ ] Install DomPDF, ReportService orchestrator + interface
- [ ] GenerateReport background job
- [ ] All 10 report generators + Blade templates (ADIME Individual/Aggregate, NCP Census, Inventory, Budget, Menu Cycle, Patient Menu Plan, Inspection, Marketing Statement x2)
- [ ] PDF signed URL download endpoint

### Monitoring (Frontend)
- [ ] Monitoring entry form
- [ ] Trend graphs (weight, labs over time)
- [ ] Goal achievement display + AI decision panel

### Food Service UI (Frontend)
- [ ] Inventory table (stock levels, alerts, restock modal)
- [ ] Menu cycle planner (7-day × 5-meal grid, daily cost tracker)
- [ ] Budget summary (planned vs actual, daily log view)
- [ ] Procurement (shopping lists, purchase orders, suppliers)

### Calendar & Notifications (Frontend)
- [ ] Calendar (system events + manual events)
- [ ] Notification bell + notifications page

### Reports Center (Frontend)
- [ ] Report type picker, status tracking, PDF download
- [ ] ADIME Individual and NCP Census report pages

### Settings & Admin UI (Frontend)
- [ ] User profile + system settings pages
- [ ] Admin dashboard (KPIs, activity feed)
- [ ] Users page + audit log viewer

---

## Phase 2 (Post-Capstone)

### FSS Mobile App (React Native / Expo)
- [ ] Login + token storage
- [ ] Dashboard (today's meals, inventory alerts, announcements)
- [ ] Inventory view + stock update
- [ ] Menu cycle read-only view
- [ ] Meal prep log (checklist, real-time updates)
- [ ] Notifications
