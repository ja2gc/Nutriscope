# FSS Native App Consolidation — Implementation Report

Verified against the repository, current application source, release metadata, and live download endpoints on **2026-08-27**.

## Outcome

NutriScope now has one operational FSS client: the Expo/React Native Android application. RND and Admin continue to use the browser website. The temporary FSS PWA and duplicate React web implementation were removed rather than maintained as a second mobile frontend.

The public website retains only the distribution handoff at `https://nutriscope.live/mobile-app`. Phones receive the APK action and desktops receive a permanent QR. Both lead to stable NutriScope URLs, so the QR does not change between releases.

## Architecture Consolidation

The consolidation removed:

- `/fss` operational pages from the Next.js frontend;
- the FSS web shell, home, meal-prep, and purchase components;
- the PWA manifest, service worker, offline page, registration/install code, and PWA-specific icons;
- obsolete client routes for completing or reversing meal service;
- the RND service-log panel;
- PWA plans/specifications that no longer represented the selected architecture.

The final platform boundary is:

| Role | Operational client |
|---|---|
| RND | Browser website |
| Admin | Browser website |
| FSS | Expo/React Native Android app |
| Public visitor | Website landing/download handoff only |

Laravel remains the shared API and permission authority. Platform checks reject FSS website login and reject RND/Admin login from the FSS app.

## Native FSS Application

### Navigation

The mobile bottom navigation contains six tabs in this order:

1. Home
2. Announcement
3. Menu
4. Meal Prep
5. Accomplish
6. Purchase

The header retains the notification bell and profile icon. The profile side menu contains Profile, Notifications, Help, Settings, Check for updates, and Sign out.

### Home and Communication

- Home shows current queues including meals to log, pending POs, active menu-cycle information, today's service, waiting reasons, and announcements.
- Announcement is a full bottom tab with separate **Announcements** and **SOP** views.
- SOP includes the current procedure and paginated version history.
- Notifications preserve unread state, pagination, mark-read behavior, mark-all-read, and supported target routing.

### Menu and Food Profile

- Menu is a read-only weekly reference; it no longer contains operational population or completion controls.
- Selecting a meal opens a dedicated native food-profile page with normal back navigation.
- The profile identifies whether values come from the master recipe, a menu-slot version, or a frozen purchase-order snapshot.
- Ingredient quantities and costs use the slot's applicable servings or frozen snapshot without changing the master recipe.

### Meal Prep

- Meal Prep defaults to today and supports earlier planned dates in the active cycle.
- It shows the selected date's planned meals and links to their food profiles.
- FSS records or updates one positive whole-number actual population served.
- There is no client-side prep-completion, mark-served, or reverse-service action.

### Accomplish and Reports

- Daily Log opens on the device's local date.
- FSS may select any past date; future dates are blocked.
- A visible **Today** action returns from a backdated log to the current date.
- Working entries record ward, meals distributed, and seven duty flags.
- Multiple working rows can represent separate wards on the same date.
- Off-duty/absent records zero numeric values plus an `X` in the semi-monthly report.
- Weekly report aggregation sums ward populations and combines completed duties.
- **My Reports** is inside Accomplish, uses owner-scoped paginated data, opens report details, and provides **View PDF** and **Download PDF** through authenticated handling. Reports update progressively for each semi-monthly period.

### Purchase

- FSS sees assigned food and supplies POs but cannot author shopping lists or POs.
- Open vendor groups show planned values and prefilled actual values.
- Actual quantity supports decimals and actual unit price remains editable while unlocked.
- **Calculation details** exposes calculated, planned, and actual values only when requested.
- Before evidence or receiving, **Change vendor for all** changes a group and row-level **Change vendor** moves one item.
- Receipt and proof images can be selected from the library or camera, captioned, viewed with authentication, and deleted while unlocked.
- OR number is optional.
- Receipt, proof, reviewed actuals, supplier assignment, and **Mark vendor received** are required for vendor completion.
- Completed or archived records lock execution edits.

### Account and Reliability

- First-login setup supports password replacement and recovery email, with a deferral reminder.
- Profile supports first/last name, sign-in email, contact, recovery email verification, and password change; role/status remain read-only.
- Authentication stores the mobile token securely and guards public/private routes.
- Authenticated API images and report PDFs send the bearer token.
- Network failures show retry/error states instead of false empty screens.
- API IDs are normalized before entering string-based native selection/routing state.
- The app uses a compact NutriScope `N` launcher identity rather than the default Expo icon.

## Laravel Changes

- Added date-scoped FSS diet-list/accomplishment reads.
- Enforced Philippine-local future-date rejection.
- Allowed separate working rows for different wards while blocking off-duty/working conflicts.
- Corrected Monday–Sunday accomplishment aggregation and frozen archive generation.
- Enforced owner scope for FSS report listing, detail, preparation, and download.
- Restored required FSS read routes while preserving planning-write restrictions.
- Hardened served-population validation and menu-cycle identifiers.
- Kept RND/Admin report access within their existing allowed report scopes.

Meal-service completion/reversal API endpoints still exist and retain audit coverage, but no current web or mobile control invokes them.

## Web Distribution and Update Flow

- `/mobile-app` is the stable public handoff.
- `/downloads/nutriscope-fss.apk` is the stable APK URL.
- `/downloads/nutriscope-fss.json` is the stable version/checksum URL used by the app update check.
- The desktop QR targets the stable handoff rather than a temporary Expo artifact.
- Old FSS web routes redirect to the download handoff.
- Legacy PWA registrations and caches are cleaned up by the website.
- Nginx serves APK files with the Android package MIME type and disables stale caching for the APK/metadata endpoints.

The app checks periodically and also exposes **Check for updates** in the profile menu. Because distribution is outside Google Play, Android can still require normal unknown-source approval; a QR or stable URL cannot remove that operating-system warning.

## Android Release 1.2.1

| Field | Verified value |
|---|---|
| Application | NutriScope FSS |
| Android package | `live.nutriscope.fss` |
| Version | `1.2.1` |
| Version code | `5` |
| EAS build ID | `10410351-ef16-47d7-983d-94271c15dd34` |
| APK bytes | `82,693,016` |
| SHA-256 | `a30f995adf57d261b73c7a89f8a8eb6506a3471fb6a1f2559a5cfd012b2f02a0` |
| Landing page | `https://nutriscope.live/mobile-app` |
| APK | `https://nutriscope.live/downloads/nutriscope-fss.apk` |
| Metadata | `https://nutriscope.live/downloads/nutriscope-fss.json` |

The production APK, metadata, MIME type, content length, stable landing page, and checksum were verified after publication.

## Release Automation and Deployment Hardening

- Added a GitHub Actions release workflow that reads `mobile/release.json`, validates release fields, downloads the signed EAS artifact, verifies SHA-256, and atomically publishes APK/JSON files.
- Added a manual dispatch path accepting the same release values.
- Updated checkout to the Node 24-compatible action version.
- Excluded `mobile/release.json`-only pushes from the full Docker deployment so mobile publication does not compete with a server rebuild for SSH.
- Documented the one-time Nginx `/downloads/` installation and verification requirement.

## Verification Evidence

The completed application changes were checked with:

- backend full suite: **1,252 tests, 8,340 assertions**;
- frontend full suite: **103 test files, 315 tests**;
- frontend TypeScript, lint, and production build;
- mobile source contracts: **31 tests**;
- mobile TypeScript;
- final focused backend FSS/report/menu-cycle run: **57 tests, 166 assertions**;
- final focused website login/download contracts: **3 tests**;
- workflow contract checks, YAML parsing, and `git diff --check`;
- live HTTP, metadata, APK size, and checksum checks;
- local/remote `main` reference equality after delivery.

## Delivered Commits

| Commit | Purpose |
|---|---|
| `39bc159` | Restore native FSS workflows and remove the duplicate PWA client |
| `b057c2e` | Keep Announcement inside the native tab shell |
| `9047f7d` | Harden native routing, reports, receiving, errors, and release support |
| `6d8c05e` | Allow signed mobile releases through a pushed release request |
| `638986e` | Publish Android `1.2.1` release metadata |
| `e5b432b` | Isolate APK releases from full server deployment |

## Deliberate Boundaries

- The FSS app requires network access for operational data; it is not an offline database.
- APK sideloading may show Android security/install-source prompts.
- The website is not an FSS operational fallback.
- Menu planning remains RND-owned and read-only to FSS.
- The app records actual served population, not a redundant prep-completion status.
- Historical design/implementation plans remain historical and may describe superseded architecture.
