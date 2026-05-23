Laravel /backend
app/
  Http/
    Controllers/        one per resource
    Requests/           one per endpoint that accepts input
    Resources/          one per model for JSON formatting
  Models/               one per table
  Services/
    AIService.php       all Anthropic calls (both models), token tracking
    FoodService.php     all USDA calls + local food_items queries
    OCRService.php      all PaddleOCR calls
    ReportService.php   all PDF generation (DomPDF)
    MealPlanService.php algorithm-based meal plan generation
    RecommendService.php algorithm-based recommend/avoid list
  Jobs/
    ProcessOCRDocument.php
    GenerateAISuggestion.php
    GenerateReport.php
routes/
  api.php               all API routes grouped by role
Next.js /frontend
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
lib/
  api.ts                axios instance with auth token
  auth.ts
middleware.ts           route protection by role