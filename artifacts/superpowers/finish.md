# Finish Notes — NCP Diagnosis Page

## Completed
- `diagnosisService.ts` created with full typed API surface (CRUD + AI)
- Diagnosis page replaced with 6-tab working implementation
- TypeScript passes clean (`tsc --noEmit --skipLibCheck` → no errors)

## What Was Built
| Feature | Status |
|---|---|
| Tab 1: Diagnosis table with domain filter, edit/delete, AI badge | ✅ |
| Tab 2: P builder (NI direction+nutrient, NC/NB multi-select checklists) | ✅ |
| Tab 3: E builder with domain-specific etiology checklists + free text | ✅ |
| Tab 4: S builder with domain-specific S&S checklists + free text | ✅ |
| Tab 5: PES auto-assembly + manual override + save | ✅ |
| Tab 6: AI suggest → suggestion cards → Accept/Edit/Reject | ✅ |
| Patient header (same pattern as assessment page) | ✅ |
| Placeholder state for select-patient / select-ncp | ✅ |

## Known Issues / Follow-up
- `AIService` model: still uses `claude-haiku-20240307`. Should be updated to `claude-haiku-4-5-20251001` in backend `config/services.php` or `.env`.
- `AIService` prompt is bare JSON dump. For reliable JSON output in Tab 6, the backend prompt should request a structured JSON response with `suggestions` array. Current implementation may return 0 suggestions if the model outputs freeform text rather than the expected `{suggestions:[...]}` wrapper.

## Verification Needed
- [ ] Browser: Tab 1 renders diagnosis list with domain filter working
- [ ] Browser: Tab 2→3→4→5 builder flow saves a new diagnosis to API
- [ ] Browser: Edit existing diagnosis loads correctly into builder
- [ ] Browser: Delete with confirm prompt works
- [ ] Browser: Tab 6 AI suggest generates and accept/reject/edit work
