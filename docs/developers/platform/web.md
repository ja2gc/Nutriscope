# Web

Refresh behavior verified against source: `8a7846b3` plus the in-place update changes in this working tree (2026-10-27). Other architectural pointers retain their previous source verification (`52a01780`, 2026-10-23).

Purpose: find Next.js screens, existing controls and API transport.

- [app](../../../frontend/app) owns pages and API handlers; [services](../../../frontend/services) owns client calls/types.
- [AuthContext](../../../frontend/contexts/AuthContext.tsx) owns web auth state; [login handler](../../../frontend/app/api/auth/login/route.ts) stores HttpOnly cookies. [laravelProxy](../../../frontend/lib/laravelProxy.ts) forwards bearer auth; [privateBinaryProxy](../../../frontend/lib/privateBinaryProxy.ts) handles private binaries.
- [components/ui](../../../frontend/components/ui) contains existing Input, NumericInput, Button, Card, PageHeader, ImageFilePicker and ImageUploadGallery controls. [uploadValidation](../../../frontend/lib/uploadValidation.ts) checks caller-supplied limits. Profile cropping and clinical PDF attachments have different workflows.
- [hooks](../../../frontend/hooks) includes useDebouncedValue and useUnsavedChanges. Keep domain state local unless actual matching callers justify reuse.
- After mutations, keep existing content mounted. Local collection loaders accept an optional `quiet` flag, following [Backups](../../../frontend/app/admin/backups/page.tsx): initial/filter/page navigation may show loading placeholders; mutation reconciliation skips them. Inventory, Food Library/templates, menu directories, Procurement, Announcements, clinical Diagnosis/Monitoring and patient cycles use this convention. Keep dependent totals, ordering and pagination authoritative through their existing fetch functions. SOP uses the saved response directly; Vendor edits use the saved response and refetch quietly when names or active search affect membership/order. Budget adjustments refresh summary and ledger without reloading the fiscal-year list. Failed background refreshes retain prior content and show errors. Explicit save-and-return navigation still loads its destination normally.
- NCP pages under [app/(rnd)/ncp](<../../../frontend/app/(rnd)/ncp>) use clinical components and headers. Food-service screens under [app/(rnd)/food-service](<../../../frontend/app/(rnd)/food-service>) use their own operational services.
- [Sidebar](../../../frontend/components/layout/Sidebar.tsx) remembers the selected NCP cycle per signed-in user through [ncpWorkflow](../../../frontend/lib/ncpWorkflow.ts). Opening a patient profile selects its current cycle, or latest past cycle when none is current. Loading, failures and patients without cycles keep step links on that profile rather than opening an empty placeholder or using another patient's remembered cycle. Explicit cycle-step URLs remain authoritative; leaving a profile cancels its pending selection update.
- [ReportPreview](../../../frontend/components/ReportPreview.tsx) owns browser PDF presentation.

Change a screen by tracing its service and API handler through Laravel. Reuse matching controls; preserve differing file limits, validation rules, permissions and submit behavior. Browser checks improve UX; Laravel remains authoritative.

Configuration: server-only LARAVEL_API_URL includes /api. Never put provider keys in NEXT_PUBLIC variables. Read [Next config](../../../frontend/next.config.ts): production builds skip type errors, so a separate type check is required.

Commands declared in [package.json](../../../frontend/package.json): npm test, npm run lint, npm run build. Run only after [isolation checks](testing.md). Exact framework instructions: [frontend/AGENTS.md](../../../frontend/AGENTS.md).

Related: [backend](backend.md), [clinical](../ncp/README.md), [food service](../food-service/README.md).
