# Complete Backend Implementation Plan (Milestone 3 to 10)

This document details the complete step-by-step backend implementation plan for the NutriScope clinical nutrition management system. It covers all schema migrations, models, services, seeders, controllers, form requests, resources, and API routes.

---

## Goal
Implement a robust, production-grade backend for NutriScope that complies with Romana Pangan District Hospital clinical workflows, supporting deterministic risk scoring, regex-based OCR document extraction, clinical recommendation rules, meal planning algorithms, private file storage, and PDF report generation.

---

## Assumptions
- Laravel 13 with PHP 8.3.
- Database is MySQL for local/production, SQLite in-memory for testing.
- Redis is configured and active for queues, caching, and sessions.
- No frontend components are to be touched. All endpoints will return Laravel API Resources and follow standard RESTful routing.
- High code quality is achieved through strict test-driven development (TDD).

---

## Plan

### Phase 1: Database Scaffolding (Migrations & Models)

1. **Step 1: NCP Tables Migration & Model Updates**
   - **Files**:
     - [NEW] [2026_06_03_000001_add_ncp_columns_to_assessments_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000001_add_ncp_columns_to_assessments_table.php)
     - [NEW] [2026_06_03_000002_add_ncp_columns_to_patients_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000002_add_ncp_columns_to_patients_table.php)
     - [NEW] [2026_06_03_000003_create_monitorings_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000003_create_monitorings_table.php)
     - [NEW] [Monitoring.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Monitoring.php)
     - [MODIFY] [Patient.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Patient.php)
     - [MODIFY] [Assessment.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Assessment.php)
   - **Change**: Alter `assessments` and `patients` tables with new clinical attributes. Create the `monitorings` table. Define Eloquent casts, fillable attributes, and relations.
   - **Verify**: `php artisan migrate:fresh` runs successfully. Models load correctly in Tinker.

2. **Step 2: OCR and Extraction Tables**
   - **Files**:
     - [NEW] [2026_06_03_000004_create_ocr_documents_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000004_create_ocr_documents_table.php)
     - [NEW] [2026_06_03_000005_create_screening_documents_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000005_create_screening_documents_table.php)
     - [NEW] [2026_06_03_000006_create_extraction_templates_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000006_create_extraction_templates_table.php)
     - [NEW] [2026_06_03_000007_create_extraction_logs_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000007_create_extraction_logs_table.php)
     - [NEW] [OcrDocument.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/OcrDocument.php)
     - [NEW] [ScreeningDocument.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ScreeningDocument.php)
     - [NEW] [ExtractionTemplate.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ExtractionTemplate.php)
     - [NEW] [ExtractionLog.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ExtractionLog.php)
   - **Change**: Setup schemas and models for template-driven document extraction pipeline.
   - **Verify**: `php artisan migrate:fresh` runs successfully.

3. **Step 3: Meal Plan Templates**
   - **Files**:
     - [NEW] [2026_06_03_000008_create_meal_plan_templates_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000008_create_meal_plan_templates_table.php)
     - [NEW] [2026_06_03_000009_create_meal_plan_template_days_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000009_create_meal_plan_template_days_table.php)
     - [NEW] [MealPlanTemplate.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MealPlanTemplate.php)
     - [NEW] [MealPlanTemplateDay.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MealPlanTemplateDay.php)
   - **Change**: Define tables for saving/loading meal plan configurations.
   - **Verify**: Schema constraints load correctly.

4. **Step 4: Food Service & Inventory**
   - **Files**:
     - [NEW] [2026_06_03_000010_create_inventory_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000010_create_inventory_table.php)
     - [NEW] [2026_06_03_000011_create_suppliers_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000011_create_suppliers_table.php)
     - [NEW] [2026_06_03_000012_create_shopping_lists_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000012_create_shopping_lists_table.php)
     - [NEW] [2026_06_03_000013_create_shopping_list_items_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000013_create_shopping_list_items_table.php)
     - [NEW] [2026_06_03_000014_create_purchase_orders_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000014_create_purchase_orders_table.php)
     - [NEW] [2026_06_03_000015_create_purchase_order_items_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000015_create_purchase_order_items_table.php)
     - [NEW] [2026_06_03_000016_create_menu_cycles_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000016_create_menu_cycles_table.php)
     - [NEW] [2026_06_03_000017_create_menu_cycle_days_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000017_create_menu_cycle_days_table.php)
     - [NEW] [2026_06_03_000018_create_meal_prep_logs_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000018_create_meal_prep_logs_table.php)
     - [NEW] [2026_06_03_000019_create_budgets_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000019_create_budgets_table.php)
     - [NEW] [2026_06_03_000020_create_budget_daily_logs_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000020_create_budget_daily_logs_table.php)
     - [NEW] [Inventory.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Inventory.php)
     - [NEW] [Supplier.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Supplier.php)
     - [NEW] [ShoppingList.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ShoppingList.php)
     - [NEW] [ShoppingListItem.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ShoppingListItem.php)
     - [NEW] [PurchaseOrder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/PurchaseOrder.php)
     - [NEW] [PurchaseOrderItem.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/PurchaseOrderItem.php)
     - [NEW] [MenuCycle.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MenuCycle.php)
     - [NEW] [MenuCycleDay.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MenuCycleDay.php)
     - [NEW] [MealPrepLog.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MealPrepLog.php)
     - [NEW] [Budget.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Budget.php)
     - [NEW] [BudgetDailyLog.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/BudgetDailyLog.php)
   - **Change**: Define tables for food service operations. Implement unique indexes and foreign keys.
   - **Verify**: fresh migration script runs successfully.

5. **Step 5: Operational PDFs & System Tables**
   - **Files**:
     - [NEW] [2026_06_03_000021_create_inspection_reports_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000021_create_inspection_reports_table.php)
     - [NEW] [2026_06_03_000022_create_inspection_report_items_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000022_create_inspection_report_items_table.php)
     - [NEW] [2026_06_03_000023_create_marketing_statements_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000023_create_marketing_statements_table.php)
     - [NEW] [2026_06_03_000024_create_marketing_statement_items_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000024_create_marketing_statement_items_table.php)
     - [NEW] [2026_06_03_000025_create_marketing_summaries_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000025_create_marketing_summaries_table.php)
     - [NEW] [2026_06_03_000026_create_reports_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000026_create_reports_table.php)
     - [NEW] [2026_06_03_000027_create_report_templates_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000027_create_report_templates_table.php)
     - [NEW] [2026_06_03_000028_create_calendar_events_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000028_create_calendar_events_table.php)
     - [NEW] [2026_06_03_000029_create_notifications_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000029_create_notifications_table.php)
     - [NEW] [2026_06_03_000030_create_ai_usage_logs_table.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/migrations/2026_06_03_000030_create_ai_usage_logs_table.php)
     - [NEW] [InspectionReport.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/InspectionReport.php)
     - [NEW] [InspectionReportItem.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/InspectionReportItem.php)
     - [NEW] [MarketingStatement.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MarketingStatement.php)
     - [NEW] [MarketingStatementItem.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MarketingStatementItem.php)
     - [NEW] [MarketingSummary.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/MarketingSummary.php)
     - [NEW] [Report.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Report.php)
     - [NEW] [ReportTemplate.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/ReportTemplate.php)
     - [NEW] [CalendarEvent.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/CalendarEvent.php)
     - [NEW] [Notification.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/Notification.php)
     - [NEW] [AiUsageLog.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Models/AiUsageLog.php)
   - **Change**: Define tables for PDF outputs, user reports, and system tables.
   - **Verify**: `php artisan migrate:fresh` runs and builds the full database schema.

6. **Step 6: Database Scaffold Tests**
   - **Files**:
     - [NEW] [DatabaseScaffoldTest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/DatabaseScaffoldTest.php)
   - **Change**: Assert that all tables exist and all Eloquent models have working relationships.
   - **Verify**: `php artisan test --filter=DatabaseScaffoldTest` passes successfully.

### Phase 2: Seeders

7. **Step 7: Seeders Implementation**
   - **Files**:
     - [NEW] [ExtractionTemplateSeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/ExtractionTemplateSeeder.php)
     - [NEW] [ReportTemplateSeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/ReportTemplateSeeder.php)
     - [NEW] [FoodItemsSeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/FoodItemsSeeder.php)
     - [NEW] [InventorySeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/InventorySeeder.php)
     - [MODIFY] [ClinicalRulesSeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/ClinicalRulesSeeder.php)
     - [MODIFY] [DatabaseSeeder.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/database/seeders/DatabaseSeeder.php)
   - **Change**: Seed 3 active extraction templates, 10 report templates, Philippine food items, corresponding stock levels, and clinical rules.
   - **Verify**: `php artisan db:seed` executes completely.

### Phase 3: OCR & Extraction Pipeline

8. **Step 8: OCR Service, DTO, and Extraction Service**
   - **Files**:
     - [NEW] [OCRService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/OCRService.php)
     - [NEW] [ParsedDocument.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/DTOs/ParsedDocument.php)
     - [NEW] [ExtractionService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/ExtractionService.php)
   - **Change**: Implement OCR client with exponential backoff retries and local mock fallback. Implement regex parsing engine and log to `extraction_logs`.
   - **Verify**: Unit tests for services.

9. **Step 9: Background Jobs & Events**
   - **Files**:
     - [NEW] [ProcessDocumentExtraction.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Jobs/ProcessDocumentExtraction.php)
     - [NEW] [DocumentExtractionCompleted.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Events/DocumentExtractionCompleted.php)
   - **Change**: Implement queue job running OCRService -> ExtractionService -> updates model -> fires event.
   - **Verify**: Unit and integration tests verify job execution.

10. **Step 10: OCR Pipeline Tests**
    - **Files**:
      - [NEW] [OCRExtractionTest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/OCRExtractionTest.php)
    - **Change**: Verify OCR mock returns text, extraction templates compile and parse fields correctly, job executes asynchronously, and event is fired.
    - **Verify**: `php artisan test --filter=OCRExtractionTest` passes.

### Phase 4: Risk Score & Assessment API

11. **Step 11: Risk Scoring Service**
    - **Files**:
      - [NEW] [RiskScoreService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/RiskScoreService.php)
    - **Change**: Calculate deterministic risk score (0-7) and assign nutritional status (Normal, Moderate, Severe).
    - **Verify**: Unit tests on various checkbox configurations.

12. **Step 12: Assessment & Screening Controllers**
    - **Files**:
      - [NEW] [NcpRecordController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/NcpRecordController.php)
      - [NEW] [AssessmentController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/AssessmentController.php)
      - [NEW] [ScreeningDocumentController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/ScreeningDocumentController.php)
      - [NEW] [StoreNcpRecordRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreNcpRecordRequest.php)
      - [NEW] [StoreAssessmentRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreAssessmentRequest.php)
      - [NEW] [ApproveScreeningDocumentRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/ApproveScreeningDocumentRequest.php)
      - [NEW] [NcpRecordResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/NcpRecordResource.php)
      - [NEW] [AssessmentResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/AssessmentResource.php)
      - [NEW] [ScreeningDocumentResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/ScreeningDocumentResource.php)
    - **Change**: Add validation, controllers, and JSON resources for NCP assessments and screening form review/approval.
    - **Verify**: Test endpoints.

13. **Step 13: Assessment API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [AssessmentAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/AssessmentAPITest.php)
    - **Change**: Define API routes for assessment and screening operations. Test that endpoints are role-guarded and successfully record assessment data.
    - **Verify**: `php artisan test --filter=AssessmentAPITest` passes.

### Phase 5: Diagnosis Backend & AI Draft

14. **Step 14: Diagnosis CRUD & PES builder**
    - **Files**:
      - [NEW] [DiagnosisController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/DiagnosisController.php)
      - [NEW] [StoreDiagnosisRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreDiagnosisRequest.php)
      - [NEW] [UpdateDiagnosisRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/UpdateDiagnosisRequest.php)
      - [NEW] [DiagnosisResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/DiagnosisResource.php)
    - **Change**: Implement controller and requests for PES statement builder (Problem, Etiology, Signs & Symptoms).
    - **Verify**: Endpoints functional.

15. **Step 15: AI Diagnosis suggestions (AIService)**
    - **Files**:
      - [NEW] [AIService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/AIService.php)
    - **Change**: Setup Anthropic API connection to retrieve structured PES statements and record usage in `ai_usage_logs`.
    - **Verify**: Mocked AI responses return clean JSON structures.

16. **Step 16: Diagnosis API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [DiagnosisAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/DiagnosisAPITest.php)
    - **Change**: Implement routes and tests for diagnosis operations and AI recommendations.
    - **Verify**: `php artisan test --filter=DiagnosisAPITest` passes.

### Phase 6: Intervention Backend & Meal Planning Algorithms

17. **Step 17: Recommend/Avoid Logic**
    - **Files**:
      - [NEW] [RecommendService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/RecommendService.php)
    - **Change**: Algorithmic rules matching clinical criteria (CKD, DM, Hypertension, Cardiac, Liver, etc.), allergies, and lab value deviations.
    - **Verify**: Unit tests assert expected items are recommended or avoided based on patient state.

18. **Step 18: Meal Plan Generation Algorithm**
    - **Files**:
      - [NEW] [MealPlanService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/MealPlanService.php)
    - **Change**: Create auto-generation service scoring recipes and food items, adjusting quantities mathematically to be within 10% tolerance, and adding an AI fallback for low recipe count (<5).
    - **Verify**: Service tests prove nutritional alignment.

19. **Step 19: Intervention & Meal Plan CRUD Controllers**
    - **Files**:
      - [NEW] [InterventionController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/InterventionController.php)
      - [NEW] [MealPlanController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/MealPlanController.php)
      - [NEW] [StoreInterventionRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreInterventionRequest.php)
      - [NEW] [StoreMealPlanRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreMealPlanRequest.php)
      - [NEW] [InterventionResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/InterventionResource.php)
      - [NEW] [MealPlanResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/MealPlanResource.php)
    - **Change**: Standard CRUD endpoints for interventions, meal plans, custom nutrient limits, and saving templates.
    - **Verify**: Integration tests.

20. **Step 20: Intervention & Meal Plan API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [InterventionAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/InterventionAPITest.php)
    - **Change**: Wire routes and test RecommendService and MealPlanService via API.
    - **Verify**: `php artisan test --filter=InterventionAPITest` passes.

### Phase 7: Monitoring Backend

21. **Step 21: Monitoring Controller & AI Decision Panel**
    - **Files**:
      - [NEW] [MonitoringController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/MonitoringController.php)
      - [NEW] [StoreMonitoringRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreMonitoringRequest.php)
      - [NEW] [MonitoringResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/MonitoringResource.php)
    - **Change**: Implement monitoring endpoint storing versioned weight, BMI, lab value JSON trends, and fetching Claude Sonnet suggestions for ESCALATE/MODIFY/DISCHARGE.
    - **Verify**: Routes and features are functional.

22. **Step 22: Monitoring API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [MonitoringAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/MonitoringAPITest.php)
    - **Change**: Routes and test coverage for monitoring trends and AI suggestions.
    - **Verify**: `php artisan test --filter=MonitoringAPITest` passes.

### Phase 8: Food Service Operations Backend

23. **Step 23: Inventory & Restocking**
    - **Files**:
      - [NEW] [InventoryController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/InventoryController.php)
      - [NEW] [StoreInventoryRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreInventoryRequest.php)
      - [NEW] [UpdateInventoryRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/UpdateInventoryRequest.php)
      - [NEW] [InventoryResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/InventoryResource.php)
    - **Change**: CRUD endpoints for physical inventory tracking, stock thresholds, low stock flagging, and restock actions.
    - **Verify**: Integration tests.

24. **Step 24: Menu Cycle Planning**
    - **Files**:
      - [NEW] [MenuCycleController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/MenuCycleController.php)
      - [NEW] [StoreMenuCycleRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreMenuCycleRequest.php)
      - [NEW] [MenuCycleResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/MenuCycleResource.php)
    - **Change**: Implement weekly grid planning, cost-per-person validation against 150 pesos budget, templates saving, and cycle activation.
    - **Verify**: API resources populate relations correctly.

25. **Step 25: Budgets planned/actual/daily logs**
    - **Files**:
      - [NEW] [BudgetController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/BudgetController.php)
      - [NEW] [StoreBudgetRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreBudgetRequest.php)
      - [NEW] [BudgetResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/BudgetResource.php)
    - **Change**: Implement budget configuration controllers with daily planned vs actual cost tracking and variance logging.
    - **Verify**: Endpoints functional.

26. **Step 26: Procurement (Shopping Lists, Purchase Orders & Suppliers)**
    - **Files**:
      - [NEW] [ShoppingListController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/ShoppingListController.php)
      - [NEW] [PurchaseOrderController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/PurchaseOrderController.php)
      - [NEW] [SupplierController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/SupplierController.php)
      - [NEW] [StoreShoppingListRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreShoppingListRequest.php)
      - [NEW] [StorePurchaseOrderRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StorePurchaseOrderRequest.php)
      - [NEW] [UpdatePurchaseOrderStatusRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/UpdatePurchaseOrderStatusRequest.php)
      - [NEW] [StoreSupplierRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreSupplierRequest.php)
      - [NEW] [ShoppingListResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/ShoppingListResource.php)
      - [NEW] [PurchaseOrderResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/PurchaseOrderResource.php)
      - [NEW] [SupplierResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/SupplierResource.php)
    - **Change**: Auto-suggest shopping lists based on active menus and stock levels. Track PO status transitions (Draft -> Ordered -> Received). Restock inventory on Received.
    - **Verify**: Supplier records increment PO count.

27. **Step 27: Food Service API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [FoodServiceAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/FoodServiceAPITest.php)
    - **Change**: Define routes and tests for inventory, menus, shopping lists, PO transitions, and budget alerts.
    - **Verify**: `php artisan test --filter=FoodServiceAPITest` passes.

### Phase 9: PDF Report Generation Pipeline

28. **Step 28: PDF Orchestration Structure**
    - **Files**:
      - [NEW] [ReportService.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Services/ReportService.php)
      - [NEW] [ReportGeneratorInterface.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Contracts/ReportGeneratorInterface.php)
      - [NEW] [GenerateReport.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Jobs/GenerateReport.php)
      - [NEW] [ReportController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/RND/ReportController.php)
      - [NEW] [StoreReportRequest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Requests/StoreReportRequest.php)
      - [NEW] [ReportResource.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Resources/ReportResource.php)
    - **Change**: Configure `dompdf` rendering pipeline, report status transitions (`queued` -> `generating` -> `completed`/`failed`), background queue processing, and downloads.
    - **Verify**: API resources and files generated correctly.

29. **Step 29: Report Generators & PDF Templates**
    - **Files**:
      - [NEW] [AdimeIndividualGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/AdimeIndividualGenerator.php)
      - [NEW] [AdimeAggregateGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/AdimeAggregateGenerator.php)
      - [NEW] [NcpCensusGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/NcpCensusGenerator.php)
      - [NEW] [InventoryReportGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/InventoryReportGenerator.php)
      - [NEW] [BudgetReportGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/BudgetReportGenerator.php)
      - [NEW] [MenuCycleReportGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/MenuCycleReportGenerator.php)
      - [NEW] [PatientMenuPlanGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/PatientMenuPlanGenerator.php)
      - [NEW] [InspectionReportGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/InspectionReportGenerator.php)
      - [NEW] [MarketingStatementGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/MarketingStatementGenerator.php)
      - [NEW] [MarketingSummaryGenerator.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Reports/MarketingSummaryGenerator.php)
      - Create Blades: [adime_individual.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/adime_individual.blade.php), [adime_aggregate.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/adime_aggregate.blade.php), [ncp_census.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/ncp_census.blade.php), [inventory.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/inventory.blade.php), [budget.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/budget.blade.php), [menu_cycle.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/menu_cycle.blade.php), [patient_menu_plan.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/patient_menu_plan.blade.php), [inspection_report.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/inspection_report.blade.php), [marketing_statement.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/marketing_statement.blade.php), [marketing_summary.blade.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/resources/views/reports/marketing_summary.blade.php)
   - **Change**: Build data collection and Blade HTML layouts for 10 report types, compiling with exact visual structures (e.g. Romana Pangan District Hospital titles, certification statements, and signatory blocks).
   - **Verify**: Generators return structured array arrays.

30. **Step 30: Report API Routes & Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [ReportAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/ReportAPITest.php)
    - **Change**: Wire routes and test PDF generation jobs, downloading signed URLs, and role-guarded permissions (RND sees own, Admin sees all).
    - **Verify**: `php artisan test --filter=ReportAPITest` passes.

### Phase 10: Admin & System Integrations

31. **Step 31: Admin Controllers**
    - **Files**:
      - [NEW] [UserController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/Admin/UserController.php)
      - [NEW] [AuditLogController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/Admin/AuditLogController.php)
   - **Change**: CRUD for user account management, password resets, role assignment, and audit trail query/filtering.
   - **Verify**: Routes Guarded.

32. **Step 32: Notifications & Calendar Subscriptions**
    - **Files**:
      - [NEW] [CalendarEventController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/System/CalendarEventController.php)
      - [NEW] [NotificationController.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/app/Http/Controllers/System/NotificationController.php)
   - **Change**: Fetch auto-events (monitoring rechecks, recipe/food expiry warnings, budget deadlines) and manage read receipts for bell alerts.
   - **Verify**: Integration tests.

33. **Step 33: System API Routes & Final Verification Tests**
    - **Files**:
      - [MODIFY] [api.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/routes/api.php)
      - [NEW] [SystemAPITest.php](file:///c:/Users/User/Documents/Nutriscope/Nutriscope/backend/tests/Feature/SystemAPITest.php)
    - **Change**: Define final endpoints and verify full system integrations. Run complete test coverage.
    - **Verify**: `php artisan test` returns all passing tests.

---

## Risks & mitigations
- **PaddleOCR Timeout**: OCRService requests could block queue workers. Mitigation: Set strict curl timeout (30 seconds) and retry with exponential backoff on background queues.
- **Large PDF sizes in DomPDF**: Memory exhaustion on rendering huge lists. Mitigation: Implement chunked database loading inside Report generators and limit page size.

---

## Rollback plan
- Keep git tags prior to executing each Phase.
- Revert schema modifications using rollback migrations (`php artisan migrate:rollback`).
- Restore the local Git index using `git checkout -f` if a test run introduces regressions.
