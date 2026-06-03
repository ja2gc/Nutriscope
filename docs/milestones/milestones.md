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
- [x] Fix `PatientResource` risk score output to use `risk_score`
- [x] Add `PatientFeatureTest` coverage for `risk_score` and NCP workflow behavior

**Frontend** ✅:
- [x] **Iteration: Patient UI**
  - [x] Plan: Patient List Page, workflow-first assessment entry, Patient Profile shell
  - [x] Execute Frontend: Build UI components, wire to API, verify browser rendering
  - [x] Review: UX and API integration check

**M2B Cleanup**:
- [x] Sidebar dropdowns remain expanded while users are inside NCP and Food Service child pages
- [x] Remove patient creation modal from the NCP patient directory
- [x] `Create Patient & Start Assessment` navigates directly to the assessment workflow
- [x] Existing patient profiles use workflow navigation actions instead of `Start NCP Cycle`
- [x] Announcement backend, model, resource, routes, tests, seeder, and runtime migration are completed

---

## Milestone 3: Database Scaffold (All Tables)

### 3A — Database Scaffold
- [ ] Migration: `monitorings` table
- [ ] Migration: `ocr_documents` table (with `document_type`, `extraction_template_id`, `parsed_fields`, `confidence_score`, `processing_time_ms`)
- [ ] Migration: `screening_documents` table
- [ ] Migration: `extraction_templates` + `extraction_logs` tables
- [ ] Migration: `meal_plan_templates` + `meal_plan_template_days` tables
- [ ] Migration: `inventory` table
- [ ] Migration: `suppliers` table
- [ ] Migration: `shopping_lists` + `shopping_list_items` tables
- [ ] Migration: `purchase_orders` + `purchase_order_items` tables
- [ ] Migration: `menu_cycles` + `menu_cycle_days` tables
- [ ] Migration: `meal_prep_logs` table
- [ ] Migration: `budgets` + `budget_daily_logs` tables
- [ ] Migration: `inspection_reports` + `inspection_report_items` tables
- [ ] Migration: `marketing_statements` + `marketing_statement_items` + `marketing_summaries` tables
- [ ] Migration: `reports` + `report_templates` tables
- [ ] Migration: `calendar_events` table
- [ ] Migration: `notifications` table
- [ ] Migration: `ai_usage_logs` table
- [ ] Migration: Alter `patients` — add `screening_type`, `hospital_number`, `age_group_category`
- [ ] Migration: Alter `assessments` — add clinical, weight-history, and dietary fields
- [ ] Create all new Models with relationships and casts
- [ ] Base Scaffold verification feature tests

---

## Milestone 4: NCP (Nutrition Care Process) Core & OCR

### 4A — OCR & Screening Form Extraction
- [ ] Create `OCRService` (HTTP client with exponential backoff & mock fallback)
- [ ] Create `ExtractionService` (template-based parsing engine)
- [ ] Create `ParsedDocument` DTO, `ProcessDocumentExtraction` Job, and `DocumentExtractionCompleted` Event
- [ ] Seeder: `ExtractionTemplateSeeder` (seeding screening_adult, screening_pediatric, and lab_result templates)
- [ ] Unit and Feature tests for OCR mock, templates, and jobs
- [ ] Implement deterministic risk score calculation and update `ncp_records.risk_score` and `assessments.nutritional_status`

### 4B — NCP Assessment Backend
- [ ] Implement `NcpRecordController` (create, show, update)
- [ ] Implement `AssessmentController` (create/update with nested `BiochemicalData` upsert)
- [ ] Implement `ScreeningDocumentController` (upload, show, approve mapping)
- [ ] Form Requests: `StoreNcpRecordRequest`, `StoreAssessmentRequest`, `ApproveScreeningDocumentRequest`
- [ ] Resources: `NcpRecordResource`, `AssessmentResource`, `ScreeningDocumentResource`

### 4C — NCP Diagnosis Backend
- [ ] Implement `DiagnosisController` (CRUD)
- [ ] Form Requests: `StoreDiagnosisRequest`, `UpdateDiagnosisRequest`
- [ ] Resource: `DiagnosisResource`
- [ ] Create `AIService` wrapping Anthropic client (Claude Haiku) for draft PES statements
- [ ] Connect AI Review endpoint to AIService and save approved statements

### 4D — NCP Intervention & Monitoring Backend
- [ ] Implement `InterventionController` (CRUD)
- [ ] Implement `MonitoringController` (CRUD with trends and AI decision support)
- [ ] Form Requests: `StoreInterventionRequest`, `StoreMonitoringRequest`
- [ ] Resources: `InterventionResource`, `MonitoringResource`

---

## Milestone 5: Recipes & Food Library

### 5A — Food Library Backend
- [ ] Seeder: `FoodItemsSeeder` ( Philippine hospital ingredients )
- [ ] Seeder: `ClinicalRulesSeeder` (CKD, DM, Hypertension, Cardiac, Liver, Malnutrition, etc.)
- [ ] Seeder: `InventorySeeder` (seed initial stock levels for food items)
- [ ] Implement `RecipeController` (CRUD with auto-calculation of calories, protein, carbs, fat, cost)
- [ ] Implement `FoodItemController` (CRUD)
- [ ] Implement `RecommendService` algorithm (avoid/limit/recommend rules based on allergies, religion, medications, conditions, rules)

### 5B — Meal Plan Generation
- [ ] Implement `MealPlanService` weekly auto-generation algorithm (recipe nutrient fit scoring, portion adjustment, 10% daily tolerance validation)
- [ ] Setup Claude Sonnet fallback generator for low recipe match count (<5)
- [ ] Implement `MealPlanController` (store, update, show, generate, templates)
- [ ] Form Request: `StoreMealPlanRequest`
- [ ] Resource: `MealPlanResource`

---

## Milestone 6: Food Service Operations

### 6A — Inventory restock & menu cycles
- [ ] Implement `InventoryController` (index, store, update, destroy, restock)
- [ ] Implement `MenuCycleController` (index, store, show, update, activate with daily cost limit check against 150 pesos)
- [ ] Form Requests & Resources

### 6B — Budgets, Procurement & Shopping Lists
- [ ] Implement `BudgetController` (index, store, show, update, daily logs)
- [ ] Implement `ShoppingListController` (suggest list by comparing menu cycles vs inventory stock shortfall)
- [ ] Implement `PurchaseOrderController` (Draft -> Ordered -> Received status transitions, upload receipt, auto restock inventory)
- [ ] Implement `SupplierController` (index, store, update)
- [ ] Form Requests & Resources

---

## Milestone 7: PDF Report Generation Pipeline

### 7A — Report Orchestration
- [ ] Setup `barryvdh/laravel-dompdf` configuration
- [ ] Create `ReportService` orchestrator
- [ ] Create `ReportGeneratorInterface` contract
- [ ] Create `GenerateReport` background Job (Redis queue)
- [ ] Implement `ReportController` (generate, index, show, download)
- [ ] Form Request: `StoreReportRequest` and `ReportResource`

### 7B — Report Generators & Templates (10 types)
- [ ] Create generators and Blade layouts for all 10 report types under `resources/views/reports/`:
  - `AdimeIndividualGenerator`
  - `AdimeAggregateGenerator`
  - `NcpCensusGenerator` (Appendix B.08 format)
  - `InventoryReportGenerator`
  - `BudgetReportGenerator`
  - `MenuCycleReportGenerator`
  - `PatientMenuPlanGenerator`
  - `InspectionReportGenerator`
  - `MarketingStatementGenerator`
  - `MarketingSummaryGenerator`

---

## Milestone 8: Calendar & Notifications

### 8A — Calendar events
- [ ] Implement `CalendarEventController` (list, complete system events, edit manual events)
- [ ] Register automated events (monitoring rechecks, menu activations, expiry warnings, budget deadlines)

### 8B — Bell Alerts & Notifications
- [ ] Implement `NotificationController` (fetch alerts, mark read, badges)

---

## Milestone 9: Admin Module & Final verification

### 9A — Admin Backend
- [ ] Implement `UserController` (CRUD, password reset)
- [ ] Implement `AuditLogController` (activity feed filtering, audit trails)

### 9B — Final Verification
- [ ] Verify full test suite passes: `php artisan test`

