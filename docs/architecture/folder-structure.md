## Folder Structure

### Laravel /backend

```
app/
  DTOs/
    ParsedDocument.php          extracted document value object
  Events/
    DocumentExtractionCompleted.php
  Http/
    Controllers/
      Auth/
        AuthController.php
      RND/
        PatientController.php
        AnnouncementController.php
        NcpRecordController.php
        AssessmentController.php
        DiagnosisController.php
        InterventionController.php
        MonitoringController.php
        ScreeningDocumentController.php
        RecipeController.php
        FoodItemController.php
        InventoryController.php
        MenuCycleController.php
        BudgetController.php
        ProcurementController.php
        ReportController.php
        CalendarController.php
        NotificationController.php
      FSS/
        DashboardController.php
        InventoryController.php
        MenuCycleController.php
        MealPrepLogController.php
      Admin/
        UserController.php
        AuditLogController.php
        AnnouncementController.php
        ReportController.php
        SettingsController.php
    Middleware/
      RoleMiddleware.php
      AuditMiddleware.php
    Requests/                   one per endpoint that accepts input
    Resources/                  one per model for JSON formatting
  Models/                       one per table
    Announcement.php
  Services/
    AIService.php               all Anthropic calls (both models), token tracking
    FoodService.php             all USDA calls + local food_items queries
    OCRService.php              PaddleOCR HTTP client (mock in dev until M4)
    ExtractionService.php       template-based document parsing engine
    MealPlanService.php         algorithm-based meal plan generation
    RecommendService.php        algorithm-based recommend/avoid list
    Reports/
      ReportService.php         orchestrator: template loading, PDF rendering, storage
      Contracts/
        ReportGeneratorInterface.php
      Generators/
        AdimeIndividualGenerator.php
        AdimeAggregateGenerator.php
        NcpCensusGenerator.php
        InventoryReportGenerator.php
        BudgetReportGenerator.php
        MenuCycleReportGenerator.php
        PatientMenuPlanGenerator.php
        InspectionReportGenerator.php
        MarketingStatementGenerator.php
  Jobs/
    ProcessDocumentExtraction.php   generic OCR → parse → map → store
    GenerateAISuggestion.php
    GenerateReport.php              dispatches to appropriate generator

routes/
  api.php                       all API routes grouped by role

database/
  seeders/
    AdminUserSeeder.php
    AnnouncementSeeder.php

resources/
  views/
    reports/                    Blade templates for PDF generation
      adime-individual.blade.php
      adime-aggregate.blade.php
      ncp-census.blade.php
      inventory.blade.php
      budget.blade.php
      menu-cycle.blade.php
      patient-menu-plan.blade.php
      inspection-report.blade.php
      marketing-statement.blade.php
```

### Next.js /frontend

```
app/
  (auth)/login/
  (rnd)/
    dashboard/
    recipes/
    ncp/
      patients/
      assessment/
      diagnosis/
      intervention/
      monitoring/
    food-service/
      inventory/
      menu-cycle/
      budget/
      procurement/
    reports/
    calendar/
    notifications/
    settings/
  (fss)/
    dashboard/
    inventory/
    menu-cycle/
    meal-prep-log/
    notifications/
    settings/
  (admin)/
    dashboard/
    users/
    reports/
    announcements/
    audit-logs/
    settings/
components/
  shared/
  rnd/
  fss/
  admin/
services/
  announcementService.ts        dashboard announcement API client
  authService.ts
  patientService.ts
lib/
  api.ts                        axios instance with auth token
  auth.ts
middleware.ts                   route protection by role
```
