# NutriScope Overview

Verified against current role boundaries and application navigation on **2026-08-27**.

### 1. System Purpose

NutriScope is a clinical nutrition system for hospital workflows that manages patient nutrition care, meal planning, and food service operations.

It combines deterministic algorithms, including system-calculated nutrition risk scoring, with limited AI assistance for clinical reasoning support. The system features an OCR-based document extraction pipeline for screening forms, lab results, and procurement documents, plus a modular report generation engine for clinical, operational, and financial reporting.

### 2. Core Users
- RND — clinical nutrition care and NCP workflow
- FSS — native-mobile food-service execution, served population, receiving evidence, daily accomplishments, and own reports
- Admin — role and system management

### 3. Main Modules
- RND Clinical System (NCP, patients, interventions, monitoring)
- Communication System (database-backed announcements with role visibility)
- Document Extraction Pipeline (screening forms, lab results, procurement docs → auto-populate)
- Report Generation Engine (NCP Summary, Patient Menu Plan, Demographic Census, Program Project Activity, Menu Calendar, Procurement Pack, and Accomplishment Report)
- Food Service System (reference catalogs, recipes, menu cycles, procurement, served population, accomplishments, and budget outcomes)
- Admin System (users, reports, audit, configuration)

### 4. Documentation Map

For details, refer to:

- `modules/` → current role behavior and flowcharts
- `logic/` → deterministic nutrition and meal-planning rules
- `architecture/` → stack, search, rate limits, audit logging, and compatibility notes
- `security/` → access-control and security guidance
- `operations/` → deployment, backup, recovery, and platform handoff
- `developer/` → maintenance guidance
- `database-schema.md` → schema reference; migrations remain authoritative
- `FAQ.md`, `ROLE-HOW-TO.md`, and `STORYBOARD.md` → user and presentation guidance
- `fss-native-app-consolidation-report.md` → PWA removal through native APK delivery
- `mobile-apk-release.md` → repeatable Android release and stable-QR procedure
