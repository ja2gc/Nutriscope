# NutriScope Milestones

## Rules & Workflow (Superpowers Mode)
- **Mark already done**: Existing completed items (`[x]`) must remain marked as completed.
- **Never mark done without testing and getting approved by user**:
  - **Backend**: Write PHPUnit feature tests. Run `php artisan test`. All tests must pass.
  - **Frontend**: Confirm components render correctly in the browser.
- **Test verification**: Follow the Superpowers workflow for all remaining tasks:
  1. `/superpowers-plan` (Plan step)
  2. `/superpowers-tdd` (Backend step)
  3. `/superpowers-execute-plan` (Frontend step)
  4. `/superpowers-review` (Review step)

## Development Tracks (5 Parallel Tracks)

| Track | Focus | Milestones |
|-------|-------|------------|
| **Backend Core** | Models, Controllers, APIs, Algorithms | M0→M1→M2→M3→M4→M5→M6→M7→M8→M9→M10 |
| **Extraction Pipeline** | OCR, ExtractionService, Document parsing | M3→M4→M5→M9 |
| **Reports** | ReportService, Generators, Blade templates, DomPDF | M6→M8→M9→M10 |
| **Frontend** | Next.js pages, components, API integration | M2→M4→M5→M7→M9→M10 |
| **Documentation** | Schema, architecture, module docs | M3→M6→M10 |

---

## Milestone 0: Planning & Architecture ✅

- [x] Models: `User`, `Patient`, `NcpRecord`, `Assessment`, `BiochemicalData`, `Diagnosis`, `Intervention`, `FoodItem`, `Recipe`, `RecipeIngredient`, `MealPlan`, `MealPlanDay`, `MealPlanItem`, `ClinicalRule`
- [x] Controllers: `AuthController` (Auth), `PatientController` (RND)
- [x] Migrations: 11 core migrations (Users, Patients, NCP, Assessments, Biochemical Data, Diagnoses, Interventions, Food Items, Recipes, Clinical Rules, Meal Plans) + Activity Log + Personal Access Tokens
- [x] Seeders: `DatabaseSeeder`, `AdminUserSeeder`, `ClinicalRulesSeeder`, `FoodItemsSeeder`, `RecipeSeeder`
- [x] System enhancement planning: extraction pipeline, report architecture, schema additions, milestone restructure
- [x] Reference document analysis: NCP forms, screening forms, bi-annual report, procurement documents
- [x] Documentation updates: all docs aligned with new system direction

---

## Phase 1: Laravel Foundation & Route Setup ✅

- [x] Run `php artisan install:api` to generate `routes/api.php`
- [x] Configure `.env` for Redis (queue, session, cache)
- [x] Set up route groups in `api.php` (`auth:sanctum`, `role:RND`, `role:FSS`, `role:Admin`)
- [x] Implement `RoleMiddleware` for role-based route guarding
- [x] **Audit Middleware**: `AuditMiddleware` using `spatie/laravel-activitylog` — implemented and verified with PHPUnit

---

## Milestone 1: Authentication Endpoints & UI ✅

- [x] Backend: Create `LoginRequest` and `UserResource`
- [x] Backend: Implement login, logout, and me logic in `AuthController`
- [x] Frontend: Auth context, `middleware.ts`, Login Page, RND shell layout — scaffolded and verified

---

## Milestone 2: Patient Management

**Backend** ✅:
- [x] Create `StorePatientRequest`, `UpdatePatientRequest`, and `PatientResource`
- [x] Implement Patient CRUD endpoints in `PatientController`

**Frontend** ✅:
- [x] **Iteration: Patient UI**
  - [x] Plan: Patient List Page, Add Patient Modal, Patient Profile shell
  - [x] Execute Frontend: Build UI components, wire to API, verify browser rendering
  - [] Review: UX and API integration check

---

## Milestone 3: Database Scaffold + OCR Foundation

### 3A — Database Scaffold
- [ ] Migration: `monitorings` table
- [ ] Migration: `ocr_documents` table (with `document_type`, `extraction_template_id`, `parsed_fields`, `confidence_score`, `processing_time_ms`)
- [ ] Migration: `screening_documents` table
- [ ] Migration: `extraction_templates` + `extraction_logs` tables
- [ ] Migration: `inventory` table
- [ ] Migration: `menu_cycles` + `menu_cycle_days` tables
- [ ] Migration: `meal_prep_logs` table
- [ ] Migration: `budgets` + `budget_daily_logs` tables
- [ ] Migration: `procurements` + `procurement_items` tables
- [ ] Migration: `inspection_reports` + `inspection_report_items` tables
- [ ] Migration: `marketing_statements` + `marketing_statement_items` + `marketing_summaries` tables
- [ ] Migration: `reports` + `report_templates` tables
- [ ] Migration: `announcements` table
- [ ] Migration: `calendar_events` table
- [ ] Migration: `notifications` table
- [ ] Migration: `ai_usage_logs` table
- [ ] Migration: Alter `patients` — add `screening_type`, `hospital_number`, `age_group_category`
- [ ] Create all new Models with relationships and casts
- [ ] Seeder: `ExtractionTemplateSeeder` (5 document types)
- [ ] Seeder: `ReportTemplateSeeder` (9 report types)

### 3B — OCR Foundation
- [ ] Add PaddleOCR service to `docker-compose.yml` (mock used until M4)
- [ ] Create `OCRService` — HTTP client with mock fallback
- [ ] Create `ExtractionService` — template-based parsing engine
- [ ] Create `ParsedDocument` DTO
- [ ] Create `ProcessDocumentExtraction` Job
- [ ] Create `DocumentExtractionCompleted` Event
- [ ] Tests: OCR mock, extraction with sample text, job dispatch

### 3C — Documentation
- [x] Update `docs/database-schema.md` with all new tables
- [x] Update `docs/architecture/folder-structure.md`
- [x] Create `docs/architecture/extraction-pipeline.md`
- [x] Create `docs/architecture/report-pipeline.md`
- [x] Update `docs/integrations/integrations.md`
- [x] Update `docs/modules/rnd.md` and `docs/modules/admin.md`
- [x] Update `docs/security/security.md`
- [x] Update `docs/architecture/stack.md`
- [x] Update `docs/overview.md`

---

## Milestone 4: NCP Assessment + Screening Extraction

### 4A — NCP Backend
- [ ] `NcpRecordController` — create, show, update
- [ ] `AssessmentController` — create/update with nested `BiochemicalData` upsert
- [ ] Form Requests: `StoreNcpRecordRequest`, `StoreAssessmentRequest`
- [ ] Resources: `NcpRecordResource`, `AssessmentResource`, `BiochemicalDataResource`

### 4B — Screening Form Extraction (Extraction Track)
- [ ] `ScreeningDocumentController` — upload, review, approve mapping
- [ ] Upload endpoint: PDF/image → dispatches `ProcessDocumentExtraction` (type=screening)
- [ ] Auto-populate matching assessment fields from extraction results
- [ ] Deterministic risk score calculation from screening checklist
- [ ] Tests: extraction with sample screening text, risk score calculation

### 4C — Frontend: Assessment UI
- [ ] Assessment form with tabs: Dietary, Anthropometric, Client History, Biochemical, Referral, RND Summary
- [ ] Screening form upload component with OCR status indicator
- [ ] Extraction review panel: extracted values + confidence + manual override
- [ ] Biochemical data grid with monospace font, out-of-range flagging

---

## Milestone 5: Diagnosis + Intervention + Lab Extraction

### 5A — Diagnosis Backend
- [ ] `DiagnosisController` — CRUD
- [ ] PES statement builder logic
- [ ] `DiagnosisResource`

### 5B — Intervention Backend
- [ ] `InterventionController` — CRUD
- [ ] `InterventionResource`

### 5C — Lab Result Extraction (Extraction Track)
- [ ] Extend `ProcessDocumentExtraction` for `lab_result` template
- [ ] OCR upload on Biochemical tab → auto-populate `biochemical_data` fields
- [ ] Confidence scoring per lab value
- [ ] Tests: lab extraction with sample text

### 5D — Frontend: Diagnosis + Intervention UI
- [ ] Diagnosis Builder: tabs for P→E→S→PES
- [ ] Intervention Form: nutrient goals, macro targets, encounter context
- [ ] Lab upload integrated into Assessment Biochemical tab

---

## Milestone 6: Algorithms + Report Infrastructure

### 6A — Core Algorithms
- [ ] `RecommendService` — clinical_rules-driven recommend/avoid engine
- [ ] `MealPlanService` — algorithm-based 7-day meal plan generation
- [ ] Validation: 10% target tolerance, allergen/restriction enforcement

### 6B — Report Infrastructure (Report Track)
- [ ] Install `barryvdh/laravel-dompdf`
- [ ] `ReportService` orchestrator
- [ ] `ReportGeneratorInterface` contract
- [ ] `GenerateReport` Job
- [ ] `ReportController` (RND) — generate, index, show, download
- [ ] `StoreReportRequest`, `ReportResource`
- [ ] Base Blade template with header/footer/pagination

### 6C — Frontend: Meal Plan UI
- [ ] Meal Plan Grid (7-day × 5 meals)
- [ ] Macro tracker with real-time totals
- [ ] Cell swap/edit functionality

---

## Milestone 7: Recipes & Food Library

### 7A — Backend
- [ ] `RecipeController` — CRUD with auto-calculation
- [ ] `FoodItemController` — CRUD + USDA search
- [ ] `FoodService` — USDA API integration with Redis cache

### 7B — Frontend
- [ ] Foods Library page with USDA search
- [ ] Recipe Builder with multi-ingredient input, auto-calculated macros/cost

---

## Milestone 8: Monitoring + Dashboard + ADIME Reports

### 8A — Monitoring Backend
- [ ] `MonitoringController` — create/update with versioned entries
- [ ] Trend calculation logic
- [ ] Goal achievement algorithm

### 8B — ADIME Reports (Report Track)
- [ ] `AdimeIndividualGenerator` — single patient NCP summary PDF
- [ ] `AdimeAggregateGenerator` — aggregate analytics across patients
- [ ] Blade templates for both

### 8C — Frontend
- [ ] RND Dashboard with KPIs, active NCPs, budget snapshot
- [ ] Monitoring page with trend graphs
- [ ] Report generation UI (type picker, filters, status, download)

---

## Milestone 9: Food Service Operations + Operational Reports

### 9A — Food Service Backend
- [ ] `InventoryController`, `MenuCycleController`, `BudgetController`, `ProcurementController`
- [ ] All Form Requests and Resources

### 9B — Procurement Document Extraction (Extraction Track)
- [ ] Extend extraction for `inspection_report` + `marketing_statement` templates
- [ ] Upload → OCR → auto-populate inspection/marketing records
- [ ] Fuzzy match extracted items to `food_items`

### 9C — Operational Reports (Report Track)
- [ ] `InventoryReportGenerator`
- [ ] `BudgetReportGenerator`
- [ ] `MenuCycleReportGenerator`
- [ ] `PatientMenuPlanGenerator`
- [ ] `InspectionReportGenerator` (output from system data)
- [ ] `MarketingStatementGenerator` (output from system data)
- [ ] All Blade templates

### 9D — Frontend
- [ ] Inventory, Menu Cycle, Budget, Procurement pages (RND web)
- [ ] Procurement document upload with extraction review

---

## Milestone 10: Admin Module + Census Reports + Final Polish

### 10A — Admin Backend
- [ ] `UserController`, `AuditLogController`, `AnnouncementController`
- [ ] `Admin\ReportController` — access to all reports

### 10B — NCP Census Report (Report Track)
- [ ] `NcpCensusGenerator` — B.08 format, arbitrary date range
- [ ] Age/sex breakdown queries
- [ ] Malnutrition classification aggregation
- [ ] ADIME completion metrics

### 10C — Frontend
- [ ] Admin Dashboard, Users, Audit Logs, Announcements pages
- [ ] Report hub with all 9 report types

### 10D — Documentation Final Sweep
- [ ] All docs verified against final implementation
- [ ] API documentation complete
- [ ] Deployment guide updated

---

## Phase 2: External Integrations (Final Layer)

*To be implemented only when the UI and core database logic are fully functional.*

- [ ] **USDA Integration** (if not done in M7): `FoodService` API caller, Redis caching, Foods Library search bar
- [ ] **AI Integration**: `AIService`, token tracking, AI Endpoints for PES/Monitoring/Meal Plans, AI Review Panels
- [ ] **FSS Mobile App**: React Native (Expo) architecture, Mobile Dashboard, Inventory, Menu Cycle, Meal Prep Log