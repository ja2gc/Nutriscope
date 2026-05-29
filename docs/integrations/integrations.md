## Key Integrations

### PaddleOCR

Called from Laravel via HTTP POST to http://paddleocr:5000/ocr
Used in: Document extraction pipeline (screening forms, biochemical labs, procurement documents)
Always processed as background Job (Laravel Queue + Redis)
Returns extracted text → ExtractionService parses and maps to fields using extraction_templates
Manual override always available via review panel

**Integration flow:**
1. User uploads document image/PDF via controller endpoint
2. Controller creates record (screening_document/ocr_document) with status=pending
3. `ProcessDocumentExtraction` Job dispatched to queue
4. Job calls `OCRService::extract()` → HTTP POST to PaddleOCR
5. Raw text passed to `ExtractionService::parse()` with matching extraction_template
6. Parsed key-value pairs stored with per-field confidence scores
7. Mapped fields auto-populate target model (assessment, biochemical_data, etc.)
8. `DocumentExtractionCompleted` event fires → notification dispatched
9. Frontend shows review panel: extracted values + confidence + manual override

**Document types supported:**
- `screening_adult` — Adult Nutrition Screening & Referral form
- `screening_pediatric` — Pediatric Nutrition Screening & Referral form
- `lab_result` — Biochemical lab results (key-value extraction matching BiochemicalData fields)
- `inspection_report` — Acceptance & Inspection Report (line items, supplier, dates)
- `marketing_statement` — Statement of Marketing Purchased (line items, totals, certifications)

**Development approach:**
- M3: PaddleOCR Docker container added, mock OCRService used for testing
- M4: Live screening form extraction
- M5: Live lab result extraction
- M9: Live procurement document extraction

### Anthropic API

Called from Laravel AIService class only — never from frontend
Uses two models:

- claude-haiku-4-5 for simple tasks (PES drafting, explanations, fallback meal suggestions)
- claude-sonnet-4-6 for complex reasoning (M&E decisions, risk analysis, trend forecasting)

Rate limited: throttle:10,1 on all AI endpoints
Always dispatched as background Job for non-blocking UX
Daily token limit enforced in AIService before every call
All usage logged to ai_usage_logs

### USDA FoodData Central

Called from Laravel FoodService class
Used in: Foods Library, Recipe Builder nutrient data
All responses cached in Redis for 7 days
Nutrient data stored locally in food_items table after first fetch
Meal plan auto-generation uses local food_items table only — does not call USDA during generation
API key in .env as USDA_API_KEY
Pre-seed 15-20 common Filipino hospital foods on first setup using USDA data

### DomPDF (Report Generation)

Used in: Report generation pipeline
All reports rendered via Blade templates → DomPDF → PDF file
Always processed as background Job (`GenerateReport`)
Reports stored in `storage/reports/` with status tracking
PDF only — no Excel/CSV export

**Report types generated:**
- ADIME Individual — single patient NCP summary
- ADIME Aggregate — aggregate patient analytics
- NCP Census — patient demographics/malnutrition breakdown (B.08 reference)
- Inventory — stock levels, expiry, usage rates
- Budget & Procurement — planned vs actual, variance analysis
- Menu Cycle — weekly schedule with recipes/costs/nutrition
- Patient Menu Plan — individual patient meal plan
- Inspection Report — generated from system data or OCR-populated
- Marketing Statement — generated from system data or OCR-populated

**Architecture:**
- `ReportService` orchestrates template loading and PDF rendering
- Each report type has a `Generator` class implementing `ReportGeneratorInterface`
- Generators query data, format it, return to ReportService for rendering
- All date ranges are arbitrary (user-selected), not locked to fixed periods