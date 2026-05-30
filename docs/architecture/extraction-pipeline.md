# Extraction Pipeline Architecture

## Overview

The extraction pipeline provides reusable document parsing across NutriScope. It accepts uploaded documents (images/PDFs), runs OCR via PaddleOCR, parses extracted text using template-defined field mappings, scores confidence per field, and auto-populates target models — always with a human review step before finalization.

## Components

### OCRService
- HTTP client to PaddleOCR microservice (`http://paddleocr:5000/ocr`)
- Single method: `extract(string $filePath): string` → returns raw text
- Handles timeout (30s), retry (3 attempts), error logging
- Called only from Jobs, never directly from controllers
- Uses mock implementation during development (M3), live from M4

### ExtractionService
- Template-based parsing engine
- Accepts raw OCR text + document type
- Loads matching `extraction_template` from database
- Applies field_mappings (regex patterns, checkbox detection, table parsing)
- Scores confidence per field (0.0–1.0) based on pattern match quality
- Returns `ParsedDocument` DTO
- Logs everything to `extraction_logs`

### ProcessDocumentExtraction Job
- Generic extraction job (replaces single-purpose ProcessOCRDocument concept)
- Accepts: `document_id`, `document_type`, `target_model`, `target_id`
- Flow: OCRService → ExtractionService → update target model → fire event
- Runs on Redis queue, non-blocking

### ParsedDocument DTO
```
fields: array<string, mixed>           // extracted key-value pairs
confidenceScores: array<string, float> // per-field confidence (0.0-1.0)
rawText: string                        // full OCR output
documentType: string                   // template type used
processingTimeMs: int                  // OCR + parsing time
errors: array                          // any parsing errors
```

## Document Types

### Screening Forms (Adult/Pediatric)
- **Source**: Appendix B.06 (Pediatric), B.07 (Adult) — Nutrition Screening & Referral Tool
- **Extraction targets**: `screening_documents.extracted_data`
- **Auto-mapped to**: `assessments` (clinical conditions, intake/weight flags)
- **Key fields**:
  - Clinical conditions (checkbox array — 15 adult / 18 pediatric conditions)
  - Intake/weight history (checkbox array)
  - Referral type (Per Orem / Tube Feeding / NPO/TPN)
  - Patient demographics (name, age, sex, height, weight)
- **Risk score calculation**: Deterministic from checked items -> stored in nullable `ncp_records.risk_score`

### Lab Results (Biochemical Data)
- **Source**: NCP form — Biochemical Data section
- **Extraction targets**: `biochemical_data` fields
- **Key fields**: Albumin, Hematocrit, BUN, Hemoglobin, Calcium, LDL, Cholesterol, Phosphate, Creatinine, Potassium, Glucose, Sodium, HbA1C, Triglycerides, HDL, URR, BP, ABG
- **Pattern**: `FieldName[:\s]+([value])` regex matching

### Procurement Documents
- **Acceptance & Inspection Report**: Line item table extraction (Item No, Unit, Description, Quantity), supplier info, dates, certification signatories
- **Statement of Marketing Purchased**: Line item extraction (Item, Unit Price, Total Value), grand total, period, certifications
- **Both**: Items fuzzy-matched to `food_items` table by name

## Extraction Templates (Database-Driven)

Templates stored in `extraction_templates` table — not hardcoded. Each template defines:
- `document_type` — enum matching supported types
- `field_mappings` — JSON defining regex patterns, target fields, and value types
- `validation_rules` — JSON defining acceptable ranges/formats per field
- `version` — for template evolution tracking
- `is_active` — only one active template per type

Seeded via `ExtractionTemplateSeeder` with initial templates for all 5 document types.

## Confidence Scoring

Per-field confidence based on:
- Regex match quality (exact match = 1.0, partial = 0.5-0.8)
- Value validation (within expected range = +0.2)
- Multiple pattern hits for same field = higher confidence
- Overall document confidence = average of field confidences

UI displays: Green (>0.8), Yellow (0.5-0.8), Red (<0.5) confidence indicators.

## Data Flow

```
Upload → Controller creates record (status=pending)
       → Dispatches ProcessDocumentExtraction Job
       → Job: OCRService.extract() → raw text
       → Job: ExtractionService.parse(text, template) → ParsedDocument
       → Job: Store parsed fields + confidence scores
       → Job: Auto-map to target model (tentative)
       → Job: Fire DocumentExtractionCompleted event
       → Frontend: Poll or receive notification
       → Frontend: Show review panel (extracted values + confidence + override)
       → User: Accept / modify / reject individual fields
       → Controller: Finalize accepted fields into target model
```
