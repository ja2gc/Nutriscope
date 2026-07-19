# NutriScope Help Page Design

**Date:** 2026-07-20  
**Status:** Approved for implementation planning

## Purpose

Create an authenticated **Help** destination for RND, FSS, and Admin users. It will turn the verified FAQ and role guides into searchable, role-aware in-app guidance without adding support-ticket infrastructure or exposing private operational data.

## Research Basis

Established support experiences generally use a broad help destination rather than limiting navigation to a label such as “FAQ.” Google, Slack, and Shopify lead with a help prompt, search, and topic browsing. NutriScope will use the shorter product label requested by the owner: **Help**.

- [Google Help](https://support.google.com/) combines “How can we help you?” search with product/topic selection.
- [Slack Help](https://slack.com/help) combines search with grouped articles and guides.
- [Shopify Help](https://help.shopify.com/en) combines search with browse-by-topic navigation.
- [W3C’s accordion pattern](https://www.w3.org/WAI/ARIA/apg/patterns/accordion/) defines keyboard and expanded-state behavior for disclosure controls.
- [GOV.UK’s accordion guidance](https://design-system.service.gov.uk/components/accordion/) cautions against nested accordions and hiding content everyone needs to see.

The UI/UX Pro Max review also prioritizes visible focus states, semantic headings, keyboard access, 44 px touch targets, useful empty states, consistent navigation placement, and restrained motion.

## Selected Approach

Build one searchable, role-aware Help experience with platform-specific presentation:

- Web: shared React component rendered at `/help` for RND and `/admin/help` for Admin.
- Mobile: Expo screen rendered at `/help` for FSS.
- Content: typed, static content modules within each client package. The verified `docs/FAQ.md` and `docs/ROLE-HOW-TO.md` remain the human-readable source material. Client-local modules avoid cross-package bundler configuration and keep each application independently buildable.

Role visibility is fixed by the authenticated surface. RND sees Shared + RND guidance, FSS sees Shared + FSS guidance, and Admin sees Shared + Admin guidance. Users cannot switch roles or inspect another role's guidance. Shared questions are deliberately repeated on every role's Help page because account, saving, announcements, SOP, and troubleshooting guidance applies across roles.

## Information Architecture

### Navigation placement

- RND web sidebar: **Help** immediately before **Settings**.
- Admin web sidebar: **Help** immediately before **Settings**.
- FSS mobile: **Help** inside a new **Help & Support** section in Settings.

Help is secondary, persistent navigation. It does not consume one of FSS’s five primary bottom tabs and does not compete with core daily operations.

### Page hierarchy

1. Page title and short orientation.
2. Search field with visible label and descriptive placeholder.
3. Popular questions for the signed-in role when no search is active.
4. Topic groups containing expandable questions.
5. Results count or a useful no-results recovery message.
6. “Still need help?” guidance directing users to an administrator and telling them what diagnostic details to provide.

### Topic coverage

- Shared: sign-in, forgotten password, recovery email, first-login setup, profile, saving, notifications, announcements, SOP, privacy, and troubleshooting.
- RND: Nutrition Care Process, patient records, assessment, diagnosis, intervention, monitoring, food library, food-service planning, reports, and communication.
- FSS: five-tab mobile navigation, active menu, meal preparation, accomplishments, purchase orders, receipt uploads, announcements/SOP, reports, and device troubleshooting.
- Admin: users and roles, account resets, announcements/SOP, reports, budget visibility, audit logs, AI usage controls, and system settings.

## Interaction Design

### Search

- Filtering occurs locally as the user types; no network request or AI service is required.
- Search matches question, answer, category, and explicit keywords case-insensitively.
- Whitespace is normalized.
- The page announces the result count through a polite live region on web.
- A no-results state recommends broader wording or clearing the search.
- Clearing search restores popular questions and all topic groups for the authenticated role.

### Question disclosure

- Web uses semantic question buttons with `aria-expanded` and `aria-controls`, inside correctly ordered headings.
- Mobile uses accessible pressable rows with announced expanded state.
- Multiple answers may remain open so users can compare related guidance.
- Search results remain collapsed initially; search must never auto-open a large set of answers.
- No nested accordions are used.

## Visual Design

The page reuses NutriScope’s current typography, warm neutral surfaces, brand green interaction color, orange accent, rounded cards, spacing rhythm, and Lucide icon family.

- Content width remains readable rather than spanning the entire desktop canvas.
- Search is the strongest control but does not resemble a primary data-entry action.
- Category cards use borders and spacing instead of decorative gradients.
- Body text remains at least 16 px on mobile and uses comfortable line height.
- Focus rings are visible, and hover is never the only indication of interactivity.
- Expanding answers uses existing restrained transitions and respects reduced-motion preferences.
- Empty and escalation states include text and icons; meaning never depends on color alone.

## Components and Data Boundaries

### Web

- `frontend/lib/helpContent.ts`: types, role/category definitions, FAQ data, search/filter functions, and popular-item selection.
- `frontend/components/help/HelpPage.tsx`: reusable interactive page for RND and Admin layouts.
- `frontend/components/help/HelpQuestionList.tsx`: reusable semantic question disclosure list.
- `frontend/app/(rnd)/help/page.tsx`: RND route wrapper.
- `frontend/app/admin/help/page.tsx`: Admin route wrapper.
- `frontend/components/layout/Sidebar.tsx`: persistent Help navigation.
- `frontend/components/layout/TopBar.tsx`: Help module title.

### Mobile

- `mobile/lib/helpContent.ts`: mobile-safe typed content and pure filtering behavior.
- `mobile/components/help/HelpQuestionList.tsx`: reusable mobile question disclosure list.
- `mobile/app/help.tsx`: FSS Help screen composed from the shared mobile component.
- `mobile/app/_layout.tsx`: authenticated stack registration.
- `mobile/app/settings.tsx`: Help & Support entry.
- `mobile/app.json`: patch-version and Android version-code increment for the updated installable build.

### Documentation

- `docs/FAQ.md`: remains in the docs root.
- `docs/ROLE-HOW-TO.md`: remains in the docs root.
- `docs/STORYBOARD.md`: contains only the complete no-screenshot backup storyboard.
- `docs/modules/STORYBOARD-SCREENSHOT-GUIDE.md`: contains the sequential screenshot-required storyboard.

The Help content is deliberately static. Editing Help does not change Laravel APIs, database schema, authentication policy, audit behavior, or clinical calculations.

## Error and Privacy Handling

- The page requires the existing authenticated role layouts and mobile route guard.
- Help content contains no patient names, clinical values, credentials, recovery codes, audit details, or live operational records.
- Search text stays in local component state and is neither transmitted nor logged.
- Escalation guidance asks users to report the screen, time, action, and safe error wording, but explicitly warns against sharing passwords, recovery codes, or patient data.
- Because content is bundled locally, unavailable backend services do not prevent users from reading Help.

## Testing Strategy

Implementation follows test-first development.

### Web tests

- Filtering includes Shared plus the authenticated role and excludes every unrelated role.
- Search matches questions, answers, categories, and keywords.
- Empty and whitespace-only searches behave consistently.
- Sidebar exposes Help for RND and Admin at the approved paths and placement.
- Both route wrappers exist and reuse the shared component.
- Disclosure controls expose required accessible attributes and search has a visible/associated label.
- Existing Vitest suite, ESLint, TypeScript, and Next.js build pass.

### Mobile tests

- A source contract verifies the Help route, Settings entry, Help & Support label, and accessible disclosure state.
- Mobile TypeScript passes.
- Existing Node-based mobile contract tests pass.
- The app-version contract proves the patch version and Android version code were incremented before the APK build.

### Documentation checks

- The three primary documents remain directly under `docs/`.
- Only the screenshot-required storyboard exists under `docs/modules/`.
- Markdown links resolve and fenced blocks remain balanced.

## Acceptance Criteria

- Every authenticated role can reach a page labeled **Help**.
- Help exposes only Shared plus the authenticated role; no cross-role selector exists.
- Users can search the complete verified question set and recover from no results.
- Question disclosures are keyboard and screen-reader operable.
- FSS retains exactly five primary bottom tabs.
- The requested storyboard files are split into their correct directories.
- No unrelated `.codex/config.toml` or `AGENTS.md` changes enter the Help commits.
- Focused and full client verification passes before commits are pushed to `main`.
- An installable Android APK is produced from the EAS `preview` profile against `https://nutriscope.live/mobile-api`, and its download URL or local artifact is handed to the owner.
