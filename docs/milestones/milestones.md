# NutriScope Milestones

## Rules & Workflow (Superpowers Mode)
- **Mark already done**: Existing completed items (`[x]`) must remain marked as completed.
- **Never mark done without testing**:
  - **Backend**: Write PHPUnit feature tests. Run `php artisan test`. All tests must pass.
  - **Frontend**: Confirm components render correctly in the browser.
- **Test verification**: Follow the Superpowers workflow for all remaining tasks:
  1. `/superpowers-write-plan` (Plan step)
  2. `/superpowers-tdd` (Backend step)
  3. `/superpowers-execute-plan` (Frontend step)
  4. `/superpowers-review` (Review step)

---

## Phase 0: Already Completed (Foundation)
*Based on the existing files in `app/Models`, `app/Http/Controllers`, `database/migrations`, and `database/seeders`.*

- [x] Models: `User`, `Patient`, `NcpRecord`, `Assessment`, `BiochemicalData`, `Diagnosis`, `Intervention`, `FoodItem`, `Recipe`, `RecipeIngredient`, `MealPlan`, `MealPlanDay`, `MealPlanItem`, `ClinicalRule`
- [x] Controllers: `AuthController` (Auth), `PatientController` (RND)
- [x] Migrations: 11 core migrations (Users, Patients, NCP, Assessments, Biochemical Data, Diagnoses, Interventions, Food Items, Recipes, Clinical Rules, Meal Plans) + Activity Log + Personal Access Tokens
- [x] Seeders: `DatabaseSeeder`, `AdminUserSeeder`, `ClinicalRulesSeeder`, `FoodItemsSeeder`, `RecipeSeeder`

---

## Phase 1: Laravel Foundation & Route Setup
*Ensure the base backend is ready to accept API requests and enforce roles.*

- [x] Run `php artisan install:api` to generate `routes/api.php`
- [x] Configure `.env` for Redis (queue, session, cache)
- [x] Set up route groups in `api.php` (`auth:sanctum`, `role:RND`, `role:FSS`, `role:Admin`)
- [x] Implement `RoleMiddleware` for role-based route guarding
- [x] **Iteration: Audit Middleware**
  - [x] Plan (`/superpowers-write-plan`): `AuditMiddleware` using `spatie/laravel-activitylog`.
  - [x] Execute Backend (`/superpowers-tdd`): Implement middleware and verify with PHPUnit.
  - [x] Review (`/superpowers-review`): Ensure security and performance.

---

## Milestone 1: Authentication Endpoints & UI
*Get users logging in and interacting with a protected UI.*

- [x] Backend: Create `LoginRequest` and `UserResource`
- [x] Backend: Implement login, logout, and me logic in `AuthController`
- [ ] **Iteration: Frontend Auth UI**
  - [ ] Plan (`/superpowers-write-plan`): Auth context, `middleware.ts`, Login Page, RND shell layout.
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Scaffold Next.js auth, cookie storage, route protection, build UI. Verify browser rendering.
  - [ ] Review (`/superpowers-review`): UX and security check.

---

## Milestone 2: Patient Management
*RNDs need to be able to list, add, and view patients.*

- [x] Backend: Create `StorePatientRequest`, `UpdatePatientRequest`, and `PatientResource`
- [x] Backend: Implement Patient CRUD endpoints in `PatientController`
- [ ] **Iteration: Frontend Patient UI**
  - [ ] Plan (`/superpowers-write-plan`): Patient List Page, Add Patient Modal, Patient Profile shell.
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build UI components, wire to API, verify browser rendering.
  - [ ] Review (`/superpowers-review`): UX and API integration check.

---

## Milestone 3: Core MVP Missing Backend Infrastructure
*Scaffold the rest of the database for the remaining modules.*

- [ ] **Iteration: Database Scaffold**
  - [ ] Plan (`/superpowers-write-plan`): Remaining tables (`monitorings`, `ocr_documents`, `inventory`, `menu_cycles`, `meal_prep_logs`, `budgets`, `procurements`, `notifications`, `calendar_events`, `reports`, `ai_usage_logs`, `announcements`).
  - [ ] Execute Backend (`/superpowers-tdd`): Create migrations and Models with relationships. Verify with PHPUnit.
  - [ ] Review (`/superpowers-review`): Schema and relation check.

---

## Milestone 4: NCP Records & Assessment
*The first step in the ADIME workflow.*

- [ ] **Iteration: NCP Backend**
  - [ ] Plan (`/superpowers-write-plan`): API endpoints for NCP Records and Assessments.
  - [ ] Execute Backend (`/superpowers-tdd`): Create Requests, Resources, Controllers. Verify nested upserts (BiochemicalData).
- [ ] **Iteration: NCP Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Assessment Form UI tabs and wire to endpoints. Verify rendering.
  - [ ] Review (`/superpowers-review`): E2E functionality.

---

## Milestone 5: Diagnosis & Intervention
*The middle steps in the ADIME workflow (manual input only for now).*

- [ ] **Iteration: Diagnosis & Intervention Backend**
  - [ ] Execute Backend (`/superpowers-tdd`): Create Requests, Resources, Controllers for both.
- [ ] **Iteration: Diagnosis & Intervention Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Diagnosis Builder UI, Intervention Form UI, wire to API.
  - [ ] Review (`/superpowers-review`): Verify workflow.

---

## Milestone 6: Core Deterministic Algorithms (No AI)
*The system needs to generate safe recommendations and meal plans using internal logic.*

- [ ] **Iteration: Algorithms Backend**
  - [ ] Plan (`/superpowers-write-plan`): Core `RecommendService` and `MealPlanService` algorithms.
  - [ ] Execute Backend (`/superpowers-tdd`): Implement algorithms, Controllers, and Resources. Verify edge cases (allergens, nutrients).
- [ ] **Iteration: Meal Plan Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Meal Plan Grid UI, Macro Tracker, handle cell swaps.
  - [ ] Review (`/superpowers-review`): Validate algorithm outputs in UI.

---

## Milestone 7: Recipes & Food Library
*Allow RNDs to manage the local database of foods and recipes.*

- [ ] **Iteration: Recipes Backend**
  - [ ] Execute Backend (`/superpowers-tdd`): Implement `RecipeController`, auto-calculation logic for macros/costs.
- [ ] **Iteration: Recipes Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Foods Library Page and Recipe Builder Page.
  - [ ] Review (`/superpowers-review`): Accurate calculation display.

---

## Milestone 8: RND Dashboards & Monitoring
*Provide oversight and track patient progress over time.*

- [ ] **Iteration: Monitoring Backend**
  - [ ] Execute Backend (`/superpowers-tdd`): `MonitoringController`, `CalendarEvents`, `Notifications`.
- [ ] **Iteration: Monitoring Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build RND Dashboard, Monitoring Page, Calendar Page.
  - [ ] Review (`/superpowers-review`): UI rendering and responsiveness.

---

## Milestone 9: Food Service Operations (RND Web & FSS Mobile)
*Manage hospital inventory and kitchen operations.*

- [ ] **Iteration: FSS Backend**
  - [ ] Execute Backend (`/superpowers-tdd`): Controllers for Inventory, MenuCycles, Budgets, Procurements.
- [ ] **Iteration: RND Web Frontend**
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build web pages for Inventory, Menu Cycle, Budget, Procurement.
- [ ] **Iteration: FSS Mobile App**
  - [ ] Plan (`/superpowers-write-plan`): React Native (Expo) architecture for FSS App.
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Mobile Dashboard, Inventory List, Menu Cycle View, Meal Prep Log. Wire to API.
  - [ ] Review (`/superpowers-review`): Mobile usability.

---

## Milestone 10: Admin Module
*System management and audit oversight.*

- [ ] **Iteration: Admin End-to-End**
  - [ ] Execute Backend (`/superpowers-tdd`): `UserController`, `AuditLogController`, `AnnouncementController`.
  - [ ] Execute Frontend (`/superpowers-execute-plan`): Build Admin Dashboard, Users, Audit Logs, Announcements pages.
  - [ ] Review (`/superpowers-review`): Admin workflow integrity.

---

## Phase 2: External Integrations (Final Layer)
*To be implemented only when the UI and core database logic are fully functional.*

- [ ] **USDA Integration**: `FoodService` API caller, Redis caching, Foods Library search bar. (`/superpowers-write-plan` -> Execute -> Review)
- [ ] **PDF Reports**: `ReportService` (DomPDF), `GenerateReport` Job, `ReportController`, Frontend UI. (`/superpowers-write-plan` -> Execute -> Review)
- [ ] **OCR Integration**: `OCRService` (PaddleOCR), `ProcessOCRDocument` Job, File upload endpoints/UI. (`/superpowers-write-plan` -> Execute -> Review)
- [ ] **AI Integration**: `AIService`, token tracking, AI Endpoints for PES/Monitoring/Meal Plans, AI Review Panels. (`/superpowers-write-plan` -> Execute -> Review)