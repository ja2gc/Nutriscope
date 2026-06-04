# NCP Diagnosis Page — Implementation Plan

## Context
Current milestone: **4C — NCP Diagnosis Backend** (routes, controller, AI service all wired). Frontend placeholder exists at `/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` but is a static shell.

## Backend Audit (pre-implementation)

| Artifact | Status | Notes |
|---|---|---|
| `DiagnosisController` (CRUD) | ✅ Done | index / store / update / destroy |
| `AiDiagnosisController` | ✅ Done | aiSuggest / aiApprove |
| `StoreDiagnosisRequest` | ✅ Done | domain in:NI,NC,NB + problem/etiology/S&S |
| `UpdateDiagnosisRequest` | ✅ Done | all nullable patch |
| `DiagnosisResource` | ✅ Done | returns all fields + pes_statement |
| Routes | ✅ Done | CRUD + AI routes registered under `rnd` middleware |
| `Diagnosis` model + `buildPes` | ✅ Done | static PES builder |
| `AIService::suggestDiagnoses` | ⚠️ Functional but bare | Prompt just dumps JSON; model is `claude-haiku-20240307` (old). Functional for now. |
| `diagnosisService.ts` (frontend) | ❌ Missing | Must create |

## Missing Backend (none blocking diagnosis page)
All diagnosis-specific backend is implemented. The AI model string is outdated but the endpoint works. No new controllers or migrations needed for this feature.

## Frontend Plan

### 1. `frontend/services/diagnosisService.ts`
Typed service file wrapping:
- `GET  /api/rnd/ncp-records/{id}/diagnoses` → `fetchDiagnoses`
- `POST /api/rnd/ncp-records/{id}/diagnoses` → `storeDiagnosis`
- `PATCH /api/rnd/ncp-records/{id}/diagnoses/{diagId}` → `updateDiagnosis`
- `DELETE /api/rnd/ncp-records/{id}/diagnoses/{diagId}` → `deleteDiagnosis`
- `POST /api/rnd/ncp-records/{id}/diagnoses/ai-suggest` → `aiSuggest`
- `POST /api/rnd/ncp-records/{id}/diagnoses/ai-approve` → `aiApprove`

### 2. Diagnosis Page — 6 Tabs

**Tab 1 — Diagnosis Table**
- Load and display all diagnoses for NCP record
- Columns: #, Domain badge, Problem, Etiology, S&S, PES statement, Extra Notes, Actions
- Domain filter chip buttons (All / NI / NC / NB)
- AI Suggested badge on `ai_generated = true` rows
- Edit → loads into builder tabs, Delete → confirm + remove
- "Add New Diagnosis" → clears builder, jumps to Tab 2

**Tab 2 — Problem (P) Builder**
Domain selector → domain-specific problem options:
- NI: Direction (Inadequate/Excessive) + Nutrient dropdown
- NC: Multi-select checklist of standardized NC problems
- NB: Multi-select checklist of standardized NB problems
- Free-text Extra Notes

**Tab 3 — Etiology (E) Builder**
Domain-conditional checklist + free text override

**Tab 4 — Signs & Symptoms (S) Builder**
Domain-conditional checklist + free text override

**Tab 5 — PES Statement**
- Auto-assembled: "[Problem] related to [Etiology] as evidenced by [S&S]"
- Editable textarea for manual override
- Save button → POST/PATCH → returns to Tab 1

**Tab 6 — AI Review**
- Sends patient conditions + ibw_percentage to `ai-suggest`
- Renders suggestion cards (domain badge, PES preview, confidence)
- Per card: Accept (→ ai-approve → saved) | Reject (dismiss) | Edit (load into builder)

## G-NCP Standard Problems Reference
**NI**: Inadequate/Excessive [Energy | Protein | Carbohydrates | Fat | Fluid | Fiber | Vitamins | Minerals | Oral intake]
**NC**: Unintended Weight Loss · Overweight/Obesity · Malnutrition · Altered GI Function · Swallowing Difficulty · Altered Lab Values · Food-Medication Interaction · Predicted Suboptimal Intake
**NB**: Food and Nutrition Knowledge Deficit · Harmful Beliefs about Food · Not Ready for Lifestyle Change · Self-Monitoring Deficit · Disordered Eating Pattern · Limited Adherence · Undesirable Food Choices · Physical Inactivity · Food Insecurity

## Execution Order
1. Write `diagnosisService.ts`
2. Implement full page replacing placeholder
3. Verify in browser

## Workflow Tokens
- Plan: `superpowers/plan.md` (this file)
- Execution: `superpowers/execution.md`
- Review: `superpowers/review.md`
- Finish: `superpowers/finish.md`
