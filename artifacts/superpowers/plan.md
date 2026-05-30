# Milestone 2B — UI Scaffold, Navigation Refactor & Feature Additions (Frontend Focus)

## Goal
Implement Milestone 2B on the frontend: Sidebar accordion collapsible NCP submenus, simplified Master Patients list with a single 'View Profile' link, Patient Profile page displaying ADIME cycle summaries and direct edit/view links to dyn shells, a RND dashboard with upcoming follow-ups and budget KPIs, Facebook-style announcement feed modal, and dynamic placeholder shells. No database tables or schema extensions will be scaffolded, and all advanced metrics use rich frontend clinical mocks.

## Plan

### Step 1: Document Milestone 2B Specifications
- **Files**: `docs/milestones/milestones.md`
- **Change**: Document the scope of Milestone 2B including UI improvements, RND/Admin scaffolding, and the mock-data boundary rule.
- **Verify**: Confirm milestones.md contains Milestone 2B description and checklist.

### Step 2: Collapsible Navigation & Sidebar Layouts
- **Files**: `frontend/components/layout/Sidebar.tsx`
- **Change**: Implement collapsible sub-menus, smooth transitions, active status highlighting, and user-role-based sidebar lists (RND items vs. Admin items).
- **Verify**: Compile TypeScript and test sidebar expansion/role changes.

### Step 3: Implement RND Scaffolding Route Shells
- **Files**: Create dynamic route placeholder pages for Recipes, Assessment, Diagnosis, Intervention, Monitoring, Food Service, Reports, Calendar, Notifications, and Settings under `frontend/app/(rnd)/...`.
- **Change**: Scaffold pages with breadcrumb paths, headings, and clean empty state messaging.
- **Verify**: Test routing to `/recipes` and `/ncp/123/assessment/456` in the dev server.

### Step 4: Implement Admin Scaffolding Route Shells & Layout
- **Files**: Create layout and five admin shells under `frontend/app/admin/...`.
- **Change**: Build persistent admin sidebar structure, roles authorization protection, and empty states.
- **Verify**: Verify admin routes load correctly when authenticated.

### Step 5: Relocate & Overhaul Master Patient Listing
- **Files**: `frontend/app/(rnd)/ncp/patients/page.tsx` [NEW], `frontend/app/(rnd)/ncp/page.tsx` [DELETE]
- **Change**: Relocate the patient listing. Incorporate revamped clinical columns (`Name/ID | Age/Sex | Physician | Last Assessment | Next Follow-up | Risk Status | Actions`). In the Actions column, render only the single "View Profile" action link. Configure form submissions to redirect to `/ncp/patients/[id]`.
- **Verify**: Access `/ncp/patients`, verify columns, confirm the only action is "View Profile", and verify registration redirects to the profile.

### Step 6: Patient Profile ADIME Records Tab with Summary & Workflow Actions
- **Files**: `frontend/app/(rnd)/ncp/patients/[id]/page.tsx` [NEW], `frontend/app/(rnd)/ncp/[id]/page.tsx` [DELETE]
- **Change**: Relocate profile views and add persistent banners, restricted indicators, and the ADIME Records tab. Implement the NCP Summary Box (summarizing Assessment findings, active Diagnoses, Interventions, and Monitoring dates) for each cycle in the list. Under each summary, render the "Manage ADIME Workflow" buttons (Edit Assessment, Edit Diagnosis, Edit Intervention, Edit Monitoring & Eval) linking to the dynamic placeholder routes.
- **Verify**: Open `/ncp/patients/[id]`, verify the NCP summary box displays properly under the ADIME Records tab, and click the direct step links to verify they route to the correct scaffolds.

### Step 7: Dashboard Metrics & Announcements Modal
- **Files**: `frontend/app/(rnd)/dashboard/page.tsx`
- **Change**: Remove old table. Implement "Upcoming Follow-ups" widget and "Budget Per Person" KPI. Implement dynamic announcements feed and the Facebook-style click modal view.
- **Verify**: Check the dashboard rendering, KPI cards, and trigger the Facebook-style announcement modal.

## Risks & Mitigations
- **Risk**: Overlapping pages resolving to identical URLs between the dynamic role scopes.
- **Mitigation**: Admin actions and sections are strictly isolated inside the `/admin/...` subdirectory, completely avoiding path clashes.

## Rollback Plan
- Revert Git changes and remove new layout templates:
  `git reset --hard HEAD`
  `git clean -fd`
