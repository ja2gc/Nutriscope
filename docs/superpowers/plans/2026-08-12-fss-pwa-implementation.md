# FSS Progressive Web App Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a secure phone/tablet-only installable FSS web app at `/fss`, advertised by install controls on phone/tablet and a phone QR handoff on desktop.

**Architecture:** Extend the existing Next.js app rather than create a second web build. Reuse cookie auth, Laravel proxy helpers, report browser, menu-cycle page, procurement page, services, and UI primitives; add only PWA plumbing, FSS shell, FSS Home/Meal Prep pages, and missing thin proxies.

**Tech Stack:** Next.js 16 App Router, React 19, TypeScript, Tailwind CSS 4, Vitest, Laravel Sanctum APIs.

## Global Constraints

- PWA is for FSS phone/tablet use only; desktop shows only `Scan with your phone` QR handoff.
- Manifest `start_url` is `/fss`.
- RND and Admin continue using `/dashboard` and `/admin/dashboard` in the regular browser site.
- No auth token, API response, report, receipt, upload, or authenticated HTML enters a service-worker cache.
- No offline writes, background sync, push notifications, user-agent package, runtime QR package, or Laravel change unless an existing contract is missing.
- Preserve the Expo app and EAS configuration.
- Preserve unrelated `.codex/config.toml` staging.
- Git metadata and commits stay neutral with no AI attribution.

---

### Task 1: PWA foundation and device-aware install handoff

**Files:**
- Create: `frontend/app/manifest.ts`
- Create: `frontend/public/sw.js`
- Create: `frontend/public/offline.html`
- Create: `frontend/public/nutriscope-fss-192.png`
- Create: `frontend/public/nutriscope-fss-512.png`
- Create: `frontend/public/nutriscope-mobile-qr.svg`
- Create: `frontend/components/pwa/PwaRegistration.tsx`
- Create: `frontend/components/pwa/InstallNutriScope.tsx`
- Create: `frontend/lib/pwa.ts`
- Create: `frontend/lib/pwa.test.ts`
- Create: `frontend/app/pwa-contract.test.ts`
- Modify: `frontend/app/layout.tsx`
- Modify: `frontend/middleware.ts`

**Interfaces:**
- Produces: `isPhoneOrTablet(capabilities: { coarsePointer: boolean; viewportWidth: number }): boolean`.
- Produces: `<InstallNutriScope mode="login" | "landing" />`.
- Produces: installable manifest starting at `/fss`; static service worker caching only `/offline.html` and icon assets.

- [ ] **Step 1: Write failing PWA tests**

Add `pwa.test.ts` cases proving coarse-pointer devices and widths `<=1024` are phone/tablet, while a `1440px` fine-pointer device is desktop. Add `pwa-contract.test.ts` source assertions for manifest `start_url: "/fss"`, `display: "standalone"`, service-worker rejection of `/api/`, and middleware public access to `/mobile-app` plus `/offline`.

- [ ] **Step 2: Verify RED**

Run: `npm test -- lib/pwa.test.ts app/pwa-contract.test.ts`

Expected: FAIL because `lib/pwa.ts`, `app/manifest.ts`, and PWA assets do not exist.

- [ ] **Step 3: Implement minimal PWA foundation**

Implement:

```ts
export function isPhoneOrTablet(input: { coarsePointer: boolean; viewportWidth: number }) {
  return input.coarsePointer || input.viewportWidth <= 1024;
}
```

`InstallNutriScope` captures `beforeinstallprompt`, detects standalone display, and uses `matchMedia("(any-pointer: coarse)")` plus `window.innerWidth`. Phone/tablet gets `Install NutriScope` when prompt exists and concise Add-to-Home-Screen guidance otherwise. Desktop gets only `Scan with your phone` plus `/nutriscope-mobile-qr.svg`. Register `/sw.js` once in production-capable browsers.

Service worker behavior:

```js
if (url.pathname.startsWith('/api/') || request.method !== 'GET') return fetch(request);
// cache-first only explicit public static assets; navigation failure returns /offline.html
```

Generate PNG icons from existing NutriScope artwork and generate a static QR containing exactly `https://nutriscope.live/mobile-app`; do not add a runtime dependency.

- [ ] **Step 4: Verify GREEN**

Run: `npm test -- lib/pwa.test.ts app/pwa-contract.test.ts`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
feat(pwa): add secure install foundation
```

### Task 2: Public landing, login placement, and role routing

**Files:**
- Create: `frontend/app/mobile-app/page.tsx`
- Create: `frontend/app/mobile-app/mobile-app-contract.test.ts`
- Create: `frontend/app/login/login-pwa-contract.test.ts`
- Modify: `frontend/app/login/page.tsx`
- Modify: `frontend/app/page.tsx`

**Interfaces:**
- Consumes: `<InstallNutriScope mode="login" | "landing" />`.
- Produces: public `/mobile-app`; FSS post-login destination `/fss`.

- [ ] **Step 1: Write failing route and UI tests**

Assert login imports `InstallNutriScope`, FSS routes to `/fss`, Admin remains `/admin/dashboard`, RND remains `/dashboard`, and desktop copy contains `Scan with your phone` but no `desktop app`. Assert `/mobile-app` renders the shared install component and labels the app as Food Service Staff-only.

- [ ] **Step 2: Verify RED**

Run: `npm test -- app/login/login-pwa-contract.test.ts app/mobile-app/mobile-app-contract.test.ts`

Expected: FAIL because landing page and login install panel are missing and FSS still routes to `/dashboard`.

- [ ] **Step 3: Implement minimal public surfaces**

Add one compact panel below login form. Keep form hierarchy primary. `/mobile-app` uses existing `Logo`, green/warm tokens, one headline, one explanatory paragraph, and shared install component. Update authenticated root redirect and login redirect:

```ts
if (role === "Admin") return "/admin/dashboard";
if (role === "FSS") return "/fss";
return "/dashboard";
```

- [ ] **Step 4: Verify GREEN**

Run: `npm test -- app/login/login-pwa-contract.test.ts app/mobile-app/mobile-app-contract.test.ts`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
feat(auth): add FSS PWA entry points
```

### Task 3: FSS-only shell and reusable core screens

**Files:**
- Create: `frontend/app/fss/layout.tsx`
- Create: `frontend/app/fss/page.tsx`
- Create: `frontend/app/fss/menu/page.tsx`
- Create: `frontend/app/fss/purchase/page.tsx`
- Create: `frontend/app/fss/accomplish/page.tsx`
- Create: `frontend/components/fss/FssShell.tsx`
- Create: `frontend/components/fss/FssDesktopHandoff.tsx`
- Create: `frontend/components/fss/FssHome.tsx`
- Create: `frontend/components/fss/fss-shell-contract.test.ts`
- Create: `frontend/app/api/fss/announcements/route.ts`
- Modify: `frontend/app/(rnd)/layout.tsx`
- Modify: `frontend/services/menuCycleService.ts`

**Interfaces:**
- Consumes: `isPhoneOrTablet`, `useAuth`, `getFssDashboard`, `ReportsBrowser`, `FSS_CATALOG`, existing menu-cycle page, existing procurement page.
- Produces: `/fss`, `/fss/menu`, `/fss/meal-prep`, `/fss/accomplish`, `/fss/purchase` navigation.

- [ ] **Step 1: Write failing shell tests**

Assert five exact tab labels and hrefs; non-FSS redirect rules; desktop handoff use; absence of RND sidebar; FSS denial in `(rnd)/layout.tsx`; menu and purchase pages reuse existing default page exports; accomplishment page uses `FSS_CATALOG` and `apiPrefix="fss"`.

- [ ] **Step 2: Verify RED**

Run: `npm test -- components/fss/fss-shell-contract.test.ts`

Expected: FAIL because FSS shell and routes do not exist and RND layout does not reject FSS.

- [ ] **Step 3: Implement shell and reused pages**

`FssShell` checks auth role, renders desktop QR handoff for fine-pointer widths over `1024px`, and otherwise renders a compact header, safe-area content, and five fixed bottom tabs. Re-export existing page implementations:

```ts
export { default } from "@/app/(rnd)/food-service/menu-cycle/page";
export { default } from "@/app/(rnd)/food-service/procurement/page";
```

Render accomplishment reports directly:

```tsx
<ReportsBrowser catalog={FSS_CATALOG} apiPrefix="fss" />
```

`FssHome` fetches dashboard summary plus three FSS announcements through thin existing proxy patterns. Extend `FssDashboardSummary` with the backend's nullable `active_cycle` contract. Show only meals-to-log, pending POs, active cycle, today's service, and announcements. Add explicit `user.role !== "RND"` rejection to RND layout; Admin layout remains unchanged.

- [ ] **Step 4: Verify GREEN**

Run: `npm test -- components/fss/fss-shell-contract.test.ts`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
feat(fss): add mobile web shell
```

### Task 4: Meal Prep workflow and missing proxy contracts

**Files:**
- Create: `frontend/app/fss/meal-prep/page.tsx`
- Create: `frontend/components/fss/FssMealPrep.tsx`
- Create: `frontend/components/fss/fss-meal-prep-contract.test.ts`
- Create: `frontend/app/api/fss/meal-prep-logs/route.ts`
- Create: `frontend/app/api/fss/meal-prep-logs/[id]/reverse/route.ts`
- Create: `frontend/app/api/fss/menu-cycles/[id]/complete-day/route.ts`

**Interfaces:**
- Consumes: `listCycles`, `getCycle`, `listServiceLogs`, `completeServiceDay`, `reverseServiceDay`, `setServedPopulation`.
- Produces: FSS service-day completion and reversal UI using existing Laravel authority.

- [ ] **Step 1: Write failing workflow tests**

Assert all three thin proxies call `proxy()` with exact Laravel paths/methods. Assert Meal Prep loads active cycle/service logs, accepts positive served population, calls `completeServiceDay`, and requires an explicit confirmation before `reverseServiceDay`.

- [ ] **Step 2: Verify RED**

Run: `npm test -- components/fss/fss-meal-prep-contract.test.ts`

Expected: FAIL because page and proxies do not exist.

- [ ] **Step 3: Implement minimal Meal Prep UI**

Render active cycle days as stacked cards. Each uncompleted day has one numeric population field and `Complete service day`; each completed day shows status, population, total value, and `Reverse` behind `window.confirm`. Use existing service functions; no duplicate calculation or offline queue.

Thin proxies:

```ts
return proxy("/fss/meal-prep-logs", { search: req.nextUrl.searchParams });
return proxy(`/fss/meal-prep-logs/${id}/reverse`, { method: "POST" });
return proxy(`/fss/menu-cycles/${id}/complete-day`, { method: "POST", body: await req.json() });
```

- [ ] **Step 4: Verify GREEN**

Run: `npm test -- components/fss/fss-meal-prep-contract.test.ts`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
feat(fss): add meal prep workflow
```

### Task 5: Final security, responsive, and production verification

**Files:**
- Verify: all files created or modified in Tasks 1-4.
- Repair rule: when a gate fails, add a focused failing regression test beside the affected feature before changing its implementation.

- [ ] **Step 1: Run focused tests**

Run: `npm test -- lib/pwa.test.ts app/pwa-contract.test.ts app/login/login-pwa-contract.test.ts app/mobile-app/mobile-app-contract.test.ts components/fss/fss-shell-contract.test.ts components/fss/fss-meal-prep-contract.test.ts`

Expected: all focused tests PASS.

- [ ] **Step 2: Run full frontend gates**

Run from `frontend`:

```text
npm test
npx tsc --noEmit
npm run lint
npm run build
```

Expected: all exit `0`. If external Google Font fetch blocks build, record exact environment failure, then use the current project-approved local verification alternative without claiming a successful production build.

- [ ] **Step 3: Run repository checks**

Run from repository root:

```text
git diff --check
git status --short
git diff --name-only HEAD~4..HEAD
git log -6 --format="%h %s%n%b"
```

Expected: no whitespace errors; only task files plus pre-existing `.codex/config.toml`; no AI/Codex attribution or branch metadata.

- [ ] **Step 4: Verify responsive and install behavior**

Run app locally. Check `375px`, `768px`, `1024px`, and `1440px`:

- phone/tablet login shows install/open flow;
- desktop login and `/mobile-app` show QR only;
- desktop `/fss` shows QR handoff;
- FSS shell has five touch-safe tabs and no horizontal scroll;
- RND/Admin destinations remain unchanged;
- offline navigation exposes generic page and no cached operational data.

- [ ] **Step 5: Commit verification fixes, if any**

```text
fix(pwa): harden FSS install flow
```

- [ ] **Step 6: Push main and verify remote**

Run:

```text
git push origin main
git rev-parse HEAD
git ls-remote origin refs/heads/main
```

Expected: local `HEAD` equals remote `refs/heads/main`.
