# Report Pipeline Architecture

## Overview

NutriScope's report system generates PDF documents from system data using a modular generator pattern. Reports are queued as background jobs, rendered via Blade templates + DomPDF, and stored for download. All reports support arbitrary date ranges.

## Components

### ReportService (Orchestrator)
- Receives report type + filters from controller
- Resolves the appropriate `ReportGenerator` implementation
- Calls `generator->getData(filters)` to fetch and aggregate data
- Loads the corresponding Blade template
- Renders HTML → DomPDF → PDF binary
- Stores PDF to `storage/reports/{report_id}.pdf`
- Updates report record status

### ReportGeneratorInterface (Contract)
```php
interface ReportGeneratorInterface
{
    public function getData(array $filters): array;
    public function getTemplateName(): string;
    public function getDefaultFilters(): array;
}
```

### GenerateReport Job
- Dispatched by `ReportController::generate()`
- Resolves generator from `report.type`
- Calls `ReportService::render(generator, filters)`
- Updates report status: `queued → generating → completed/failed`
- Dispatches notification on completion
- Runs on Redis queue

### Report Templates (Blade)
- Located in `resources/views/reports/`
- Standard HTML/CSS for DomPDF compatibility (no JS, limited CSS3)
- Base layout with header (hospital name, report title, date) and footer (page numbers)
- Each template receives typed data array from its Generator

## Report Types

### 1. ADIME Individual
- **Generator**: `AdimeIndividualGenerator`
- **Data**: Single patient → NcpRecord → Assessment + BiochemicalData + Diagnosis + Intervention + Monitoring
- **Filters**: `patient_id`, `ncp_record_id`
- **Template**: Full ADIME summary matching NCP form layout

### 2. ADIME Aggregate
- **Generator**: `AdimeAggregateGenerator`
- **Data**: All NCP records in date range → aggregated statistics
- **Filters**: `date_from`, `date_to`, `rnd_user_id` (optional)
- **Metrics**: Average risk scores, common diagnoses by domain, intervention outcomes, most-used meal plans
- **Available from**: M8 (when monitoring data exists)

### 3. NCP Census
- **Generator**: `NcpCensusGenerator`
- **Data**: Patients + NCP records + demographics in date range
- **Filters**: `date_from`, `date_to` (arbitrary range, not locked to bi-annual)
- **Layout**: Matches Appendix B.08 visual format
- **Metrics**:
  - Admissions by age group (0-4, 5-9, 10-14, 15-18, 19-29, 30-39, 40-59, 60+) × sex (M/F)
  - NAR patient counts
  - Malnutrition categories (wasting, moderate, severe, stunting, underweight, overweight, obese)
  - Disease & co-morbidities
  - ADIME step completion counts (screening, assessment, intervention, full documentation)
- **Available from**: M10 (requires complete patient data coverage)

### 4. Inventory Report
- **Generator**: `InventoryReportGenerator`
- **Data**: `inventory` + `food_items` snapshot
- **Filters**: `date_from`, `date_to`, `category` (optional), `stock_status` (low/expiring/all)
- **Metrics**: Stock levels, expiry dates, usage rates, low-stock flags, estimated days remaining

### 5. Budget & Procurement
- **Generator**: `BudgetReportGenerator`
- **Data**: `budgets` + `budget_daily_logs` + `procurements` + `inspection_reports` + `marketing_statements`
- **Filters**: `date_from`, `date_to`, `budget_id` (optional)
- **Metrics**: Planned vs actual spending, daily variance, procurement totals, cost per person

### 6. Menu Cycle Report
- **Generator**: `MenuCycleReportGenerator`
- **Data**: `menu_cycles` + `menu_cycle_days` + `recipes` + `food_items`
- **Filters**: `menu_cycle_id` or `date_from`/`date_to`
- **Layout**: Weekly grid with meals, recipes, ingredients, per-meal and daily nutritional totals, cost per person

### 7. Patient Menu Plan
- **Generator**: `PatientMenuPlanGenerator`
- **Data**: `meal_plans` + `meal_plan_days` + `meal_plan_items` for specific patient
- **Filters**: `patient_id`, `meal_plan_id` (optional — latest if not specified)
- **Layout**: 7-day × 5-meal grid with food items, quantities, daily macro totals
- **Flow**: Open report → search patient → select plan → generate/export

### 8. Inspection Report (Output)
- **Generator**: `InspectionReportGenerator`
- **Data**: `inspection_reports` + `inspection_report_items`
- **Filters**: `inspection_report_id` or `date_from`/`date_to`
- **Layout**: Matches Acceptance & Inspection Report format (Province of Pampanga LGU header)

### 9. Marketing Statement (Output)
- **Generator**: `MarketingStatementGenerator`
- **Data**: `marketing_statements` + `marketing_statement_items`
- **Filters**: `marketing_statement_id` or `date_from`/`date_to`
- **Layout**: Matches Statement of Marketing Purchased format with certifications

## Access Control

- **RND**: Can generate and view own reports only
- **Admin**: Can generate and view ALL reports across all users
- Report files stored in private storage, served via signed temporary URLs
- Report records include `user_id` for ownership tracking

## Data Flow

```
User selects report type + filters in UI
  → POST /api/rnd/reports {type, filters}
  → StoreReportRequest validates type + filter schema
  → Report::create(status=queued)
  → GenerateReport Job dispatched
  → Job resolves Generator by type
  → Generator queries database, aggregates data
  → ReportService loads Blade template + data → DomPDF → PDF
  → PDF saved to storage/reports/{id}.pdf
  → Report::update(status=completed, file_path, generated_at)
  → Notification dispatched to user
  → Frontend polls or receives update → shows download button
  → GET /api/rnd/reports/{id}/download → signed URL → PDF stream
```
