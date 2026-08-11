# FSS Progressive Web App Design

## Goal

Make NutriScope's existing web deployment installable as a phone/tablet-only Food Service Staff (FSS) application. Staff install it from the shared login page without downloading an APK or accepting Android unknown-source warnings. RND and Admin continue using the regular browser website.

## Decisions

- Build inside the existing Next.js frontend.
- Reuse the current Laravel APIs, Next.js API proxies, cookie authentication, services, UI primitives, and brand assets.
- Set the PWA start URL to `/fss`. Do not advertise or present NutriScope as a desktop app.
- Keep the Expo project and APK build path unchanged as a temporary fallback. Do not add an APK download link until a stable APK URL exists.
- Add no new backend endpoint unless implementation proves an existing FSS operation has no web proxy.
- Do not add offline submissions, background sync, push notifications, a second design system, or a separate Expo web deployment.

## User Flow

### Login page

Add one compact `Food Service Staff mobile app` panel below the login form.

- On a phone or tablet, show `Install NutriScope` when the browser fires `beforeinstallprompt`; otherwise show concise browser installation instructions and an `Open FSS app` link.
- On desktop, show only `Scan with your phone` and a QR code targeting the stable `/mobile-app` route. Show no install button, open-app button, desktop-install instructions, or desktop-app language.
- When running in standalone display mode, hide install guidance and show only `Open FSS app`.
- If installation cannot be prompted, show short browser instructions instead of a dead button.

### Public app page

`/mobile-app` is public and stable. On a phone or tablet it explains that the PWA is for FSS and provides install/open actions. On desktop it shows only the `Scan with your phone` QR handoff. The QR never targets a build artifact.

### Authentication and routing

- Existing login remains shared by all roles.
- Successful FSS login always routes to `/fss`: phone/tablet receives the workflow; desktop receives the QR handoff.
- RND routes to `/dashboard`; Admin routes to `/admin/dashboard`.
- Authenticated non-FSS users cannot enter `/fss`.
- FSS users are kept out of RND and Admin shells.
- A desktop visit to `/fss` shows the QR handoff rather than FSS workflows or desktop-app promotion.
- Authentication remains in secure, HTTP-only, `SameSite=Lax` cookies. No bearer token enters `localStorage`, IndexedDB, manifest data, or service-worker caches.

## FSS Application Shell

Create a focused `/fss` route group with a small responsive header and five-item bottom navigation matching the current mobile app:

1. Home
2. Menu
3. Meal Prep
4. Accomplish
5. Purchase

Phone and tablet layouts use bottom navigation, safe-area padding, and a centered responsive content canvas. Desktop renders only the phone QR handoff; it does not expose FSS workflows or the RND sidebar.

Secondary pages such as notifications, reports, profile, account setup, settings, and help open from the header or a small More menu. Existing shared web components are reused when role behavior matches.

## Workflow Scope

Port current FSS behavior, not every visual detail from React Native:

- Home: FSS dashboard summary and relevant announcements.
- Menu: active/current menu-cycle viewing and served-population entry where authorized.
- Meal Prep: preparation rows and completion/service logging already supported by existing APIs.
- Accomplish: accomplishment history and generated report access.
- Purchase: purchase-order viewing and receipt/proof attachment upload.

Browser camera capture uses a normal file input with `accept="image/*"` and `capture="environment"`. Existing multipart upload routes remain authoritative.

## PWA Files and Caching

Use Next.js native metadata support for `app/manifest.ts` with `start_url: "/fss"` and standalone display. Register one small service worker from the root layout or a focused client component.

Service worker caches only versioned static assets and a generic offline page. It must not cache:

- `/api/**`
- authenticated page HTML
- reports or downloads
- receipt images or other uploaded files
- any response containing user or operational data

When offline, existing pages show clear retry guidance. Mutations remain disabled by normal request failure; no client-side queue is created.

## Install UX

One small client hook/component owns install state:

- captures `beforeinstallprompt` where supported;
- detects standalone mode;
- treats a device as phone/tablet when it has a coarse pointer or a viewport no wider than 1024 CSS pixels, avoiding a user-agent package;
- invokes the saved prompt from explicit user action;
- clears stale prompt state after acceptance or dismissal;
- falls back to concise Android/iOS browser instructions.

No forced popup, auto-prompt, modal on first visit, user-agent library, analytics package, or QR dependency. Generate the QR as a static project asset for the fixed production URL. Desktop browsers may expose their own browser-level install command, but NutriScope never advertises or triggers it and `/fss` remains a QR handoff on desktop.

## Security

- Preserve backend role middleware as final authority.
- Add frontend route guards for correct UX and defense in depth.
- Keep sensitive responses network-only.
- Do not put auth tokens, API payloads, report files, or uploads in PWA caches.
- Keep the app online-only for writes to avoid duplicate receipts, accomplishments, or service logs.
- Use existing CSRF-resistant same-site proxy pattern and existing upload validation.

Laravel Boost is unnecessary for initial implementation because no Laravel behavior changes are designed. Use it only if a missing proxy or backend contract is discovered.

## Accessibility and Responsive Behavior

- Minimum 44px touch targets.
- Visible keyboard focus.
- Text labels on all five navigation items.
- Current page exposed with `aria-current`.
- Install status announced through a polite live region.
- No horizontal scrolling at 375px.
- Bottom content clears navigation and device safe area.
- Reduced-motion preferences remain respected.

## Testing

Follow red-green-refactor.

- Contract tests for manifest, service-worker exclusions, and public middleware paths.
- Component tests for install states: prompt available, unavailable, dismissed, installed.
- Login tests for role destinations and phone/desktop app controls.
- Layout tests for FSS-only navigation, route guards, and desktop QR handoff.
- Workflow tests around each ported FSS page using existing service contracts.
- TypeScript, focused ESLint, focused Vitest, full frontend test suite, production build, and `git diff --check`.
- Manual responsive check at 375px, 768px, 1024px, and desktop; manual install check in Android Chrome when available.

## Delivery Order

1. PWA manifest, service worker, offline page, and install controller.
2. Public `/mobile-app` page and login-page controls.
3. FSS route guard and focused shell.
4. Port five FSS workflows by reusing existing services and proxies.
5. Validate install, role boundaries, mobile layout, camera upload, and production build.

## Non-Goals

- Removing the Expo app or its EAS configuration.
- Publishing to Google Play.
- Native push notifications.
- Full offline mode or queued writes.
- Rebuilding backend business logic.
- Redesigning RND or Admin pages.
- Advertising or supporting a desktop-installed FSS application.
