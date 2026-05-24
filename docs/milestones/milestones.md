Never mark milestone done without testing first
Backend = write PHPUnit feature tests and run php artisan test. All tests must pass
Frontend = confirm renders in browser
Done = tests written in tests/Feature/ + php artisan test passes = mark [x]

Phase 0: Already Completed (Foundation)
*Based on the existing files in `app/Models`, `app/Http/Controllers`, `database/migrations`, and `database/seeders`.*

- [x] Models: `User`, `Patient`, `NcpRecord`, `Assessment`, `BiochemicalData`, `Diagnosis`, `Intervention`, `FoodItem`, `Recipe`, `RecipeIngredient`, `MealPlan`, `MealPlanDay`, `MealPlanItem`, `ClinicalRule`
- [x] Controllers: `AuthController` (Auth), `PatientController` (RND)
- [x] Migrations: 11 core migrations (Users, Patients, NCP, Assessments, Biochemical Data, Diagnoses, Interventions, Food Items, Recipes, Clinical Rules, Meal Plans) + Activity Log + Personal Access Tokens
- [x] Seeders: `DatabaseSeeder`, `AdminUserSeeder`, `ClinicalRulesSeeder`, `FoodItemsSeeder`, `RecipeSeeder`

---

Phase 1: Laravel Foundation & Route Setup
*Ensure the base backend is ready to accept API requests and enforce roles.*

- [x] Run `php artisan install:api` to generate `routes/api.php`
- [x] Configure `.env` for Redis (queue, session, cache)
- [x] Set up route groups in `api.php` (`auth:sanctum`, `role:RND`, `role:FSS`, `role:Admin`)
- [x] Implement `RoleMiddleware` for role-based route guarding
- [ ] Implement `AuditMiddleware` using `spatie/laravel-activitylog` for sensitive routes

Milestone 1: Authentication Endpoints & UI
*Get users logging in and interacting with a protected UI.*

- [x] Backend: Create `LoginRequest` and `UserResource`
- [x] Backend: Implement login, logout, and me logic in `AuthController`
- [ ] Frontend: Scaffold Next.js auth context and HTTP-only cookie token storage
- [ ] Frontend: Create `middleware.ts` for route protection and role redirection
- [ ] Frontend: Build Login Page UI (`/login`)
- [ ] Frontend: Build RND shell layout (`Sidebar`, `TopBar`)

Milestone 2: Patient Management
*RNDs need to be able to list, add, and view patients.*

- [x] Backend: Create `StorePatientRequest`, `UpdatePatientRequest`, and `PatientResource`
- [x] Backend: Implement Patient CRUD endpoints in `PatientController`
- [ ] Frontend: Build Patient List Page with sortable table
- [ ] Frontend: Build Add Patient Modal
- [ ] Frontend: Build Patient Profile shell (header and empty tabs)

Milestone 3: Core MVP Missing Backend Infrastructure
*Scaffold the rest of the database for the remaining modules.*

- [ ] Backend: Create migrations for remaining tables (`monitorings`, `ocr_documents`, `inventory`, `menu_cycles`, `meal_prep_logs`, `budgets`, `procurements`, `notifications`, `calendar_events`, `reports`, `ai_usage_logs`, `announcements`)
- [ ] Backend: Create Models for the newly created tables with their respective relationships

Milestone 4: NCP Records & Assessment
*The first step in the ADIME workflow.*

- [ ] Backend: Create `StoreNcpRecordRequest` and `NcpRecordResource`
- [ ] Backend: Create `NcpRecordController` with open/fetch/update endpoints
- [ ] Backend: Create `StoreAssessmentRequest` and `AssessmentResource`
- [ ] Backend: Create `AssessmentController` (handling nested `BiochemicalData` upserts)
- [ ] Frontend: Build Assessment Form UI (Dietary, Anthropometric, Client History, Biochemical, RND Summary tabs)
- [ ] Frontend: Wire Assessment forms to API endpoints

Milestone 5: Diagnosis & Intervention
*The middle steps in the ADIME workflow (manual input only for now).*

- [ ] Backend: Create `StoreDiagnosisRequest`, `DiagnosisResource`, and `DiagnosisController`
- [ ] Backend: Create `StoreInterventionRequest`, `InterventionResource`, and `InterventionController`
- [ ] Frontend: Build Diagnosis Table UI and Diagnosis Builder (Domain → Problem → Etiology → S&S → PES)
- [ ] Frontend: Build Intervention Form UI (Nutrient Delivery, Education, Counseling, Encounter tabs)
- [ ] Frontend: Wire Diagnosis and Intervention UI to API endpoints

Milestone 6: Core Deterministic Algorithms (No AI)
*The system needs to generate safe recommendations and meal plans using internal logic.*

- [ ] Backend: Implement `RecommendService` logic (reading `clinical_rules` and assessment filters)
- [ ] Backend: Create `RecommendController` for fetching recommend/avoid lists
- [ ] Backend: Implement `MealPlanService` core algorithm (nutrient matching, allergen exclusion)
- [ ] Backend: Create `MealPlanController` and `MealPlanResource`
- [ ] Frontend: Build Meal Plan Grid UI (7-day × 5-meal)
- [ ] Frontend: Build Real-time Macro Tracker UI (target vs actual calculations)
- [ ] Frontend: Wire Meal Plan generation to API and handle manual cell swaps


Milestone 7: Recipes & Food Library
*Allow RNDs to manage the local database of foods and recipes.*

- [ ] Backend: Create `RecipeController`, `StoreRecipeRequest`, and `RecipeResource`
- [ ] Backend: Implement logic to auto-calculate recipe totals (macros, cost) from ingredients
- [ ] Frontend: Build Foods Library Page (grid view, macro details)
- [ ] Frontend: Build Recipe Builder Page (ingredient addition, auto-calculation display)

Milestone 8: RND Dashboards & Monitoring
*Provide oversight and track patient progress over time.*

- [ ] Backend: Create `MonitoringController`, `StoreMonitoringRequest`, and `MonitoringResource`
- [ ] Backend: Create Controllers for `CalendarEvents` and `Notifications`
- [ ] Frontend: Build RND Dashboard (announcements, active NCPs, system alerts)
- [ ] Frontend: Build Monitoring Page (versioned entries, manual lab entry, trend graphs)
- [ ] Frontend: Build Calendar Page using `FullCalendar` library

Milestone 9: Food Service Operations (RND Web & FSS Mobile)
*Manage hospital inventory and kitchen operations.*

- [ ] Backend: Create Controllers/Requests/Resources for Inventory, MenuCycles, Budgets, and Procurements
- [ ] Frontend (Web): Build Inventory, Menu Cycle, Budget, and Procurement UI pages for RND
- [ ] Frontend (Mobile): Initialize React Native (Expo) project for FSS App
- [ ] Frontend (Mobile): Build FSS Dashboard, Inventory List, Menu Cycle View, and Meal Prep Log screens
- [ ] Frontend (Mobile): Wire Mobile UI to Laravel API

Milestone 10: Admin Module
*System management and audit oversight.*

- [ ] Backend: Create Admin Controllers (`UserController`, `AuditLogController`, `AnnouncementController`)
- [ ] Frontend: Build Admin Dashboard and Management pages (Users, Audit Logs, Announcements)

Phase 2: External Integrations (Final Layer)
*To be implemented only when the UI and core database logic are fully functional.*

- [ ] USDA Integration: Implement `FoodService` API caller, Redis caching, and wire to Foods Library search bar.
- [ ] PDF Reports: Implement `ReportService` (DomPDF), `GenerateReport` Job, `ReportController`, and Frontend Report Generator UI.
- [ ] OCR Integration: Implement `OCRService` (PaddleOCR), `ProcessOCRDocument` Job, upload endpoints, and add file upload UI to the Assessment Biochemical tab.
- [ ] AI Integration: Implement `AIService` (Anthropic Haiku/Sonnet), token tracking, `GenerateAISuggestion` Job. Add AI Endpoints for PES drafting, Monitoring decisions, and Meal Plan fallbacks. Add AI Review Panels to the Frontend UI.