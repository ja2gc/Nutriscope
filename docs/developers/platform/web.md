# Web

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: find Next.js screens, existing controls and API transport.

- [app](../../../frontend/app) owns pages and API handlers; [services](../../../frontend/services) owns client calls/types.
- [AuthContext](../../../frontend/contexts/AuthContext.tsx) owns web auth state; [login handler](../../../frontend/app/api/auth/login/route.ts) stores HttpOnly cookies. [laravelProxy](../../../frontend/lib/laravelProxy.ts) forwards bearer auth; [privateBinaryProxy](../../../frontend/lib/privateBinaryProxy.ts) handles private binaries.
- [components/ui](../../../frontend/components/ui) contains existing Input, NumericInput, Button, Card, PageHeader, ImageFilePicker and ImageUploadGallery controls. [uploadValidation](../../../frontend/lib/uploadValidation.ts) checks caller-supplied limits. Profile cropping and clinical PDF attachments have different workflows.
- [hooks](../../../frontend/hooks) includes useDebouncedValue and useUnsavedChanges. Keep domain state local unless actual matching callers justify reuse.
- NCP pages under [app/(rnd)/ncp](<../../../frontend/app/(rnd)/ncp>) use clinical components and headers. Food-service screens under [app/(rnd)/food-service](<../../../frontend/app/(rnd)/food-service>) use their own operational services.
- [ReportPreview](../../../frontend/components/ReportPreview.tsx) owns browser PDF presentation.

Change a screen by tracing its service and API handler through Laravel. Reuse matching controls; preserve differing file limits, validation rules, permissions and submit behavior. Browser checks improve UX; Laravel remains authoritative.

Configuration: server-only LARAVEL_API_URL includes /api. Never put provider keys in NEXT_PUBLIC variables. Read [Next config](../../../frontend/next.config.ts): production builds skip type errors, so a separate type check is required.

Commands declared in [package.json](../../../frontend/package.json): npm test, npm run lint, npm run build. Run only after [isolation checks](testing.md). Exact framework instructions: [frontend/AGENTS.md](../../../frontend/AGENTS.md).

Related: [backend](backend.md), [clinical](../ncp/README.md), [food service](../food-service/README.md).
