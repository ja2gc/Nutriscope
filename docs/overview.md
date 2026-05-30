## Overview

### 1. System Purpose

NutriScope is a clinical nutrition system for hospital workflows that manages patient nutrition care, meal planning, and food service operations.

It combines deterministic algorithms, including system-calculated nutrition risk scoring, with limited AI assistance for clinical reasoning support. The system features an OCR-based document extraction pipeline for screening forms, lab results, and procurement documents, plus a modular report generation engine for clinical, operational, and financial reporting.

### 2. Core Users
- RND — clinical nutrition care and NCP workflow
- FSS — food service operations and inventory
- Admin — role and system management

### 3. Main Modules
- RND Clinical System (NCP, patients, interventions, monitoring)
- Communication System (database-backed announcements with role visibility)
- Document Extraction Pipeline (screening forms, lab results, procurement docs → auto-populate)
- Report Generation Engine (ADIME, census, inventory, budget, procurement, menu cycle, patient menu plan)
- Food Service System (inventory, menu cycles, meal prep)
- Admin System (users, reports, audit, configuration)

### 4. Documentation Map

For details, refer to:

- modules/ → role-based system behavior
- logic/ → deterministic decision systems (meal planning algorithm, recommend/avoid engine)
- ai-policy/ → AI usage rules
- database-schema.md → data structure
- security/ → access control
- milestones/ → list of milestones and progress
- integrations/ → api integrations
- architecture/ → folder structure, role navigation, stack, extraction pipeline, report pipeline
- ui/ → UI/UX Architecture & Design System, and how roles workflow
