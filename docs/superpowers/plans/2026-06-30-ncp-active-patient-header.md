# NCP Active Patient Header Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the active patient name at the top of every real NCP step and provide a reliable "Change Patient" action back to the NCP patient directory.

**Architecture:** Reuse the existing patient data contract through `frontend/services/patientService.ts` and the existing Next API proxy routes. Add one shared client component for the patient banner, then replace duplicated per-page headers in Assessment and Diagnosis, add the same header to Intervention and Monitoring, and keep placeholder/no-patient states unchanged.

**Tech Stack:** Next.js 16 App Router, React 19 client components, TypeScript, Laravel 13 API via Next proxy, Sanctum auth, Vitest, PHPUnit.

---

## Relevant Files Read

- `frontend/app/(rnd)/ncp/patients/page.tsx` - patient directory and current target for selecting a different patient.
- `frontend/app/(rnd)/ncp/patients/[patientId]/page.tsx` - patient profile, NCP cycle start/continue buttons, and existing ADIME route generation through `getNcpStepState`.
- `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx` - already fetches `fetchPatientById(patientId)` and renders a patient header; this is source UI to extract.
- `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx` - already fetches patient and renders a similar header; must use same shared header.
- `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx` - fetches patient only inside `loadMetrics`, but does not store/display patient header in main UI; must add explicit patient state or shared hook.
- `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx` - does not fetch patient details; currently shows only patient ID in sidebar, so it needs patient fetch for name.
- `frontend/services/patientService.ts` - frontend patient/NCP types and `fetchPatientById`, `fetchPatientNcpRecords`, `createNcpRecord`; use this instead of new fetch code.
- `frontend/app/api/patients/route.ts`, `frontend/app/api/patients/[id]/route.ts`, `frontend/app/api/patients/[id]/ncp-records/route.ts` - Next proxy routes used by `patientService`; do not bypass these from pages.
- `frontend/lib/apiFetch.ts` - central client fetch wrapper; keeps 401 redirect behavior.
- `frontend/lib/laravelProxy.ts` - pattern for small Next proxy routes; useful only if a new proxy is needed.
- `frontend/lib/ncpWorkflow.ts` and `frontend/lib/ncpWorkflow.test.ts` - canonical NCP step hrefs and gate behavior; do not duplicate route strings outside this helper where step links are needed.
- `backend/routes/api.php` - Laravel RND routes already expose `GET /api/rnd/patients/{patient}` and NCP step resources; no new backend route should be necessary.
- `backend/app/Http/Controllers/RND/PatientController.php` - confirms `show(Patient $patient)` uses implicit route model binding and loads NCP records.
- `backend/app/Http/Resources/PatientResource.php` - confirms `name`, `ward`, `medical_diagnosis`, `risk_score`, and `latest_ncp_id` exist in patient response.
- `docs/superpowers/specs/2026-06-25-ncp-navigation-design.md` - existing NCP route/gate decisions to preserve.
- `docs/superpowers/specs/2026-06-22-patient-specific-monitoring-design.md` - monitoring context decisions; header change must not disturb monitoring-plan behavior.

## Constraints

- No Laravel API change unless implementation proves current patient payload lacks required display fields.
- No new route shape. Keep `/ncp/[patientId]/assessment/[ncpId]`, `/diagnosis/`, `/intervention/`, `/monitoring/`.
- Placeholder routes stay reachable: `/ncp/select-patient/{step}/select-ncp`.
- "Change Patient" goes to `/ncp/patients`, not browser back, so user can pick any patient.
- On Intervention, respect existing `dirty` guard before navigating away.
- Do not hide the button behind loading states. Show disabled/loading patient name text while data loads, but keep directory route available.

## Proposed File Changes

- Create `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`.
  - Props: `patient`, `patientId`, `ncpId`, `stepLabel`, `cycleLabel?`, `badges?`, `onChangePatientClick?`.
  - Render patient name, `NS-00000` ID, `NCP-00000` cycle ID, ward, medical diagnosis, and "Change Patient" link/button to `/ncp/patients`.
  - If `onChangePatientClick` exists, use a button and let page confirm unsaved edits before `router.push("/ncp/patients")`.
- Modify `frontend/app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx`.
  - Replace local patient header JSX with `NcpPatientHeader`.
  - Keep existing `patient` state and `fetchPatientById(patientId)`.
- Modify `frontend/app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx`.
  - Replace duplicated patient header JSX with `NcpPatientHeader`.
  - Keep existing patient fetch and diagnosis count badge.
- Modify `frontend/app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx`.
  - Add `const [patient, setPatient] = useState<Patient | null>(null)` or reuse typed `fetchPatientById` result from `loadMetrics`.
  - Render `NcpPatientHeader` above step title.
  - Wire `onChangePatientClick` to existing dirty confirmation, then `router.push("/ncp/patients")`.
- Modify `frontend/app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx`.
  - Import `fetchPatientById` and `Patient`.
  - Fetch patient in `loadData` with existing Promise settlement pattern.
  - Render `NcpPatientHeader` above "Step 4".
  - Keep workflow-block and placeholder views, but include the header in workflow-block when patient fetch succeeds.
- Add/update tests:
  - `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.test.tsx` if existing test setup supports React component tests.
  - Otherwise add static route/content tests similar to existing `patient-profile-attachments.test.ts`.
  - Extend `frontend/lib/ncpWorkflow.test.ts` only if any helper changes are required; expected no helper change.

## Task Plan

### Task 1: Lock Data Contract

- [ ] Verify `Patient` type has `name`, `ward`, `medical_diagnosis`, `status`, and `id`.
- [ ] Verify `fetchPatientById(patientId)` returns `responseData.data || responseData`.
- [ ] Verify Next route `frontend/app/api/patients/[id]/route.ts` proxies to `${LARAVEL_API}/rnd/patients/${id}`.
- [ ] Do not add backend endpoint if above remains true.

Commands:

```bash
cd frontend
npm test -- lib/ncpWorkflow.test.ts
```

Expected: existing workflow tests pass before edits.

### Task 2: Create Shared Header

- [ ] Create `frontend/app/(rnd)/ncp/_components/NcpPatientHeader.tsx`.
- [ ] Use `Link` for default change-patient action.
- [ ] Use `button` only when page passes `onChangePatientClick`, needed for unsaved-change guard.
- [ ] Format IDs locally:

```ts
function formatSystemId(id: number | string) {
  return `NS-${String(id).padStart(5, "0")}`;
}

function formatCycleId(id: number | string) {
  return `NCP-${String(id).padStart(5, "0")}`;
}
```

- [ ] Use lucide icons already installed: `Heart`, `ArrowLeftRight` or `Users`.
- [ ] Render loading fallback: patient name `Loading patient...`, ID from route param, active button to `/ncp/patients`.

### Task 3: Replace Assessment Header

- [ ] Import shared header in Assessment page.
- [ ] Remove duplicated "Persistent Patient Header" block.
- [ ] Pass existing `patient`, `patientId`, `ncpId`, `stepLabel="Assessment"`.
- [ ] Preserve allergy/risk badges by passing `badges`.
- [ ] Confirm save bar and tabs still render below header.

### Task 4: Replace Diagnosis Header

- [ ] Import shared header in Diagnosis page.
- [ ] Remove duplicated "Patient Header" block.
- [ ] Pass existing `patient`, `patientId`, `ncpId`, `stepLabel="Diagnosis"`.
- [ ] Pass diagnosis count badge as `badges`.
- [ ] Preserve placeholder/no-patient rendering.

### Task 5: Add Intervention Header

- [ ] Store patient loaded by `fetchPatientById(patientId)` in page state.
- [ ] Avoid extra patient fetch if `loadMetrics` already has fulfilled patient result.
- [ ] Render shared header before Intervention step title.
- [ ] Add guarded change-patient handler:

```ts
const handleChangePatient = () => {
  if (dirty && !window.confirm("You have unsaved changes. Leave without saving?")) return;
  router.push("/ncp/patients");
};
```

- [ ] Pass `onChangePatientClick={handleChangePatient}`.
- [ ] Keep existing dirty guard on breadcrumb link.

### Task 6: Add Monitoring Header

- [ ] Add `patient` state and include `fetchPatientById(patientId)` in `Promise.allSettled`.
- [ ] Do not let patient fetch failure block monitoring data; show route ID fallback and error only if existing monitoring load fails.
- [ ] Render shared header on loaded monitoring page.
- [ ] In workflow-block page, render shared header if not placeholder so user still sees active patient before "Continue Care Plan".
- [ ] Keep placeholder route showing "No Patient Selected" with directory button.

### Task 7: Route/API Safety Checks

- [ ] Ensure all patient links use `/ncp/patients`, not `/patients` or `/api/patients`.
- [ ] Ensure step links remain `getCycleStepHref(record.patient_id, step, record.id)` from `frontend/lib/ncpWorkflow.ts`.
- [ ] Ensure page data calls use `fetchPatientById(patientId)`, not direct `fetch("/api/rnd/...")`.
- [ ] Ensure no new Laravel route is added unless a test proves a missing backend contract.
- [ ] If backend touched, use implicit model binding and scoped relationships per Laravel 13 docs and local Laravel best-practices.

### Task 8: Verification

- [ ] Run targeted frontend tests:

```bash
cd frontend
npm test -- lib/ncpWorkflow.test.ts
```

- [ ] Run new/changed header tests:

```bash
cd frontend
npm test -- app/\(rnd\)/ncp
```

- [ ] Run TypeScript:

```bash
cd frontend
npx tsc --noEmit
```

- [ ] Run lint:

```bash
cd frontend
npm run lint
```

- [ ] If backend changes happen, run patient/NCP feature tests:

```bash
cd backend
php artisan test --filter=PatientFeatureTest
php artisan test --filter=NcpAssessmentTest
php artisan test --filter=NcpDiagnosisTest
php artisan test --filter=NcpInterventionTest
php artisan test --filter=NcpMonitoringTest
```

## Failure Modes To Prevent

- Header shows `Loading...` forever because Monitoring never fetches patient.
- "Change Patient" uses browser back and returns to wrong previous page.
- Intervention loses unsaved-change protection.
- New component imports from route folders with invalid relative depth; use alias only if configured, otherwise local relative import from page to `_components`.
- Button exists but does nothing in one step due missing `router`.
- Backend route mismatch between frontend `/api/patients/{id}` and Laravel `/api/rnd/patients/{patient}`.
- Placeholder pages accidentally fetch `select-patient` as real ID and produce 500/404 noise.

## Recommended Implementation Order

1. Shared header component.
2. Assessment replacement.
3. Diagnosis replacement.
4. Intervention patient state + guarded button.
5. Monitoring patient fetch + header.
6. Tests and typecheck.

